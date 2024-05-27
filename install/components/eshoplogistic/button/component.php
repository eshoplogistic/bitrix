<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
/** @var CBitrixCatalogSmartFilter $this */
/** @var array $arParams */
/** @var array $arResult */
/** @var string $componentPath */
/** @var string $componentName */
/** @var string $componentTemplate */
/** @global CDatabase $DB */
global $DB;
/** @global CUser $USER */
global $USER;
/** @global CMain $APPLICATION */
global $APPLICATION;


use \Bitrix\Main\Application;
use \Bitrix\Main\Text\Encoding;
use \Bitrix\Main\Localization\Loc;

$arJsConfig = array(
    'esl_modal_v2' => array(
        'js' => 'https://api.esplc.ru/widgets/modal/app.js',
    ),
    'esl_block_v2' => array(
        'js' => 'https://api.esplc.ru/widgets/block/app.js',
    )
);
foreach ($arJsConfig as $ext => $arExt) {
    \CJSCore::RegisterExt($ext, $arExt);
}

$this->includeComponentTemplate();

?>