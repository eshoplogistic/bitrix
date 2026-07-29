<?
use Bitrix\Main\Localization\Loc,
	Bitrix\Main\HttpApplication,
	Bitrix\Main\Loader,
	Bitrix\Main\Config\Option,
	Bitrix\Main\Data\Cache,
	Bitrix\Main\UI;
use Bitrix\Sale\OrderStatus;
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
	Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/eshoplogistic.delivery/lib/helpers/exportfileds.php');
	Loader::includeModule($module_id);
	Loader::includeModule('sale');

	$siteClass = new EshopLogistic\Delivery\Api\Site();
	$authStatus = $siteClass->getAuthStatus();

	if($authStatus['success'] == true) {

		if ($authStatus['blocked']) {
			$accountStatus = Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_BLOCKED");
		} else {
			$accountStatus = Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_ACTIVE");
		}

		$note = Loc::getMessage(
			"ESHOP_LOGISTIC_AUTH_STATUS",
			array(
				'#BLOCKED#'    => $accountStatus,
				'#BALANSE#'    => $authStatus['balance'],
				'#FREE_DAYS#'  => $authStatus['free_days'],
				'#PAID_DAYS#' => $authStatus['paid_days'],
			)
		);
	} else {
		$note = Loc::getMessage("ESHOP_LOGISTIC_UNAUTHORIZED");
	}

    $currentSendPoint = Loc::getMessage("ESHOP_LOGISTIC_CURRENT_CITY_V2");

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

    $statusesList = OrderStatus::getAllStatusesNames();

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

    // Настройки по умолчанию для выгрузки заказов по каждой службе доставки (ТК).
    // Общий набор полей строится циклом по списку служб, чтобы не дублировать одинаковые
    // 11 полей 14 раз; уникальные для отдельных ТК поля описаны в $transportServiceNiche.
    $paymentTypeValues = array(
        'not_selected'    => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYMENT_NONE"),
        'already_paid'    => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYMENT_PAID"),
        'cash_on_receipt' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYMENT_RECEIPT"),
    );
    $pickupValues = array(
        '1' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PICKUP_TK"),
        '0' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PICKUP_SELF"),
    );
    $vatValues = array(
        '-1' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_VAT_NONE"),
        '0'  => '0%',
        '5'  => '5%',
        '7'  => '7%',
        '10' => '10%',
        '22' => '22%',
    );
    $payerValues2 = array(
        'sender'   => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYER_SENDER"),
        'receiver' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYER_RECEIVER"),
    );
    $payerValues3 = $payerValues2 + array(
        'third' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYER_THIRD"),
    );

    $transportServiceNiche = array(
        'sdek' => array(
            array(
                "type-order-sdek",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TYPE_ORDER_SDEK"),
                "1",
                array('selectbox', Loc::getMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_SDEK_1"))
            ),
        ),
        'boxberry' => array(
            array(
                "type_order-boxberry",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TYPE_ORDER_BB"),
                "0",
                array('selectbox', Loc::getMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_1"))
            ),
            array(
                "packing_type-boxberry",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PACKING_BB"),
                "1",
                array('selectbox', Loc::getMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_2"))
            ),
            array(
                "order_issue-boxberry",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_ISSUE_BB"),
                "0",
                array('selectbox', Loc::getMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_3"))
            ),
        ),
        'yandex' => array(
            array(
                "platform_id-yandex",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PLATFORM_ID"),
                "",
                array("text")
            ),
        ),
        'fivepost' => array(
            array(
                "platform_id-fivepost",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PLATFORM_ID"),
                "",
                array("text")
            ),
        ),
        'delline' => array(
            array(
                "mode-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_MODE_DELLINE"),
                "auto",
                array('selectbox', Loc::getMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_DELLINE_2"))
            ),
            array(
                "sender-payer-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYER"),
                "sender",
                array('selectbox', $payerValues3)
            ),
            array(
                "order-accept-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_ACCEPT_DELLINE"),
                "1",
                array('selectbox', Loc::getMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_DELLINE_1"))
            ),
            array(
                "order-freight-type-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_FREIGHT_TYPE"),
                "",
                array("text")
            ),
            array(
                "sender-counteragent-from-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_COUNTERAGENT_FORM"),
                "0x92ee03691f25a9fe4be9910cd87ca9ca",
                array('selectbox', array(
                    '0x92ee03691f25a9fe4be9910cd87ca9ca' => 'ООО',
                    '0xaa9042fea4fa169d4d021c6941f2090f' => 'ИП',
                    '0x8390b2048d37e0154b845fb22793e865' => 'ОАО',
                    '0xae7b742e5861514f4f5729fa97b77a42' => 'ЗАО',
                    '0x81318eb6f150096b494a15ff66c37823' => 'МУ',
                    '0x80958580c73df96f4c677eefef87422c' => 'ГК',
                    '0x81ab99926ac959594af2f6f0a77b7353' => 'ОФ',
                    '0x83180c1320f58a344588220de53696e7' => 'ТОО',
                ))
            ),
            array(
                "sender-counteragent-name-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_COUNTERAGENT_NAME"),
                "",
                array("text")
            ),
            array(
                "sender-counteragent-inn-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_COUNTERAGENT_INN"),
                "",
                array("text")
            ),
        ),
        'pecom' => array(
            array(
                "order-content-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_CONTENT"),
                "",
                array("text")
            ),
            array(
                "sender-payer-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYER"),
                "sender",
                array('selectbox', $payerValues2)
            ),
        ),
        'baikal' => array(
            array(
                "order-content-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_CONTENT"),
                "",
                array("text")
            ),
            array(
                "sender-payer-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYER"),
                "sender",
                array('selectbox', $payerValues2)
            ),
        ),
        'dpd' => array(
            array(
                "order-content-dpd",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_CONTENT"),
                "",
                array("text")
            ),
            array(
                "order-costly-dpd",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_COSTLY_DPD"),
                "",
                array("checkbox")
            ),
            array(
                "produce-time-interval-dpd",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PRODUCE_TIME_DPD"),
                "9-18",
                array('selectbox', array(
                    '9-18' => '9-18',
                    '9-13' => '9-13',
                    '13-18' => '13-18',
                ))
            ),
        ),
    );

    $transportServices = array(
        'sdek'          => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_SDEK"),
        'boxberry'      => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_BOXBERRY"),
        'yandex'        => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_YANDEX"),
        'fivepost'      => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_FIVEPOST"),
        'delline'       => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_DELLINE"),
        'kit'           => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_KIT"),
        'postrf'        => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_POSTRF"),
        'pecom'         => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_PECOM"),
        'halva'         => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_HALVA"),
        'baikal'        => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_BAIKAL"),
        'magnit'        => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_MAGNIT"),
        'dpd'           => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_DPD"),
        'sberlogistics' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_SBERLOGISTICS"),
        'pochtalion'    => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TK_POCHTALION"),
    );

    // Службы, для которых МойСклад показывает кнопку поиска терминала (совпадает с
    // теми, у кого в Iframe.php поле sender-terminal-* имеет type=textNbutton).
    $terminalSearchServices = array('sdek', 'boxberry', 'yandex', 'kit', 'pecom', 'delline', 'dpd', 'baikal');

    $transportOptions = array();
    foreach ($transportServices as $svcCode => $svcHeading) {
        $transportOptions[] = $svcHeading;
        $transportOptions[] = array(
            'note' => '<input type="button" class="button" value="' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_ADDFIELD_BUTTON")) . '" onclick="(new BX.CAdminDialog({'
                . "'content_url': '/bitrix/admin/eshoplogistic_delivery_additionalservices.php?service=" . $svcCode . "',"
                . "'draggable': true, 'resizable': true, 'width': 700, 'height': 500"
                . '})).Show();">'
        );
        $transportOptions[] = array(
            "payment_type-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYMENT_TYPE"),
            "not_selected",
            array('selectbox', $paymentTypeValues)
        );
        $transportOptions[] = array(
            "type_delivery_from_tk-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PICKUP"),
            "0",
            array('selectbox', $pickupValues)
        );
        $transportOptions[] = array(
            "sender-terminal-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TERMINAL"),
            "",
            array("text")
        );
        // Поиск терминала по адресу доступен только там, где он есть в МойСклад
        // (в 5Post он там же отключён, а у остальных служб этого поля вообще нет).
        if (in_array($svcCode, $terminalSearchServices, true)) {
            $transportOptions[] = array(
                'note' => '<input type="button" class="button" value="' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TERMINAL_SEARCH_BUTTON")) . '" onclick="window.__eslTerminalDialog=(new BX.CAdminDialog({'
                    . "'content_url': '/bitrix/admin/eshoplogistic_delivery_terminalsearch.php?service=" . $svcCode . "&target=sender-terminal-" . $svcCode . "',"
                    . "'draggable': true, 'resizable': true, 'width': 620, 'height': 480"
                    . '}));window.__eslTerminalDialog.Show();">'
            );
        }
        $transportOptions[] = array(
            "type-price-null-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PRICE_NULL"),
            "",
            array("checkbox")
        );
        $transportOptions[] = array(
            "take-payment-default-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TAKE_PAYMENT"),
            "",
            array("checkbox")
        );
        $transportOptions[] = array(
            "combine-places-apply-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_COMBINE_PLACES"),
            "",
            array("checkbox")
        );
        $transportOptions[] = array(
            "combine-places-dimensions-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_COMBINE_PLACES_DIMENSIONS"),
            "",
            array("text")
        );
        $transportOptions[] = array(
            "combine-places-weight-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_COMBINE_PLACES_WEIGHT"),
            "",
            array("text")
        );
        $transportOptions[] = array(
            "cost-custom-delivery-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_VAT"),
            "-1",
            array('selectbox', $vatValues)
        );
        $transportOptions[] = array(
            "seller-name-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_SELLER_NAME"),
            "",
            array("text")
        );
        $transportOptions[] = array(
            "seller-phone-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_SELLER_PHONE"),
            "",
            array("text")
        );

        if (isset($transportServiceNiche[$svcCode])) {
            foreach ($transportServiceNiche[$svcCode] as $nicheField) {
                $transportOptions[] = $nicheField;
            }
        }
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
                    "weight_default",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_WEIGHT_DEFAULT"),
                    "1",
                    array("text")
                ),
                array(
                    "width_default",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_WIDTH_DEFAULT"),
                    "0",
                    array("text")
                ),
                array(
                    "height_default",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_HEIGHT_DEFAULT"),
                    "0",
                    array("text")
                ),
                array(
                    "length_default",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_LENGTH_DEFAULT"),
                    "0",
                    array("text")
                ),
                array(
                    "api_address_requar",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_ADDRESS_REQUAR"),
                    '',
                    ['multiselectbox', $fieldsFeatures]
                ),
                array(
                    "chose_frame",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_CHOSE_FRAME"),
                    "",
                    array("text")
                ),
                array(
                    "terminal_pvz",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TERMINAL_PVZ"),
                    "",
                    array("text")
                ),
                array(
                    "price_empty",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PRICE_EMPTY"),
                    "",
                    array("text")
                ),
                array(
                    "price_hide",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PRICE_HIDE"),
                    "",
                    array("checkbox")
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
            "OPTIONS" => array_merge(array(
                array(
                    "sender-uid-kit",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_UID_KIT"),
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
            ), $transportOptions, array(
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_STATUS_UNLOADING"),
                array(
                    'note' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_CRON_URL_UNLOADING")
                ),
                array(
                    "cron-status-unloading",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_CRON_UNLOADING_STATUS"),
                    '',
                    ['multiselectbox', $statusesList]
                ),
                array(
                    "status-form",
                    "",
                    "",
                    array("text")
                ),
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_STATUS_ORDER")
            )),
        ),
	);

	if($request->isPost() && check_bitrix_sessid() && $LOG_ELEMUPD_RIGHT>="W"){

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
				<tr class="heading"><td colspan="2"><?=Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_INSTALL_TITLE")?></td></tr>
				<tr>
					<td class="esl-faq-text" colspan="2">
						<?=GetMessage('ESHOP_LOGISTIC_OPTIONS_INSTALL_DESC')?>
					</td>
				</tr>
				<tr class="heading"><td colspan="2"><?=Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SETTING_TITLE")?></td></tr>
				<tr>
					<td class="esl-faq-text" colspan="2">
						<?=GetMessage('ESHOP_LOGISTIC_OPTIONS_SETTING_DESC')?>
					</td>
				</tr>
				<tr class="heading"><td colspan="2"><?=Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_MOMENTS_TITLE")?></td></tr>
				<tr>
					<td class="esl-faq-text" colspan="2">
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
                                                        <?php echo htmlspecialcharsbx((string)$name) ?>
                                                    </div>
                                                    <ul class="js-inner-connected sortable" name="<?php echo htmlspecialcharsbx((string)$key) ?>"
                                                        aria-dropeffect="move">
                                                        <?php if(isset($status_form[$key]) && $status_form[$key]): ?>
                                                            <?php foreach ( $status_form[$key] as $item ): ?>
                                                                <li name="<?php echo htmlspecialcharsbx((string)$item['name']) ?>"
                                                                    data-desc="<?php echo htmlspecialcharsbx((string)$item['desc']) ?>" class="esl-status__wp"
                                                                    role="option" aria-grabbed="false">
                                                                    <span class="" draggable="true"><?php echo htmlspecialcharsbx((string)$item['desc']) ?></span>
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
                                                    <li name="<?php echo htmlspecialcharsbx((string)$key) ?>" data-desc="<?php echo htmlspecialcharsbx((string)$value) ?>"
                                                        class="esl-status__wp" role="option" aria-grabbed="false">
                                                        <span class="" draggable="true"><?php echo htmlspecialcharsbx((string)$value) ?></span>
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
