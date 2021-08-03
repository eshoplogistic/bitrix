<?
namespace Webnauts\EshopLogistic\Event;

use \Webnauts\EshopLogistic\Config;

/** Class for adding a handler for the delivery service in the admin menu
 * Class DeliveryBuildList
 * @package Webnauts\EshopLogistic\Event
 * @copyright webnauts.pro
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