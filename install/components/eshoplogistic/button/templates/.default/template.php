<?
ini_set("display_errors","Off");
global $USER, $APPLICATION;
use Bitrix\Main\Page\Asset,
    Bitrix\Main\Page\AssetLocation,
    Bitrix\Main\Web\Json,
    Bitrix\Main\Localization\Loc;

if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
    die();


$this->setFrameMode(true);

$this->addExternalCss('/bitrix/components/eshoplogistic/button/css/styles.css');
if($arParams['ESL_WIDGET_V_API'] == 'Y'){
    $this->addExternalJs('/bitrix/components/eshoplogistic/button/js/scriptv2'.(mb_strtolower(LANG_CHARSET)!='utf-8'?'-1251':'').'.js');
}else{
    $this->addExternalJs('/bitrix/components/eshoplogistic/button/js/script'.(mb_strtolower(LANG_CHARSET)!='utf-8'?'-1251':'').'.js');
}

$rsUser = CUser::GetByID($USER->GetID());
$arUser = $rsUser->Fetch();

$component = new EslButtonComponent();
$element = $component->getElementById($arParams['ELEMENT_ID']);
$ar_res = CCatalogProduct::GetByID($arParams['ELEMENT_ID']);
$curSKU = $arParams['ELEMENT_ID'];
if($element['offers_exists']){
    $curSKU = array_keys($element['offers']['offers'])[0];
}
?>

<?php if($arParams['ESL_WIDGET_V_API'] == 'Y'):
    $item[]   = array(
        'article' => $curSKU,
        'name'    => $element['name'],
        'count'   => 1,
        'price'   => $element['price'],
        'weight'  => 1
    );
    $jsonItem = htmlspecialchars( json_encode( $item ) );
    CUtil::InitJSCore(array('esl_modal_v2'));
    ?>
    <button type="button"
            class="<?=$arParams['BUTTON_ONE_CLICK_CLASS']?> esl-button_modal esl-button_data"
            data-esl-widget>
        <?=$arParams['BUTTON_ONE_CLICK']?>
    </button>
    <div id="eShopLogisticWidgetModal"
         data-lazy-load="true"
         data-debug="1"
         data-key="<?=$arParams['ESL_WIDGET_KEY']?>"
         data-offers="<?=$jsonItem?>"
    ></div>
<?php else: ?>
    <button type="button"
            class="<?=$arParams['BUTTON_ONE_CLICK_CLASS']?> esl-button_modal esl-button_data"
            data-widget-button=""
            data-article="<?=$curSKU?>"
            data-id="<?=$arParams['ELEMENT_ID']?>"
            data-name="<?=$element['name']?>"
            data-price="<?=$element['price']?>"
            data-unit=""
            data-weight="1">
        <?=$arParams['BUTTON_ONE_CLICK']?>
    </button>
    <div id="eShopLogisticApp" data-key="<?=$arParams['ESL_WIDGET_KEY']?>"></div>
<?php endif; ?>

