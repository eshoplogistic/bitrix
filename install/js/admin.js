// Условная видимость полей на форме выгрузки заказа — портировано из МойСклад
// (assets/js/script.js: displayForm/displayFormInitVisible). Контроллер (select/
// чекбокс) хранит значение, при совпадении с которым перечисленные поля показываются,
// иначе скрываются. Поля адресуются по name (как в остальном модуле).
var ESL_UNLOADING_VISIBILITY_RULES = [
    // "Курьер" (door) — доставка по адресу, код/адрес ПВЗ не нужны;
    // "Пункт выдачи" (terminal) — нужен код и адрес терминала/ПВЗ.
    { controller: 'delivery_type', values: ['terminal'], targets: ['terminal-code', 'terminal-address'] },
    // Улица/дом/квартира получателя имеют смысл только при доставке курьером до двери.
    // "delivery[location_to][comment]" рендерится только для Байкал Сервиса (см.
    // exportfileds.php) — на других ТК этого поля в форме просто нет, скрывать нечего.
    { controller: 'delivery_type', values: ['door'], targets: ['receiver-street', 'receiver-house', 'receiver-room', 'delivery[location_to][comment]'] },
    // Габариты/вес итогового места имеют смысл только если места объединяются в одно.
    { controller: 'order[combine_places][apply]', values: ['1'], targets: ['order[combine_places][dimensions]', 'order[combine_places][weight]'] },
    // "Сами привезём на терминал" (0) — нужен код терминала отгрузки (см. unloading.php:
    // pick_up==0 -> delivery.location_from.terminal, обязательно по контракту API);
    // "Груз заберёт ТК" (1) — нужен адрес отправителя (pick_up==1 -> ...location_from.address).
    { controller: 'pick_up', values: ['0'], targets: ['sender-terminal'] },
    { controller: 'pick_up', values: ['1'], targets: ['sender-region', 'sender-city', 'sender-street', 'sender-house', 'sender-room'] },
    // ПЭК: юрлицо/ИП отправителя — нужен документ представителя, физлицо — реквизиты
    // организации не нужны, зато нужны собственные ФИО (см. Iframe.php / ExportFileds.php МойСклад).
    { controller: 'sender[identity][org_type]', values: ['1', '2'], targets: ['sender[identity][type]', 'sender[identity][series]', 'sender[identity][number]', 'sender[identity][date]', 'sender[identity][first_name]', 'sender[identity][last_name]', 'sender[identity][patronymic]'] },
    { controller: 'sender[identity][org_type]', values: ['3'], targets: ['sender[requisites][name]', 'sender[requisites][inn]'] },
    // ПЭК: то же самое для получателя — юрлицо/ИП удостоверяется документом
    // представителя, физлицо — только ИНН. Поле "receiver[identity][type]" под тем же
    // именем есть и у Байкал Сервиса (см. exportfileds.php), но с другим словарём
    // значений (1/5/9/12 — форма организации, а не юрлицо/ИП/физлицо) — без привязки к
    // carrier это правило гасило Байкалу ИНН получателя при значении '1' ("Физическое
    // лицо" в словаре Байкала, но не входит в список ['1','2'] с точки зрения ПЭК).
    { controller: 'receiver[identity][type]', values: ['1', '2'], targets: ['receiver[identity][document_type]', 'receiver[identity][passport_series]', 'receiver[identity][passport_number]', 'receiver[identity][passport_date_of_issue]', 'receiver[last_name]'], carrier: 'pecom' },
    { controller: 'receiver[identity][type]', values: ['3'], targets: ['receiver[requisites][inn]'], carrier: 'pecom' },
    // Байкал Сервис: тот же контроллер "receiver[identity][type]", но значения — код
    // организационно-правовой формы получателя (1=физ.лицо, 5=ООО, 9=ИП, 12=АО, см.
    // ESHOP_LOGISTIC_HELPERS_TYPE_BAIKAL_1). Портировано из ExportFileds.php МойСклад
    // (displayForm/displayFormInitVisible): паспорт нужен только физлицу, ИНН — только
    // организациям/ИП, а КПП — только настоящим юрлицам (у ИП, как и у физлица, КПП по
    // закону не бывает).
    { controller: 'receiver[identity][type]', values: ['1'], targets: ['receiver[identity][passport_series]', 'receiver[identity][passport_number]'], carrier: 'baikal' },
    { controller: 'receiver[identity][type]', values: ['5', '9', '12'], targets: ['receiver[requisites][inn]'], carrier: 'baikal' },
    { controller: 'receiver[identity][type]', values: ['5', '12'], targets: ['receiver[requisites][kpp]'], carrier: 'baikal' },
    // Байкал Сервис: то же самое для отправителя — "sender[legal]" (1=юрлицо,
    // 2=физлицо, см. ESHOP_LOGISTIC_HELPERS_LEGAL_TYPE_BAIKAL) определяет, нужна ли
    // организационно-правовая форма + реквизиты организации (юрлицо), или серия/номер
    // документа (физлицо). Портировано из ExportFileds.php МойСклад так же, как и
    // получательский блок выше — оба поля были переведены из text в select в этом же
    // разделе, но правила видимости для них тогда не завели.
    { controller: 'sender[legal]', values: ['1'], targets: ['sender[identity][type]', 'sender[requisites][inn]', 'sender[requisites][kpp]'], carrier: 'baikal' },
    { controller: 'sender[legal]', values: ['2'], targets: ['sender[identity][series]', 'sender[identity][number]'], carrier: 'baikal' }
];

// Значение скрытого поля "delivery_id" (см. lib/view/unloading/form.php) — код текущей
// ТК формы. Правило с rule.carrier применяется только на форме этой ТК: несколько служб
// используют одинаковые имена полей ("receiver[identity][type]" и у ПЭК, и у Байкал
// Сервиса) с разными словарями значений — без этой проверки правило одной ТК может
// перезаписать видимость одноимённого, но не связанного с ним поля другой.
function eslUnloadingCurrentCarrier() {
    var el = document.getElementsByName('delivery_id')[0];
    return el ? el.value : null;
}

function eslUnloadingControllerValue(el) {
    if (!el) {
        return null;
    }
    return el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
}

function eslUnloadingApplyVisibilityRules() {
    var currentCarrier = eslUnloadingCurrentCarrier();
    ESL_UNLOADING_VISIBILITY_RULES.forEach(function (rule) {
        if (rule.carrier && rule.carrier !== currentCarrier) {
            return;
        }
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
                // Bitrix оборачивает ЛЮБОЙ return экшна в {status:"success", data:<результат>},
                // если сам контроллер не вызвал addError() — а unloadingFormAction() для бизнес-
                // ошибок (отказ API в выгрузке) просто возвращает ['success'=>false,'errors'=>...],
                // не вызывая addError(). Поэтому json.status тут ВСЕГДА "success", даже когда
                // выгрузка реально не удалась — проверять нужно вложенный json.data.success.
                // json.status !== 'success' остаётся для framework-уровня (нет доступа, неверный
                // csrf-токен и т.п. — там ошибки лежат в json.errors, а не в json.data).
                const payload = json.data || {};
                const isSuccess = json.status === 'success' && payload.success === true;

                if (!isSuccess) {
                    let errorList = getPropVal(json.status === 'success' ? payload.errors : json.errors);

                    // Общее текстовое описание сбоя запроса от API (например "Данные не
                    // получены") — отдельно от errors (поле-специфичных ошибок), показываем
                    // заголовком блока, если оно есть и не дублирует уже показанный текст.
                    let title = (json.status === 'success' && payload.http_status_message && errorList.indexOf(payload.http_status_message) === -1)
                        ? payload.http_status_message
                        : '';

                    // Один блок вместо стопки отдельных карточек на каждую ошибку — при
                    // нескольких поле-специфичных ошибках (например по всем незаполненным
                    // адресным полям) список читается компактнее, чем N одинаковых плашек.
                    let html = '<div class="esl-error-box">';
                    if (title) {
                        html += '<div class="esl-error-box__title">' + title + '</div>';
                    }
                    if (errorList.length) {
                        html += '<ul class="esl-error-box__list">' + errorList.map(function (message) {
                            return '<li>' + message + '</li>';
                        }).join('') + '</ul>';
                    } else if (!title) {
                        html += '<div class="esl-error-box__title">Ошибка при выгрузке заказа</div>';
                    }
                    html += '</div>';

                    obForm.getElementsByClassName('error-msg')[0].innerHTML = html;
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