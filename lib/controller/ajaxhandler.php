<?php
namespace Eshoplogistic\Delivery\Controller;

use Bitrix\Main\Config\Option;
use \Bitrix\Main\Engine\Controller,
    \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Application,
    \Bitrix\Main\Data\Cache,
    \Bitrix\Sale\Delivery\Services\Table,
    \Eshoplogistic\Delivery\Config;
use Bitrix\Main\Engine\ActionFilter\Authentication;
use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Main\Engine\ActionFilter\HttpMethod;
use Bitrix\Main\Request;
use Bitrix\Main\Web\Json;
use Eshoplogistic\Delivery\Event\Unloading;
use Eshoplogistic\Delivery\Helpers\OrderHandler;
use Eshoplogistic\Delivery\Api\Search;
use Eshoplogistic\Delivery\Helpers\LocationHandler;

Loader::includeModule('sale');

Loc::loadMessages(__FILE__);

/** Class for getting PVZ by ajax request
 * Class AjaxHandler
 * @package Eshoplogistic\Delivery\Controller
 * @author negen
 */

class AjaxHandler extends Controller
{
    static $cacheTime = Config::CACHE_TIME;
    static $cacheDir  = Config::CACHE_DIR;
    static $cacheKey   = 'pvzlist';


    /**
     * @return array
     */
    public function configureActions()
    {
        return [
            'getPvzList' => [
                'prefilters' => []
            ],
            'getDefaultCity' => [
                'prefilters' => []
            ],
            'widgetData' => [
                // Анонимный read-only прокси: только методы из WIDGET_ALLOWED_METHODS (без
                // widget/send) и только с ключом, выданным сессии (isIssuedWidgetKey) —
                // Authentication невозможен (виджет вызывается неавторизованными посетителями
                // витрины), но CSRF-токен добавлен: componentorder.php кладёт bitrix_sessid()
                // прямо в URL data-controller (?sessid=...), который виджет использует как есть
                // для своих запросов, поэтому запрос всегда несёт валидный токен текущей сессии.
                'prefilters' => [
                    new Csrf(),
                    new HttpMethod([HttpMethod::METHOD_POST]),
                ],
            ],
            'unloadingForm' => [
                'prefilters' => [
                    new Authentication(),
                    new Csrf(),
                    new HttpMethod([HttpMethod::METHOD_POST]),
                ],
            ]
        ];
    }

    /**
     * Clearing cache and managed cache directories
     * return string
     */
    public function clearCacheAction()
    {
        global $USER;
        if (!$USER->IsAdmin()) {
            $this->addError(new \Bitrix\Main\Error('Access denied'));
            return null;
        }

        $cache = Cache::createInstance();
        $cache->CleanDir(Config::CACHE_DIR);

        $managedCahe = Application::getInstance()->getManagedCache();
        $managedCahe->cleanDir( Config::CACHE_DIR);

        return Loc::getMessage('ESHOP_LOGISTIC_OPTIONS_CLEAR_CACHE_RESULT');
    }

    /** Getting PVZ list for sale.order.ajax component (popup)
     * @param string $profileId
     * @param string $locationCode
     * @param integer $paymentId
     * @return array
     */
    public static function getPvzListAction($profileId= '', $locationCode = '', $paymentId = 0, $isAddressName = 0, $fallbackLocationCode = '')
    {
        $pvz = array();
        if(!$profileId || !$locationCode) return $pvz;

        // Если передано текстовое поле — пробуем найти город.
        // Если поле содержит полный адрес (например «Тверь, ул. Оснабрюкская, 36»),
        // извлекаем город из первой части до запятой.
        if ($isAddressName) {
            $resolved = LocationHandler::resolveCityFromText($locationCode);
            if (empty($resolved)) {
                // Город не найден — если есть fallback (bitrix location code), используем его
                if ($fallbackLocationCode) {
                    $locationCode = $fallbackLocationCode;
                    $isAddressName = 0;
                } else {
                    // Город не найден и fallback отсутствует — возвращаем пустой массив
                    return [$pvz];
                }
            } else {
                // Используем нормализованное имя города (может быть извлечено из полного адреса)
                $locationCode = $resolved['resolvedName'];
            }
        }

        $rsDelivery = Table::getList(array(
            'filter' => array('ACTIVE'=>'Y', 'ID' => $profileId),
            'select' => array('CODE')
        ));

        if($profile = $rsDelivery->fetch()) {
            $profileClass = self::getProfileClassByCode($profile['CODE']);
        } else {
            // Неизвестный/неактивный profileId — нет профиля, для которого искать ПВЗ.
            return [$pvz];
        }

        $cacheKey = self::$cacheKey.'-'.$profileClass.'-'.$locationCode;
        $cache = Cache::createInstance();

        if ($cache->initCache(self::$cacheTime, $cacheKey, self::$cacheDir)) {
            $vars = $cache->getVars();
            return [$vars['pvz']];
        } elseif ($cache->startDataCache()) {
            $pvz = $profileClass::getPvzData($locationCode, $paymentId, (bool)$isAddressName);

            if ($pvz['success'] == true) {
                $cache->endDataCache(array("pvz" => $pvz));
            } else {
                $cache->abortDataCache();
            }
        }

        return [
            $pvz,
        ];
    }

    /** Getting deliveri profile class by code
     * @param string $profileCode
     * @return mixed
     */
    private static function getProfileClassByCode($profileCode) {
        $profileCodeParts = explode(':', $profileCode);
        $profileCode = end($profileCodeParts);
        $config = new Config();
        $classList = $config->profileClasses;
        return $classList[$profileCode];
    }

    public function getDefaultCityAction(){
        $locationCode = OrderHandler::getCodeCityByApi();
        return [
            $locationCode,
        ];
    }

    /** Методы api.esplc.ru, которые виджет корзины (widgets/cart, см. componentorder.php::frameHtmlField)
     * реально запрашивает через data-controller. widget/send (создание заявки у перевозчика) сюда
     * сознательно не входит: заказ в режиме виджета оформляет Bitrix, виджет корзины submitOrder
     * не вызывает, а анонимный прокси для создания заявок от имени сайта — это обход аутентификации
     * (любые проверки Origin/сессии тут подконтрольны самому вызывающему).
     */
    private const WIDGET_ALLOWED_METHODS = [
        'widget/client',
        'widget/search',
        'widget/calculation',
        'widget/geo',
        'widget/terminals',
        'widget/distance',
    ];

    public static function widgetDataAction()
    {
        $out    = [];
        $request = Application::getInstance()->getContext()->getRequest();

        $method = trim((string)$request->getPost('method'));

        // Точный allowlist вместо шаблона widget/*: через CURLOPT_URL (ApiQuery) уходит только
        // заранее известный путь API.
        if (!empty($method) && !in_array($method, self::WIDGET_ALLOWED_METHODS, true)) {
            \CEventLog::Add([
                'SEVERITY' => \CEventLog::SEVERITY_SECURITY,
                'AUDIT_TYPE_ID' => 'ESHOPLOGISTIC_WIDGET_METHOD_BLOCKED',
                'MODULE_ID' => Config::MODULE_ID,
                'ITEM_ID' => $request->getRemoteAddress() ?: 'unknown',
                'DESCRIPTION' => 'widgetData blocked: method ' . mb_substr($method, 0, 100) . ' is not allowed',
            ]);
            http_response_code(403);
            echo Json::encode(['error' => 'Method is not allowed']);
            exit();
        }

        if (!self::isSameOriginRequest($request)) {
            http_response_code(403);
            echo Json::encode(['error' => 'Forbidden origin']);
            exit();
        }

        if (!self::isIssuedWidgetKey($request)) {
            \CEventLog::Add([
                'SEVERITY' => \CEventLog::SEVERITY_SECURITY,
                'AUDIT_TYPE_ID' => 'ESHOPLOGISTIC_WIDGET_KEY_BLOCKED',
                'MODULE_ID' => Config::MODULE_ID,
                'ITEM_ID' => $request->getRemoteAddress() ?: 'unknown',
                'DESCRIPTION' => 'widgetData blocked: key does not match the widget key issued to this session',
            ]);
            http_response_code(403);
            echo Json::encode(['error' => 'Forbidden']);
            exit();
        }

        if (!self::checkWidgetRateLimit($request)) {
            http_response_code(429);
            echo Json::encode(['error' => 'Too many requests']);
            exit();
        }

        if ( ! empty( $method ) ) {
            $query_data = @$_POST;
            unset( $query_data['method'] );
            $cache_key  = md5( $method . json_encode( $query_data ) );
            $cache = Cache::createInstance();
            $cache_data = $cache->initCache(Config::CACHE_TIME, $cache_key, Config::CACHE_DIR);

            if ( ! empty( $cache_data ) ) {
                $out = $cache->getVars();
            } elseif($cache->startDataCache()) {
                if ( $requestOut = self::ApiQuery( $method, $query_data ) ) {
                    if ( ! empty( $requestOut ) && $requestOut['http_status'] == 200 ) {
                        $cache->endDataCache($requestOut);
                    } else {
                        $cache->abortDataCache();
                    }
                    $out = $requestOut;
                } else {
                    $cache->abortDataCache();
                }
            }
        }

        $json = Json::encode( $out );
        echo $json;
        exit();

    }

    private const WIDGET_KEY_SESSION = 'esl_widget_key';

    /** Запоминает в сессии ключ виджета, который сервер сам отдал в разметку чекаута
     * (componentorder.php::frameHtmlField). widgetData проксирует только запросы с этим ключом —
     * прокси нельзя использовать с произвольным чужим key.
     * @param string $widgetKey
     */
    public static function rememberIssuedWidgetKey(string $widgetKey): void
    {
        Application::getInstance()->getSession()->set(self::WIDGET_KEY_SESSION, $widgetKey);
    }

    /** Ключ из запроса (для widget/calculation виджет шлёт его как "<key>:<суффикс>") совпадает
     * с ключом, выданным этой сессии. Запрос без key пропускается — API без ключа ничего не отдаст.
     * @param Request $request
     * @return bool
     */
    private static function isIssuedWidgetKey($request): bool
    {
        $key = $request->getPost('key');
        if ($key === null || $key === '') {
            return true;
        }
        if (!is_string($key)) {
            return false;
        }

        $issued = (string)Application::getInstance()->getSession()->get(self::WIDGET_KEY_SESSION);
        if ($issued === '') {
            return false;
        }

        return hash_equals($issued, explode(':', $key)[0]);
    }

    /** widgetData has no Authentication filter (it's called anonymously by the api.esplc.ru
     * widget script embedded on storefront pages) but does have Csrf (see configureActions() —
     * componentorder.php embeds bitrix_sessid() as a query param in the data-controller URL the
     * widget is given, so check_bitrix_sessid() finds it merged into the request regardless of
     * what the vendor script's own POST body contains). Origin/Referer below is additional
     * defense against direct cross-site calls to this proxy from a page carrying a stolen/replayed
     * sessid. Requests without either header (e.g. forged via curl) are rejected rather than
     * allowed through, since a real browser call to this same-origin endpoint always carries at
     * least one of them.
     * @param Request $request
     * @return bool
     */
    private static function isSameOriginRequest($request): bool
    {
        $host = $request->getHttpHost();
        $origin = $request->getHeader('Origin') ?: $request->getHeader('Referer');
        if (!$origin) {
            return false;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        return $originHost && strcasecmp($originHost, $host) === 0;
    }

    /** Limits anonymous calls to widgetData per remote IP to reduce abuse of the proxied external API
     * @param Request $request
     * @param int $limit
     * @param int $period seconds
     * @param string $scope separates this counter's bucket from other callers sharing the same IP (e.g. 'send' vs. the general limit)
     * @return bool
     */
    private static function checkWidgetRateLimit($request, int $limit = 120, int $period = 60, string $scope = ''): bool
    {
        $ip = $request->getRemoteAddress() ?: 'unknown';
        // Bucketing the key by time window (instead of a rolling TTL that gets re-extended
        // on every hit) makes the window actually expire under continuous traffic.
        $bucket = (int)floor(time() / $period);
        $cacheKey = 'widget_rl_' . $scope . '_' . md5($ip) . '_' . $bucket;
        $cache = Cache::createInstance();

        $count = 0;
        if ($cache->initCache($period * 2, $cacheKey, Config::CACHE_DIR)) {
            $count = (int)$cache->getVars();
        }

        $count++;

        $cache->clean($cacheKey, Config::CACHE_DIR);
        if ($cache->startDataCache($period * 2, $cacheKey, Config::CACHE_DIR)) {
            $cache->endDataCache($count);
        }

        return $count <= $limit;
    }



    public static function ApiQuery( string $method, array $data = [], string $raw = '' ) {

        $calculation = false;


        $apiKey = Option::get(Config::MODULE_ID, 'api_key');
        if ( empty( $apiKey ) ) {
            return [];
        }

        $apiUrl = Config::API_UNLOADIG;
        if ( empty( $apiUrl ) ) {
            return [];
        }


        $lc = substr( $apiUrl, - 1 );
        if ( $lc != '/' ) {
            $apiUrl .= '/';
        }

        $curl = curl_init();
        curl_setopt( $curl, CURLOPT_URL, $apiUrl . $method );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
        curl_setopt( $curl, CURLOPT_POST, 1 );
        if ( preg_match( '/widget/', $method ) ) {
            # заказ из виджета отправляется в raw
            if ( $method == 'widget/send' ) {
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $raw );
                curl_setopt( $curl, CURLOPT_HTTPHEADER, [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                    'Content-Type: application/json'
                ] );
            } elseif ( $method == 'widget/calculation' ) {
                $encoded        = json_decode( stripslashes( $data['offers'] ) );
                $data['offers'] = json_encode( $encoded );
                $data['debug']  = 1;
                $calculation    = true;
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
                curl_setopt( $curl, CURLOPT_HTTPHEADER, [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                ] );
            } else {
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
                curl_setopt( $curl, CURLOPT_HTTPHEADER, [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                ] );
            }
        } else {
            # выгрузка заказа в raw
            if ( $method == 'delivery/order' ) {
                $raw = json_encode( array_merge( $data, [ 'key' => $apiKey ] ) );
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $raw );
                curl_setopt( $curl, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json'
                ] );
            } else {
                curl_setopt( $curl, CURLOPT_POSTFIELDS, array_merge( $data, [ 'key' => $apiKey ] ) );
            }
        }

        $result = curl_exec( $curl );
        curl_close( $curl );


        if ( $result = \Bitrix\Main\Web\Json::decode($result) ) {
            if ( is_array( $result ) ) {

                if ( $calculation && isset( $result['debug'] ) ) {
                    if(isset($result['data']['terminal']['price']['value']) && !is_int($result['data']['terminal']['price']['value'])){
                        $result['data']['terminal']['price']['value'] = (int)$result['data']['terminal']['price']['value'];
                    }
                    if(isset($result['data']['door']['price']['value']) && !is_int($result['data']['door']['price']['value'])){
                        $result['data']['door']['price']['value'] = (int)$result['data']['door']['price']['value'];
                    }
                    $keyWidget = explode( ':', $data['key'] );
                    $cacheJson = array(
                        'city' => $data['to'],
                        'key'  => $keyWidget[0],
                        'service' => $data['service']
                    );
                    $cache_key = md5( $method . json_encode( $cacheJson ) );
                    $cache = Cache::createInstance();
                    $cache->initCache(Config::CACHE_TIME, $cache_key, Config::CACHE_DIR);

                    if($cache->startDataCache()){
                        $cache->endDataCache($result);
                    }
                }

                return $result;
            }
        }

        return false;
    }

    /** Форма выгрузки заказа не задаёт checkbox'ам атрибут value, поэтому браузер шлёт для
     * отмеченных полей литеральную строку "on" (как и в МС). В МС это нормализуется на клиенте
     * (assets/js/script.js: serializeForm(), val === 'on' ? '1' : val) перед отправкой — у нас
     * форма отправляется через raw FormData без такой нормализации, поэтому делаем то же самое
     * здесь, один раз для всего запроса, чтобы "on" не улетал в API как есть (order[costly],
     * order[packing], lift, complement[...] и т.д.).
     * @param mixed $data
     * @return mixed
     */
    private static function normalizeCheckboxValues($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::normalizeCheckboxValues($value);
            }
            return $data;
        }

        return $data === 'on' ? '1' : $data;
    }

    public function unloadingFormAction()
    {
        global $APPLICATION;

        if ($APPLICATION->GetGroupRight('sale') !== 'W') {
            $this->addError(new \Bitrix\Main\Error('Access denied'));
            return null;
        }

        $request = $this->getRequest()->getPostList()->toArray();
        $request = self::normalizeCheckboxValues($request);
        $request['order_id'] = (int)($request['order_id'] ?? 0);
        if ($request['order_id'] <= 0) {
            $this->addError(new \Bitrix\Main\Error('Bad request'));
            return null;
        }

        $unloading = new Unloading();
        $result = $unloading->params_delivery_init($request);
        if (isset($result['errors'])) {
            // http_status_message описывает HTTP-статус САМОГО запроса к API, а не бизнес-
            // результат: часть ошибок (например неподтверждённый трек-номер СДЭК, см.
            // params_delivery_init) — это наше решение считать выгрузку неуспешной при
            // формально успешном (200/"OK") ответе API. Показывать в этом случае "OK" как
            // заголовок ошибки было бы противоречиво, поэтому передаём его фронту только
            // когда сам HTTP-статус запроса действительно означает сбой.
            $httpStatus = $result['http_status'] ?? null;
            return [
                'success' => false,
                'errors' => $result['errors'],
                'http_status_message' => ($httpStatus !== null && $httpStatus >= 400) ? ($result['http_status_message'] ?? null) : null,
            ];
        }

        return ['success' => true, 'message' => $result['http_status_message'] ?? 'OK'];
    }

}