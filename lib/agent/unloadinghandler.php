<?php
namespace Eshoplogistic\Delivery\Agent;

use Bitrix\Main\Config\Option;
use Bitrix\Sale\Order;
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

    /**
     * @return string
     */
    public static function update()
    {
        global $CModule;
        if(!\CModule::IncludeModule("sale"))
            // Возврат false/'' здесь заставил бы ядро Bitrix удалить агента из b_agent
            // насовсем (см. classes/general/agent.php: $eval_result == '' -> DELETE).
            // Возвращаем строку переустановки, чтобы агент повторил попытку на следующем запуске.
            return "Eshoplogistic\Delivery\Agent\UnloadingHandler::update();";

        $statusEnd = Option::get(Config::MODULE_ID, 'cron-status-unloading');
        $filter = [
            'LID' => \Bitrix\Main\Context::getCurrent()->getSite(),
        ];
        if($statusEnd){
            $statusEnd = explode(",", $statusEnd);
            $filter['STATUS_ID'] = $statusEnd;
        }

        $orders = Order::loadByFilter(array(
            'filter' => $filter,
            'select' => ['ID'],
            'order' => ['ID' => 'DESC'],

        ));

        $shippingHelper = new ShippingHelper();
        foreach ($orders as $order) {
            $orderValues = $order->getFields()->getValues();
            $orderId = $orderValues['ID'];

            // Проверка, что доставка заказа принадлежит данному плагину
            $shipmentCollection = $order->getShipmentCollection();
            $checkUnloading = false;

            foreach ($shipmentCollection as $shipment) {
                if ($shipment->isSystem()) continue;
                $deliveryId = $shipment->getDeliveryId();
                $deliveryService = Manager::getObjectById($deliveryId);
                if ($deliveryService) {
                    $deliveryCode = $deliveryService->getCode();
                    $currectDeliveryEsl = $shippingHelper->getSlugMethod($deliveryCode);
                    if($currectDeliveryEsl)
                        $checkUnloading = $shippingHelper->checkUnloadingDelivery($currectDeliveryEsl);
                }
    
            }
            if (!$checkUnloading) {
                continue;
            }

            $unloading = new Unloading();
            $status = $unloading->infoOrder($orderId);

            $result = array();
            $result['idOrder'] = $orderId;
            if (isset($status['http_status']) && $status['http_status'] === 422) {
                $result['unloading'] = $status;
            } elseif(isset($status['data'])) {
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
            $description = 'Заказ #' . $orderId . '<br>' . Logger::pretty($result);
            Logger::log('UNLOADING_CRON', $description, $severity, $orderId);

        }

        return "Eshoplogistic\Delivery\Agent\UnloadingHandler::update();";
    }
}