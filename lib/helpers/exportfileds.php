<?php
namespace Eshoplogistic\Delivery\Helpers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use DateTime;
use Eshoplogistic\Delivery\Api\Tariffs;
use Eshoplogistic\Delivery\Config;

class ExportFileds {

    public function sendExportFields($name){
        $result = array();
        if ( $name === 'boxberry' ) {
            $result = array(
                'order' => array(
                    'barcode' => '',
                    'type' => '',
                    'packing_type' => '',
                    'issue'        => ''
                )
            );
        }
        if ( $name === 'sdek' ) {
            $result = array(
                'order'    => array(
                    'type' => ''
                ),
                'delivery' => array(
                    'tariff' => '',
                )
            );
        }
        if ( $name === 'delline' ) {
            $result = array(
                'sender'   => array(
                    'requester'    => '',
                    'counterparty' => '',
                ),
                'order'    => array(
                    'accept' => '',
                ),
                'delivery' => array(
                    'mode' => '',
                    'produce_date' => '',
                )
            );
        }

        if ( $name === 'kit' ) {
            $result = array(
                'sender'   => array(
                    'requester' => '',
                ),
                'receiver' => array(
                    'legal' => '',
                    'company' => '',
                    'requisites' => array(
                        'inn' => '',
                        'kpp' => '',
                        'unp' => '',
                        'bin' => '',
                    ),
                ),
                'delivery' => array(
                    'variant' => '',
                    'location_from' => array(
                        'pick_up_data' => array(
                            'date' => '',
                            'time_from' => '',
                            'time_to' => '',
                            'comment' => '',
                        )
                    )
                ),
            );
        }

        if( $name === 'postrf'){
            $result = array(
                'delivery' => array(
                    'tariff' => '',
                    'location_to' => array(
                        'index' => ''
                    )
                ),
            );
        }

        if( $name === 'pecom'){
            $result = array(
                'sender' => array(
                    'identity' => array(
                        'type' => '',
                        'series' => '',
                        'number' => '',
                        'date' => '',
                    )
                ),
                'delivery'   => array(
                    'produce_date' => '',
                ),
            );
        }

        return $result;
    }


    public function exportFields($name , $shippingMethods = array()){
        $result = array();
        if($name === 'boxberry'){
            $result = array(
                'order' => array(
                    'barcode||text' => '',
                    'type||select' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_1"),
                    'packing_type||select' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_2"),
                    'issue||select' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_3"),
                )
            );
        }
        if($name === 'sdek') {
            $tariffsApi = new Tariffs();
            $tariffs = $tariffsApi->sendExport($name);
            $tariffs = $tariffs['data']??'';
            if(isset($shippingMethods['terminal']['tariff'])){
                $selectedTariffCode = $shippingMethods['terminal']['tariff']['code'];
                if(isset($tariffs[$selectedTariffCode])) {
                    $value[$selectedTariffCode] = $tariffs[$selectedTariffCode];
                    unset($tariffs[$selectedTariffCode]);
                    $tariffs = $value + $tariffs;
                }
            }
            $result = array(
                'order' => array(
                    'type||select' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_SDEK_1"),
                ),
                'delivery' => array(
                    'tariff||select' => $tariffs,
                )
            );
        }
        if ( $name === 'delline' ) {
            $date = new DateTime();
            $date->modify('+1 day');
            $produce_date = $date->format('Y-m-d');

            $result = array(
                'sender'   => array(
                    'requester||text'    => (Option::get(Config::MODULE_ID, 'sender-uid-delline'))??'',
                    'counterparty||text' => (Option::get(Config::MODULE_ID, 'sender-counter-delline'))??'',
                ),
                'order'    => array(
                    'accept||select' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_DELLINE_1"),
                ),
                'delivery' => array(
                    'mode||select' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_DELLINE_2"),
                    'produce_date||date' => $produce_date,
                )
            );
        }

        if ( $name === 'kit' ) {
            $date = new DateTime();
            $date->modify('+1 day');
            $produce_date = $date->format('Y-m-d');

            $result = array(
                'sender'   => array(
                    'requester||text'    => (Option::get(Config::MODULE_ID, 'sender-uid-kit'))??'',
                ),
                'receiver' => array(
                    'legal||select' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_KIT_1"),
                    'company||text' => '',
                ),
                'receiver[requisites]' => array(
                    'inn||text' => '',
                    'kpp||text' => '',
                    'unp||text' => '',
                    'bin||text' => '',
                ),
                'delivery' => array(
                    'variant||select' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_KIT_2"),
                ),
                'delivery[location_from][pick_up_data]' => array(
                    'date||date' => $produce_date,
                    'time_from||date' => $produce_date,
                    'time_to||date' => $produce_date,
                    'comment||text|' => '',

                )
            );
        }

        if ( $name === 'postrf'){
            $tariffsApi = new Tariffs();
            $tariffs = $tariffsApi->sendExport($name);
            $tariffs = $tariffs['data']??'';
            if ( isset( $shippingMethods['tariff'] ) ) {
                $selectedTariffCode = $shippingMethods['tariff']['code'];
                if ( isset( $tariffs[ $selectedTariffCode ] ) ) {
                    $value[ $selectedTariffCode ] = $tariffs[ $selectedTariffCode ];
                    unset( $tariffs[ $selectedTariffCode ] );
                    $tariffs = $value + $tariffs;
                }
            }

            $tariffsResult = array();
            foreach ($tariffs as $key=>$value){
                $tariffsResult[$key] = $value['name'];
            }

            $result = array(
                'delivery' => array(
                    'tariff||select' => $tariffsResult,
                ),
                'delivery[location_to][address]' => array(
                    'index||text' => ''
                )
            );
        }

        if ( $name === 'pecom' ) {
            $date = new DateTime();
            $date->modify('+1 day');
            $produce_date = $date->format('Y-m-d');

            $result = array(
                'sender[identity]'   => array(
                    'type||select'    => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_PECOM_1")??'',
                    'series||text' => '',
                    'number||text' => '',
                    'date||date' => '',
                ),
                'delivery' => array(
                    'produce_date||date' => $produce_date,
                )
            );
        }

        return $result;
    }

}
