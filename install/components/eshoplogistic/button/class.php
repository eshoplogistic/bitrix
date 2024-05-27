<?php

use Bitrix\Main,
    Bitrix\Main\Loader,
    Bitrix\Main\Context,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem;

use Eshoplogistic\Delivery\Config;

if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
	die();

GLOBAL $sale_module;
$sale_module=true;

if(!Loader::includeModule('sale')) {
    $sale_module=false;
}




/**
 * @var $APPLICATION CMain
 * @var $USER        CUser
 */

class EslButtonComponent extends \CBitrixComponent
{

    public static function getPropertyByCode($propertyCollection, $code)  {
        foreach ($propertyCollection as $property)
        {
            if($property->getField('CODE') == $code)
                return $property;
        }
    }

    private function getOfferProps($offerIds,$propIds)
    {
        GLOBAL $sale_module;
        $param_group_names=array();
        $offers=array();

        foreach($offerIds as $offerId)
        {
            $arFilter = Array("ID"=>$offerId);
            $arProps=array();
            $res = CIBlockElement::GetList(Array(), $arFilter);

            if ($ob = $res->GetNextElement()){;
                //$arFields = $ob->GetFields();
                $arProps = $ob->GetProperties();
            }
            foreach($arProps as $key => $ar)
            {
                if(in_array($ar['ID'],$propIds) && $key!='CML2_LINK')
                {
                    if($ar['PROPERTY_TYPE']=='S')
                    {
                        $arval=self::getDirectoryLivVal($ar['VALUE']);
                        $offers[$offerId][$key]['value']=$arval['name'];
                        if(isset($arval['file']))$offers[$offerId][$key]['file']=$arval['file'];
                    }
                    else
                        $offers[$offerId][$key]['value']=$ar['VALUE'];
                    if(!isset($param_group_names[$key]))$param_group_names[$key]=$ar['NAME'];
                }
            }
            //$offers[$offerId]['price']=CPrice::GetBasePrice($offerId)['PRICE'];
            if($sale_module)
                $offers[$offerId]['price']=CCatalogProduct::GetOptimalPrice($offerId)['RESULT_PRICE']['DISCOUNT_PRICE'];
            else $offers[$offerId]['price']=0;
        }

        return array("offers"=>$offers,"param_group_names"=>$param_group_names);
    }

    public static function getDirectoryLivVal($XML_ID)
    {
        CModule::IncludeModule('highloadblock');
        $arret=array();
        $rsData = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array()));
        while ($hldata = $rsData->fetch())
        {
            $hlentity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hldata);
            $hlDataClass = $hldata['NAME'].'Table';
            $res = $hlDataClass::getList(array(
                    'filter' => array(
                        'UF_XML_ID' => $XML_ID,
                    ),
                    'select' => array("*"),
                    'order' => array(
                        'UF_NAME' => 'asc'
                    ),
                )
            );
            if ($row = $res->fetch()) {
                if(isset($row['UF_NAME']))
                {
                    $arret['name']=$row['UF_NAME'];
                    if($row['UF_FILE']>0)$arret['file']=self::getFileById($row['UF_FILE']);
                    return $arret;
                }
            }

        }
        return $arret;
    }

    public static function getFileById($FILE_ID)
    {
        $rsFile = CFile::GetByID($FILE_ID);
        $arFile = $rsFile->Fetch();
        return '/upload/'.$arFile['SUBDIR'].'/'.$arFile['FILE_NAME'];
    }


    public function getElementById($element_id)
    {
        GLOBAL $sale_module;
        $arRet=array();
        if(!$fields)
            $fields = array();

        if(!$fields_price)
            $fields_price = '';

        if(!CModule::IncludeModule("iblock"))return array("result"=>"error",'description'=>"Error include iblock");

        if(!function_exists("GetIBlockElementListEx"))return array("result"=>"error",'description'=>"Error GetIBlockElementListEx not exist");

        $items= GetIBlockElementListEx("",array(),array(),array(),0,array("ID"=>$element_id));
        while($arItem =  $items->GetNext())
            $arRet['detail_page_url']=$arItem["DETAIL_PAGE_URL"];

        if($sale_module)$arRet['price']=CCatalogProduct::GetOptimalPrice($element_id)['RESULT_PRICE']['DISCOUNT_PRICE'];
        //else $arRet['price']=CPrice::GetBasePrice($element_id)['PRICE'];
        else {
            $arRet['price']=0;
            $props=self::getElementPropValues($element_id,$fields_price);
            foreach($props['offers'] as $ar)
                foreach($ar as $arr)
                    foreach($arr as $arrr)
                        if($arrr[0] > 0)$arRet['price']=$arrr[0];
        }
        $this->offerPrice=$arRet['price'];

        if($fields)
            $fields=array_diff($fields,array(""));

        if($fields_price)
            $fields_price=array_diff($fields_price,array(""));

        $arSelect  = array("*");
        $arSelect  = array_unique(array_merge($arSelect, $fields));


        $rezE = CIBlockElement::GetList(array(), array('=ID'=> $element_id), false, false, $arSelect);
        if($arElement = $rezE->Fetch()) {
            $picture='';
            if($arElement['PREVIEW_PICTURE'] || $arElement['DETAIL_PICTURE']) {
                $picture =  CFile::ResizeImageGet(($arElement['PREVIEW_PICTURE'] ? $arElement['PREVIEW_PICTURE'] : $arElement['DETAIL_PICTURE']), array("width" => 200, "height" => 200))['src'];
            }
            $arRet["name"]=$arElement['NAME'];
            $arRet["description"]=(trim($arElement['PREVIEW_TEXT'])!=''?$arElement['PREVIEW_TEXT']:$arElement['DETAIL_TEXT']);
            $arRet["img"]=$picture;
            $ret_self=false;
            if($sale_module)
            {
                $arRet['offers_exists']=CCatalogSKU::getExistOffers(array($arElement['ID']))[$arElement['ID']];
                if($arRet['offers_exists'])
                {
                    $offers=CCatalogSKU::getOffersList($arElement['ID'],0,array('=ACTIVE' => 'Y'),array('*'),array("*"));
                    $arOffers=array();
                    foreach ($offers as $id=>$offer)
                        foreach ($offer as $offer_id => $arO)
                            $arOffers[]=$offer_id;
                    $arRet["offers"]=self::getOfferProps($arOffers,$fields);
                }
                else $ret_self=true;
            }
            else
                if(count($fields_price)>0)
                {
                    $props=self::getElementPropValues($element_id,$fields);
                    $this->arProps=array();
                    $iii=0;
                    foreach ($props['offers'][$element_id] as $code => $ar)
                    {
                        if(isset($ar['value']))$this->arProps[$iii][$code]=$ar;
                        $iii++;
                    }

                    self::get_reoffers();
                    $arRet["props"]=$this->arProps;
                    $arRet["offers"]["offers"]=$this->offerVariants;
                    $arRet["offers"]["param_group_names"]=$props['param_group_names'];
                    if(!$arRet["offers"] || count($arRet["offers"]["offers"])==0)$ret_self=true;
                    else $arRet['offers_exists']=true;

                }
                else $ret_self=true;

            if($ret_self)$arRet["offers"]=array("offers"=>array($element_id=>array("price"=>$arRet['price'])));

            return $arRet;
        }
    }


    public static function create_order()
    {
        global $USER,$sale_module;

        $request = Context::getCurrent()->getRequest();

        if($request['offers']){
            $offers = json_decode($request['offers'], true)[0];
            $item_id = (isset($request['article']))?$request['article']:false;
            $item_name = (isset($request['name']))?$request['name']:false;
            $item_price = (isset($request['price']))?$request['price']:false;
            $item_summ = (isset($request['summ']))?$request['summ']:false;
            $item_unit = (isset($request['unit']))?$request['unit']:false;
            $item_count = (isset($request['count']))?$request['count']:1;
            $item_weight = (isset($request['weight']))?$request['weight']:1;
        }else{
            return false;
        }

        if(!isset($item_id) && !$item_id)
            return false;

        $arUser=array();
        $arUser['phone'] = $request["phone"];
        $arUser['name'] = $request["name"];
        $arUser['email'] = $request["email"];
        $arUser['comment'] = $request["comment"];

        $idShipper = json_decode($request['idShipper'], true);
        $selectedDelivery = json_decode($request['selectedDelivery'], true);
        $selectedPayment = json_decode($request['selectedPayment'], true);
        $city = json_decode($request['city'], true);
        $addressForDelivery = $request['addressForDelivery'];
        $costDelivery = $request['costDelivery'];

        if($sale_module)
        {
            Bitrix\Main\Loader::includeModule("sale");
            Bitrix\Main\Loader::includeModule("catalog");

            $siteId = Context::getCurrent()->getSite();
            $currencyCode = CurrencyManager::getBaseCurrency();

            $order = Order::create($siteId, $USER->isAuthorized() ? $USER->GetID() :  \CSaleUser::GetAnonymousUserID());
            $order->setPersonTypeId(1);
            $order->setField('CURRENCY', $currencyCode);
            if ($arUser['comment']) {
                $order->setField('USER_DESCRIPTION',
                    $arUser['comment']);
            }


            $basket = Basket::create($siteId);
            $item = $basket->createItem('catalog', $offers['article']);
            $item->setFields(array(
                'QUANTITY' => $item_count,
                'CURRENCY' => $currencyCode,
                'LID' => $siteId,
                'PRODUCT_PROVIDER_CLASS' => '\CCatalogProductProvider',
            ));
            $order->setBasket($basket);

            $deliveryCurrectBX = Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId();
            $rsDelivery = Delivery\Services\Table::getList(array(
                'filter' => array('ACTIVE'=>'Y', '=CODE' => Config::DELIVERY_CODE),
                'select' => array('ID')
            ));

            if($delivery=$rsDelivery->fetch()) {

                $rsProfile = Delivery\Services\Table::getList(array(
                    'filter' => array('ACTIVE' => 'Y', 'PARENT_ID' => $delivery['ID']),
                    'select' => array('ID', 'CODE', 'DESCRIPTION')
                ));
                while ($profile = $rsProfile->fetch()) {
                    $deliveryBX = self::findDeliveryByName($profile, $idShipper['keyShipper'], $selectedDelivery['key']);
                    if($deliveryBX)
                        $deliveryCurrectBX = $deliveryBX;
                }
            }


            $shipmentCollection = $order->getShipmentCollection();
            $shipment = $shipmentCollection->createItem();
            $service = Delivery\Services\Manager::getById($deliveryCurrectBX);
            $shipment->setFields(array(
                'DELIVERY_ID' => $service['ID'],
                'DELIVERY_NAME' => $service['NAME'],
            ));
            $shipmentItemCollection = $shipment->getShipmentItemCollection();
            $shipmentItem = $shipmentItemCollection->createItem($item);
            $shipmentItem->setQuantity($item_count);
            $shipment->setBasePriceDelivery($costDelivery);

            $paymentCollection = $order->getPaymentCollection();
            $payment = $paymentCollection->createItem();
            $paySystemService = PaySystem\Manager::getObjectById(1);
            $payment->setFields(array(
                'PAY_SYSTEM_ID' => $paySystemService->getField("PAY_SYSTEM_ID"),
                'PAY_SYSTEM_NAME' => $paySystemService->getField("NAME"),
            ));

            $propertyCollection = $order->getPropertyCollection();

            if($phoneProp = self::getPropertyByCode($propertyCollection, 'PHONE'))
                $phoneProp->setValue($arUser['phone']);
            if($nameProp = self::getPropertyByCode($propertyCollection, 'FIO'))
                $nameProp->setValue($arUser['name']);
            if($emailProp = self::getPropertyByCode($propertyCollection, 'EMAIL'))
                $emailProp->setValue($arUser['email']);
            if($zipProp = self::getPropertyByCode($propertyCollection, 'ZIP'))
                $zipProp->setValue($city['postal_code']);
            if($cityProp = self::getPropertyByCode($propertyCollection, 'CITY'))
                $cityProp->setValue($city['name']);
            if($pvzProp = self::getPropertyByCode($propertyCollection, 'ESHOPLOGISTIC_PVZ'))
                $pvzProp->setValue($addressForDelivery);
            //if($locationProp = self::getPropertyByCode($propertyCollection, 'LOCATION'))
            //$locationProp->setValue($addressForDelivery);
            //if($addressProp = self::getPropertyByCode($propertyCollection, 'ADDRESS'))
            //$addressProp->setValue($addressForDelivery);

            $order->doFinalAction(true);
            $result = $order->save();
            $orderId = $order->getId();
	        if($orderId > 0)return array("success"=>true,"message"=>"Order save success");
	        else return array("success"=>"error","message"=>"Order save error");
        }

    }

    private function findDeliveryByName($deliveryBX, $code, $type){
        $result = false;

        if($type === 'terminal')
            $nameTypeBx = 'term';

        $nameDeliveryBx = 'eslogistic:'.$code.'_'.$nameTypeBx;

        if($nameDeliveryBx === $deliveryBX['CODE'])
            $result = $deliveryBX['ID'];

        return $result;
    }


}