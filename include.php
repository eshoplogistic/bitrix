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
    // Только CSS страницы настроек, без settings.js - для settings/additionalservices.php
    // (диалог CAdminDialog без своего <head>, settings.js там не нужен и не проверялся).
    'settings_css_lib' => array(
        'css' => '/bitrix/css/'.$moduleId.'/settings.css',
    ),
    'unloading_lib' => array(
        'js' => '/bitrix/js/'.$moduleId.'/admin.js',
        'css' => '/bitrix/css/'.$moduleId.'/admin.css',
    ),
    // Диалоги CAdminDialog вкладки "Заказ" (checkstatus/updatestatus/clearstatus/print.php) -
    // рендерятся отдельным фрагментом без прогона prolog_admin_after.php, поэтому обычное
    // InitJSCore($ext) молча теряется (некому вызвать ShowHeadScripts/ShowHeadStrings). Эти
    // страницы вместо этого делают echo CUtil::InitJSCore(['dialog_lib'], true) - с $bReturn=true
    // CJSCore возвращает готовые <link>/<script src> строкой, а не только регистрирует в Asset.
    'dialog_lib' => array(
        'js' => '/bitrix/js/'.$moduleId.'/unloading-dialog.js',
        'css' => '/bitrix/css/'.$moduleId.'/unloading-dialog.css',
    ),
    'html5sortable' => array(
        'js' => '/bitrix/js/'.$moduleId.'/html5sortable.js',
    )

);

foreach ($arJsConfig as $ext => $arExt) {
    \CJSCore::RegisterExt($ext, $arExt);
}