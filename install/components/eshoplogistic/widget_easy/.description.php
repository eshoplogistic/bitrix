<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
	die();

use Bitrix\Main\Localization\Loc;

$arComponentDescription = array(
	"NAME"        => Loc::GetMessage("ESHOP_LOGISTIC_B_NAME"),
	"DESCRIPTION" => Loc::GetMessage("ESHOP_LOGISTIC_B_DESCRIPTION"),
	"ICON"        => "/images/logo.png",
	"PATH"        => array(
		"ID"   => Loc::GetMessage("ESHOP_LOGISTIC_B_PATH_ID"),
		"NAME" => Loc::GetMessage("ESHOP_LOGISTIC_B_PATH_NAME"),
	),
);
?>