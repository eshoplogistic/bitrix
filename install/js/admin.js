// Условная видимость полей на форме выгрузки заказа — портировано из МойСклад
// (assets/js/script.js: displayForm/displayFormInitVisible). Контроллер (select/
// чекбокс) хранит значение, при совпадении с которым перечисленные поля показываются,
// иначе скрываются. Поля адресуются по name (как в остальном модуле).
var ESL_UNLOADING_VISIBILITY_RULES = [
    // "Курьер" (door) — доставка по адресу, код/адрес ПВЗ не нужны;
    // "Пункт выдачи" (terminal) — нужен код и адрес терминала/ПВЗ.
    { controller: 'delivery_type', values: ['terminal'], targets: ['terminal-code', 'terminal-address'] },
    // Улица/дом/квартира получателя имеют смысл только при доставке курьером до двери.
    { controller: 'delivery_type', values: ['door'], targets: ['receiver-street', 'receiver-house', 'receiver-room'] },
    // Габариты/вес итогового места имеют смысл только если места объединяются в одно.
    { controller: 'order[combine_places][apply]', values: ['1'], targets: ['order[combine_places][dimensions]', 'order[combine_places][weight]'] },
    // "Груз заберёт ТК" (1) — нужен код терминала отгрузки;
    // "Сами привезём на терминал" (0) — нужен адрес отправителя.
    { controller: 'pick_up', values: ['1'], targets: ['sender-terminal'] },
    { controller: 'pick_up', values: ['0'], targets: ['sender-region', 'sender-city', 'sender-street', 'sender-house', 'sender-room'] }
];

function eslUnloadingControllerValue(el) {
    if (!el) {
        return null;
    }
    return el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
}

function eslUnloadingApplyVisibilityRules() {
    ESL_UNLOADING_VISIBILITY_RULES.forEach(function (rule) {
        var controller = document.getElementsByName(rule.controller)[0];
        if (!controller) {
            return;
        }
        var match = rule.values.indexOf(eslUnloadingControllerValue(controller)) !== -1;
        rule.targets.forEach(function (targetName) {
            var target = document.getElementsByName(targetName)[0];
            // "Габариты"/"Вес итогового места" рендерятся вне table.edit-table (см.
            // .esl-combine-places в lib/view/unloading/form.php), поэтому ищем ближайший
            // подходящий контейнер — <tr> для обычных полей формы или .esl-combine-places__field.
            var row = target && target.closest('tr, .esl-combine-places__field');
            if (row) {
                row.style.display = match ? '' : 'none';
            }
        });
    });
}

function eslUnloadingWireVisibilityRules() {
    var bound = {};
    ESL_UNLOADING_VISIBILITY_RULES.forEach(function (rule) {
        if (bound[rule.controller]) {
            return;
        }
        bound[rule.controller] = true;
        var controller = document.getElementsByName(rule.controller)[0];
        if (controller) {
            controller.addEventListener('change', eslUnloadingApplyVisibilityRules);
        }
    });
    eslUnloadingApplyVisibilityRules();
}

// Таблица мест (вкладка "Места") — портировано из wp-content/plugins/eshoplogisticru
// (assets/js/settings_unloading.js: eslAddPlaceRow/eslDeletePlaceRow/eslPlacesRenumber).
// После удаления строки остальные строки переиндексируются, иначе имена полей
// products[N][...] у следующей добавленной строки могли совпасть с уже существующей
// (счётчик считал только количество оставшихся строк, не фактический максимальный индекс).
function eslPlacesRenumber(table) {
    if (!table) {
        return;
    }

    table.querySelectorAll('tbody tr').forEach(function (tr, index) {
        tr.setAttribute('data-number', index);
        tr.querySelectorAll('td input[data-field]').forEach(function (input) {
            input.name = 'products[' + index + '][' + input.getAttribute('data-field') + ']';
        });
    });
}

function eslAddPlaceRow(button) {
    let wrapper = button.closest('.esl-places__main');
    if (!wrapper) {
        return;
    }

    let table = wrapper.querySelector('.esl-places-table');
    let template = wrapper.querySelector('template.esl-row-template');
    if (!table || !template) {
        return;
    }

    let row = template.content.firstElementChild.cloneNode(true);
    table.querySelector('tbody').appendChild(row);
    eslPlacesRenumber(table);
}

function eslDeletePlaceRow(button) {
    let table = button.closest('.esl-places-table');
    let row = button.closest('tr');
    if (!table || !row) {
        return;
    }

    row.remove();
    eslPlacesRenumber(table);
}

BX.ready(function() {
    eslUnloadingWireVisibilityRules();

    document.addEventListener('click', function (e) {
        if (e.target.id === 'buttonModalUnloadAdd') {
            e.preventDefault();
            eslAddPlaceRow(e.target);
            return;
        }

        let deleteBtn = e.target.closest('.esl-delete_table_elem');
        if (deleteBtn) {
            e.preventDefault();
            eslDeletePlaceRow(deleteBtn);
        }
    });
});

function ajaxFormEsl(obForm, link) {
    BX.bind(obForm, 'submit', BX.proxy(function(e) {
        BX.PreventDefault(e);
        obForm.getElementsByClassName('error-msg')[0].innerHTML = '';

        let xhr = new XMLHttpRequest();
        xhr.open('POST', link);

        xhr.onload = function() {
            if (xhr.status !== 200) {
                alert(`Ошибка ${xhr.status}: ${xhr.statusText}`);
            } else {
                const json = JSON.parse(xhr.responseText);
                const isSuccess = json.status === 'success' || json.success === true;

                if (!isSuccess) {
                    let errorStr = '';
                    let errorList = getPropVal(json.errors);

                    for (let val in errorList){
                        errorStr += '<p>'+errorList[val]+'</p>';
                    }

                    if (!errorStr) {
                        errorStr = '<p>Ошибка при выгрузке заказа</p>';
                    }

                    obForm.getElementsByClassName('error-msg')[0].innerHTML = errorStr;
                } else {
                    window.location = window.location.href+'&UNLOADING_SAVED=true';
                }
            }
            BX.adminPanel.closeWait();
        };

        xhr.onerror = function() {
            alert("Запрос не удался");
        };

        xhr.send(new FormData(obForm));
    }, obForm, link));
}

function getPropVal(o, result = []) {
    for (let k in o) {
        if (o.hasOwnProperty(k)) {
            if (typeof o[k] === 'object') {
                getPropVal(o[k], result)
            } else {
                result.push(o[k])
            }
        }
    }

    return result
}