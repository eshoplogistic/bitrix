<?php
namespace Eshoplogistic\Delivery\Agent;

use Bitrix\Main\Config\Option;
use Bitrix\Sale\Order;
use Eshoplogistic\Delivery\Config;
use Eshoplogistic\Delivery\Event\Unloading;
use Eshoplogistic\Delivery\Logger\Logger;

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

        foreach ($orders as $order) {
            $orderValues = $order->getFields()->getValues();
            $orderId = $orderValues['ID'];

            $unloading = new Unloading();
            $status = $unloading->infoOrder($orderId);

            $result = array();
            $result['idOrder'] = $orderId;
            if (isset($status['http_status']) && $status['http_status'] === 422) {
                $result['unloading'] = $status;
            } else {
                $result['unloading'] = $status;
                $result['updateStatus'] = $unloading->updateStatusById($status['data'], $orderId);
            }

            $logger = new Logger('unloading-cron');
            $logger->log($result);
        }


        return "Eshoplogistic\Delivery\Agent\UnloadingHandler::update();";
    }
}