<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Eshoplogistic\Delivery\Api\Additional;
use Eshoplogistic\Delivery\Config;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

Loader::includeModule("eshoplogistic.delivery");
IncludeModuleLangFile(__FILE__);
// Названия групп ("Упаковка"/"Груз"/...) определены в лейауте формы выгрузки — переиспользуем
// тот же лейбл-набор ADDITIONAL_FIELDS вместо дублирования строк в отдельном lang-файле.
Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/eshoplogistic.delivery/lib/view/unloading/form.php');

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

$moduleRight = $APPLICATION->GetGroupRight(Config::MODULE_ID);
if ($moduleRight < 'R') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$service = trim((string)$request->getQuery('service'));
if ($service === '') {
    die('Bad request');
}

$type = null;
$message = null;

if ($request->isPost()) {
    if ($moduleRight < 'W') {
        die('Access denied');
    }
    if (!check_bitrix_sessid()) {
        die('Access denied');
    }

    $posted = $request->getPostList()->toArray();
    $complement = is_array($posted['complement'] ?? null) ? $posted['complement'] : [];

    // Тип поля (число/чекбокс) берём из того же API-ответа, что рисует форму, а не
    // угадываем по присланному значению: отмеченный чекбокс шлёт "on", и это легко
    // спутать с числовым полем (приведение (int)"on" молча даёт 0 — предыдущий баг).
    // Полный список кодов нужен и для того, чтобы явно сохранить "выключено" для
    // чекбоксов, которых нет среди $complement при POST (иначе не отличить "снял"
    // от "никогда не было").
    $additionalFieldsForSave = Additional::sendExport(['service' => $service, 'detail' => true]);
    $codeTypes = [];
    if (isset($additionalFieldsForSave['data'])) {
        foreach ($additionalFieldsForSave['data'] as $group) {
            foreach ($group as $code => $field) {
                $codeTypes[$code] = ($field['type'] ?? '') === 'integer';
            }
        }
    }

    foreach ($codeTypes as $code => $isInteger) {
        if ($isInteger) {
            Option::set(Config::MODULE_ID, 'addfield-' . $service . '-' . $code, (string)(int)($complement[$code] ?? 0));
        } else {
            Option::set(Config::MODULE_ID, 'addfield-' . $service . '-' . $code, isset($complement[$code]) ? 'Y' : 'N');
        }
    }

    $type = 'success';
    $message = Loc::GetMessage("ESHOP_LOGISTIC_SETTINGS_ADDFIELD_SAVED");
}

$additionalFields = Additional::sendExport(['service' => $service, 'detail' => true]);
$additionalFieldsRu = GetMessage("ADDITIONAL_FIELDS");

?>
<?php
$settingsCssPath = '/bitrix/css/eshoplogistic.delivery/settings.css';
$settingsCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $settingsCssPath) ?: '1';
?>
<link rel="stylesheet" href="<?= $settingsCssPath ?>?<?= $settingsCssVer ?>">
<div class="esl-addfield-services" id="esl-addfield-root">
    <?php if ($type === 'success'): ?>
        <div class="esl-addfield-result esl-addfield-result--success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (isset($additionalFields['data']) && $additionalFields['data']): ?>
        <form id="esl-addfield-form" method="POST" action="<?= $APPLICATION->GetCurPage() ?>?service=<?= urlencode($service) ?>">
            <?= bitrix_sessid_post() ?>
            <?php foreach ($additionalFields['data'] as $groupKey => $group): ?>
                <div class="esl-addfield-group">
                    <div class="esl-addfield-group__title"><?= htmlspecialchars($additionalFieldsRu[$groupKey] ?? $groupKey) ?></div>
                    <div class="esl-addfield-grid">
                        <?php foreach ($group as $code => $field): ?>
                            <?php if (!isset($field['name'])) continue; ?>
                            <?php $saved = Option::get(Config::MODULE_ID, 'addfield-' . $service . '-' . $code); ?>
                            <div class="esl-addfield-field">
                                <label for="esl-addfield-<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($field['name']) ?></label>
                                <?php if (($field['type'] ?? '') === 'integer'): ?>
                                    <input type="number" id="esl-addfield-<?= htmlspecialchars($code) ?>"
                                           name="complement[<?= htmlspecialchars($code) ?>]"
                                           value="<?= htmlspecialchars((string)($saved !== '' && $saved !== 'N' ? $saved : 0)) ?>"
                                           max="<?= htmlspecialchars((string)($field['max_value'] ?? '')) ?>" min="0">
                                <?php else: ?>
                                    <input type="checkbox" id="esl-addfield-<?= htmlspecialchars($code) ?>"
                                           name="complement[<?= htmlspecialchars($code) ?>]"
                                           <?= ($saved === 'Y') ? 'checked' : '' ?>>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php
            // BX.CAdminDialog перехватывает submit формы только если диалог создан с
            // параметром buttons — у нас его нет, поэтому обычный submit/type=submit
            // пробивает диалог и уводит на голую страницу без оформления админки.
            // <script>-теги, вставленные через outerHTML/innerHTML, браузер не выполняет,
            // поэтому логика сохранения — целиком в атрибуте onclick, а не в отдельном script.
            $onclickJs = "var f=this.closest('form'),x=new XMLHttpRequest();"
                . "x.open('POST',f.action,true);"
                . "x.onload=function(){if(x.status===200){document.getElementById('esl-addfield-root').outerHTML=x.responseText;}else{alert('Ошибка '+x.status+' при сохранении');}};"
                . "x.onerror=function(){alert('Запрос не удался');};"
                . "x.send(new FormData(f));";
            ?>
            <button type="button" class="esl-addfield-submit" onclick="<?= htmlspecialchars($onclickJs) ?>"><?= GetMessage("ESHOP_LOGISTIC_SETTINGS_ADDFIELD_SAVE_BUTTON") ?></button>
        </form>
    <?php else: ?>
        <div class="esl-addfield-empty">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 13V6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v7M4 13l2.5 5h11L20 13M4 13h5.2a.8.8 0 0 1 .74.5l.62 1.5a.8.8 0 0 0 .74.5h1.4a.8.8 0 0 0 .74-.5l.62-1.5a.8.8 0 0 1 .74-.5H20" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span><?= GetMessage("ERROR_SERVICES") ?></span>
        </div>
    <?php endif; ?>
</div>
