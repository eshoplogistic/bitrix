<?
namespace Eshoplogistic\Delivery\Event;

use \Eshoplogistic\Delivery\Config;

/** Class for adding a handler for the delivery service in the admin menu
 * Class DeliveryBuildList
 * @package Eshoplogistic\Delivery\Event
 * @author negen
 */


class DeliveryBuildList{

    /** Adding a handler for the delivery service in the admin menu
     * @return \Bitrix\Main\EventResult
     */
    static function deliveryBuildList(): \Bitrix\Main\EventResult
    {
        $class = new Config();
        $eventDeliveryList = $class->getEventDeliveryList();

        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            $eventDeliveryList
        );
    }
}
?>