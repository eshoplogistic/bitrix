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
            var row = target && target.closest('tr');
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

BX.ready(function() {
    eslUnloadingWireVisibilityRules();

    let deleteElemTable = function(e) {
        e.preventDefault();
        e.target.closest('tr').remove();
    };

    let buttonAddTable = document.getElementById('buttonModalUnloadAdd')
    if(buttonAddTable){
        buttonAddTable.addEventListener("click", (event) => {
            event.preventDefault();
            let table = document.querySelector('#edit3_edit_table');
            let tbodyTr = table.querySelector('.mainTbody tr');
            let tbodyTrAll = table.querySelectorAll('.mainTbody tr');
            let tbodyTd = tbodyTr.querySelectorAll('td');
            let tbodyTdArray = [...tbodyTd];
            let tbodyTrArray = [...tbodyTrAll];
            let tbodyTrArrayCount = Number(tbodyTrArray.length) + 1;
            let tr = document.createElement('tr');
            tbodyTdArray.forEach(element => {
                let td = document.createElement('td');
                if (element.getAttribute('name') === 'delete') {
                    let deleteBtn = document.createElement('div');
                    deleteBtn.className = 'esl-delete_table_elem';
                    deleteBtn.innerHTML = '&#65794;';
                    deleteBtn.addEventListener('click', deleteElemTable, false);
                    td.appendChild(deleteBtn);
                } else {
                    let input = document.createElement('input');
                    input.name = 'products[' + tbodyTrArrayCount + '][' + element.getAttribute('name') + ']';
                    input.type = 'text';
                    td.appendChild(input);
                }
                tr.appendChild(td);
            });
            table.querySelector('.mainTbody').appendChild(tr);
        });
    }

    let deleteElements = document.getElementsByClassName("esl-delete_table_elem");

    for (let i = 0; i < deleteElements.length; i++) {
        deleteElements[i].addEventListener('click', deleteElemTable, false);
    }
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