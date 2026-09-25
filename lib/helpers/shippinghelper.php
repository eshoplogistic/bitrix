<?php

namespace Eshoplogistic\Delivery\Helpers;

use Eshoplogistic\Delivery\Config;

class ShippingHelper
{
    public $adressRequired = array(
        'dostavista',
    );

    public function isEslMethod($methodId)
    {
        return (strpos($methodId, Config::DELIVERY_CODE) !== false);
    }

    public function getTypeMethod($methodId)
    {
        if(!$this->isEslMethod($methodId)) return null;

        $idWithoutPrefix = explode(Config::DELIVERY_CODE, $methodId)[1];

        $explodeMethod = explode('_', $idWithoutPrefix)[1] ?? null;

        if($explodeMethod == 'term')
            $explodeMethod = 'terminal';

        return $explodeMethod;
    }

    public function getSlugMethod($methodId)
    {
        if(!$this->isEslMethod($methodId)) return null;

        $idWithoutPrefix = explode(Config::DELIVERY_CODE.':', $methodId)[1];

        return explode('_', $idWithoutPrefix)[0];
    }

    public function getAdressRequired($delivery, $shipping_method)
    {
        if(!$shipping_method) return null;

        $result = array(
            'current' => false,
            'adress_required' => false,
        );
        $idWithoutPrefix = explode(Config::DELIVERY_CODE, $shipping_method)[1] ?? '';
        $idWithoutPrefix =  explode('_', $idWithoutPrefix)[0];
        $nameCurrectDelivery = $shipping_method;
        if($idWithoutPrefix)
            $nameCurrectDelivery = $idWithoutPrefix;

        if(in_array($nameCurrectDelivery, $this->adressRequired)){
            $result['current'] = true;
        }

        if(in_array($delivery, $this->adressRequired)){
            $result['adress_required'] = true;
        }
        return $result;
    }

    public function checkUnloadingDelivery($name)
    {
        // Коды служб, для которых доступна выгрузка заказа. Раньше это был массив
        // "Название => код", но сравнивались только коды, а кириллические ключи ломались
        // на UTF-8 сайтах (пакет в cp1251, вне lang/ Маркетплейс его не перекодирует).
        $codes = array(
            'sberlogistics', 'fivepost', 'boxberry', 'yandex', 'sdek', 'delline', 'halva',
            'kit', 'postrf', 'pecom', 'magnit', 'baikal', 'dpd', 'pochtalion',
        );

        return in_array(mb_strtolower((string)$name), $codes, true);
    }

}