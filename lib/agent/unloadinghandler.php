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
            return false;

        $statusEnd = Option::get(Config::MODULE_ID, 'cron-status-unloading');
        $filter = [
            'LID' => 's1',
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
            }else{
                $result['unloading'] = $status;
            }

            if (class_exists('\Eshoplogistic\Delivery\Logger\Logger')) {
                $logger = new Logger('unloading-cron');
                $logger->log($result);
            }

        }

        return "Eshoplogistic\Delivery\Agent\UnloadingHandler::update();";
    }
}