<?
use Bitrix\Main\Localization\Loc,
	Bitrix\Main\HttpApplication,
	Bitrix\Main\Loader,
	Bitrix\Main\Config\Option,
	Bitrix\Main\Data\Cache,
	Bitrix\Main\UI;
use Eshoplogistic\Delivery\Api\Counterparties;
use Eshoplogistic\Delivery\Config;

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

    $configClass = new Config();
    $apiV = $configClass->apiV;
    if($apiV){
        $currentSendPoint = Loc::getMessage("ESHOP_LOGISTIC_CURRENT_CITY_V2");;
    }else{
        $sendPoint = $siteClass->getSendPoint();
        if($sendPoint) {
            $currentSendPoint = $sendPoint['city_name'];

        } else {
            $currentSendPoint = Loc::getMessage("ESHOP_LOGISTIC_CURRENT_CITY_EMPTY");
        }

        $currentSendPoint = Loc::getMessage("ESHOP_LOGISTIC_CURRENT_CITY", array("#CITY#" => $currentSendPoint));
    }


	$paySystemResult = \Bitrix\Sale\PaySystem\Manager::getList(array(
		'filter'  => array('ACTIVE' => 'Y'),
		'select' => array('ID', 'PAY_SYSTEM_ID', 'NAME')
	));

	$paySystemList = array();

	while ($paySystem = $paySystemResult->fetch())

	{
		if(!$paySystem['ID']) continue;
		$paySystemList[$paySystem['ID']] = $paySystem['NAME'].'['.$paySystem['ID'].']';
	}


    \CUtil::InitJSCore(array('html5sortable'));
    \CUtil::InitJSCore(array('settings_lib'));
    $dbStatus = CSaleStatus::GetList(Array("SORT" => "ASC"), Array("LID" => LANGUAGE_ID), false, false, Array("ID", "NAME", "SORT"));
    while ($arStatus = $dbStatus->GetNext())
    {
        $statusBx[$arStatus["ID"]] = "[".$arStatus["ID"]."] ".$arStatus["NAME"];
    }
    $status_translate = Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_STATUS_TRANSLATE");
    $status_form = [];
    $status_form = Option::get(Config::MODULE_ID, 'status-form');
    if($status_form){
        $status_form = json_decode($status_form, true);
    }

    $counterparties = new Counterparties();
    $counterparties = $counterparties->sendExport('delline');
    $counterparties = $counterparties['data']??'';
    if(isset($counterparties['counterparties'])){
        $tmpFields = array();
        foreach ($counterparties['counterparties'] as $value){
            $tmpFields[$value['uid']] = $value['name'];
        }
        $counterFields = array('selectbox',
            $tmpFields
        );
    }else{
        $counterFields = array('text');
    }

    $dbRes = CSaleOrderProps::GetList(
        array(
            "SORT" => "ASC",
        )
    );
    while ($item = $dbRes->fetch())
    {
        $fieldsFeatures[$item['ID']] = $item['NAME'];
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
                array(
                    'note' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_API_YAMAP_KEY_DESC")
                ),
                array(
                    "api_v2",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_API_V2"),
                    "",
                    array("checkbox")
                ),
				array(
					"api_log",
					Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_API_LOG"),
					"",
					array("checkbox")
				),
                array(
                    "api_payment_check",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_CHECK"),
                    "",
                    array("checkbox")
                ),
                array(
                    "frame_lib",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_FRAME_LIB"),
                    "",
                    array("checkbox")
                ),
                array(
                    "requary_pvz_address",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_REQUARY_PVZ_ADDRESS"),
                    "",
                    array("checkbox")
                ),
                array(
                    "requary_pvz",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_REQUARY_PVZ"),
                    "",
                    array("checkbox")
                ),
                array(
                    "widget_key",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_WIDGET_KEY"),
                    "",
                    array("text")
                ),
                array(
                    "api_address_requar",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_ADDRESS_REQUAR"),
                    '',
                    ['multiselectbox', $fieldsFeatures]
                ),
				Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_DESCRIPTION"),
				array(
					"api_payment_card",
					Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_CARD"),
                    'Не выбрано',
					['multiselectbox', $paySystemList]
				),
				array(
					"api_payment_cache",
					Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_CACHE"),
                    '',
					['multiselectbox', $paySystemList]
				),
				array(
					"api_payment_cashless",
					Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_CASHLESS"),
                    '',
					['multiselectbox', $paySystemList]
				),
				array(
					"api_payment_prepay",
					Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_PREPAY"),
                    '',
					['multiselectbox', $paySystemList]
				),
                array(
                    "api_payment_upon_receipt",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_RECEIPT"),
                    '',
                    ['multiselectbox', $paySystemList]
                ),
			),
		),
		array(
			"DIV"       => "faq",
			"TAB"       => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TAB2_NAME"),
		),
        array(
            "DIV"       => "unloading",
            "TAB"       => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_UNLOADING_TITLE"),
            "OPTIONS" => array(
                array(
                    "sender-terminal-sdek",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_SDEK"),
                    "",
                    array("text")
                ),
                array(
                    "sender-terminal-boxberry",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_BOXBERRY"),
                    "",
                    array("text")
                ),
                array(
                    "sender-terminal-yandex",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_YANDEX"),
                    "",
                    array("text")
                ),
                array(
                    "sender-terminal-fivepost",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_FIVEPOST"),
                    "",
                    array("text")
                ),
                array(
                    "sender-terminal-delline",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_DELLINE"),
                    "",
                    array("text")
                ),
                array(
                    "sender-terminal-kit",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_KIT"),
                    "",
                    array("text")
                ),
                array(
                    "sender-uid-kit",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_UID_KIT"),
                    "",
                    array("text")
                ),
                array(
                    "sender-terminal-postrf",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_POSTRF"),
                    "",
                    array("text")
                ),
                array(
                    "sender-terminal-baikal",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_BAIKAL"),
                    "",
                    array("text")
                ),
                array(
                    "sender-uid-delline",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_UID_DELLINE"),
                    "",
                    $counterFields
                ),
                array(
                    "sender-counter-delline",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_COUNTER_DELLINE"),
                    "",
                    array("text")
                ),
                array(
                    "sender-name",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_NAME"),
                    "",
                    array("text")
                ),
                array(
                    "sender-phone",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_PHONE"),
                    "",
                    array("text")
                ),
                array(
                    "sender-email",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_EMAIL"),
                    "",
                    array("text")
                ),
                array(
                    "sender-region",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_REGION"),
                    "",
                    array("text")
                ),
                array(
                    "sender-city",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_CITY"),
                    "",
                    array("text")
                ),
                array(
                    "sender-street",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_STREET"),
                    "",
                    array("text")
                ),
                array(
                    "sender-house",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_HOUSE"),
                    "",
                    array("text")
                ),
                array(
                    "sender-room",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_ROOM"),
                    "",
                    array("text")
                ),
                array(
                    "sender-legal",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SENDER_LEGAL"),
                    "",
                    array('selectbox',
                        array(
                            '1' =>  Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_LEGAL"),
                            '2' =>  Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_NATURAL"),
                        )
                    )
                ),
                array(
                    "sender-type",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SENDER_TYPE"),
                    "",
                    array('selectbox',
                        array(
                            '1' =>  Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_NATURAL"),
                            '5' =>  Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_OOO"),
                            '9' =>  Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_IP"),
                            '12' =>  Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_AO"),
                        )
                    )
                ),
                array(
                    "sender-series",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SERIES"),
                    "",
                    array("text")
                ),
                array(
                    "sender-number",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_NUMBER"),
                    "",
                    array("text")
                ),
                array(
                    "sender-inn",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_INN"),
                    "",
                    array("text")
                ),
                array(
                    "sender-kpp",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_KPP"),
                    "",
                    array("text")
                ),
                array(
                    "status-form",
                    "",
                    "",
                    array("text")
                ),
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_STATUS_ORDER"),
            ),
        ),
	);

	if($request->isPost() && check_bitrix_sessid()){

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
            if($aTab["DIV"] == 'unloading'){
				$tabControl->BeginNextTab();

                __AdmSettingsDrawList($module_id, $aTab["OPTIONS"]);
				?>
                <tr class="esl-section_drag">
                    <td style="color:#555;" colspan="2">

                        <div class="card-body" id="eslExportFormWrap">
                            <div class="form-group row align-items-center mb-3">
                                <div class="col-sm-12">

                                    <div class="row">
                                        <div class="esl-inner_status col-sm-6">
                                            <?php foreach ( $status_translate as $key => $value ):
                                                $name = $key;
                                                if ( isset( $status_translate[ $key ] ) ) {
                                                    $name = $status_translate[ $key ];
                                                }
                                                ?>
                                                <div class="esl-inner_item">
                                                    <div class="esl-status_api">
                                                        <?php echo $name ?>
                                                    </div>
                                                    <ul class="js-inner-connected sortable" name="<?php echo $key ?>"
                                                        aria-dropeffect="move">
                                                        <?php if(isset($status_form[$key]) && $status_form[$key]): ?>
                                                            <?php foreach ( $status_form[$key] as $item ): ?>
                                                                <li name="<?php echo $item['name'] ?>"
                                                                    data-desc="<?php echo $item['desc'] ?>" class="esl-status__wp"
                                                                    role="option" aria-grabbed="false">
                                                                    <span class="" draggable="true"><?php echo $item['desc'] ?></span>
                                                                    <span class="sortable-delete" onclick="sortableDelete(this)">х</span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        <?php endif;?>
                                                    </ul>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="esl-inner_item col-sm-6">

                                            <ul class="js-connected sortable-copy" aria-dropeffect="move">
                                                <?php foreach ( $statusBx as $key => $value ): ?>
                                                    <li name="<?php echo $key ?>" data-desc="<?php echo $value ?>"
                                                        class="esl-status__wp" role="option" aria-grabbed="false">
                                                        <span class="" draggable="true"><?php echo $value ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>

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
