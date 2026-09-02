/* JS диалогов CAdminDialog вкладки "Заказ": clearstatus.php (eslClearSubmit),
 * print.php (eslPrintSubmit), settings/additionalservices.php (eslAddFieldSubmit).
 *
 * BX.CAdminDialog не изолирует контент в iframe и перехватывает submit формы только
 * если диалог создан с параметром buttons (у нас его нет) - обычный submit пробивает
 * диалог и уводит на голую страницу без оформления админки. Поэтому сохранение здесь -
 * всегда вручную через XHR, с урлом из f.action / data-атрибута (НЕ window.location -
 * он в этих диалогах указывает на РОДИТЕЛЬСКУЮ страницу, например sale_order_view.php).
 *
 * Ответ на такой XHR подставляется через element.outerHTML/innerHTML - а <script>-теги,
 * вставленные так, браузер не выполняет. Поэтому сами функции определяются один раз (при
 * начальной загрузке диалога, когда этот файл подключён через <script src>), а сервер в
 * ответе на XHR возвращает только разметку, без повторных <script>.
 */

function eslClearSubmit(btn, mode) {
    var f = btn.closest('form');
    f.querySelector('[name="mode"]').value = mode;
    var x = new XMLHttpRequest();
    x.open('POST', f.action, true);
    x.onload = function () {
        if (x.status === 200) {
            document.getElementById('esl-clear-root').outerHTML = x.responseText;
        } else {
            alert('Ошибка ' + x.status);
        }
    };
    x.onerror = function () {
        alert('Запрос не удался');
    };
    x.send(new FormData(f));
}

function eslPrintSubmit(btn) {
    document.querySelectorAll('.esl-print-btn').forEach(function (b) {
        b.classList.remove('esl-print-btn--active');
    });
    btn.classList.add('esl-print-btn--active');

    var root = document.getElementById('esl-print-root');
    var paperSelect = document.getElementById('esl-print-paper');
    var resultEl = document.getElementById('esl-print-result');
    resultEl.innerHTML = '<div class="esl-print-loading"><span class="esl-print-spinner"></span>' + root.getAttribute('data-loading-text') + '</div>';

    var fd = new FormData();
    fd.append('sessid', root.getAttribute('data-sessid'));
    fd.append('mode', btn.getAttribute('data-mode'));
    fd.append('type', btn.getAttribute('data-type') || '');
    fd.append('paper', paperSelect ? paperSelect.value : '');

    var x = new XMLHttpRequest();
    x.open('POST', root.getAttribute('data-action-url'), true);
    x.onload = function () {
        if (x.status === 200) {
            resultEl.innerHTML = x.responseText;
        } else {
            resultEl.innerHTML = '<div class="esl-print-result esl-print-result--error">Ошибка ' + x.status + '</div>';
        }
    };
    x.onerror = function () {
        resultEl.innerHTML = '<div class="esl-print-result esl-print-result--error">Запрос не удался</div>';
    };
    x.send(fd);
}

function eslAddFieldSubmit(btn) {
    var f = btn.closest('form');
    var x = new XMLHttpRequest();
    x.open('POST', f.action, true);
    x.onload = function () {
        if (x.status === 200) {
            document.getElementById('esl-addfield-root').outerHTML = x.responseText;
        } else {
            alert('Ошибка ' + x.status + ' при сохранении');
        }
    };
    x.onerror = function () {
        alert('Запрос не удался');
    };
    x.send(new FormData(f));
}
