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
                // Анонимный прокси (см. isSameOriginRequest/checkWidgetRateLimit ниже) —
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

    public static function widgetDataAction()
    {
        $out    = [];
        $request = Application::getInstance()->getContext()->getRequest();

        $method = trim((string)$request->getPost('method'));

        // Только widget/<имя>[/<имя>...]: префикса мало — "widget/../delivery/order" или
        // "widget/x?key=..." через CURLOPT_URL (ApiQuery) ушли бы на произвольный путь API.
        if (!empty($method) && !preg_match('#^widget(/[A-Za-z0-9_\-]+)+$#D', $method)) {
            echo Json::encode(['error' => 'Method is not allowed']);
            exit();
        }

        if (!self::isSameOriginRequest($request)) {
            http_response_code(403);
            echo Json::encode(['error' => 'Forbidden origin']);
            exit();
        }

        if (!self::checkWidgetRateLimit($request)) {
            http_response_code(429);
            echo Json::encode(['error' => 'Too many requests']);
            exit();
        }

        // widget/send places a real order, unlike the read-only methods sharing the general
        // limit above, so it gets its own much tighter per-IP cap.
        if ($method === 'widget/send' && !self::checkWidgetRateLimit($request, 10, 60, 'send')) {
            http_response_code(429);
            echo Json::encode(['error' => 'Too many requests']);
            exit();
        }

        if ($method === 'widget/send' && !self::hasRecentWidgetCalculation()) {
            \CEventLog::Add([
                'SEVERITY' => \CEventLog::SEVERITY_SECURITY,
                'AUDIT_TYPE_ID' => 'ESHOPLOGISTIC_WIDGET_SEND_BLOCKED',
                'MODULE_ID' => Config::MODULE_ID,
                'ITEM_ID' => $request->getRemoteAddress() ?: 'unknown',
                'DESCRIPTION' => 'widget/send blocked: no valid widget/calculation marker in session',
            ]);
            http_response_code(403);
            echo Json::encode(['error' => 'Forbidden']);
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
                $raw = ( $method == 'widget/send' ) ? $request->getPost( 'raw' ) : '';

                if ( $requestOut = self::ApiQuery( $method, $query_data, $raw ) ) {
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

            if ($method === 'widget/calculation' && !empty($out)) {
                self::markWidgetCalculationDone();
            }
        }

        $json = Json::encode( $out );
        echo $json;
        exit();

    }

    /** Marks in the visitor's session that a widget/calculation call has completed, so that
     * widget/send (which places a real delivery order) can require it — see hasRecentWidgetCalculation().
     * Signed with Bitrix's own site key (TimeSigner) rather than a plain flag, so the marker
     * can't be forged/extended by tampering with the stored session value directly, and expiry
     * is enforced by the signature itself rather than a manually compared timestamp.
     */
    private static function markWidgetCalculationDone(): void
    {
        $session = Application::getInstance()->getSession();
        $signed = (new \Bitrix\Main\Security\Sign\TimeSigner())->sign(self::WIDGET_CALC_MARKER, '+' . self::WIDGET_CALC_TTL . ' seconds');
        $session->set('esl_widget_calc_token', $signed);
    }

    private const WIDGET_CALC_MARKER = 'esl_widget_calc_ok';
    // Real users calculate a price and send within the same short checkout flow; keeping this
    // tight shrinks the window in which a self-issued marker (see hasRecentWidgetCalculation())
    // stays usable.
    private const WIDGET_CALC_TTL = 300;

    /** widget/send creates a real order via the proxied API, so — since it can't be gated behind
     * Bitrix Authentication (the widget is used by anonymous storefront visitors, see
     * isSameOriginRequest() below; Csrf alone is not enough — a sessid is only proof of an
     * anonymous session, not of any particular prior action in it) — it's instead gated behind
     * a prior widget/calculation having
     * completed in the same session. A blind/direct POST to widget/send (curl, forged Origin) has
     * no session with that marker and is rejected; the real widget always calculates before sending.
     *
     * This does NOT stop an anonymous scripted attacker who calls widget/calculation themselves
     * first (a legitimately public, read-only method) to mint their own valid marker, then calls
     * widget/send with it — that's not closable without either requiring login (breaks anonymous
     * storefront checkout) or adding user-facing friction (CAPTCHA/challenge), neither of which is
     * a pure server-side fix. This raises the bar against blind/single-request abuse and — combined
     * with the tight per-IP rate limit on widget/send and the logging below — makes sustained abuse
     * both harder to automate and visible in the event log; it is not a complete authentication.
     * @return bool
     */
    private static function hasRecentWidgetCalculation(): bool
    {
        $session = Application::getInstance()->getSession();
        if (!$session->has('esl_widget_calc_token')) {
            return false;
        }

        try {
            $value = (new \Bitrix\Main\Security\Sign\TimeSigner())->unsign((string)$session->get('esl_widget_calc_token'));
        } catch (\Bitrix\Main\Security\Sign\BadSignatureException $e) {
            return false;
        }

        return $value === self::WIDGET_CALC_MARKER;
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