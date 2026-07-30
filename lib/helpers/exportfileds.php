<?php
namespace Eshoplogistic\Delivery\Helpers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use DateTime;
use Eshoplogistic\Delivery\Api\Tariffs;
use Eshoplogistic\Delivery\Config;

class ExportFileds {

    /** Форма выгрузки рендерит <select> без атрибута selected — браузер сам выбирает первый
     * пункт. Чтобы значение из настроек ТК по умолчанию реально было выбрано в форме,
     * переносим его на первое место в списке значений.
     * @param array $values
     * @param string|int|null $default
     * @return array
     */
    private static function moveToFront($values, $default)
    {
        if ($default === null || $default === '' || !isset($values[$default])) {
            return $values;
        }

        return [$default => $values[$default]] + $values;
    }

    /** @param string $optionKey
     * @return bool
     */
    private static function isChecked($optionKey)
    {
        return Option::get(Config::MODULE_ID, $optionKey) == 'Y';
    }

    /** @param bool $withThird
     * @return array
     */
    private static function payerValues($withThird = false)
    {
        $values = [
            'sender' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_PAYER_SENDER"),
            'receiver' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_PAYER_RECEIVER"),
        ];

        if ($withThird) {
            $values['third'] = Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_PAYER_THIRD");
        }

        return $values;
    }

    public function sendExportFields($name){
        $result = array();
        if ( $name === 'boxberry' ) {
            $result = array(
                'order' => array(
                    'barcode' => '',
                    'type' => '',
                    'packing_type' => '',
                    'issue'        => '',
                    'combine_places' => array(
                        'apply' => '',
                        'dimensions' => '',
                        'weight' => ''
                    )
                )
            );
        }
        if ( $name === 'sdek' ) {
            $result = array(
                'order'    => array(
                    'type' => '',
                    'combine_places' => array(
                        'apply' => '',
                        'dimensions' => '',
                        'weight' => ''
                    )
                ),
                'delivery' => array(
                    'tariff' => '',
                    'take_payment' => '',
                    'delivery-custom-cost' => '',
                )
            );
        }
        if ( $name === 'delline' ) {
            $result = array(
                'sender'   => array(
                    'requester'    => '',
                    'counterparty' => '',
                    'counteragent' => array(
                        'form' => '',
                        'name' => '',
                        'inn' => '',
                    ),
                ),
                'order'    => array(
                    'accept' => '',
                    'freight_type' => '',
                ),
                'delivery' => array(
                    'mode' => '',
                    'produce_date' => '',
                    'location_from' => array(
                        'pick_up_data' => array(
                            'time_from' => '',
                            'time_to' => '',
                        )
                    )
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
                    'take_payment' => '',
                    'delivery-custom-cost' => '',
                    'location_to' => array(
                        'address' => array(
                            'index' => ''
                        )
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
                        'first_name' => '',
                        'last_name' => '',
                        'patronymic' => '',
                    ),
                    'requisites' => array(
                        'name' => '',
                        'inn' => '',
                    ),
                ),
                'order' => array(
                    'content' => '',
                    'payer' => '',
                ),
                'delivery'   => array(
                    'produce_date' => '',
                ),
            );
        }

        if( $name === 'halva'){
            $result = array(
                'order' => array(
                    'packing' => ''
                )
            );
        }

        if ( $name === 'baikal' ) {
            $result = array(
                'sender'   => array(
                    'legal' => '',
                    'identity' => array(
                        'type' => '',
                        'series' => '',
                        'number' => '',
                    ),
                    'requisites' => array(
                        'inn' => '',
                        'kpp' => '',
                    ),
                ),
                'receiver' => array(
                    'legal' => '',
                    'identity' => array(
                        'type' => '',
                        'series' => '',
                        'number' => '',
                    ),
                    'requisites' => array(
                        'inn' => '',
                        'kpp' => '',
                    ),
                ),
                'order' => array(
                    'content' => '',
                    'payer' => '',
                ),
                'delivery' => array(
                    'location_from' => array(
                        'pick_up_data' => array(
                            'date' => '',
                            'time_from' => '',
                            'time_to' => '',
                            'lift' => '',
                            'floor' => '',
                            'comment' => '',
                        )
                    )
                ),
            );
        }

        if ( $name === 'magnit' ) {
            $result = array(
                'receiver' => array(
                    'last_name' => ''
                ),
                'order' => array(
                    'combine_places' => array(
                        'apply' => '',
                        'dimensions' => '',
                        'weight' => ''
                    )
                )
            );
        }

        if ( $name === 'dpd' ) {
            $result = array(
                'receiver' => array(
                    'email' => ''
                ),
                'sender' => array(
                    'email' => '',
                    'company' => '',
                ),
                'order' => array(
                    'content' => '',
                    'costly' => '',
                    'combine_places' => array(
                        'apply' => '',
                        'dimensions' => '',
                        'weight' => ''
                    )
                ),
                'delivery' => array(
                    'produce_date' => '',
                    'produce_time' => '',
                    'tariff' => '',
                ),
            );
        }

        if ( $name === 'fivepost' ) {
            $result = array(
                'delivery' => array(
                    'take_payment' => '',
                    'delivery-custom-cost' => '',
                ),
            );
        }

        if ( $name === 'yandex' ) {
            $result = array(
                'delivery' => array(
                    'take_payment' => '',
                    'delivery-custom-cost' => '',
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
                    'type||select' => self::moveToFront(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_1"), Option::get(Config::MODULE_ID, 'type_order-boxberry')),
                    'packing_type||select' => self::moveToFront(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_2"), Option::get(Config::MODULE_ID, 'packing_type-boxberry')),
                    'issue||select' => self::moveToFront(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_3"), Option::get(Config::MODULE_ID, 'order_issue-boxberry')),
                ),
                'order[combine_places]' => array(
                    'apply||checkbox' => self::isChecked('combine-places-apply-boxberry') ? 'checked' : '',
                    'dimensions||text' => Option::get(Config::MODULE_ID, 'combine-places-dimensions-boxberry') ?? '',
                    'weight||text' => Option::get(Config::MODULE_ID, 'combine-places-weight-boxberry') ?? ''
                ),
            );
        }
        if($name === 'sdek') {
            $tariffsApi = new Tariffs();
            $tariffs = $tariffsApi->sendExport($name);
            $tariffs = $tariffs['data']??'';
            $savedTariff = $shippingMethods['terminal']['tariff'] ?? null;
            if($savedTariff){
                $selectedTariffCode = $savedTariff['code'];
                if(isset($tariffs[$selectedTariffCode])) {
                    $value[$selectedTariffCode] = $tariffs[$selectedTariffCode];
                    unset($tariffs[$selectedTariffCode]);
                    $tariffs = $value + $tariffs;
                } elseif(empty($tariffs)) {
                    $tariffs = [$selectedTariffCode => $savedTariff['name'] ?? $selectedTariffCode];
                }
            }
            $result = array(
                'order' => array(
                    'type||select' => self::moveToFront(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_SDEK_1"), Option::get(Config::MODULE_ID, 'type-order-sdek')),
                ),
                'order[combine_places]' => array(
                    'apply||checkbox' => self::isChecked('combine-places-apply-sdek') ? 'checked' : '',
                    'dimensions||text' => Option::get(Config::MODULE_ID, 'combine-places-dimensions-sdek') ?? '',
                    'weight||text' => Option::get(Config::MODULE_ID, 'combine-places-weight-sdek') ?? ''
                ),
                'delivery' => array(
                    'tariff||select' => $tariffs,
                    'take_payment||checkbox' => self::isChecked('take-payment-default-sdek') ? 'checked' : '',
                    'delivery-custom-cost||text' => '0',
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
                'sender[counteragent]' => array(
                    'form||select' => self::moveToFront([
                        '0x92ee03691f25a9fe4be9910cd87ca9ca' => 'ООО',
                        '0xaa9042fea4fa169d4d021c6941f2090f' => 'ИП',
                        '0x8390b2048d37e0154b845fb22793e865' => 'ОАО',
                        '0xae7b742e5861514f4f5729fa97b77a42' => 'ЗАО',
                        '0x81318eb6f150096b494a15ff66c37823' => 'МУ',
                        '0x80958580c73df96f4c677eefef87422c' => 'ГК',
                        '0x81ab99926ac959594af2f6f0a77b7353' => 'ОФ',
                        '0x83180c1320f58a344588220de53696e7' => 'ТОО',
                        'xaba390e912918cea417d5be67b8d492a' => 'АО',
                    ], Option::get(Config::MODULE_ID, 'sender-counteragent-from-delline')),
                    'name||text' => Option::get(Config::MODULE_ID, 'sender-counteragent-name-delline') ?? '',
                    'inn||text' => Option::get(Config::MODULE_ID, 'sender-counteragent-inn-delline') ?? '',
                ),
                'order'    => array(
                    'accept||select' => self::moveToFront(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_DELLINE_1"), Option::get(Config::MODULE_ID, 'order-accept-delline')),
                    'payer||select' => self::moveToFront(self::payerValues(true), Option::get(Config::MODULE_ID, 'sender-payer-delline')),
                    'freight_type||text' => Option::get(Config::MODULE_ID, 'order-freight-type-delline') ?? '',
                ),
                'delivery' => array(
                    'mode||select' => self::moveToFront(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_DELLINE_2"), Option::get(Config::MODULE_ID, 'mode-delline')),
                    'produce_date||date' => $produce_date,
                ),
                'delivery[location_from][pick_up_data]' => array(
                    'time_from||time' => Option::get(Config::MODULE_ID, 'sender-time-from-delline') ?? '',
                    'time_to||time' => Option::get(Config::MODULE_ID, 'sender-time-to-delline') ?? '',
                ),
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
                    'comment||text' => '',

                )
            );
        }

        if ( $name === 'postrf'){
            $tariffsApi = new Tariffs();
            $tariffs = $tariffsApi->sendExport($name);
            $tariffs = $tariffs['data']??'';
            $savedTariff = $shippingMethods['tariff'] ?? null;
            if ( $savedTariff ) {
                $selectedTariffCode = $savedTariff['code'];
                if ( isset( $tariffs[ $selectedTariffCode ] ) ) {
                    $value[ $selectedTariffCode ] = $tariffs[ $selectedTariffCode ];
                    unset( $tariffs[ $selectedTariffCode ] );
                    $tariffs = $value + $tariffs;
                } elseif ( empty( $tariffs ) ) {
                    $tariffs = [ $selectedTariffCode => $savedTariff['name'] ?? $selectedTariffCode ];
                }
            }

            $tariffsResult = array();
            foreach ($tariffs as $key=>$value){
                $tariffsResult[$key] = $value;
            }

            $result = array(
                'delivery' => array(
                    'tariff||select' => $tariffsResult,
                    'take_payment||checkbox' => self::isChecked('take-payment-default-postrf') ? 'checked' : '',
                    'delivery-custom-cost||text' => '0',
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
                    'type||select'    => self::moveToFront(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_PECOM_1") ?? [], Option::get(Config::MODULE_ID, 'sender-identity-type-pecom')),
                    'series||text' => Option::get(Config::MODULE_ID, 'sender-identity-series-pecom') ?? '',
                    'number||text' => Option::get(Config::MODULE_ID, 'sender-identity-number-pecom') ?? '',
                    'date||date' => Option::get(Config::MODULE_ID, 'sender-identity-date-pecom') ?? '',
                    // API ПЭК: в JSON-поле identity.last_name фактически передаётся отчество,
                    // а в identity.patronymic — фамилия (перепутаны местами на стороне ТК).
                    'first_name||text' => Option::get(Config::MODULE_ID, 'sender-identity-first-name-pecom') ?? '',
                    'last_name||text' => Option::get(Config::MODULE_ID, 'sender-identity-last-name-pecom') ?? '',
                    'patronymic||text' => Option::get(Config::MODULE_ID, 'sender-identity-patronymic-pecom') ?? '',
                ),
                'sender[requisites]' => array(
                    'name||text' => Option::get(Config::MODULE_ID, 'sender-requisites-name-pecom') ?? '',
                    'inn||text' => Option::get(Config::MODULE_ID, 'sender-requisites-inn-pecom') ?? '',
                ),
                'order' => array(
                    'content||text' => Option::get(Config::MODULE_ID, 'order-content-pecom') ?? '',
                    'payer||select' => self::moveToFront(self::payerValues(), Option::get(Config::MODULE_ID, 'sender-payer-pecom')),
                ),
                'delivery' => array(
                    'produce_date||date' => $produce_date,
                )
            );
        }

        if ( $name === 'halva' ) {
            $result = array(
                'order' => array(
                    'packing||checkbox' => '',
                )
            );
        }

        if ( $name === 'baikal' ) {
            $date = new DateTime();
            $date->modify('+1 day');
            $produce_date = $date->format('Y-m-d');

            $result = array(
                'hr' => array(
                    'sender||hr' => ''
                ),
                'sender'   => array(
                    'legal||text'    => Option::get(Config::MODULE_ID, 'sender-type-baikal'),
                    'email||text'    => Option::get(Config::MODULE_ID, 'sender-email-baikal'),
                    'company||text'  => Option::get(Config::MODULE_ID, 'sender-company-baikal'),
                ),
                'sender[identity]' => array(
                    'type||text' => Option::get(Config::MODULE_ID, 'sender-org-form-baikal'),
                    'series||text' => Option::get(Config::MODULE_ID, 'sender-identity-series-baikal'),
                    'number||text' => Option::get(Config::MODULE_ID, 'sender-identity-number-baikal'),
                ),
                'sender[requisites]' => array(
                    'inn||text' => Option::get(Config::MODULE_ID, 'sender-inn-baikal'),
                    'kpp||text' => Option::get(Config::MODULE_ID, 'sender-kpp-baikal'),
                ),
                'hr2' => array(
                    'receiver||hr' => ''
                ),
                'receiver'   => array(
                    'legal||select'    => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_LEGAL_BAIKAL_1"),
                ),
                'receiver[identity]' => array(
                    'type||select' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TYPE_BAIKAL_1"),
                    'series||text' => '',
                    'number||text' => '',
                ),
                'receiver[requisites]' => array(
                    'inn||text' => '',
                    'kpp||text' => '',
                ),
                'hr3' => array(
                    'empty||hr' => ''
                ),
                'order' => array(
                    'content||text' => Option::get(Config::MODULE_ID, 'order-content-baikal') ?? '',
                    'payer||select' => self::moveToFront(self::payerValues(), Option::get(Config::MODULE_ID, 'sender-payer-baikal')),
                ),
                'delivery[location_from][pick_up_data]' => array(
                    'date||date' => $produce_date,
                    'time_from||date' => $produce_date,
                    'time_to||date' => $produce_date,
                    'lift||checkbox' => '',
                    'floor||text' => '',
                    'comment||text' => Option::get(Config::MODULE_ID, 'sender-pickup-comment-baikal') ?? '',
                )
            );
        }

        if ( $name === 'magnit' ) {
            $result = array(
                'receiver' => array(
                    'last_name||text' => '',
                ),
                'order[combine_places]' => array(
                    'apply||checkbox' => self::isChecked('combine-places-apply-magnit') ? 'checked' : '',
                    'dimensions||text' => Option::get(Config::MODULE_ID, 'combine-places-dimensions-magnit') ?? '',
                    'weight||text' => Option::get(Config::MODULE_ID, 'combine-places-weight-magnit') ?? ''
                ),
            );
        }

        if ( $name === 'dpd' ) {
            $date = new DateTime();
            $date->modify('+1 day');
            $produce_date = $date->format('Y-m-d');
            $tariffsApi = new Tariffs();
            $tariffs = $tariffsApi->sendExport($name);
            $tariffs = $tariffs['data']??'';
            $savedTariff = $shippingMethods['terminal']['tariff'] ?? null;
            if($savedTariff){
                $selectedTariffCode = $savedTariff['code'];
                if(isset($tariffs[$selectedTariffCode])) {
                    $value[$selectedTariffCode] = $tariffs[$selectedTariffCode];
                    unset($tariffs[$selectedTariffCode]);
                    $tariffs = $value + $tariffs;
                } elseif(empty($tariffs)) {
                    $tariffs = [$selectedTariffCode => $savedTariff['name'] ?? $selectedTariffCode];
                }
            }
            $result = array(
                'receiver' => array(
                    'email||text' => ''
                ),
                'sender' => array(
                    'email||text' => Option::get(Config::MODULE_ID, 'sender-email-dpd') ?? '',
                    'company||text' => Option::get(Config::MODULE_ID, 'sender-company-dpd') ?? '',
                ),
                'order' => array(
                    'content||text' => Option::get(Config::MODULE_ID, 'order-content-dpd') ?? '',
                    'costly||checkbox' => self::isChecked('order-costly-dpd') ? 'checked' : '',
                ),
                'order[combine_places]' => array(
                    'apply||checkbox' => self::isChecked('combine-places-apply-dpd') ? 'checked' : '',
                    'dimensions||text' => Option::get(Config::MODULE_ID, 'combine-places-dimensions-dpd') ?? '',
                    'weight||text' => Option::get(Config::MODULE_ID, 'combine-places-weight-dpd') ?? ''
                ),
                'delivery' => array(
                    'produce_date||date' => $produce_date,
                    'produce_time||text' => Option::get(Config::MODULE_ID, 'produce-time-interval-dpd') ?? '',
                    'tariff||select' => $tariffs,
                ),
            );
        }

        if ( $name === 'fivepost' ) {
            $result = array(
                'delivery' => array(
                    'take_payment||checkbox' => self::isChecked('take-payment-default-fivepost') ? 'checked' : '',
                    'delivery-custom-cost||text' => '0',
                ),
            );
        }

        if ( $name === 'yandex' ) {
            $result = array(
                'delivery' => array(
                    'take_payment||checkbox' => self::isChecked('take-payment-default-yandex') ? 'checked' : '',
                    'delivery-custom-cost||text' => '0',
                ),
            );
        }

        return $result;
    }

}
