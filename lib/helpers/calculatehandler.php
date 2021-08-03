<?
namespace Webnauts\EshopLogistic\Helpers;

use \Bitrix\Sale,
    \Bitrix\Main\Error,
    \Bitrix\Main\Localization\Loc,
    \Webnauts\EshopLogistic\Api,
    \Webnauts\EshopLogistic\Helpers,
    \Webnauts\EshopLogistic\Config;



/** Class for calculate deliveries
 * Class LocationHandler
 * @package Webnauts\EshopLogistic\Helpers
 * @copyright webnauts.pro
 * @author negen
 */

class CalculateHandler
{

    /** Calculating deliveries
     * @param Sale\Shipment $shipment
     * @param string $service
     * @param string $type
     * @return Sale\Delivery\CalculationResult $result
     */
    public function getDefaultCalculateDelivery(Sale\Shipment $shipment, $service, $type)
    {


        $order = $shipment->getCollection()->getOrder();
        $basket = $order->getBasket();
        $props = $order->getPropertyCollection();
        $locationCode = $props->getDeliveryLocation()->getValue();

        $paymentCollection = $order->getPaymentCollection();

        $configClass = new Config();
        $paymentType = '';
        $paymentTypesList = $configClass->getPaymentTypes();

        foreach ($paymentCollection as $payment) {
            $paymentId = $payment->getPaymentSystemId();
            $paymentType = self::getCurrentPaymentTypes($paymentTypesList, $paymentId);
            if($paymentType !== '') break;
        }

        $result = new Sale\Delivery\CalculationResult();

        $sendPoint = self::getSendPoint();

        $orderData['payment'] = $paymentType;

        $orderData['offers'] = OrderHandler::getCurrentBasketItems($basket);

        $deliveriesListFrom = $sendPoint['services'];
        $from = $deliveriesListFrom[$service]['city_code'];

        $deliveriesListTo = LocationHandler::getAvailableDeliveriesByLocation($locationCode);
        $to = $deliveriesListTo['services'][$service];

        if(!$to) {
            $result->addError(new \Bitrix\Main\Error($configClass->locationError));
            return $result;
        }

        $deliveryProfileData = Api\Delivery::getLocationDeliveryData($service, $from, $to, $orderData);

        unset($deliveryProfileData['data']['terminals']);

        if($deliveryProfileData['success']) {
            if(empty($deliveryProfileData['data'][$type])) {
                $result->addError(new \Bitrix\Main\Error($configClass->dataError));
            }

            if($deliveryProfileData['data'][$type]['price'] === 0) {
                $deliveryProfileData['data'][$type]['price'] = 'free';
            }

            if($deliveryProfileData['data']['comments']) {
                $result->setDescription($deliveryProfileData['data']['comments']);
            }

            $result->setDeliveryPrice(
                roundEx(
                    $deliveryProfileData['data'][$type]['price'],
                    SALE_VALUE_PRECISION
                )
            );
            if(empty($deliveryProfileData['data'][$type]['price'])) {
                $result->addError(new \Bitrix\Main\Error($configClass->priceError));
            }

            $result->setPeriodDescription( $deliveryProfileData['data'][$type]['time']);
        } else {
            $errorString = '';
            if(is_array($deliveryProfileData['msg'])) {
                foreach ($deliveryProfileData['msg'] as $err => $msg) {
                    if($msg)
                    $errorString .= 'Error: '.$err.' '.$msg.'. ';
                }
            } else {
                $errorString = 'Error: '.$deliveryProfileData['msg'];
            }

            if(!$errorString) $errorString == 'Unknown error';

            $result->addError(new \Bitrix\Main\Error($errorString));
        }
        return $result;
    }

    /** Get paysystem type
     * @param array $paymentTypesList
     * @param integer $psID
     * @return string
     */
    private function getCurrentPaymentTypes($paymentTypesList, $psID)
    {

        $paymentType = '';

        if(in_array($psID, $paymentTypesList['card'])) {
            $paymentType = 'card';
        } else if (in_array($psID, $paymentTypesList['cache'])) {
            $paymentType = 'cash';
        } else if (in_array($psID, $paymentTypesList['cashless'])) {
            $paymentType = 'cashless';
        } else if (in_array($psID, $paymentTypesList['prepay'])) {
            $paymentType = 'prepay';
        }
        return $paymentType;
    }

    /** Get send point
     * @return string
     */
    public function getSendPoint(){
        $sendPoint = Api\Site::getSendPoint();
        return $sendPoint;
    }

    /** Get current delivery PVZ list
     * @param $locationCode
     * @param $service
     * @param $paymentId
     * @return array
     */
    public function getDefaultPvzData($locationCode, $service, $paymentId) {

        $sendPoint = self::getSendPoint();

        $configClass = new Config();
        $paymentTypesList = $configClass->getPaymentTypes();
        $paymentType = self::getCurrentPaymentTypes($paymentTypesList, $paymentId);
        $basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), \Bitrix\Main\Context::getCurrent()->getSite());

        $orderData['payment'] = $paymentType;

        $orderData['offers'] = Helpers\OrderHandler::getCurrentBasketItems($basket);

        $deliveriesListFrom = $sendPoint['services'];
        $from = $deliveriesListFrom[$service]['city_code'];

        $deliveriesListTo = Helpers\LocationHandler::getAvailableDeliveriesByLocation($locationCode);
        $to = $deliveriesListTo['services'][$service];

        $deliveryProfileData = Api\Delivery::getLocationDeliveryData($service, $from, $to, $orderData);

        return $deliveryProfileData;
    }
}