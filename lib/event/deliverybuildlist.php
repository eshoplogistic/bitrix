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
    function deliveryBuildList()
    {
        $class = new Config();
        $eventDeliveryList = $class->getEventDeliveryList();

        $result = new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            $eventDeliveryList
        );
        return $result;
    }
}
?>