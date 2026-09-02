<?php

use Bitrix\Main\Loader;
use Eshoplogistic\Delivery\Config;
use Eshoplogistic\Delivery\Helpers\Dimensions;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

Loader::includeModule("eshoplogistic.delivery");
IncludeModuleLangFile(__FILE__);

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

$moduleRight = $APPLICATION->GetGroupRight(Config::MODULE_ID);
if ($moduleRight < 'R') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$axis = trim((string)$request->getQuery('axis'));
if (!in_array($axis, Dimensions::AXES, true)) {
    die('Bad request');
}

$standardLabels = [
    'WIDTH' => GetMessage('ESHOP_LOGISTIC_SETTINGS_DIMFIELD_STANDARD_WIDTH'),
    'HEIGHT' => GetMessage('ESHOP_LOGISTIC_SETTINGS_DIMFIELD_STANDARD_HEIGHT'),
    'LENGTH' => GetMessage('ESHOP_LOGISTIC_SETTINGS_DIMFIELD_STANDARD_LENGTH'),
];

$properties = Dimensions::getCandidateProperties();
?>
<?php
$settingsCssPath = '/bitrix/css/eshoplogistic.delivery/settings.css';
$settingsCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $settingsCssPath) ?: '1';
?>
<link rel="stylesheet" href="<?= $settingsCssPath ?>?<?= $settingsCssVer ?>">
<div class="esl-dimfield-picker" id="esl-dimfield-root" data-axis="<?= htmlspecialcharsbx($axis) ?>">
    <div class="esl-dimfield-picker__search">
        <input type="text" id="esl-dimfield-search" placeholder="<?= GetMessage('ESHOP_LOGISTIC_SETTINGS_DIMFIELD_SEARCH_PH') ?>" autocomplete="off">
    </div>
    <div class="esl-dimfield-picker__list" id="esl-dimfield-list">
        <div class="esl-dimfield-picker__group-title"><?= GetMessage('ESHOP_LOGISTIC_SETTINGS_DIMFIELD_GROUP_STANDARD') ?></div>
        <?php foreach ($standardLabels as $code => $label): ?>
            <div class="esl-dimfield-picker__item" data-source="standard" data-code="<?= htmlspecialcharsbx($code) ?>"
                 data-name="<?= htmlspecialcharsbx($label) ?>" data-search="<?= htmlspecialcharsbx(mb_strtolower($label . ' ' . $code)) ?>">
                <span class="esl-dimfield-picker__item-name"><?= htmlspecialcharsbx($label) ?></span>
                <span class="esl-dimfield-picker__item-code"><?= htmlspecialcharsbx($code) ?></span>
            </div>
        <?php endforeach; ?>

        <div class="esl-dimfield-picker__group-title"><?= GetMessage('ESHOP_LOGISTIC_SETTINGS_DIMFIELD_GROUP_PROPERTY') ?></div>
        <?php if ($properties): ?>
            <?php foreach ($properties as $property): ?>
                <div class="esl-dimfield-picker__item" data-source="property" data-code="<?= htmlspecialcharsbx($property['code']) ?>"
                     data-name="<?= htmlspecialcharsbx($property['name']) ?>" data-search="<?= htmlspecialcharsbx(mb_strtolower($property['name'] . ' ' . $property['code'])) ?>">
                    <span class="esl-dimfield-picker__item-name"><?= htmlspecialcharsbx($property['name']) ?></span>
                    <span class="esl-dimfield-picker__item-code"><?= htmlspecialcharsbx($property['code']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="esl-dimfield-picker__empty"><?= GetMessage('ESHOP_LOGISTIC_SETTINGS_DIMFIELD_EMPTY') ?></div>
        <?php endif; ?>
    </div>
</div>
<script>
    (function () {
        // data-атрибуты вместо инлайновых onclick с интерполяцией названия свойства:
        // htmlspecialcharsbx() по умолчанию не экранирует одинарные кавычки (ENT_COMPAT),
        // а название свойства — не полностью доверенные данные (задаётся в админке
        // инфоблоков произвольным пользователем с правом W). Один делегированный
        // обработчик и .getAttribute() снимают этот риск инъекции целиком.
        var axis = document.getElementById('esl-dimfield-root').getAttribute('data-axis');
        var search = document.getElementById('esl-dimfield-search');
        var list = document.getElementById('esl-dimfield-list');
        if (!search || !list) return;

        list.addEventListener('click', function (e) {
            var item = e.target.closest('.esl-dimfield-picker__item');
            if (!item || !window.eslDimAdd) return;
            window.eslDimAdd(axis, item.getAttribute('data-source'), item.getAttribute('data-code'), item.getAttribute('data-name'));
            if (window.__eslDimDialog) window.__eslDimDialog.Close();
        });

        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            var items = list.querySelectorAll('.esl-dimfield-picker__item');
            var groups = list.querySelectorAll('.esl-dimfield-picker__group-title');
            for (var i = 0; i < items.length; i++) {
                items[i].hidden = q !== '' && items[i].getAttribute('data-search').indexOf(q) === -1;
            }
            for (var g = 0; g < groups.length; g++) {
                var next = groups[g].nextElementSibling, hasVisible = false;
                while (next && !next.classList.contains('esl-dimfield-picker__group-title')) {
                    if (!next.hidden) hasVisible = true;
                    next = next.nextElementSibling;
                }
                groups[g].hidden = !hasVisible;
            }
        });
        search.focus();
    })();
</script>
