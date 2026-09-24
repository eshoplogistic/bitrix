let init_esl = false
let search_city = false
let global_check = false
let first_load = true
let button_click = false
let add_frame_esl
let servicesLoad = false
let eslAddressChanged = false
// eslRun() может вызываться повторно на каждый onAjaxSuccess, пока global_check не
// станет true (виджет ещё не отрисовал свои поля) — без этого флага каждый такой вызов
// навешивал бы ещё одну копию обработчика onAjaxSuccess внутри eslRun(), и калькулятор
// доставки пересчитывался бы N раз на одно и то же AJAX-обновление чекаута.
let eslRunAjaxSuccessBound = false
let widgetWatchdogTimer = null
let eslWidgetButtonBound = false

// Скрипт работает внутри чужого чекаута: наши обработчики вызываются из BX.ajax
// (onAjaxSuccess), из патча XMLHttpRequest/fetch, из колбэков sale.order.ajax. Исключение,
// вылетевшее из них, ломает не только калькулятор, но и сам чекаут клиента (обработка
// AJAX-ответа обрывается, лоадер не снимается и т.п.). Поэтому каждая точка входа
// оборачивается в eslSafe: ошибка пишется в консоль и дальше не пробрасывается.
function eslLogError(e) {
    try { console.error('ESL:', e) } catch (_) {}
}

function eslSafe(fn) {
    return function () {
        try {
            let result = fn.apply(this, arguments)
            if (result && typeof result.then === 'function') {
                result.then(null, eslLogError)
            }
            return result
        } catch (e) {
            eslLogError(e)
        }
    }
}

function eslOn(target, name, fn) {
    if (target && target.addEventListener) {
        target.addEventListener(name, eslSafe(fn))
    }
}

function eslOnCustom(target, name, fn) {
    if (window.BX && BX.addCustomEvent) {
        BX.addCustomEvent(target, name, eslSafe(fn))
    }
}

function eslMessage(code) {
    return (window.BX && BX.message) ? BX.message(code) : ''
}

// Виджет (api.esplc.ru) при неверном "Ключе widget" ничего не бросает как JS-ошибку и
// не диспатчит ни одно из своих кастомных событий — просто вечно висит в состоянии
// загрузки. #eslCalcErrorMsg — видимый плейсхолдер внутри самой карточки способа
// доставки (см. componentorder.php), а не внутри #eShopLogisticWidgetCart, который до
// открытия попапа скрыт через display:none.
function eslShowWidgetError() {
    let box = document.getElementById('eslCalcErrorMsg')
    if (!box) return
    box.textContent = eslMessage('ESHOP_LOGISTIC_WIDGET_CALC_ERROR')
    box.style.display = 'block'
}

function eslClearWidgetError() {
    let box = document.getElementById('eslCalcErrorMsg')
    if (box) box.style.display = 'none'
}

function eslStartWidgetWatchdog() {
    if (widgetWatchdogTimer) clearTimeout(widgetWatchdogTimer)

    function fire(retriesLeft) {
        let btn = document.getElementById('container_widget_esl_button')
        let box = document.getElementById('eslCalcErrorMsg')
        let buttonStuck = btn && btn.classList.contains('loading-esl')

        // #eslCalcErrorMsg рисуется Bitrix-ом отдельно от #eShopLogisticWidgetCart и может
        // на момент первой проверки ещё не оказаться в DOM — вместо того чтобы сразу
        // сдаваться, дожидаемся его появления повторными попытками в течение ~5 секунд.
        if (!box) {
            if (retriesLeft > 0) {
                widgetWatchdogTimer = setTimeout(eslSafe(function () { fire(retriesLeft - 1) }), 500)
            }
            return
        }

        if (buttonStuck || !servicesLoad) {
            if (btn) btn.classList.remove('loading-esl')
            eslShowWidgetError()
        }
    }

    widgetWatchdogTimer = setTimeout(eslSafe(function () { fire(10) }), 30000)
}

// Не полагаемся на 'DOMContentLoaded' ниже по файлу: если этот скрипт подгружается уже
// после того, как это событие произошло (например, блок доставки перерисован по AJAX),
// слушатель 'DOMContentLoaded' никогда не сработает — событие задним числом не вызывается.
// Поэтому вотчдог стартует сам по себе, независимо от остального кода файла и порядка
// загрузки: как только контейнер виджета появляется в DOM — запускаем 30-секундный отсчёт.
;(function eslWaitForWidgetContainer() {
    if (document.getElementById('eShopLogisticWidgetCart')) {
        eslStartWidgetWatchdog()
    } else {
        setTimeout(eslWaitForWidgetContainer, 200)
    }
})()

function init_popup(){
    button_click = true
}

function isHidden(el) {
    var style = window.getComputedStyle(el);
    return (style.display === 'none')
}

function isNumeric(value) {
    return /^-{0,1}\d+$/.test(value);
}

(function () {
    let esl = {
        items: {
            widget_id: 'eShopLogisticWidgetCart',
            esldata_field_id: 'widgetCityEsl',
            esldata_offers_id: 'widgetOffersEsl',
            esldata_payments_id: 'widgetPaymentEsl',
            esl_button_id: 'container_widget_esl_button',
        },
        current: {payment_id: null, delivery_id: null},
        widget_offers: '',
        widget_city: {name: null, type: null, fias: null, services: {}},
        widget_payment: {key: ''},
        esldata_value: {},
        request: function (action) {
            return new Promise(function (resolve, reject) {
                BX.EShopLogistic.OrderAjaxComponent.sendRequest('refreshOrderAjax', action);
            })
        },
        check: function () {
            let check = true

            const esldata = document.getElementById(this.items.esldata_field_id)
            const current_payment = document.querySelector('input[name=PAY_SYSTEM_ID]:checked')
            if (esldata) {
                this.esldata_value = esldata.value
                window.esldata_value = esldata.value
            } else if (window.esldata_value) {
                this.esldata_value = window.esldata_value
            } else {
                check = false
            }
            if (!current_payment) {
                check = false
            } else {
                this.current.payment_id = current_payment.value
            }
            // Контейнер виджета и эти поля выводятся только при первой загрузке страницы
            // (см. printFrameHtmlField в componentorder.php) — без них prepare()/run() упадут.
            if (!document.getElementById(this.items.widget_id)
                || !document.getElementById(this.items.esldata_offers_id)
                || !document.getElementById(this.items.esldata_payments_id)) {
                check = false
            }
            global_check = check

            return check
        },
        prepare: function () {

            const payments = JSON.parse(document.getElementById(this.items.esldata_payments_id).value)
            const terminal = document.getElementById('terminalEsl')
            const to = JSON.parse(this.esldata_value)
            this.widget_offers = document.getElementById(this.items.esldata_offers_id).value
            this.widget_city.type = to.type
            this.widget_city.name = to.name
            this.widget_city.fias = to.fias
            this.widget_city.services = to.services
            this.widget_payment = (this.current.payment_id) ? this.current.payment_id : 'card'

            let current_payment = this.current.payment_id
            if (current_payment) {
                for (const [key, value] of Object.entries(payments)) {
                    if (key.indexOf(current_payment) != -1) {
                        this.widget_payment = value
                    }
                }
            }

            if(isNumeric(this.widget_payment))
                this.widget_payment = 'card'

        },
        run: async function (reload = '') {
            if (!this.check()) {
                return false
            }
            const widget = document.getElementById(this.items.widget_id)
            this.prepare()

            let settlement = this.widget_city
            let params = {
                offers: this.widget_offers,
                payment: this.widget_payment
            }

            if (reload.length !== 0) {
                switch (reload) {
                    case 'offers':
                        let offers = await this.request('cart=1')
                        params = {
                            offers: JSON.stringify(offers)
                        }
                        break
                    case 'payment':
                        params = {
                            offers: this.widget_offers,
                            payment: this.widget_payment
                        }
                        break
                    case 'city':
                        settlement = this.widget_city

                }
                widget.dispatchEvent(new CustomEvent('eShopLogisticWidgetCart:updateParamsRequest', {
                    detail: {
                        settlement: settlement,
                        requestParams: params
                    }
                }))
            } else {
                eslOn(widget, 'eShopLogisticWidgetCart:onLoadApp', (event) => {
                    widget.dispatchEvent(new CustomEvent('eShopLogisticWidgetCart:updateParamsRequest', {
                        detail: {
                            settlement: settlement,
                            requestParams: params
                        }
                    }))
                })
            }
        },
        confirm: async function (response) {
            //document.getElementById('widgetDeliveriesEsl').value = ''
            let deliveryMethods = {};
            let esldata = {
                price: 0,
                time: '',
                name: response.service.name,
                key: response.service.code,
                mode: response.typeDelivery,
                address: '',
                comment: '',
                deliveryMethods: '',
                selectPvz: ''
            }

            if(document.getElementById('terminalEsl') && document.getElementById('terminalEsl').value){
                esldata.selectPvz = document.getElementById('terminalEsl').value
            }

            if (response.service.comment) {
                esldata.comment = response.service.comment
            }

            if (response.service.responseData) {
                deliveryMethods = response.service.responseData
            }

            if (esldata.key === 'postrf') {
                esldata.price = deliveryMethods.terminal.price.value
                esldata.time = deliveryMethods.terminal.time.value
                esldata.unit = deliveryMethods.terminal.time.unit
            } else {
                esldata.price = deliveryMethods[esldata.mode].price.value
                esldata.time = deliveryMethods[esldata.mode].time.value
                esldata.unit = deliveryMethods[esldata.mode].time.unit
            }

            if (response[esldata.mode]) {
                esldata.address = response[esldata.mode].code + ' ' + response[esldata.mode].address
            }

            await this.request(JSON.stringify(esldata))

        },
        setTerminal: function (response) {
            const terminal = document.getElementById('terminalEsl'),
                info = document.getElementById('eslogisticDescription')

            if(!terminal)
                return false


            terminal.value = response.code + ', ' + response.address
            if (info) {
                info.innerHTML = eslMessage('ESHOP_LOGISTIC_FRAME_PVZ')+': ' + response.address
            }

            let addressRequar = document.getElementById('eslogic-address-requar');
            if (addressRequar && addressRequar.value && addressRequar.value !== '0') {
                let locationFields = document.getElementById('eslogic-location-fields');
                let locationIds = (locationFields && locationFields.value) ? locationFields.value.split(',').map(s => s.trim()) : [];
                const addressRequarArr = addressRequar.value.split(',')
                addressRequarArr.forEach((val) => {
                    val = val.trim();
                    if (locationIds.indexOf(val) !== -1) return;
                    let field = document.querySelector('[name=ORDER_PROP_'+val+']');
                    if (field) {
                        field.value = response.address;
                    }
                })
            }
        },
        error: function (response) {
            console.error('ESL: request error', response)
        },
    }

    esl.run = eslSafe(esl.run)
    esl.confirm = eslSafe(esl.confirm)
    esl.setTerminal = eslSafe(esl.setTerminal)
    eslRun = eslSafe(eslRun)
    eslBindAddressChange = eslSafe(eslBindAddressChange)
    initWidgetPopup = eslSafe(initWidgetPopup)
    validate = eslSafe(validate)

    function eslBindAddressChange() {
        var addressRequar = document.getElementById('eslogic-address-requar');
        if (!addressRequar || !addressRequar.value || addressRequar.value === '0') return;
        var locationFields = document.getElementById('eslogic-location-fields');
        var locationIds = (locationFields && locationFields.value) ? locationFields.value.split(',').map(function(s){ return s.trim(); }) : [];
        var ids = addressRequar.value.split(',');
        ids.forEach(function(id) {
            id = id.trim();
            // LOCATION-поля обслуживает Bitrix — не привязываем change listener
            if (locationIds.indexOf(id) !== -1) return;
            var field = document.querySelector('[name="ORDER_PROP_' + id + '"]');
            if (field && !field.dataset.eslChangeBound) {
                field.dataset.eslChangeBound = '1';
                eslOn(field, 'change', function() {
                    eslAddressChanged = true;
                    BX.Sale.OrderAjaxComponent.sendRequest();
                });
            }
        });
    }

    function eslRun() {
        if (eslRunAjaxSuccessBound) {
            return
        }
        eslRunAjaxSuccessBound = true

        const delivery = document.querySelector('input[name=DELIVERY_ID]')
        esl.run()

        eslOnCustom(window, 'onAjaxSuccess', function (e, t) {
            // t.url обычно содержит query-параметры поиска (?q=...), поэтому строгое
            // сравнение с путём компонента никогда не совпадает — ищем подстроку.
            if (typeof t.url === 'string' && t.url.indexOf('/sale.location.selector.search/get.php') !== -1){
                search_city = true
                first_load = false
            }

            if (eslAddressChanged) {
                eslAddressChanged = false;
                setTimeout(function () {
                    esl.run('city')
                }, 500);
            } else if (!init_esl && e.order && search_city) {
                setTimeout(function () {
                    esl.run('city')
                    search_city = false
                },2500);
            } else if (!init_esl && e.order) {
                esl.run('payment')
            } else if (t.method === 'POST'){
                esl.run('reload')
            }

            initWidgetPopup(false)
            validate()
            init_esl = false
        });
    }

    function initWidgetPopup(init) {
        let popup = document.getElementById('widget_esl_frame')
        if (init && !popup) {
            add_frame_esl = new BX.PopupWindow("widget_esl_frame", null, {
                content: BX('eShopLogisticWidgetCart'),
                closeIcon: {right: "20px", top: "10px"},
                titleBar: {
                    content: BX.create("span", {
                        html: '<b>'+eslMessage('ESHOP_LOGISTIC_FRAME_POPUP_TITLE')+'</b>',
                        'props': {'className': 'access-title-bar'}
                    })
                },
                zIndex: 0,
                offsetLeft: 0,
                offsetTop: 1,
                lightShadow: true,
                closeByEsc: true,
                overlay: {
                    backgroundColor: 'black', opacity: '80'
                },
                autoHide: true,
                draggable: {restrict: false},
                buttons: [
                    new BX.PopupWindowButton({
                        text: eslMessage('ESHOP_LOGISTIC_FRAME_SELECT'),
                        className: "webform-button-link-cancel",
                        events: {
                            click: function () {
                                this.popupWindow.close();
                            }
                        }
                    })
                ]
            });
        }
        // Делегируем на document вместо прямого bind на кнопку: блок доставки пересобирается
        // на каждый AJAX-рефреш чекаута (см. комментарий у eslStartWidgetWatchdog), а
        // initWidgetPopup() вызывается на каждый onAjaxSuccess — прямой bind на сам узел кнопки
        // накапливал бы по новому обработчику на каждый такой вызов. Навешиваем один раз и
        // без jQuery — на сайте клиента его может не быть.
        if (!eslWidgetButtonBound) {
            eslWidgetButtonBound = true
            eslOn(document, 'click', function (event) {
                if (!event.target || !event.target.closest || !event.target.closest('.container_widget_esl_button')) return
                first_load = true
                if (add_frame_esl) add_frame_esl.show()
            })
        }
    }

    eslOn(window, 'load', function (event) {
        eslRun()
        eslBindAddressChange()
    });

    eslOnCustom(window, 'onAjaxSuccess', function (e, t) {
        if(!global_check){
            eslRun()
        }
        eslBindAddressChange()

        // Блок "Доставка" на каждый AJAX-рефреш чекаута пересобирается заново (новый
        // #eShopLogisticWidgetCart/#eslCalcErrorMsg с тем же id вместо старого) — вотчдог,
        // запущенный один раз при первой загрузке, к этому моменту уже целится в удалённый
        // из DOM узел. Перезапускаем его на каждый рефреш, чтобы он всегда проверял
        // актуальный, а не устаревший элемент.
        // Важно: onAjaxSuccess — общее событие чекаута, срабатывает на ЛЮБОЙ AJAX на
        // странице (смена оплаты, купон и т.п.), а не только на действия виджета. Если
        // виджет уже успешно отработал (servicesLoad === true), нельзя сбрасывать этот
        // флаг и перезапускать вотчдог заново — иначе первое же постороннее AJAX-действие
        // после успешной загрузки виджета ошибочно покажет ошибку через 30 секунд.
        if (!servicesLoad && document.getElementById('eShopLogisticWidgetCart')) {
            eslClearWidgetError()
            eslStartWidgetWatchdog()
        }
    })

    eslOn(document, 'DOMContentLoaded', () => {


        const root = document.getElementById('eShopLogisticWidgetCart');

        if (!root) {
            console.warn('ESL: Widget key is not set. Widget will not be initialized.');
            return;
        }

        eslStartWidgetWatchdog()

        // Виджет при инициализации сам обращается к нашему data-controller (widgetData) —
        // считаем это тоже "новым запросом" и перезапускаем наблюдение за зависанием,
        // иначе первый же bootstrap-запрос виджета отключил бы проверку навсегда, ещё до
        // того как виджет успеет обратиться к api.esplc.ru с (возможно неверным) ключом.
        function onWidgetRequest() {
            servicesLoad = false
            eslClearWidgetError()
            eslStartWidgetWatchdog()
        }

        // Патчим глобальные XMLHttpRequest/fetch всего сайта — наш код внутри обязан быть
        // безопасным, иначе любая ошибка в нём сломает вообще все запросы страницы.
        let onWidgetRequestSafe = eslSafe(function (url) {
            if (typeof url === 'string' && url.indexOf('widgetData') !== -1) {
                onWidgetRequest()
            }
        })

        let origOpen = XMLHttpRequest.prototype.open
        XMLHttpRequest.prototype.open = function (method, url) {
            onWidgetRequestSafe(url)
            return origOpen.apply(this, arguments)
        }

        if (window.fetch) {
            let origFetch = window.fetch
            window.fetch = function (url) {
                onWidgetRequestSafe(url)
                return origFetch.apply(this, arguments)
            }
        }

        window.addEventListener('error', eslSafe(function (e) {
            if (!e.filename || e.filename.indexOf('api.esplc.ru') === -1) return
            eslShowWidgetError()
        }), true)

        eslOn(root, 'eShopLogisticWidgetCart:onLoadApp', (event) => {
            setTimeout(function () {
                if(servicesLoad !== true){
                    esl.run('city')
                    servicesLoad = true
                }
            },5000);

        });

        eslOn(root, 'eShopLogisticWidgetCart:onSelectedService', (event) => {
            let data = event.detail
            if (typeof data.terminal == 'object') {
                esl.setTerminal(data.terminal)
                esl.confirm(data)
                add_frame_esl.close()
                setTimeout(function () {
                    waitForElm('#terminalEsl').then((elm) => {
                        esl.setTerminal(data.terminal)
                        validate()
                    });
                }, 100)
            }
            // typeEvent: 'trigger' — виджет сам переэмитит выбор после каждой перезагрузки сервисов (смена города и т.п.),
            // без фильтра это вызывает confirm -> ajax -> ререндер блока доставки -> виджет переинициализируется -> новый trigger -> бесконечный цикл
            if(data.typeDelivery === 'door' && data.typeEvent !== 'trigger') {
                if(document.getElementById('terminalEsl')){
                    document.getElementById('terminalEsl').value = ''
                }
                if(document.getElementById('eslogisticDescription')){
                    document.getElementById('eslogisticDescription').innerHTML = ''
                }
                esl.confirm(data)
                add_frame_esl.close()
            }
        })

        eslOn(root, 'eShopLogisticWidgetCart:onAllServicesLoaded', (event) => {
            servicesLoad = true
            if (widgetWatchdogTimer) { clearTimeout(widgetWatchdogTimer); widgetWatchdogTimer = null }

            // Это событие наступает и когда с неверным ключом виджет фактически ничего не
            // смог посчитать — просто с пустым списком служб. Кнопка перестаёт "грузиться",
            // но стоимость так и остаётся пустой без всякого объяснения. Пустой список
            // считаем таким же сбоем, как и таймаут.
            let hasServices = Array.isArray(event.detail) ? event.detail.length > 0 : !!event.detail
            if (hasServices) {
                eslClearWidgetError()
            } else {
                eslShowWidgetError()
            }

            initWidgetPopup(true)

            if(document.getElementById("container_widget_esl_button")){
                document.getElementById("container_widget_esl_button").classList.remove("loading-esl");
                addEventListener("click", (event) => {
                    init_popup()
                })
            }

            if(document.getElementsByClassName("eslog-deliverey-desc")[0]){
                let countDelivery = event.detail.length
                let nameDelivery = ''
                if (event.detail) {
                    for (const [key, value] of Object.entries(event.detail)) {
                        if (value.name) {
                            if(key == 0){
                                nameDelivery = value.name
                            }else{
                                nameDelivery += ', '+value.name
                            }
                        }
                    }
                }

                let descriptionTerminal = eslMessage("ESHOP_LOGISTIC_TERMINAL_DESC_1")
                descriptionTerminal += ' '+countDelivery
                if (countDelivery == 1) {
                    descriptionTerminal += ' '+eslMessage("ESHOP_LOGISTIC_TERMINAL_DESC_2")
                }else if(countDelivery > 1 && countDelivery < 5){
                    descriptionTerminal += ' '+eslMessage("ESHOP_LOGISTIC_TERMINAL_DESC_5")
                }else{
                    descriptionTerminal += ' '+eslMessage("ESHOP_LOGISTIC_TERMINAL_DESC_3")
                }
                descriptionTerminal += '<br>'+nameDelivery


                if(countDelivery === 0)
                    descriptionTerminal = ''


                document.getElementsByClassName("eslog-deliverey-desc")[0].innerHTML = descriptionTerminal

            }


            if(isHidden(document.getElementById('widget_esl_frame'))){
                first_load = false
            }else{
                first_load = true
            }

            if(document.getElementById("widgetEslNotCalc")){
                BX.Sale.OrderAjaxComponent.result.TOTAL.DELIVERY_PRICE = 0
                BX.Sale.OrderAjaxComponent.result.TOTAL.DELIVERY_PRICE_FORMATED = "0 &#8381;"
                BX.Sale.OrderAjaxComponent.result.TOTAL.ORDER_TOTAL_PRICE = BX.Sale.OrderAjaxComponent.result.TOTAL.ORDER_PRICE
                BX.Sale.OrderAjaxComponent.result.TOTAL.ORDER_TOTAL_PRICE_FORMATED = formatPriceWithSpace(BX.Sale.OrderAjaxComponent.result.TOTAL.ORDER_PRICE) +" &#8381;"
                // Форматирование цены с пробелом между тысячами
                function formatPriceWithSpace(price) {
                    price = parseInt(price, 10);
                    if (isNaN(price)) return price;
                    return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                }
                BX.Sale.OrderAjaxComponent.editTotalBlock()
            }

        })

        eslOn(root, 'eShopLogisticWidgetCart:onSelectTypeDelivery', (event) => {
        })

        eslOn(root, 'eShopLogisticWidgetCart:onInvalidSettlementCode', () => {
            console.error('ESL: Неверный код населенного пункта')
            servicesLoad = true
            if (widgetWatchdogTimer) { clearTimeout(widgetWatchdogTimer); widgetWatchdogTimer = null }
        })

        eslOn(root, 'eShopLogisticWidgetCart:onInvalidName', () => {
            console.error('ESL: Неверный name города')
            servicesLoad = true
            if (widgetWatchdogTimer) { clearTimeout(widgetWatchdogTimer); widgetWatchdogTimer = null }
        })

        eslOn(root, 'eShopLogisticWidgetCart:onInvalidServices', () => {
            console.error('ESL: Неверный массив служб')
            servicesLoad = true
            if (widgetWatchdogTimer) { clearTimeout(widgetWatchdogTimer); widgetWatchdogTimer = null }
        })

        eslOn(root, 'eShopLogisticWidgetCart:onInvalidPayment', () => {
            console.error('ESL: Не передана оплата')
            servicesLoad = true
            if (widgetWatchdogTimer) { clearTimeout(widgetWatchdogTimer); widgetWatchdogTimer = null }
        })

        eslOn(root, 'eShopLogisticWidgetCart:onInvalidOffers', () => {
            console.error('ESL: Не передан offers')
            servicesLoad = true
            if (widgetWatchdogTimer) { clearTimeout(widgetWatchdogTimer); widgetWatchdogTimer = null }
        })

        eslOn(root, 'eShopLogisticWidgetCart:onNotAvailableServices', (event) => {
            console.error('ESL: Событие onNotAvailableServices', event.detail)
            servicesLoad = true
            if (widgetWatchdogTimer) { clearTimeout(widgetWatchdogTimer); widgetWatchdogTimer = null }
            eslShowWidgetError()
        })
    })

    function validate() {
        let fieldTerminal = document.getElementById('terminalEsl')
        let nameErrorDiv = 'errorPvzEsl'
        let cityNotFound = document.querySelector('.eslog-city-not-found')
        if(fieldTerminal){
            if (!fieldTerminal.value && !cityNotFound) {
                let element = document.createElement('div')
                element.id = nameErrorDiv
                element.innerHTML = eslMessage('ESHOP_LOGISTIC_FRAME_ERROR_PVZ')
                if(!document.getElementById(nameErrorDiv))
                    fieldTerminal.parentNode.insertBefore(element, fieldTerminal)
            }else {
                if(document.getElementById(nameErrorDiv))
                    document.getElementById(nameErrorDiv).remove()
            }
        }
    }

    function waitForElm(selector) {
        return new Promise(resolve => {
            if (document.querySelector(selector)) {
                return resolve(document.querySelector(selector));
            }

            const observer = new MutationObserver(mutations => {
                if (document.querySelector(selector)) {
                    observer.disconnect();
                    resolve(document.querySelector(selector));
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    }


})();


// Вне IIFE выше: при включённом объединении JS Bitrix склеивает скрипты в один файл,
// и исключение на верхнем уровне (например, BX ещё не загружен) оборвало бы весь бандл.
(function () {
    'use strict';

    if (!window.BX || !BX.namespace) {
        return;
    }

    BX.namespace('BX.EShopLogistic.OrderAjaxComponent');

    function formatPriceWithSpace(price) {
        price = parseInt(price, 10);
        if (isNaN(price)) return price;
        return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function endLoader() {
        try {
            BX.Sale.OrderAjaxComponent.endLoader();
        } catch (e) {
            eslLogError(e);
        }
    }

    BX.EShopLogistic.OrderAjaxComponent = {

        sendRequest: function (action, actionData) {
            if (!BX.Sale || !BX.Sale.OrderAjaxComponent || !BX.Sale.OrderAjaxComponent.startLoader())
                return;

            // Лоадер чекаута уже показан — любая ошибка ниже без endLoader() оставила бы
            // покупателя с вечно крутящимся чекаутом.
            try {
                BX.Sale.OrderAjaxComponent.firstLoad = false;

                action = BX.type.isNotEmptyString(action) ? action : 'refreshOrderAjax';

                var eventArgs = {
                    action: action,
                    cancel: false,
                    actionData: ''
                };
                BX.Event.EventEmitter.emit('BX.Sale.OrderAjaxComponent:onBeforeSendRequest', eventArgs);
                if (eventArgs.cancel) {
                    endLoader();
                    return;
                }
                var data = BX.Sale.OrderAjaxComponent.getData(eventArgs.action, eventArgs.actionData);
                var resultEsl = JSON.parse(actionData);
                data['eslData'] = actionData;
                data['location'] = BX.Sale.OrderAjaxComponent.deliveryLocationInfo.loc
                init_esl = true;
            } catch (e) {
                eslLogError(e);
                endLoader();
                return;
            }

            BX.ajax({
                method: 'POST',
                dataType: 'json',
                url: BX.Sale.OrderAjaxComponent.ajaxUrl,
                data: data,
                onsuccess: function (result) {
                    // Подмена цены — наша «надстройка»: если она не удалась, чекаут всё равно
                    // должен обновиться штатным ответом сервера.
                    try {
                        result.order.TOTAL.DELIVERY_PRICE_FORMATED = resultEsl.price + ' &#8381;';
                        result.order.TOTAL.DELIVERY_PRICE = resultEsl.price;
                        result.order.TOTAL.ORDER_TOTAL_PRICE_FORMATED = formatPriceWithSpace(result.order.TOTAL.ORDER_PRICE + resultEsl.price) + " &#8381;"
                    } catch (e) {
                        eslLogError(e);
                    }

                    try {
                        if (result && result.redirect && result.redirect.length)
                            document.location.href = result.redirect;

                        switch (eventArgs.action) {
                            case 'refreshOrderAjax':
                                BX.Sale.OrderAjaxComponent.refreshOrder(result);
                                break;
                        }
                        BX.cleanNode(BX.Sale.OrderAjaxComponent.savedFilesBlockNode);
                    } catch (e) {
                        eslLogError(e);
                    } finally {
                        endLoader();
                    }
                },
                onfailure: function () {
                    console.error('ESL: sendRequest failed', action);
                    endLoader();
                }
            });
        },

    };
})();
