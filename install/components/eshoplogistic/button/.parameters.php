<?
/** @var array $arCurrentValues */

use Bitrix\Main\Loader,
    Bitrix\Main\Localization\Loc,
    Bitrix\Main\Config\Option;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
    die();

Loc::loadMessages(__FILE__);


if (!Loader::includeModule("iblock")) {
    return false;
}

$sale_module = true;

if (!Loader::includeModule("sale")) {
    $sale_module = false;
}


if (!Loader::includeModule("catalog")) {
    $sale_module = false;
}


use Bitrix\Iblock,
    Bitrix\Catalog,
    Bitrix\Currency,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\Shipment,
    Bitrix\Sale\Location\LocationTable;


$arIblockTypes = CIBlockParameters::GetIBlockTypes(array("-" => Loc::GetMessage("webes_sconeclick_parameters_CHOOSE")));


$arIblocks = array();
if ($arCurrentValues["IBLOCK_TYPE"]) {
    $rsIblocks = Bitrix\Iblock\IblockTable::getList(array(
        "order" => array("SORT" => "ASC", "NAME" => "ASC"),
        "filter" => array("=IBLOCK_TYPE_ID" => $arCurrentValues["IBLOCK_TYPE"], "ACTIVE" => "Y"),
        "select" => array("ID", "NAME"),
    ));
    while ($row = $rsIblocks->fetch())
        $arIblocks[$row["ID"]] = "[" . $row["ID"] . "] " . $row["NAME"];
}


$arProperty = array();
$arProperty_LS = array();
$arProperty_N = array();
$arProperty_X = array();
$arProperty_F = array();
if ($arCurrentValues["IBLOCK_ID"]) {
    $propertyIterator = Iblock\PropertyTable::getList(array(
        'select' => array('ID', 'IBLOCK_ID', 'NAME', 'CODE', 'PROPERTY_TYPE', 'MULTIPLE', 'LINK_IBLOCK_ID', 'USER_TYPE', 'SORT'),
        'filter' => array('=IBLOCK_ID' => $arCurrentValues['IBLOCK_ID'], '=ACTIVE' => 'Y'),
        'order' => array('SORT' => 'ASC', 'NAME' => 'ASC'),
    ));
    while ($property = $propertyIterator->fetch()) {
        $propertyCode = (string)$property['ID'];
        if ($propertyCode == '')
            $propertyCode = $property['ID'];

        $propertyName = '[' . $propertyCode . '] ' . $property['NAME'];

        $arProperty[$propertyCode] = $propertyName;


        if ($property['PROPERTY_TYPE'] != Iblock\PropertyTable::TYPE_FILE) {
            if ($property['MULTIPLE'] == 'Y' || Iblock\PropertyTable::LISTBOX)
                $arProperty_X[$propertyCode] = $propertyName;
            elseif ($property['PROPERTY_TYPE'] == Iblock\PropertyTable::TYPE_LIST)
                $arProperty_X[$propertyCode] = $propertyName;
            elseif ($property['PROPERTY_TYPE'] == Iblock\PropertyTable::TYPE_ELEMENT && (int)$property['LINK_IBLOCK_ID'] > 0)
                $arProperty_X[$propertyCode] = $propertyName;
        } else {
            if ($property['MULTIPLE'] == 'N')
                $arProperty_F[$propertyCode] = $propertyName;
        }

        if ($property['PROPERTY_TYPE'] == Iblock\PropertyTable::TYPE_LIST || $property['PROPERTY_TYPE'] == Iblock\PropertyTable::TYPE_STRING)
            $arProperty_LS[$propertyCode] = $propertyName;

        if ($property['PROPERTY_TYPE'] == Iblock\PropertyTable::TYPE_NUMBER)
            $arProperty_N[$propertyCode] = $propertyName;
    }
    unset($propertyCode, $propertyName, $property, $propertyIterator);
}


$arComponentParameters['GROUPS'] = array(
    'GROUP_SOURCE' => array(
        'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_DATA"),
        'SORT' => 100,
    ),
    'GROUP_BASE' => array(
        'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_PARAMETERS"),
        'SORT' => 110,
    ),
);

$arComponentParameters['PARAMETERS'] = array(
    'IBLOCK_TYPE' => array(
        'PARENT' => 'GROUP_SOURCE',
        'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_TYPEIB"),
        'TYPE' => 'LIST',
        'VALUES' => $arIblockTypes,
        'REFRESH' => 'Y',
    ),
    'IBLOCK_ID' => array(
        'PARENT' => 'GROUP_SOURCE',
        'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_IB"),
        'TYPE' => 'LIST',
        'VALUES' => $arIblocks,
        'REFRESH' => 'Y',
        'ADDITIONAL_VALUES' => 'Y',
    ),

    'ELEMENT_ID' => array(
        'PARENT'  => 'GROUP_BASE',
        'NAME'    => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_ID_ELEM"),
        'TYPE'    => 'STRING',
        'DEFAULT' => '={$arResult[\'ID\']}',
    ),
    'BUTTON_ONE_CLICK' => array(
        'PARENT' => 'GROUP_BASE',
        'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_BUTNAME"),
        'TYPE' => 'STRING',
        'DEFAULT' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_1CLICK"),
    ),

    'BUTTON_ONE_CLICK_CLASS' => array(
        'PARENT' => 'GROUP_BASE',
        'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_CSSBUT"),
        'TYPE' => 'STRING',
        'DEFAULT' => "btn btn-primary",
    ),

);


$arComponentParameters['GROUPS']['GROUP_ESL'] = array(
    'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_ESLPARAMS"),
    'SORT' => 500);

$arComponentParameters['PARAMETERS']['ESL_WIDGET_KEY'] = array(
    'PARENT' => 'GROUP_ESL',
    'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_WIDGET_KEY"),
    'TYPE' => 'STRING',
    'MULTIPLE' => 'N',
    'VALUES' => "");

$arComponentParameters['PARAMETERS']['ESL_WIDGET_SECRET'] = array(
    'PARENT' => 'GROUP_ESL',
    'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_WIDGET_SECRET"),
    'TYPE' => 'STRING',
    'MULTIPLE' => 'N',
    'VALUES' => "");


