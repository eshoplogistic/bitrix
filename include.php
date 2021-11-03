<?php
use \Bitrix\Main\Config\Option,
    \Eshoplogistic\Delivery\Config;

$moduleId = Config::MODULE_ID;
$apiYaMapKey = Option::get($moduleId, 'api_yamap_key');

$link = "https://api-maps.yandex.ru/2.1/?lang=ru_RU";
if($apiYaMapKey) $link .= "&apikey=".$apiYaMapKey;

$arJsConfig = array(
    'main_lib' => array(
        'js' => '/bitrix/js/'.$moduleId.'/script.js',
        'css' => '/bitrix/css/'.$moduleId.'/style.css',
        'lang' => '/bitrix/modules/'.$moduleId.'/lang/'.LANGUAGE_ID.'/js/script.js.php',
    ),
    'yamap_lib' => array(
        'js' => $link,
    )
);

foreach ($arJsConfig as $ext => $arExt) {
    \CJSCore::RegisterExt($ext, $arExt);
}