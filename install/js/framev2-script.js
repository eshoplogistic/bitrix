let init_esl = false
let search_city = false
let global_check = false
let first_load = true
let button_click = false
let add_frame_esl
let servicesLoad = false

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
        type_delivery_default: {
            terminal: 'pickup',
            postrf: 'post',
            door: 'todoor',
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
            if(window.esldata_value && esldata == null){
                this.esldata_value = window.esldata_value
            }
            else if (window.esldata_value && esldata){
                this.esldata_value = esldata.value
                window.esldata_value = esldata.value
            }
            else if (esldata == null) {
                check = false
            }else{
                this.esldata_value = esldata.value
                window.esldata_value = esldata.value
            }
            if (!current_payment) {
                check = false
            } else {
                this.current.payment_id = current_payment.value
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
                console.log('ESL: Error check')
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
                console.log('reload')
                widget.dispatchEvent(new CustomEvent('eShopLogisticWidgetCart:updateParamsRequest', {
                    detail: {
                        settlement: settlement,
                        requestParams: params
                    }
                }))
            } else {
                console.log('load')
                widget.addEventListener('eShopLogisticWidgetCart:onLoadApp', (event) => {
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
            esldata = {
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


            terminal.value = response.code + ' ' + response.address
            if (info) {
                info.innerHTML = BX.message('ESHOP_LOGISTIC_FRAME_PVZ')+': ' + response.address
            }

            let addressRequar =  document.getElementById('eslogic-address-requar');
            if (typeof(addressRequar) != 'undefined' && addressRequar != null)
            {
                if(addressRequar.value && addressRequar.value !== '0'){
                    document.querySelector('[name=ORDER_PROP_'+addressRequar.value+']').value = response.address;
                }
            }
        },
        error: function (response) {
            console.log('Esl: error', response)
        },
    }

    function eslRun() {
        const delivery = document.querySelector('input[name=DELIVERY_ID]')
        esl.run()

        BX.addCustomEvent(window, 'onAjaxSuccess', function (e, t) {
            if (t.url === '/bitrix/components/bitrix/sale.location.selector.search/get.php'){
                search_city = true
                first_load = false
            }

            if (!init_esl && e.order && search_city) {
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
                        html: '<b>'+BX.message('ESHOP_LOGISTIC_FRAME_POPUP_TITLE')+'</b>',
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
                        text: BX.message('ESHOP_LOGISTIC_FRAME_SELECT'),
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
        $('.container_widget_esl_button').click(function () {
            first_load = true
            add_frame_esl.show();
        });
    }

    window.addEventListener('load', function (event) {
        eslRun()

    });

    BX.addCustomEvent(window, 'onAjaxSuccess', function (e, t) {
        if(!global_check){
            eslRun()
        }
    })

    document.addEventListener('DOMContentLoaded', () => {


        const root = document.getElementById('eShopLogisticWidgetCart');

        root.addEventListener('eShopLogisticWidgetCart:onLoadApp', (event) => {
            console.log('Событие onLoadApp', event.detail)

            setTimeout(function () {
                if(servicesLoad !== true){
                    console.log('reloadTime5sec')
                    esl.run('city')
                    servicesLoad = true
                }
            },5000);

        });

        root.addEventListener('eShopLogisticWidgetCart:onSelectedService', (event) => {
            let data = event.detail
            console.log('Событие onSelectedService', data)
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
            if(data.typeDelivery === 'door') {
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

        root.addEventListener('eShopLogisticWidgetCart:onAllServicesLoaded', (event) => {
            console.log('Событие onAllServicesLoaded', event.detail)
            servicesLoad = true

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

                let descriptionTerminal = BX.message("ESHOP_LOGISTIC_TERMINAL_DESC_1")
                descriptionTerminal += ' '+countDelivery
                if (countDelivery == 1) {
                    descriptionTerminal += ' '+BX.message("ESHOP_LOGISTIC_TERMINAL_DESC_2")
                }else if(countDelivery > 1 && countDelivery < 5){
                    descriptionTerminal += ' '+BX.message("ESHOP_LOGISTIC_TERMINAL_DESC_5")
                }else{
                    descriptionTerminal += ' '+BX.message("ESHOP_LOGISTIC_TERMINAL_DESC_3")
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
                BX.Sale.OrderAjaxComponent.result.TOTAL.ORDER_TOTAL_PRICE_FORMATED = BX.Sale.OrderAjaxComponent.result.TOTAL.ORDER_PRICE+" &#8381;"
                BX.Sale.OrderAjaxComponent.editTotalBlock()
            }

        })

        root.addEventListener('eShopLogisticWidgetCart:onSelectTypeDelivery', (event) => {
            console.log('Событие onSelectTypeDelivery', event.detail)
        })

        root.addEventListener('eShopLogisticWidgetCart:onInvalidSettlementCode', () => {
            console.log('Неверный код населенного пункта')
            servicesLoad = true
        })

        root.addEventListener('eShopLogisticWidgetCart:onInvalidName', () => {
            console.log('Неверный name города')
            servicesLoad = true
        })

        root.addEventListener('eShopLogisticWidgetCart:onInvalidServices', () => {
            console.log('Неверный массив служб')
            servicesLoad = true
        })

        root.addEventListener('eShopLogisticWidgetCart:onInvalidPayment', () => {
            console.log('Не передана оплата')
            servicesLoad = true
        })

        root.addEventListener('eShopLogisticWidgetCart:onInvalidOffers', () => {
            console.log('Не передан offers')
            servicesLoad = true
        })

        root.addEventListener('eShopLogisticWidgetCart:onNotAvailableServices', (event) => {
            console.log('Событие onNotAvailableServices', event)
        })
    })

    function validate() {
        let fieldTerminal = document.getElementById('terminalEsl')
        let nameErrorDiv = 'errorPvzEsl'
        if(fieldTerminal){
            if (!fieldTerminal.value) {
                let element = document.createElement('div')
                element.id = nameErrorDiv
                element.innerHTML = BX.message('ESHOP_LOGISTIC_FRAME_ERROR_PVZ')
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


BX.namespace('BX.EShopLogistic.OrderAjaxComponent');

(function () {
    'use strict';

    BX.EShopLogistic.OrderAjaxComponent = {

        sendRequest: function (action, actionData) {
            if (!BX.Sale.OrderAjaxComponent.startLoader())
                return;

            BX.Sale.OrderAjaxComponent.firstLoad = false;

            action = BX.type.isNotEmptyString(action) ? action : 'refreshOrderAjax';

            var eventArgs = {
                action: action,
                cancel: false,
                actionData: ''
            };
            BX.Event.EventEmitter.emit('BX.Sale.OrderAjaxComponent:onBeforeSendRequest', eventArgs);
            if (eventArgs.cancel) {
                BX.Sale.OrderAjaxComponent.endLoader();
                return;
            }
            var data = BX.Sale.OrderAjaxComponent.getData(eventArgs.action, eventArgs.actionData);
            var resultEsl = JSON.parse(actionData);
            data['eslData'] = actionData;
            data['location'] = BX.Sale.OrderAjaxComponent.deliveryLocationInfo.loc
            init_esl = true;

            BX.ajax({
                method: 'POST',
                dataType: 'json',
                url: BX.Sale.OrderAjaxComponent.ajaxUrl,
                data: data,
                onsuccess: BX.delegate(function (result) {
                    result.order.TOTAL.DELIVERY_PRICE_FORMATED = resultEsl.price + ' &#8381';
                    result.order.TOTAL.DELIVERY_PRICE = resultEsl.price;
                    result.order.TOTAL.ORDER_TOTAL_PRICE_FORMATED = result.order.TOTAL.ORDER_PRICE + resultEsl.price + ' &#8381';
                    if (result.redirect && result.redirect.length)
                        document.location.href = result.redirect;

                    switch (eventArgs.action) {
                        case 'refreshOrderAjax':
                            BX.Sale.OrderAjaxComponent.refreshOrder(result);
                            break;
                    }
                    BX.cleanNode(BX.Sale.OrderAjaxComponent.savedFilesBlockNode);
                    BX.Sale.OrderAjaxComponent.endLoader();
                }, this),
                onfailure: BX.delegate(function () {
                    BX.Sale.OrderAjaxComponent.endLoader();
                }, this)
            });
        },

    };
})();
