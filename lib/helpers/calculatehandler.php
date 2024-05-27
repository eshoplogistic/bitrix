<?

namespace Eshoplogistic\Delivery\Helpers;

use Bitrix\Bizproc\Workflow\Template\Packer\Result\Pack;
use \Bitrix\Sale,
    \Bitrix\Main\Error,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\EventManager,
    \Eshoplogistic\Delivery\Api,
    \Eshoplogistic\Delivery\Helpers,
    \Bitrix\Main\Application,
    \Eshoplogistic\Delivery\Config;

//EventManager::getInstance()->addEventHandler('sale', 'onSaleDeliveryServiceCalculate', 'getDefaultCalculateDelivery');
/** Class for calculate deliveries
 * Class LocationHandler
 * @package Eshoplogistic\Delivery\Helpers
 * @author negen
 */
class CalculateHandler
{


    /** Calculating deliveries
     * @param Sale\Shipment $shipmentx
     * @param string $service
     * @param string $type
     * @return Sale\Delivery\CalculationResult $result
     */
    public static function getDefaultCalculateDelivery(Sale\Shipment $shipment, $service, $type)
    {
        $order = $shipment->getCollection()->getOrder();
        $basket = $order->getBasket();
        $props = $order->getPropertyCollection();
        $locationCode = $props->getDeliveryLocation();
        if ($locationCode) {
            $locationCode = $locationCode->getValue();
        } else {
            $locationCode = Helpers\OrderHandler::getCodeCityByApi();
        }

        $paymentCollection = $order->getPaymentCollection();

        $configClass = new Config();
        $paymentType = '';
        $paymentTypesList = $configClass->getPaymentTypes();

        foreach ($paymentCollection as $payment) {
            $paymentId = $payment->getPaymentSystemId();
            $paymentType = self::getCurrentPaymentTypes($paymentTypesList, $paymentId);
            if ($paymentType !== '') break;
        }

        $result = new Sale\Delivery\CalculationResult();

        $sendPoint = self::getSendPoint();

        $orderData['payment'] = $paymentType;

        $orderData['offers'] = OrderHandler::getCurrentBasketItems($basket);

        $deliveriesListFrom = $sendPoint['services'];
        $from = $deliveriesListFrom[$service]['city_code'];
        $deliveriesListTo = LocationHandler::getAvailableDeliveriesByLocation($locationCode);
        $to = $deliveriesListTo['services'][$service]??$deliveriesListTo['fias'];


        if($service === 'dostavista'){
            $request = Application::getInstance()->getContext()->getRequest();
            $requestData = $request->getPost("order");
            $fullAdressValue = trim($requestData['ESHOPLOGISTIC_FULL_ADDRESS']);
            if($fullAdressValue)
                $to = $fullAdressValue;
        }

        if (!$to) {
            $result->addError(new \Bitrix\Main\Error($configClass->locationError));
            return $result;
        }

        $deliveryProfileData = Api\Delivery::getLocationDeliveryData($service, $from, $to, $orderData);

        unset($deliveryProfileData['data']['terminals']);

        if (isset($deliveryProfileData['success']) || (isset($deliveryProfileData['http_status']) && $deliveryProfileData['http_status'] == 200)) {
            if (empty($deliveryProfileData['data'][$type])) {
                $result->addError(new \Bitrix\Main\Error($configClass->dataError));
            }

            if ($deliveryProfileData['data'][$type]['price'] === 0) {
                $deliveryProfileData['data'][$type]['price'] = 'free';
            }

            if ($deliveryProfileData['data']['comments']) {
                $result->setDescription($deliveryProfileData['data']['comments']);
            }
            $deliveryPrice = $deliveryProfileData['data'][$type]['price']['value'] ?? $deliveryProfileData['data'][$type]['price'];

            $result->setDeliveryPrice(
                roundEx(
                    $deliveryPrice,
                    SALE_VALUE_PRECISION
                )
            );
            if (empty($deliveryProfileData['data'][$type]['price'])) {
                $result->addError(new \Bitrix\Main\Error($configClass->priceError));
            }

            $time = (isset($deliveryProfileData['data'][$type]['time']['value']))?
                $deliveryProfileData['data'][$type]['time']['value'].' '.$deliveryProfileData['data'][$type]['time']['unit']
                :$deliveryProfileData['data'][$type]['time'];
            $result->setPeriodDescription($time);

            if(isset($deliveryProfileData['data']['debug'])){
                $debugAnswer = $deliveryProfileData['data']['debug'];
                $debugArr = array(
                    'settlement_to' => $debugAnswer['shipping_route']['to']['settlement']??'',
                    'region_to' => $debugAnswer['shipping_route']['to']['region']??'',
                    'settlement_from' => $debugAnswer['shipping_route']['from']['settlement']??'',
                    'region_from' => $debugAnswer['shipping_route']['from']['region']??'',
                    'terminal_tarrif' => $deliveryProfileData['data']['terminal']['tariff']??'',
                );
                $result->setDescription('<input name="ESHOPLOGISTIC_SHIPPING_METHODS" type="hidden" value="'.htmlspecialchars(json_encode( $debugArr )).'"/>');
            }
        } else {
            $errorString = '';
            if (is_array($deliveryProfileData['msg'])) {
                foreach ($deliveryProfileData['msg'] as $err => $msg) {
                    if ($msg)
                        $errorString .= 'Error: ' . $err . ' ' . $msg . '. ';
                }
            } else {
                $errorString = 'Error: ' . $deliveryProfileData['msg'];
            }

            if (!$errorString) $errorString == 'Unknown error';

            $result->addError(new \Bitrix\Main\Error($errorString));
        }

        return $result;
    }

    /** Get paysystem type
     * @param array $paymentTypesList
     * @param integer $psID
     * @return string
     */
    private static function getCurrentPaymentTypes($paymentTypesList, $psID)
    {

        $paymentType = '';

        if (in_array($psID, $paymentTypesList['card'])) {
            $paymentType = 'card';
        } else if (in_array($psID, $paymentTypesList['cache'])) {
            $paymentType = 'cash';
        } else if (in_array($psID, $paymentTypesList['cashless'])) {
            $paymentType = 'cashless';
        } else if (in_array($psID, $paymentTypesList['prepay'])) {
            $paymentType = 'prepay';
        } else if (in_array($psID, $paymentTypesList['payment_upon_receipt'])) {
            $paymentType = 'payment_upon_receipt';
        }
        return $paymentType;
    }

    /** Get send point
     * @return string
     */
    public static function getSendPoint()
    {
        $sendPoint = Api\Site::getSendPoint();
        return $sendPoint;
    }

    /** Get current delivery PVZ list
     * @param $locationCode
     * @param $service
     * @param $paymentId
     * @return array
     */
    public static function getDefaultPvzData($locationCode, $service, $paymentId)
    {

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
        $apiV = $configClass->apiV;

        if($apiV) {
            $to = $deliveriesListTo['fias'];
        }else{
            $to = $deliveriesListTo['services'][$service];
        }

        $deliveryProfileData = Api\Delivery::getLocationDeliveryData($service, $from, $to, $orderData);

        return $deliveryProfileData;
    }
}