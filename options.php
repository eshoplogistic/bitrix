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

// Значок "?" с подсказкой при наведении (портируется текст из МойСклад, поле 'desc').
// __AdmSettingsDrawRow выводит подпись поля ($Option[1]) как есть, без экранирования,
// поэтому спан можно просто приклеить к тексту подписи. Сделано чистым CSS (см.
// .esl-hint в settings.css) вместо родового ui.hint — на этой странице подключаемые
// через UI\Extension ассеты не долетают до вывода (не тот пролог), а самодостаточный
// CSS-тултип работает без какого-либо JS вообще.
function eslHint(string $text): string
{
    return $text === '' ? '' : ' <span class="esl-hint" tabindex="0">?<span class="esl-hint__tip">' . htmlspecialcharsbx($text) . '</span></span>';
}

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
			$noteStatusClass = 'esl-status-note--warn';
		} else {
			$accountStatus = Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_ACTIVE");
			$noteStatusClass = 'esl-status-note--ok';
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
		$noteStatusClass = 'esl-status-note--error';
	}
	$note = '<div class="esl-status-note ' . $noteStatusClass . '">' . $note . '</div>';

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
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PLATFORM_ID") . eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PLATFORM_ID_YANDEX_HINT")),
                "",
                array("text")
            ),
        ),
        'fivepost' => array(
            array(
                "platform_id-fivepost",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PLATFORM_ID") . eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PLATFORM_ID_FIVEPOST_HINT")),
                "",
                array("text")
            ),
        ),
        'delline' => array(
            array(
                "sender-time-from-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TIME_FROM_DELLINE"),
                "",
                array("text")
            ),
            array(
                "sender-time-to-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TIME_TO_DELLINE"),
                "",
                array("text")
            ),
            array(
                "sender-uid-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_UID_DELLINE") . eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_UID_DELLINE_HINT")),
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
                "mode-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_MODE_DELLINE"),
                "auto",
                array('selectbox', Loc::getMessage("ESHOP_LOGISTIC_HELPERS_EXPORT_DELLINE_2"))
            ),
            array(
                "sender-payer-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYER") . eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYER_DELLINE_HINT")),
                "sender",
                array('selectbox', $payerValues3)
            ),
            array(
                "order-accept-delline",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_ACCEPT_DELLINE") . eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_ACCEPT_DELLINE_HINT")),
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
                    'xaba390e912918cea417d5be67b8d492a' => 'АО',
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
        'kit' => array(
            array(
                "sender-uid-kit",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_UID_KIT") . eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_S_UID_KIT_HINT")),
                "",
                array("text")
            ),
        ),
        'pecom' => array(
            array(
                "order-content-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_CONTENT") . eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_CONTENT_PECOM_HINT")),
                "",
                array("text")
            ),
            array(
                "sender-payer-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYER"),
                "sender",
                array('selectbox', $payerValues2)
            ),
            array(
                "sender-requisites-name-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_REQUISITES_NAME_PECOM"),
                "",
                array("text")
            ),
            array(
                "sender-requisites-inn-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_REQUISITES_INN_PECOM"),
                "",
                array("text")
            ),
            array(
                "sender-org-type-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_ORG_TYPE_PECOM"),
                "3",
                array('selectbox', array(
                    '1' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_LEGAL"),
                    '2' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_IP_FULL_PECOM"),
                    '3' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_NATURAL"),
                ))
            ),
            array(
                "sender-identity-type-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_IDENTITY_TYPE_PECOM"),
                "10",
                array('selectbox', Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_IDENTITY_TYPE_PECOM_VALUES"))
            ),
            array(
                "sender-identity-series-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_IDENTITY_SERIES_PECOM"),
                "",
                array("text")
            ),
            array(
                "sender-identity-number-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_IDENTITY_NUMBER_PECOM"),
                "",
                array("text")
            ),
            array(
                "sender-identity-date-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_IDENTITY_DATE_PECOM"),
                "",
                array("text")
            ),
            array(
                "sender-identity-first-name-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_IDENTITY_FIRST_NAME_PECOM"),
                "",
                array("text")
            ),
            array(
                "sender-identity-last-name-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_IDENTITY_LAST_NAME_PECOM"),
                "",
                array("text")
            ),
            array(
                "sender-identity-patronymic-pecom",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_IDENTITY_PATRONYMIC_PECOM"),
                "",
                array("text")
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
            array(
                "sender-email-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_EMAIL_BAIKAL"),
                "",
                array("text")
            ),
            array(
                "sender-type-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_SENDER_TYPE_BAIKAL"),
                "1",
                array('selectbox', array(
                    '1' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_LEGAL"),
                    '2' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_NATURAL"),
                ))
            ),
            array(
                "sender-org-form-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_ORG_FORM_BAIKAL"),
                "5",
                array('selectbox', array(
                    '1'  => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_NATURAL"),
                    '5'  => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_OOO"),
                    '6'  => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_OAO"),
                    '7'  => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_ZAO"),
                    '8'  => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAO"),
                    '9'  => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_IP"),
                    '12' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_AO"),
                ))
            ),
            array(
                "sender-company-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_COMPANY_BAIKAL"),
                "",
                array("text")
            ),
            array(
                "sender-inn-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_INN_BAIKAL"),
                "",
                array("text")
            ),
            array(
                "sender-kpp-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_KPP_BAIKAL"),
                "",
                array("text")
            ),
            array(
                "sender-identity-series-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_IDENTITY_SERIES_BAIKAL"),
                "",
                array("text")
            ),
            array(
                "sender-identity-number-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_IDENTITY_NUMBER_BAIKAL"),
                "",
                array("text")
            ),
            array(
                "sender-pickup-comment-baikal",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PICKUP_COMMENT_BAIKAL"),
                "",
                array("textarea", 3, 40)
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
            array(
                "sender-email-dpd",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_EMAIL_DPD"),
                "",
                array("text")
            ),
            array(
                "sender-company-dpd",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_COMPANY_DPD"),
                "",
                array("text")
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

    // Сверка с МойСклад (Iframe.php, по каждой службе): часть общих полей там
    // показывается не всем ТК, а только тем, где они осмысленны. Список служб по
    // каждой фиче — общий с form.php/unloading.php, см. Config::CARRIER_FEATURE_SCOPE.
    $pickupTerminalServices = Config::CARRIER_FEATURE_SCOPE['pickup_terminal'];
    $typePriceNullServices = Config::CARRIER_FEATURE_SCOPE['type_price_null'];
    $takePaymentServices = Config::CARRIER_FEATURE_SCOPE['take_payment'];
    $combinePlacesServices = Config::CARRIER_FEATURE_SCOPE['combine_places'];
    $sellerServices = Config::CARRIER_FEATURE_SCOPE['seller'];

    $transportOptions = array();
    // Пустые маркеры-границы нужны JS-скрипту в конце файла, который группирует
    // все строки таблицы между ними по службам и рисует вкладки поверх обычного
    // плоского списка настроек (нативный __AdmSettingsDrawList вкладок не поддерживает).
    $transportOptions[] = '<span id="esl-carriers-boundary-start" style="display:none"></span>';
    foreach ($transportServices as $svcCode => $svcHeading) {
        $transportOptions[] = '<span class="esl-carrier-heading" data-esl-service="' . htmlspecialcharsbx($svcCode) . '">' . htmlspecialcharsbx($svcHeading) . '</span>';
        $transportOptions[] = array(
            "payment_type-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PAYMENT_TYPE"),
            "not_selected",
            array('selectbox', $paymentTypeValues)
        );
        if (in_array($svcCode, $pickupTerminalServices, true)) {
            $transportOptions[] = array(
                "type_delivery_from_tk-$svcCode",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PICKUP") . eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PICKUP_HINT")),
                "0",
                array('selectbox', $pickupValues)
            );
            $transportOptions[] = array(
                "sender-terminal-$svcCode",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TERMINAL") . eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TERMINAL_HINT")),
                "",
                array("text")
            );
            // Поиск терминала по адресу доступен только там, где он есть в МойСклад
            // (в 5Post он там же отключён, а у остальных служб этого поля вообще нет).
            if (in_array($svcCode, $terminalSearchServices, true)) {
                // Кнопка переезжает в ячейку поля "Код терминала отгрузки" рядом с инпутом
                // (см. relocateInlineButtons() в settings.js) — здесь она лишь временно
                // рендерится отдельной строкой, которую JS сразу убирает.
                $transportOptions[] = array(
                    'note' => '<button type="button" class="esl-inline-btn esl-terminal-search-btn" title="' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TERMINAL_SEARCH_BUTTON")) . '" onclick="window.__eslTerminalDialog=(new BX.CAdminDialog({'
                        . "'content_url': '/bitrix/admin/eshoplogistic_delivery_terminalsearch.php?service=" . $svcCode . "&target=sender-terminal-" . $svcCode . "',"
                        . "'draggable': true, 'resizable': true, 'width': 620, 'height': 480"
                        . '}));window.__eslTerminalDialog.Show();"><svg width="14" height="14" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.6 10.6L14 14M12.3 6.65C12.3 9.73 9.73 12.3 6.65 12.3C3.57 12.3 1 9.73 1 6.65C1 3.57 3.57 1 6.65 1C9.73 1 12.3 3.57 12.3 6.65Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
                );
            }
        }
        if (in_array($svcCode, $typePriceNullServices, true)) {
            $transportOptions[] = array(
                "type-price-null-$svcCode",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PRICE_NULL") . eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_PRICE_NULL_HINT")),
                "",
                array("checkbox")
            );
        }
        if (in_array($svcCode, $takePaymentServices, true)) {
            $transportOptions[] = array(
                "take-payment-default-$svcCode",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_TAKE_PAYMENT"),
                "",
                array("checkbox")
            );
        }
        if (in_array($svcCode, $combinePlacesServices, true)) {
            $transportOptions[] = array(
                "combine-places-apply-$svcCode",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_COMBINE_PLACES") . ($svcCode === 'sdek' ? eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_COMBINE_PLACES_SDEK_HINT")) : ''),
                "",
                array("checkbox")
            );
            $transportOptions[] = array(
                "combine-places-dimensions-$svcCode",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_COMBINE_PLACES_DIMENSIONS") . ($svcCode === 'sdek' ? eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_COMBINE_PLACES_DIMENSIONS_SDEK_HINT")) : ''),
                "",
                array("text")
            );
            $transportOptions[] = array(
                "combine-places-weight-$svcCode",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_COMBINE_PLACES_WEIGHT"),
                "",
                array("text")
            );
        }
        $transportOptions[] = array(
            "cost-custom-delivery-$svcCode",
            Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_VAT") . ($svcCode === 'sdek' ? eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_VAT_SDEK_HINT")) : ''),
            "-1",
            array('selectbox', $vatValues)
        );
        if (in_array($svcCode, $sellerServices, true)) {
            $transportOptions[] = array(
                "seller-name-$svcCode",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_SELLER_NAME") . ($svcCode === 'sdek' ? eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_SELLER_NAME_HINT")) : ''),
                "",
                array("text")
            );
            $transportOptions[] = array(
                "seller-phone-$svcCode",
                Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_SELLER_PHONE") . ($svcCode === 'sdek' ? eslHint(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_SELLER_PHONE_HINT")) : ''),
                "",
                array("text")
            );
        }

        if (isset($transportServiceNiche[$svcCode])) {
            foreach ($transportServiceNiche[$svcCode] as $nicheField) {
                $transportOptions[] = $nicheField;
            }
        }

        // Кнопка добавления доп.полей — всегда последней строкой блока службы
        // (после всех общих и нишевых полей), а не сразу под заголовком.
        $transportOptions[] = array(
            'note' => '<input type="button" class="button" value="' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TKD_ADDFIELD_BUTTON")) . '" onclick="(new BX.CAdminDialog({'
                . "'content_url': '/bitrix/admin/eshoplogistic_delivery_additionalservices.php?service=" . $svcCode . "',"
                . "'draggable': true, 'resizable': true, 'width': 700, 'height': 500"
                . '})).Show();">'
        );
    }
    $transportOptions[] = '<span id="esl-carriers-boundary-end" style="display:none"></span>';

    $aTabs = array(
		array(
			"DIV"       => "edit",
			"TAB"       => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TAB_NAME"),
			"OPTIONS" => array(
				array(
					'note' => '<div class="esl-toolbar" data-esl-toolbar>'
						. '<div class="esl-toolbar-info">'
						. '<svg class="esl-toolbar-info-icon" width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="7.5" cy="7.5" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M7.5 6.8V11" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="7.5" cy="4.5" r="0.9" fill="currentColor"/></svg>'
						. '<span>' . htmlspecialcharsbx($currentSendPoint) . '</span>'
						. '</div>'
						. '<div class="esl-toolbar-actions">'
						. '<button type="button" class="esl-toolbar-btn esl-toolbar-btn--accent" onclick="eslogClearCach()">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_CLEAR_CACHE_BTN")) . '</button>'
						. '<span class="esl-toolbar-sep"></span>'
						. '<button type="button" class="esl-toolbar-btn" data-esl-action="expand-all">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_EXPAND_ALL")) . '</button>'
						. '<button type="button" class="esl-toolbar-btn" data-esl-action="collapse-all">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_COLLAPSE_ALL")) . '</button>'
						. '</div>'
						. '</div>'
				),
				'<span class="esl-section-heading">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SECTION_ACCESS")) . '</span>',
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
                    "widget_key",
                    Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_WIDGET_KEY"),
                    "",
                    array("text")
                ),
				'<span class="esl-section-heading">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SECTION_BEHAVIOR")) . '</span>',
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
				'<span class="esl-section-heading">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SECTION_PACKAGE")) . '</span>',
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
				'<span class="esl-section-heading">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SECTION_DISPLAY")) . '</span>',
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
				'<span class="esl-section-heading">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SECTION_PAYMENT")) . '</span>',
                array(
                    'note' => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_PAYMENT_DESCRIPTION")
                ),
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
            "DIV"       => "unloading",
            "TAB"       => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_UNLOADING_TITLE"),
            "OPTIONS" => array_merge(array(
                array(
                    'note' => '<div class="esl-toolbar" data-esl-toolbar>'
                        . '<div class="esl-toolbar-actions">'
                        . '<button type="button" class="esl-toolbar-btn" data-esl-action="expand-all">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_EXPAND_ALL")) . '</button>'
                        . '<button type="button" class="esl-toolbar-btn" data-esl-action="collapse-all">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_COLLAPSE_ALL")) . '</button>'
                        . '</div>'
                        . '</div>'
                ),
                '<span class="esl-section-heading">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SECTION_SENDER")) . '</span>',
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
            ), array(
                '<span class="esl-section-heading">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_SECTION_CARRIERS")) . '</span>',
            ), $transportOptions, array(
                '<span class="esl-section-heading">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_STATUS_UNLOADING")) . '</span>',
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
                '<span class="esl-section-heading">' . htmlspecialcharsbx(Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_STATUS_ORDER")) . '</span>',
            )),
        ),
		array(
			"DIV"       => "faq",
			"TAB"       => Loc::getMessage("ESHOP_LOGISTIC_OPTIONS_TAB2_NAME"),
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
