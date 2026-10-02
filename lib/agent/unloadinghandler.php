<?php
namespace Eshoplogistic\Delivery\Agent;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Order;
use Bitrix\Sale\Internals\OrderTable;
use Bitrix\Sale\Internals\OrderPropsValueTable;
use Eshoplogistic\Delivery\Config;
use Eshoplogistic\Delivery\Event\Unloading;
use Eshoplogistic\Delivery\Logger\Logger;
use Bitrix\Sale\Delivery\Services\Manager;
use Eshoplogistic\Delivery\Helpers\ShippingHelper;

/** Agents for unloading
 * Class UnloadingHandler
 * @package Eshoplogistic\Delivery\Agent
 */

class UnloadingHandler
{
    // Агент обязан успевать вернуть строку переустановки. Если PHP убивает его по
    // max_execution_time (агенты на хитах — обычно 30-60 с), ядро не сдвигает NEXT_EXEC
    // и снимает блокировку через CAgent::LOCK_TIME (600 с) — агент перезапускается на
    // ближайшем хите каждые 10 минут и каждый раз заново опрашивает те же заказы.
    // На проде это давало ~110 запросов к API каждые 10 минут (~16 000 в сутки).
    const TIME_BUDGET = 20;

    // Статус у ТК заказов старше этого срока практически не меняется — не опрашиваем их
    // ежедневно бесконечно (раньше без фильтра статусов опрашивались все заказы магазина).
    const MAX_ORDER_AGE_DAYS = 90;

    // ID заказа, на котором прервался прошлый запуск (порции идут от новых к старым);
    // 0 — начать сначала.
    const OPTION_CURSOR = 'agent_unloading_cursor';

    // Заказ, по которому API раз за разом отвечает ошибкой (удалён в кабинете ТК, неверный
    // логин/пароль службы в кабинете eShopLogistic и т.п.), после стольких ошибок подряд
    // больше не опрашивается — иначе запрос по нему уходил бы каждый день
    // до MAX_ORDER_AGE_DAYS. Счётчик привязан к содержимому ESHOPLOGISTIC_SHIPPING_METHODS:
    // повторная выгрузка заказа меняет свойство и сбрасывает счётчик.
    const MAX_FAILED_POLLS = 3;
    const OPTION_FAILURES = 'agent_unloading_failures';

    /**
     * @return string
     */
    public static function update()
    {
        $agentName = "Eshoplogistic\Delivery\Agent\UnloadingHandler::update();";

        if(!\CModule::IncludeModule("sale"))
            // Возврат false/'' здесь заставил бы ядро Bitrix удалить агента из b_agent
            // насовсем (см. classes/general/agent.php: $eval_result == '' -> DELETE).
            // Возвращаем строку переустановки, чтобы агент повторил попытку на следующем запуске.
            return $agentName;

        $deadline = time() + self::getTimeBudget();
        $cursor = (int)Option::get(Config::MODULE_ID, self::OPTION_CURSOR, 0);
        $candidates = self::getCandidateOrders($cursor);
        $failures = self::loadFailures();
        // Изменения счётчиков этого запуска (ID заказа => запись или null — удалить). В конце
        // применяются к заново прочитанной опции, а не перезаписывают её целиком: иначе
        // "Возобновить синхронизацию", нажатое менеджером во время запуска, затиралось бы.
        $failureChanges = array();

        $shippingHelper = new ShippingHelper();
        $nextCursor = 0;

        foreach ($candidates as $orderId => $propertyHash) {
            if (isset($failures[$orderId]) && $failures[$orderId]['hash'] !== $propertyHash) {
                unset($failures[$orderId]);
                $failureChanges[$orderId] = null;
            }
            if (isset($failures[$orderId]) && $failures[$orderId]['count'] >= self::MAX_FAILED_POLLS) {
                continue;
            }

            if (time() >= $deadline) {
                $nextCursor = $orderId;
                break;
            }

            // Один проблемный заказ (например Order::save()/getShipmentCollection() кинет
            // исключение D7) не должен обрывать обработку всех остальных заказов в этом
            // запуске cron - логируем и переходим к следующему.
            try {
            $order = Order::load($orderId);
            if (!$order) {
                continue;
            }

            // Проверка, что доставка заказа принадлежит данному плагину
            $shipmentCollection = $order->getShipmentCollection();
            $serviceSlug = null;

            foreach ($shipmentCollection as $shipment) {
                if ($shipment->isSystem()) continue;
                $deliveryId = $shipment->getDeliveryId();
                $deliveryService = Manager::getObjectById($deliveryId);
                if ($deliveryService) {
                    $deliveryCode = $deliveryService->getCode();
                    $currectDeliveryEsl = $shippingHelper->getSlugMethod($deliveryCode);
                    // Берём первое отправление, требующее опроса статуса: иначе оно
                    // "перекрывалось" бы последующим отправлением этого же заказа, для
                    // которого checkUnloadingDelivery() вернул false.
                    if ($currectDeliveryEsl && $shippingHelper->checkUnloadingDelivery($currectDeliveryEsl)) {
                        $serviceSlug = $currectDeliveryEsl;
                        break;
                    }
                }
            }
            if (!$serviceSlug) {
                continue;
            }

            $unloading = new Unloading();
            $status = $unloading->infoOrder($orderId);

            $result = array();
            $result['idOrder'] = $orderId;
            if (isset($status['http_status']) && $status['http_status'] === 422) {
                $result['unloading'] = $status;
                $failureChanges[$orderId] = array(
                    'hash' => $propertyHash,
                    'count' => ($failures[$orderId]['count'] ?? 0) + 1,
                    // Для сообщения в заказе (см. Unloading::orderInfoBlockShow): ошибка
                    // авторизации службы — не повод сбрасывать выгрузку, заказ у ТК может быть.
                    'credentials' => isset($status['errors']['credentials']),
                    'error' => self::errorText($status['errors'] ?? ($status['http_status_message'] ?? '')),
                );
            } elseif(isset($status['data'])) {
                $failureChanges[$orderId] = null;
                $result['unloading'] = $status;
                $result['updateStatus'] = $unloading->updateStatusById($status['data'], $orderId);

                // Трек/номер у служб с асинхронным подтверждением (например, ПЭК) мог не
                // прийти сразу при создании заказа — как только он появился, снимаем флаг
                // "ожидает подтверждения", выставленный в params_delivery_init().
                if (isset($status['data']['state']['number']) && $unloading->getPendingConfirmation($orderId)) {
                    $unloading->clearPendingConfirmation($orderId);
                }
            }else{
                $result['unloading'] = $status;
            }

            $severity = (isset($status['http_status']) && $status['http_status'] === 422)
                ? \CEventLog::SEVERITY_ERROR
                : \CEventLog::SEVERITY_INFO;
            $description = Logger::msg('ORDER', ['#ORDER_ID#' => $orderId]) . '<br>' . Logger::pretty($result);
            Logger::log('UNLOADING_CRON', $description, $severity, $orderId);
            } catch (\Throwable $e) {
                Logger::error('UNLOADING_CRON', Logger::msg('ORDER', ['#ORDER_ID#' => $orderId]) . ': ' . $e->getMessage());
                continue;
            }
        }

        $failures = self::loadFailures();
        foreach ($failureChanges as $orderId => $failure) {
            if ($failure === null) {
                unset($failures[$orderId]);
            } else {
                $failures[$orderId] = $failure;
            }
        }
        // Полный список кандидатов есть только у запуска с начала — тогда же убираем счётчики
        // заказов, которые из опроса выпали сами (старше срока, сменили статус, сброшена выгрузка).
        if ($cursor === 0) {
            $failures = array_intersect_key($failures, $candidates);
        }

        Option::set(Config::MODULE_ID, self::OPTION_CURSOR, $nextCursor);
        self::saveFailures($failures);

        return $agentName;
    }

    /** Отключён ли опрос статуса заказа после MAX_FAILED_POLLS ошибок подряд
     * @param int $orderId
     * @return array|null null — опрос работает; иначе ['credentials' => bool, 'error' => string]
     */
    public static function getPollingBan($orderId)
    {
        $orderId = (int)$orderId;
        $failure = self::loadFailures()[$orderId] ?? null;
        if (!$failure || $failure['count'] < self::MAX_FAILED_POLLS) {
            return null;
        }

        // Повторная выгрузка или сброс меняют свойство — тогда метка уже недействительна,
        // даже если агент ещё не успел её убрать.
        $row = OrderPropsValueTable::getList(array(
            'filter' => array('=ORDER_ID' => $orderId, '=CODE' => 'ESHOPLOGISTIC_SHIPPING_METHODS'),
            'select' => array('VALUE'),
        ))->fetch();
        if (!$row || md5((string)$row['VALUE']) !== $failure['hash']) {
            return null;
        }

        return array(
            'credentials' => !empty($failure['credentials']),
            'error' => (string)($failure['error'] ?? ''),
        );
    }

    /** Снимает метку "не опрашивать" с заказа — агент снова опросит его на следующем запуске
     * @param int $orderId
     */
    public static function liftPollingBan($orderId)
    {
        $failures = self::loadFailures();
        unset($failures[(int)$orderId]);
        self::saveFailures($failures);
    }

    /** Читается из b_option напрямую, а не через Option::get(): тот держит опции модуля в
     * памяти на весь хит, и изменения из другого процесса (менеджер снял метку, пока агент
     * работал) агент при сохранении не увидел бы.
     * @return array ID заказа => ['hash', 'count', 'credentials', 'error']
     */
    private static function loadFailures()
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $row = $connection->query(
            "SELECT VALUE FROM b_option WHERE MODULE_ID = '" . $helper->forSql(Config::MODULE_ID) . "'"
            . " AND NAME = '" . $helper->forSql(self::OPTION_FAILURES) . "'"
        )->fetch();
        $failures = $row ? json_decode((string)$row['VALUE'], true) : null;

        return is_array($failures) ? $failures : array();
    }

    /** JSON_UNESCAPED_UNICODE — кириллица ошибок иначе кодируется \uXXXX (6 байт на символ):
     * на сотне заказов это сотни КБ, а на старых установках b_option.VALUE — TEXT (64 КБ),
     * обрезанный JSON не разбирается и все метки разом теряются.
     * @param array $failures
     */
    private static function saveFailures(array $failures)
    {
        Option::set(Config::MODULE_ID, self::OPTION_FAILURES, json_encode($failures, JSON_UNESCAPED_UNICODE));
    }

    /** Ошибки API одной строкой — хранится в опции, поэтому с ограничением длины
     * @param mixed $errors
     * @return string
     */
    private static function errorText($errors)
    {
        $flat = array();
        if (is_array($errors)) {
            array_walk_recursive($errors, function ($value) use (&$flat) {
                $flat[] = $value;
            });
        } else {
            $flat[] = (string)$errors;
        }

        return mb_substr(implode('; ', $flat), 0, 150);
    }

    /** Бюджет времени на один запуск — с запасом от max_execution_time хита
     * @return int секунды
     */
    private static function getTimeBudget()
    {
        $budget = self::TIME_BUDGET;
        $maxExecutionTime = (int)ini_get('max_execution_time');
        if ($maxExecutionTime > 0) {
            $budget = min($budget, max(5, (int)floor($maxExecutionTime / 2)));
        }

        return $budget;
    }

    /** ID заказов, которые действительно выгружены в ТК (в ESHOPLOGISTIC_SHIPPING_METHODS
     * есть ответ ТК или флаг ожидания подтверждения), от новых к старым. Раньше агент
     * опрашивал API по каждому заказу с доставкой модуля, даже никогда не выгружавшемуся.
     * @param int $cursor обрабатывать только заказы с ID меньше этого (0 — без ограничения)
     * @return array ID заказа => md5 значения ESHOPLOGISTIC_SHIPPING_METHODS
     */
    private static function getCandidateOrders($cursor)
    {
        $propsFilter = array(
            '=CODE' => 'ESHOPLOGISTIC_SHIPPING_METHODS',
            array(
                'LOGIC' => 'OR',
                // Именно объект: после неудачной выгрузки в свойстве бывает "answer":null.
                array('%VALUE' => '"answer":{'),
                array('%VALUE' => '"pending_confirmation":true'),
            ),
        );
        if ($cursor > 0) {
            $propsFilter['<ORDER_ID'] = $cursor;
        }

        $unloadedIds = array();
        $rows = OrderPropsValueTable::getList(array(
            'filter' => $propsFilter,
            'select' => array('ORDER_ID', 'VALUE'),
            'order' => array('ORDER_ID' => 'DESC'),
        ));
        while ($row = $rows->fetch()) {
            $unloadedIds[(int)$row['ORDER_ID']] = md5((string)$row['VALUE']);
        }
        if (!$unloadedIds) {
            return array();
        }

        // Без явного LID: в cron-контексте Context::getCurrent()->getSite() не отражает
        // реальный сайт заказа, а резолвится в сайт по умолчанию - на мультисайтовых
        // инсталляциях заказы остальных сайтов агентом никогда не опрашивались.
        // Настройки модуля (api_key и т.п.) общие для всех сайтов, поэтому фильтр не нужен.
        $orderFilter = array(
            '>=DATE_INSERT' => (new DateTime())->add('-' . self::MAX_ORDER_AGE_DAYS . ' days'),
        );
        $statusEnd = Option::get(Config::MODULE_ID, 'cron-status-unloading');
        if ($statusEnd) {
            $orderFilter['@STATUS_ID'] = explode(",", $statusEnd);
        }

        $result = array();
        foreach (array_chunk(array_keys($unloadedIds), 500) as $chunk) {
            $orders = OrderTable::getList(array(
                'filter' => array_merge($orderFilter, array('@ID' => $chunk)),
                'select' => array('ID'),
            ));
            while ($order = $orders->fetch()) {
                $result[(int)$order['ID']] = $unloadedIds[(int)$order['ID']];
            }
        }
        krsort($result);

        return $result;
    }
}
