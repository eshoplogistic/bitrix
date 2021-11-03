<?
use Bitrix\Main\Localization\Loc,
    Bitrix\Main\HttpApplication,
    Bitrix\Main\Loader,
    Bitrix\Main\Config\Option,
    Bitrix\Main\Data\Cache,
    Bitrix\Main\UI;

global $APPLICATION;

UI\Extension::load("ui.notification");

$request = HttpApplication::getInstance()->getContext()->getRequest();
$module_id = htmlspecialcharsbx($request["mid"] != "" ? $request["mid"] : $request["id"]);
$cacheDir = 'eshoplogistic';

$LOG_ELEMUPD_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($LOG_ELEMUPD_RIGHT>="R") :

Loc::loadMessages(__FILE__);
Loader::includeModule($module_id);
Loader::includeModule('sale');

    $siteClass = new EshopLogistic\Delivery\Api\Site();
    $authStatus = $siteClass->getAuthStatus();

    if($authStatus['success'] == true) {

        if ($authStatus['blocked']) {
            $accountStatus = Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_ACTIVE");
        } elseif ($authStatus['free_days'] > 0) {
            $accountStatus = Loc::getMessage(
                    "ESHOP_LOGISTIC_OPTIONS_FREE_PERIOD",
                    array("#DAYS#" => $authStatus['free_days'])
            );
        } else {
            $accountStatus = Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_ACTIVE");
        }

        $note = Loc::getMessage(
            "ESHOP_LOGISTIC_AUTH_STATUS",
            array(
                '#BLOCKED#'   => $accountStatus,
                '#BALANSE#'   => $authStatus['balance'],
                '#PAID_DAYS#' => $authStatus['paid_days'],
            )
        );
    } else {
        $note = Loc::getMessage("ESHOP_LOGISTIC_UNAUTHORIZED");
    }

    $sendPoint = $siteClass->getSendPoint();
    if($sendPoint) {
        $currentSendPoint = $sendPoint['city_name'];

    } else {
        $currentSendPoint = Loc::getMessage("ESHOP_LOGISTIC_CURRENT_CITY_EMPTY");
    }

    $currentSendPoint = Loc::getMessage("ESHOP_LOGISTIC_CURRENT_CITY", array("#CITY#" => $currentSendPoint));

    $paySystemResult = \Bitrix\Sale\PaySystem\Manager::getList(array(
        'filter'  => array('ACTIVE' => 'Y'),
        'select' => array('ID', 'PAY_SYSTEM_ID', 'NAME')
    ));

    $paySystemList = array();

    while ($paySystem = $paySystemResult->fetch())

    {
        if(!$paySystem['PAY_SYSTEM_ID']) continue;
        $paySystemList[$paySystem['PAY_SYSTEM_ID']] = $paySystem['NAME'].'['.$paySystem['PAY_SYSTEM_ID'].']';
    }

$aTabs = array(
	array(
		"DIV"       => "edit",
		"TAB"       => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TAB_NAME"),
		"OPTIONS" => array(
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TITLE_NAME"),
            array(
                'note' => $note
            ),
			array(
				"api_key",
				Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_API_KEY"),
				"",
				array("text")
			),
            array(
                "api_yamap_key",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_API_YAMAP_KEY"),
                "",
                array("text")
            ),
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_DESCRIPTION"),
            array(
                "api_payment_card",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_CARD"),
                array(),
                ['multiselectbox', $paySystemList]
            ),
            array(
                "api_payment_cache",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_CACHE"),
                array(),
                ['multiselectbox', $paySystemList]
            ),
            array(
                "api_payment_cashless",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_CASHLESS"),
                array(),
                ['multiselectbox', $paySystemList]
            ),
            array(
                "api_payment_prepay",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_PREPAY"),
                array(),
                ['multiselectbox', $paySystemList]
            ),
       ),
    ),
    array(
        "DIV"       => "faq",
        "TAB"       => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TAB2_NAME"),
    ),
);

// сохранение параметров
if($request->isPost() && check_bitrix_sessid()){

    //удаление кеша с данными авторизации
    Cache::clearCache(true, $cacheDir);

    foreach($aTabs as $aTab){

       foreach($aTab["OPTIONS"] as $arOption){

           if(!is_array($arOption)){

               continue;
           }

           if($arOption["note"]){

                continue;
           }

           if($request["apply"]){

                $optionValue = $request->getPost($arOption[0]);



               Option::set($module_id, $arOption[0], is_array($optionValue) ? implode(",", $optionValue) : $optionValue);
            }elseif($request["default"]){

             Option::set($module_id, $arOption[0], $arOption[2]);
            }
       }
   }

   LocalRedirect($APPLICATION->GetCurPage()."?mid=".$module_id."&lang=".LANG);
}


$tabControl = new CAdminTabControl(
	"tabControl",
	$aTabs
);

$tabControl->Begin();
?>
<form action="<? echo($APPLICATION->GetCurPage()); ?>?mid=<? echo($module_id); ?>&lang=<? echo(LANG); ?>" method="post">
    <?
    foreach($aTabs as $aTab){
        if($aTab["DIV"] == 'edit') {

            $tabControl->BeginNextTab();
            ?>
            <tr>
                <td style='vertical-align:center;'>
                    <?= $currentSendPoint ?>
                </td>
                <td style='text-align:center'>
                    <input type='button' value='<?= Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_CLEAR_CACHE_BTN") ?>'
                           onclick='eslogClearCach()'>
                </td>
            </tr>
            <?
            __AdmSettingsDrawList($module_id, $aTab["OPTIONS"]);
        }
        if($aTab["DIV"] == 'faq'){
            $tabControl->BeginNextTab();
        ?>
            <tr class="heading"><td colspan="2" valign="top" align="center"><?=Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_INSTALL_TITLE")?></td></tr>
            <tr>
                <td style="color:#555;" colspan="2">
                <?=GetMessage('ESHOP_LOGISTIC_OPTIONS_INSTALL_DESC')?>
                </td>
            </tr>
            <tr class="heading"><td colspan="2" valign="top" align="center"><?=Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SETTING_TITLE")?></td></tr>
            <tr>
                <td style="color:#555;" colspan="2">
                <?=GetMessage('ESHOP_LOGISTIC_OPTIONS_SETTING_DESC')?>
                </td>
            </tr>
            <tr class="heading"><td colspan="2" valign="top" align="center"><?=Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_MOMENTS_TITLE")?></td></tr>
            <tr>
                <td style="color:#555;" colspan="2">
                <?=GetMessage('ESHOP_LOGISTIC_OPTIONS_MOMENTS_DESC')?>
                </td>
            </tr>
        <?
       }
   }

   $tabControl->Buttons();
  ?>

   <input type="submit" name="apply" value="<? echo(Loc::GetMessage("ESHOP_LOGISTIC_OPTIONS_INPUT_APPLY")); ?>" class="adm-btn-save" />
   <?
   echo(bitrix_sessid_post());
 ?>

</form>
<?
$tabControl->End();
?>
<?endif;?>
<script>
    function eslogClearCach()
    {
        var request = BX.ajax.runAction('eshoplogistic:delivery.api.AjaxHandler.clearCache', {
            data: {}
        });

        request.then(function(response){
            BX.UI.Notification.Center.notify({
                content: response.data
            });
        });
    }

</script>
