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

        $explodeMethod = explode('_', $idWithoutPrefix)[1];

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
        $idWithoutPrefix = explode(Config::DELIVERY_CODE, $shipping_method)[1];
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
        $nameList = array(
            'СберЛогистика'   => 'sberlogistics',
            '5POST'           => 'fivepost',
            'Boxberry'        => 'boxberry',
            'Яндекс.Доставка' => 'yandex',
            'СДЭК'            => 'sdek',
            'Деловые линии'   => 'delline',
            'Халва'           => 'halva',
            'Kit'             => 'kit',
            'Почта России'    => 'postrf',
            'ПЭК'             => 'pecom',
            'Магнит Пост'     => 'magnit',
            'Байкал Сервис'   => 'baikal',
            'Фулфилмент-оператор «Почтальон»'   => 'pochtalion',
        );

        foreach ( $nameList as $key => $value ) {
            if (str_contains(mb_strtolower($name), mb_strtolower($key))) {
                return true;
            }
        }

        return  false;
    }

}