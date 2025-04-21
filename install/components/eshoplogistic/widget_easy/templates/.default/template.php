<?
ini_set("display_errors","Off");
global $USER, $APPLICATION;

if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
    die();

$this->setFrameMode(true);

$this->addExternalCss('/bitrix/components/eshoplogistic/widget_easy/css/styles.css');
$this->addExternalJs('/bitrix/components/eshoplogistic/widget_easy/js/script'.(mb_strtolower(LANG_CHARSET)!='utf-8'?'-1251':'').'.js');
?>

<?php if($arParams['ESL_WIDGET_KEY']):
    CUtil::InitJSCore(array('esl_easy_widget'));
    ?>
    <div id="eShopLogisticWidgetForm" data-lazy-load="false" class="eshoplogistic-widget-calculate"
         data-key="<?=$arParams['ESL_WIDGET_KEY']?>"
         data-title="<?=$arParams['ESL_WIDGET_TITLE']?>"
    ></div>
<?php endif; ?>

