<?
namespace Eshoplogistic\Delivery\Event;

use \Bitrix\Main,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Sale,
    \Bitrix\Sale\Delivery,
    \Eshoplogistic\Delivery\Config;


Main\Loader::includeModule('sale');

Loc::loadMessages(__FILE__);

/** Class for handing sale.order.ajax component events
 * Class ComponentOrder
 * @package Eshoplogistic\Delivery\Event
 * @author negen
 */

class ComponentOrder
{
    /** Adding button and delivery terms information for output
     * @param array $arResult
     * @param array $arUserResult
     * @param array $arParams
     */
    public function orderDeliveryBuildList(&$arResult, &$arUserResult, $arParams)
    {
        \CUtil::InitJSCore(array('main_lib'));
        \CUtil::InitJSCore(array('yamap_lib'));

        $request = Main\Application::getInstance()->getContext()->getRequest();
        $requestData = $request->getPost("order");

        $pvzTitle = Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_PVZ_DESC_EMPTY");
        $pvzValue = '';

        foreach ($arResult['DELIVERY'] as $delivery) {
            if($delivery['CHECKED'] == 'Y') {
                $cityCheck = $requestData;
                unset($cityCheck['RECENT_DELIVERY_VALUE']);
                if($delivery['ID'] == $requestData['current-profile-id'] && in_array($requestData['RECENT_DELIVERY_VALUE'], $cityCheck)) {
                    $tmpTitle = explode(',', $requestData['ESHOPLOGISTIC_PVZ']);
                    unset($tmpTitle[0]);
                    $pvzTitle = trim(implode(', ', $tmpTitle));
                    $pvzValue = trim($requestData['ESHOPLOGISTIC_PVZ']);
                }
                break;
            }
        }

        $rsDelivery = Delivery\Services\Table::getList(array(
            'filter' => array('ACTIVE'=>'Y', '=CODE' => Config::DELIVERY_CODE),
            'select' => array('ID')
        ));

        $profileIds = array_keys($arResult['DELIVERY']);

        if($delivery=$rsDelivery->fetch()) {

            $rsProfile = Delivery\Services\Table::getList(array(
                'filter' => array('ACTIVE'=>'Y', 'PARENT_ID' => $delivery['ID'], 'ID' => $profileIds),
                'select' => array('ID', 'CODE', 'DESCRIPTION')
            ));
            while($profile = $rsProfile->fetch()) {

                if($arResult['DELIVERY'][$profile['ID']]['OWN_NAME'])
                    $arResult['DELIVERY'][$profile['ID']]['NAME'] = $arResult['DELIVERY'][$profile['ID']]['OWN_NAME'];

                if($arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'])
                    $arResult['DELIVERY'][$profile['ID']]['NAME'] = $arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'];


                if(
                    isset($arResult['DELIVERY'][$profile['ID']]) &&
                    $arResult['DELIVERY'][$profile['ID']]['CHECKED'] == 'Y') {


                    $isDeliveryHasPvz = self::isDeliveryHasPvz($profile['CODE']);

                    if ($isDeliveryHasPvz && $profile['CODE'] !== 'eslogistic:postrf_term') {
                        $arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] =
                            '<div class="eslog-deliverey-desc">'.$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'].'</div>'.
                            '<div class="eslog-deliverey-desc-lk">'.$arResult['DELIVERY'][$profile['ID']]['CALCULATE_DESCRIPTION'].'</div>'.
                            '<a 
                            onclick="BX.EShopLogistic.Delivery.sale_order_ajax.getPvzList('.$profile['ID'].')"
                            href="javascript:void(0)"
                            id ="eslogistic-btn-choose-pvz"
                            class="eslog-btn-default"
                        >'.
                            Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_BTN").
                            '</a>'.
                            '<span>.
                            <div class="eslogistic-termin">'.Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_PVZ_TERMIN").'</div>
                            <div id="eslogistic-description" class="eslogistic-description">'.$pvzTitle.'</div>
                        </span>'.
                        '<input 
                            id="eslogic-pvz-value" 
                            name="ESHOPLOGISTIC_PVZ"
                            type="hidden" value="'.$pvzValue.'"
                        >'.
                        '<input 
                            name="current-profile-id"
                            type="hidden" value="'.$profile['ID'].'"
                         >';
                    } else {
                        $arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] =
                            '<div class="eslog-deliverey-desc">'.$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'].'</div>'.
                            '<div class="eslog-deliverey-desc-lk">'.$arResult['DELIVERY'][$profile['ID']]['CALCULATE_DESCRIPTION'].'</div>';
                    }
                    $arResult['DELIVERY'][$profile['ID']]['CALCULATE_DESCRIPTION'] = '';
                }
            }
        }
    }

    /** Saving chosen PVZ to order property
     * @param object $arUserResult
     * @param object $request
     */
    public function saleOrderPropertyPvzFill(&$arUserResult, $request)
    {

        if($arUserResult['DELIVERY_ID'] > 0) {
            $rsDelivery = Delivery\Services\Table::getList(array(
                'filter' => array('ACTIVE'=>'Y', 'ID' => $arUserResult['DELIVERY_ID']),
                'select' => array('CODE')
            ));

            if($delivery = $rsDelivery->fetch()) {
                $isDeliveryHasPvz = self::isDeliveryHasPvz($delivery['CODE']);
                if($isDeliveryHasPvz) {

                    $db_props = \CSaleOrderProps::GetList(
                        array(),
                        array(
                            "PERSON_TYPE_ID" => $arUserResult['PERSON_TYPE_ID'],
                            "CODE" => "ESHOPLOGISTIC_PVZ",
                        ),
                        false,
                        false,
                        array('ID')
                    );

                    if ($props = $db_props->Fetch())
                    {
                        $pvz = $request->getPost('ESHOPLOGISTIC_PVZ');
                        if($pvz)
                            $arUserResult['ORDER_PROP'][$props['ID']] = $pvz;
                    }

                }
            }
        }

    }


    /** Mail chosen PVZ to order property
     */
    public function saleOrderPropertyMail($orderID, &$eventName, &$arFields)
    {
        $order_props = \CSaleOrderPropsValue::GetOrderProps($orderID);
        $propertyPvz="";

        while ($arProps = $order_props->Fetch())
        {
            if ($arProps["CODE"] == "ESHOPLOGISTIC_PVZ")
            {
                $propertyPvz = $arProps["VALUE"];
            }
        }

        if($propertyPvz)
            $arFields["ESHOPLOGISTIC_PVZ"] =  'EShopLogistic : '.$propertyPvz;

    }


    /** Check filling of PVZ field
     * @param Sale\Order $order
     * @return Main\EventResult
     */
    public function saleOrderBeforeSaved(Sale\Order $order)
    {
        $deliveryIds = $order->getDeliverySystemId();
        foreach($deliveryIds as $deliveryId) {
            $rsDelivery = Delivery\Services\Table::getList(array(
                'filter' => array('ACTIVE'=>'Y', 'ID' => $deliveryId),
                'select' => array('PARENT_ID', 'CODE')
            ));

            if($delivery = $rsDelivery->fetch()) {
                if($delivery['PARENT_ID'] > 0) {
                    $rsParentDelivery = Delivery\Services\Table::getList(array(
                        'filter' => array('ACTIVE'=>'Y', 'ID' => $delivery['PARENT_ID']),
                        'select' => array('CODE')
                    ));
                    if($parentDelivery = $rsParentDelivery->fetch()) {
                        $isDeliveryHasPvz = self::isDeliveryHasPvz($delivery['CODE']);

                        if($parentDelivery['CODE'] == 'eslogistic' && $isDeliveryHasPvz) {

                            $propertyCollection = $order->getPropertyCollection();

                            foreach ($propertyCollection as $propertyItem) {

                                $propertyCode = $propertyItem->getField("CODE");
                                if ($propertyCode == 'ESHOPLOGISTIC_PVZ') {
                                    if(!$propertyItem->getValue() && $delivery['CODE'] !== 'eslogistic:postrf_term') {
                                        return new Main\EventResult(
                                            Main\EventResult::ERROR,
                                            new Sale\ResultError(
                                                Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_PVZ_FIELD_EMPTY"),
                                                'ESHOP_LOGISTIC_TERMINAL_PVZ_FIELD_EMPTY'
                                            ),
                                            'sale'
                                        );
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }


    /** Check delivery type
     * @param $deliveryCode
     * @return bool
     */
    private function isDeliveryHasPvz($deliveryCode)
    {
        if (array_pop(explode('_', $deliveryCode)) == 'term') {
            return true;
        } else {
            return false;
        }
    }

}