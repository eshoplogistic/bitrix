function saveStatusForm() {
    var inputSave = document.getElementsByName("status-form")[0];

    if (!inputSave)
        return false;

    var form = sortable('.sortable', 'serialize');
    var length = form.length - 1,
        element = null,
        elementParent,
        elementParentName,
        elementLength,
        elementItems,
        result = {};

    for (var i = 0; i <= length; i++) {
        var item, itemName, itemDesc;

        element = form[i];
        elementParent = element.container.node;
        elementParentName = elementParent.getAttribute("name");
        elementItems = element.items;
        elementLength = elementItems.length;
        result[elementParentName] = [];

        for (var j = 0; j < elementLength; j++) {
            item = elementItems[j].node;
            itemName = item.getAttribute("name");
            itemDesc = item.getAttribute("data-desc");
            result[elementParentName][j] = {'name': itemName, 'desc': itemDesc};
        }
    }

    inputSave.value = JSON.stringify(result);
}

function getStatusContainers() {
    return Array.prototype.slice.call(document.querySelectorAll('.sortable'));
}

function getManagedContainers() {
    return Array.prototype.slice.call(document.querySelectorAll('.sortable, .sortable-copy'));
}

function getFixedSourceContainers() {
    return Array.prototype.slice.call(document.querySelectorAll('.sortable-copy'));
}

function getStatusItems(container) {
    return Array.prototype.slice.call(container.children).filter(function (item) {
        return item.tagName === 'LI';
    });
}

var draggedStatusName = '';
var draggedSourceContainer = null;
var draggedItem = null;

function setDropZonesState(isActive) {
    var containers = getStatusContainers();

    for (var i = 0; i < containers.length; i++) {
        containers[i].classList.toggle('esl-drop-zone-ready', isActive);
    }
}

function clearActiveDropZones() {
    var containers = getStatusContainers();

    for (var i = 0; i < containers.length; i++) {
        containers[i].classList.remove('esl-drop-zone-hover');
    }
}

function updateDropZoneHover(container) {
    clearActiveDropZones();

    if (!container || isDropBlocked(container)) {
        return;
    }

    if (container.classList.contains('sortable')) {
        container.classList.add('esl-drop-zone-hover');
    }
}

function resetDragState() {
    draggedStatusName = '';
    draggedSourceContainer = null;
    draggedItem = null;
    document.body.classList.remove('esl-status-dragging');
    setDropZonesState(false);
    clearActiveDropZones();
}

function rememberDraggedItem(event) {
    var target = event.target.closest('li');

    if (!target) {
        return;
    }

    draggedItem = target;
    draggedStatusName = target.getAttribute('name') || '';
    draggedSourceContainer = target.parentElement;
    document.body.classList.add('esl-status-dragging');
    setDropZonesState(true);
}

function hasDuplicateInContainer(container, statusName) {
    var items;

    if (!container || !statusName) {
        return false;
    }

    items = getStatusItems(container).filter(function (item) {
        return item.getAttribute('name') === statusName && item !== draggedItem;
    });

    return items.length > 0;
}

function isDropBlocked(container) {
    if (!container) {
        return false;
    }

    if (container.classList.contains('sortable-copy')) {
        return true;
    }

    if (!container.classList.contains('sortable')) {
        return false;
    }

    if (!draggedStatusName) {
        return false;
    }

    if (container === draggedSourceContainer) {
        return false;
    }

    return hasDuplicateInContainer(container, draggedStatusName);
}

function blockInvalidDrop(event) {
    var container = event.currentTarget;

    updateDropZoneHover(container);

    if (!isDropBlocked(container)) {
        return;
    }

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'none';
    }

    event.stopImmediatePropagation();
}

function preventInvalidDrop(event) {
    var container = event.currentTarget;

    if (!isDropBlocked(container)) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
}

function handleDropZoneLeave(event) {
    var relatedTarget = event.relatedTarget;
    var container = event.currentTarget;

    if (relatedTarget && container.contains(relatedTarget)) {
        return;
    }

    container.classList.remove('esl-drop-zone-hover');
}

function createDeleteButton() {
    var button = document.createElement('span');

    button.className = 'sortable-delete';
    button.textContent = 'x';
    button.addEventListener('click', function () {
        sortableDelete(button);
    });

    return button;
}

function syncItemControls(container) {
    var items = getStatusItems(container);
    var isTargetContainer = container.classList.contains('sortable');

    for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var deleteButton = item.querySelector('.sortable-delete');

        if (isTargetContainer) {
            if (!deleteButton) {
                item.appendChild(createDeleteButton());
            }
        } else if (deleteButton) {
            deleteButton.remove();
        }
    }
}

function syncAllItemControls() {
    var containers = getManagedContainers();

    for (var i = 0; i < containers.length; i++) {
        syncItemControls(containers[i]);
    }
}

function removeDuplicateStatuses(preferredItem) {
    var containers = getStatusContainers();

    for (var i = 0; i < containers.length; i++) {
        var items = getStatusItems(containers[i]);
        var usedNames = {};
        var preferredName = '';
        var preferredContainer = null;

        if (preferredItem) {
            preferredName = preferredItem.getAttribute('name') || '';
            preferredContainer = preferredItem.parentElement;
        }

        for (var j = 0; j < items.length; j++) {
            var item = items[j];
            var statusName = item.getAttribute('name');

            if (!statusName) {
                continue;
            }

            if (preferredItem && containers[i] === preferredContainer && statusName === preferredName) {
                if (item !== preferredItem) {
                    item.remove();
                }
                usedNames[statusName] = preferredItem;
                continue;
            }

            if (usedNames[statusName]) {
                item.remove();
                continue;
            }

            usedNames[statusName] = item;
        }
    }
}

function syncStatusForm(preferredItem) {
    removeDuplicateStatuses(preferredItem || null);
    syncAllItemControls();
    saveStatusForm();
}

function sortableDelete(el) {
    var li = el.closest('li');

    if (li) {
        li.remove();
        syncStatusForm();
    }
}

function handleSortUpdate(event) {
    var currentItem = event && event.detail ? event.detail.item : null;
    var preferredItem = null;

    if (currentItem && currentItem.parentElement && currentItem.parentElement.classList.contains('sortable')) {
        preferredItem = currentItem;
    }

    syncStatusForm(preferredItem);
}

BX.ready(function () {
    var managedContainers;
    var fixedSourceContainers;

    sortable('.sortable', {
        connectWith: 'js-connected',
        dropTargetContainerClass: 'esl-drop-target-active',
        placeholderClass: 'esl-sortable-placeholder'
    });
    sortable('.sortable-copy', {
        copy: true,
        connectWith: 'js-connected'
    });

    managedContainers = getManagedContainers();
    fixedSourceContainers = getFixedSourceContainers();

    for (var i = 0; i < managedContainers.length; i++) {
        managedContainers[i].addEventListener('dragstart', rememberDraggedItem);
        managedContainers[i].addEventListener('dragend', resetDragState);
        managedContainers[i].addEventListener('dragover', blockInvalidDrop, true);
        managedContainers[i].addEventListener('dragenter', blockInvalidDrop, true);
        managedContainers[i].addEventListener('dragleave', handleDropZoneLeave, true);
        managedContainers[i].addEventListener('drop', preventInvalidDrop, true);
        managedContainers[i].addEventListener('sortupdate', handleSortUpdate);
    }

    for (var j = 0; j < fixedSourceContainers.length; j++) {
        fixedSourceContainers[j].addEventListener('dragenter', blockInvalidDrop, true);
    }

    syncAllItemControls();
    syncStatusForm();
});

// Страница настроек модуля перерисовывается поверх плоских таблиц, которые рисует
// нативный __AdmSettingsDrawList (сам он не умеет ни вкладки, ни свёртывание секций,
// ни поиск). Всё ниже — надстройка над готовым DOM: группировка полей каждой ТК по
// вкладкам служб доставки, сворачиваемые секции (аккордеон) и живой поиск по полям.
BX.ready(function () {
    Array.prototype.slice.call(document.querySelectorAll('table.edit-table')).forEach(function (table) {
        initSettingsTable(table);
    });
});

function initSettingsTable(table) {
    relocateInlineButtons(table);
    convertTimeFields(table);

    var carrier = buildCarrierTabs(table);
    var sections = buildSections(table, carrier);

    wireAccordion(sections);
    wireToolbar(table, sections);
    wireVisibilityRules();
}

// Настройки модуля рендерятся через __AdmSettingsDrawList (bitrix/modules/main/admin/settings.php),
// который умеет только text/checkbox/selectbox/... — нативного type=time там нет. Поля времени
// заявлены как обычный "text" (см. options.php), здесь донастраиваем их в реальный time-picker.
var ESL_TIME_FIELDS = ['sender-time-from-delline', 'sender-time-to-delline'];

function convertTimeFields(table) {
    ESL_TIME_FIELDS.forEach(function (name) {
        var input = table.querySelector('input[name="' + name + '"]');
        if (input && input.type !== 'time') {
            input.type = 'time';
        }
    });
}

// Условная видимость полей — портировано из МойСклад (Iframe.php:
// visible_by_params_parent). Контроллер (чекбокс/селект) хранит значение, при
// совпадении с которым перечисленные поля показываются, иначе скрываются. Поля
// адресуются по name, как и везде в этом файле (совпадает с ключом настройки).
var ESL_VISIBILITY_RULES = [
    // СДЭК: "Габариты итогового места" имеет смысл только при включённом
    // "Объединить все места" (см. Iframe.php:981-990).
    { controller: 'combine-places-apply-sdek', values: ['1'], targets: ['combine-places-dimensions-sdek'] },
    // Байкал Сервис: юрлицо -> реквизиты организации, физлицо -> серия/номер
    // документа (см. Iframe.php:1871-1945, группы sender-org-baikal / sender-identity-baikal).
    { controller: 'sender-type-baikal', values: ['1'], targets: ['sender-org-form-baikal', 'sender-company-baikal', 'sender-inn-baikal', 'sender-kpp-baikal'] },
    { controller: 'sender-type-baikal', values: ['2'], targets: ['sender-identity-series-baikal', 'sender-identity-number-baikal'] }
];

function eslControllerValue(el) {
    if (!el) {
        return null;
    }
    return el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
}

// Вызывается повторно при каждом показе строк (переключение вкладки ТК,
// разворачивание секции) — иначе tabs/accordion затирают скрытое правилами состояние
// плоским "display = ''" для всех строк своей группы.
function eslApplyVisibilityRules() {
    ESL_VISIBILITY_RULES.forEach(function (rule) {
        var controller = document.getElementsByName(rule.controller)[0];
        if (!controller) {
            return;
        }
        var match = rule.values.indexOf(eslControllerValue(controller)) !== -1;
        rule.targets.forEach(function (targetName) {
            var target = document.getElementsByName(targetName)[0];
            var row = target && target.closest('tr');
            if (row) {
                row.style.display = match ? '' : 'none';
            }
        });
    });
}

function wireVisibilityRules() {
    var bound = {};
    ESL_VISIBILITY_RULES.forEach(function (rule) {
        if (bound[rule.controller]) {
            return;
        }
        bound[rule.controller] = true;
        var controller = document.getElementsByName(rule.controller)[0];
        if (controller) {
            controller.addEventListener('change', eslApplyVisibilityRules);
        }
    });
    eslApplyVisibilityRules();
}

// Кнопки вида "Поиск терминала" рендерятся options.php отдельной строкой (см.
// __AdmSettingsDrawRow — у 'note'-элементов нет своей ячейки-лейбла, только
// colspan=2), но по смыслу относятся к полю в предыдущей строке — переносим их
// в ячейку с инпутом, чтобы они стояли рядом, а не отдельным блоком снизу.
function relocateInlineButtons(table) {
    Array.prototype.slice.call(table.querySelectorAll('.esl-inline-btn')).forEach(function (btn) {
        var noteRow = btn.closest('tr');
        var targetRow = noteRow && noteRow.previousElementSibling;
        var targetCell = targetRow && targetRow.cells && targetRow.cells[1];
        if (!noteRow || !targetCell) {
            return;
        }
        targetCell.appendChild(btn);
        noteRow.parentNode.removeChild(noteRow);
    });
}

// Строки настроек каждой ТК рендерятся плоским списком между служебными
// маркерами-границами и подписями служб, которые options.php вставил в заголовочные
// строки (см. $transportOptions в options.php) — группируем их по службам на лету и
// рисуем поверх обычную панель вкладок.
function buildCarrierTabs(table) {
    var startMarker = table.querySelector('#esl-carriers-boundary-start');
    var endMarker = table.querySelector('#esl-carriers-boundary-end');
    if (!startMarker || !endMarker) {
        return null;
    }
    var startRow = startMarker.closest('tr');
    var endRow = endMarker.closest('tr');
    if (!startRow || !endRow) {
        return null;
    }

    var groups = [];
    var current = null;
    var row = startRow.nextElementSibling;
    while (row && row !== endRow) {
        var next = row.nextElementSibling;
        var headingSpan = row.querySelector('.esl-carrier-heading[data-esl-service]');
        if (headingSpan) {
            current = {
                service: headingSpan.getAttribute('data-esl-service'),
                label: headingSpan.textContent.replace(/^.*?:\s*/, ''),
                rows: []
            };
            groups.push(current);
            row.style.display = 'none';
        } else if (current) {
            current.rows.push(row);
        }
        row = next;
    }
    startRow.style.display = 'none';
    endRow.style.display = 'none';
    if (!groups.length) {
        return null;
    }

    var tabsRow = document.createElement('tr');
    var tabsCell = document.createElement('td');
    tabsCell.colSpan = 2;
    var bar = document.createElement('div');
    bar.className = 'esl-carrier-tabs';

    var active = groups[0];

    function activate(targetGroup) {
        var targetBtn = null;
        groups.forEach(function (g) {
            g.rows.forEach(function (r) { r.style.display = 'none'; });
        });
        bar.querySelectorAll('.esl-carrier-tab').forEach(function (b) {
            b.classList.remove('esl-carrier-tab--active');
            if (b.__eslGroup === targetGroup) {
                targetBtn = b;
            }
        });
        targetGroup.rows.forEach(function (r) { r.style.display = ''; });
        if (targetBtn) {
            targetBtn.classList.add('esl-carrier-tab--active');
        }
        active = targetGroup;
        eslApplyVisibilityRules();
    }

    groups.forEach(function (g, i) {
        var btn = document.createElement('a');
        btn.href = 'javascript:void(0)';
        btn.className = 'esl-carrier-tab' + (i === 0 ? ' esl-carrier-tab--active' : '');
        btn.__eslGroup = g;

        var badge = document.createElement('span');
        badge.className = 'esl-carrier-tab-badge';
        badge.textContent = g.label.replace(/[^0-9A-Za-zА-Яа-яЁё]/g, '').slice(0, 2).toUpperCase() || '?';
        btn.appendChild(badge);

        var text = document.createElement('span');
        text.textContent = g.label;
        btn.appendChild(text);

        btn.addEventListener('click', function () { activate(g); });
        bar.appendChild(btn);
        g.rows.forEach(function (r) { r.style.display = (i === 0 ? '' : 'none'); });
    });

    tabsCell.appendChild(bar);
    tabsRow.appendChild(tabsCell);
    startRow.parentNode.insertBefore(tabsRow, startRow.nextSibling);

    return {
        groups: groups,
        tabsRow: tabsRow,
        startRow: startRow,
        endRow: endRow,
        activate: activate,
        getActive: function () { return active; }
    };
}

// Разбивает строки таблицы на сворачиваемые секции по заголовкам вида
// <span class="esl-section-heading">, которые options.php расставил между блоками
// связанных полей. Секция, содержащая панель вкладок служб доставки, помечается
// ссылкой на carrier — её раскрытие/схлопывание работает как с единым целым.
function buildSections(table, carrier) {
    var sections = [];
    var current = null;
    var rows = Array.prototype.slice.call(table.rows);

    rows.forEach(function (row) {
        var headingSpan = row.classList.contains('heading') ? row.querySelector('.esl-section-heading') : null;
        if (headingSpan) {
            current = { headingRow: row, headingSpan: headingSpan, rows: [], carrier: null, collapsed: false };
            sections.push(current);
            return;
        }
        if (current) {
            current.rows.push(row);
        }
    });

    sections.forEach(function (section) {
        if (carrier && section.rows.indexOf(carrier.tabsRow) !== -1) {
            section.carrier = carrier;
        }
    });

    return sections;
}

function collapseSection(section) {
    section.rows.forEach(function (r) { r.style.display = 'none'; });
    section.headingRow.classList.add('esl-collapsed');
    section.collapsed = true;
}

function expandSection(section) {
    if (section.carrier) {
        section.carrier.tabsRow.style.display = '';
        section.carrier.activate(section.carrier.getActive());
    } else {
        section.rows.forEach(function (r) { r.style.display = ''; });
    }
    section.headingRow.classList.remove('esl-collapsed');
    section.collapsed = false;
    eslApplyVisibilityRules();
}

function wireAccordion(sections) {
    sections.forEach(function (section) {
        section.headingRow.classList.add('esl-collapsible');
        section.headingRow.addEventListener('click', function () {
            if (section.collapsed) {
                expandSection(section);
            } else {
                collapseSection(section);
            }
        });
    });
}

function wireToolbar(table, sections) {
    var toolbar = table.querySelector('[data-esl-toolbar]');
    if (!toolbar) {
        return;
    }

    var expandBtn = toolbar.querySelector('[data-esl-action="expand-all"]');
    var collapseBtn = toolbar.querySelector('[data-esl-action="collapse-all"]');

    if (expandBtn) {
        expandBtn.addEventListener('click', function () {
            sections.forEach(expandSection);
        });
    }
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            sections.forEach(collapseSection);
        });
    }
}

