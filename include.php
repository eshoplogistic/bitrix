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
    'frame_lib' => array(
        'js' => '/bitrix/js/'.$moduleId.'/frame-script.js',
        'css' => '/bitrix/css/'.$moduleId.'/frame-style.css',
        'lang' => '/bitrix/modules/'.$moduleId.'/lang/'.LANGUAGE_ID.'/js/frame-script.js.php',
    ),
    'framev2_lib' => array(
        'js' => '/bitrix/js/'.$moduleId.'/framev2-script.js',
        'css' => '/bitrix/css/'.$moduleId.'/framev2-style.css',
        'lang' => '/bitrix/modules/'.$moduleId.'/lang/'.LANGUAGE_ID.'/js/framev2-script.js.php',
    ),
    'yamap_lib' => array(
        'js' => $link,
    ),
    'settings_lib' => array(
        'js' => '/bitrix/js/'.$moduleId.'/settings.js',
        'css' => '/bitrix/css/'.$moduleId.'/settings.css',
    ),
    'unloading_lib' => array(
        'js' => '/bitrix/js/'.$moduleId.'/admin.js',
        'css' => '/bitrix/css/'.$moduleId.'/admin.css',
    ),
    'html5sortable' => array(
        'js' => '/bitrix/js/'.$moduleId.'/html5sortable.js',
    )

);

foreach ($arJsConfig as $ext => $arExt) {
    \CJSCore::RegisterExt($ext, $arExt);
}