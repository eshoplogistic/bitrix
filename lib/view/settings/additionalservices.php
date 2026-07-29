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
<style>
    .esl-addfield-services {
        --esl-accent: #4a7dff;
        --esl-accent-dark: #3563e0;
        --esl-accent-soft: rgba(74, 125, 255, 0.12);
        --esl-text: #1a2540;
        --esl-text-muted: #5a6782;
        --esl-border: #dde3ed;
        --esl-bg-subtle: #f8f9fc;
        --esl-radius: 10px;
        --esl-radius-sm: 6px;
        margin: 18px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: var(--esl-text);
    }
    .esl-addfield-group {
        margin-bottom: 16px;
        border: 1px solid var(--esl-border);
        border-radius: var(--esl-radius);
        background: #fff;
        overflow: hidden;
    }
    .esl-addfield-group__title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--esl-text-muted);
        background: var(--esl-bg-subtle);
        border-bottom: 1px solid var(--esl-border);
        padding: 9px 14px;
    }
    .esl-addfield-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 4px 16px; padding: 10px 14px; }
    .esl-addfield-field { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 0; font-size: 13px; color: var(--esl-text); }
    .esl-addfield-field input[type="number"] {
        width: 70px;
        box-sizing: border-box;
        border: 1px solid #c8d0e0;
        border-radius: var(--esl-radius-sm);
        padding: 5px 8px;
        font-size: 13px;
        color: var(--esl-text);
        background: #fafbfd;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .esl-addfield-field input[type="number"]:focus {
        border-color: var(--esl-accent);
        box-shadow: 0 0 0 3px var(--esl-accent-soft);
        background: #fff;
    }
    .esl-addfield-field input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--esl-accent); cursor: pointer; }
    .esl-addfield-result { padding: 10px 14px; margin-bottom: 14px; border-radius: var(--esl-radius-sm); font-size: 13px; }
    .esl-addfield-result--success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .esl-addfield-submit {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 18px;
        border: 1px solid var(--esl-accent);
        border-radius: 999px;
        background: var(--esl-accent);
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s;
    }
    .esl-addfield-submit:hover { background: var(--esl-accent-dark); border-color: var(--esl-accent-dark); }
</style>
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
        <p><?= GetMessage("ERROR_SERVICES") ?></p>
    <?php endif; ?>
</div>
