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

// Строки настроек каждой ТК на странице "Настройки выгрузки заказов" (options.php)
// рендерятся нативным __AdmSettingsDrawList плоским списком (сам он вкладки не
// поддерживает) — группируем их по службам на лету и рисуем поверх обычную панель
// вкладок, используя маркеры-границы и подписи служб, которые options.php вставил
// в заголовочные строки (см. $transportOptions).
BX.ready(function () {
    var startMarker = document.getElementById('esl-carriers-boundary-start');
    var endMarker = document.getElementById('esl-carriers-boundary-end');
    if (!startMarker || !endMarker) {
        return;
    }
    var startRow = startMarker.closest('tr');
    var endRow = endMarker.closest('tr');
    if (!startRow || !endRow) {
        return;
    }

    var groups = [];
    var current = null;
    var row = startRow.nextElementSibling;
    while (row && row !== endRow) {
        var next = row.nextElementSibling;
        var headingSpan = row.querySelector('.esl-carrier-heading[data-esl-service]');
        if (headingSpan) {
            current = { service: headingSpan.getAttribute('data-esl-service'), label: headingSpan.textContent.replace(/^.*?:\s*/, ''), rows: [] };
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
        return;
    }

    var tabsRow = document.createElement('tr');
    var tabsCell = document.createElement('td');
    tabsCell.colSpan = 2;
    var bar = document.createElement('div');
    bar.className = 'esl-carrier-tabs';

    function activate(activeGroup, activeBtn) {
        groups.forEach(function (g) {
            g.rows.forEach(function (r) { r.style.display = 'none'; });
        });
        bar.querySelectorAll('.esl-carrier-tab').forEach(function (b) { b.classList.remove('esl-carrier-tab--active'); });
        activeGroup.rows.forEach(function (r) { r.style.display = ''; });
        activeBtn.classList.add('esl-carrier-tab--active');
    }

    groups.forEach(function (g, i) {
        var btn = document.createElement('a');
        btn.href = 'javascript:void(0)';
        btn.className = 'esl-carrier-tab' + (i === 0 ? ' esl-carrier-tab--active' : '');
        btn.textContent = g.label;
        btn.addEventListener('click', function () { activate(g, btn); });
        bar.appendChild(btn);
        g.rows.forEach(function (r) { r.style.display = (i === 0 ? '' : 'none'); });
    });

    tabsCell.appendChild(bar);
    tabsRow.appendChild(tabsCell);
    startRow.parentNode.insertBefore(tabsRow, startRow.nextSibling);
});

