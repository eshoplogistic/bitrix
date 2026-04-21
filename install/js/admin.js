BX.ready(function() {
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