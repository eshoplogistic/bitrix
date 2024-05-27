<?

use Bitrix\Main\Localization\Loc;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
    die();

Loc::loadMessages(__FILE__);

$arComponentParameters['GROUPS']['GROUP_ESL_OPT'] = array(
    'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_ESLOPT"),
    'SORT' => 500);

$arComponentParameters['PARAMETERS']['ESL_WIDGET_TITLE'] = array(
    'PARENT' => 'GROUP_ESL_OPT',
    'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_WIDGET_TITLE"),
    'TYPE' => 'STRING',
    'MULTIPLE' => 'N',
    'VALUES' => "");


$arComponentParameters['GROUPS']['GROUP_ESL'] = array(
    'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_ESLPARAMS"),
    'SORT' => 500);

$arComponentParameters['PARAMETERS']['ESL_WIDGET_KEY'] = array(
    'PARENT' => 'GROUP_ESL',
    'NAME' => Loc::GetMessage("ESHOP_LOGISTIC_B_OPTIONS_WIDGET_KEY"),
    'TYPE' => 'STRING',
    'MULTIPLE' => 'N',
    'VALUES' => "");