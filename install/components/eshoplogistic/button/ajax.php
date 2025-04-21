<?php
ini_set("display_errors","Off");
ob_start();
ob_clean();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
ob_end_clean();

global $USER, $APPLICATION;
switch($_GET['type'])
{
    case "get_offers_array": {CBitrixComponent::includeComponentClass('eshoplogistic:button'); $component = new EslButtonComponent(); print \Bitrix\Main\Web\Json::encode($component->getElementById($_GET['element_id'])); break;}
    case "create_order": {CBitrixComponent::includeComponentClass('eshoplogistic:button'); $component = new EslButtonComponent(); print  \Bitrix\Main\Web\Json::encode($component->create_order()); break;}
}

?>