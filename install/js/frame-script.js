let init_esl = false
let search_city = false
let global_check = false
let first_load = true
let button_click = false
let add_frame_esl

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
            widget_id: 'eShopLogisticStatic',
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
            this.widget_payment.key = (this.current.payment_id) ? this.current.payment_id : 'card'
            this.widget_payment.active = true

            let current_payment = this.current.payment_id
            if (current_payment) {
                for (const [key, value] of Object.entries(payments)) {
                    if (value.indexOf(current_payment) != -1) {
                        this.widget_payment.key = key
                    }
                }
            }

            if(isNumeric(this.widget_payment.key))
                this.widget_payment.key = 'card'

        },
        run: async function (reload = '') {
            if (!this.check()) {
                console.log('ESL: Ошибка проверки элементов')
                return false
            }
            const widget = document.getElementById(this.items.widget_id)
            this.prepare()

            let detail = {
                city: this.widget_city,
                payment: this.widget_payment,
                offers: this.widget_offers
            }


            if (reload.length !== 0) {
                switch (reload) {
                    case 'offers':
                        let offers = await this.request('cart=1')
                        detail = {
                            offers: JSON.stringify(offers)
                        }
                        break
                    case 'payment':
                        detail = {
                            payment: this.widget_payment
                        }
                        break
                    case 'city':
                        detail = {
                            city: this.widget_city
                        }

                }
                widget.dispatchEvent(new CustomEvent('eShopLogistic:reload', {detail}))
            } else {
                widget.dispatchEvent(new CustomEvent('eShopLogistic:load', {detail}))
            }
        },
        confirm: async function (response) {
            //document.getElementById('widgetDeliveriesEsl').value = ''

            esldata = {
                price: 0,
                time: '',
                name: response.name,
                key: response.keyShipper,
                mode: response.keyDelivery,
                address: '',
                comment: '',
                deliveryMethods: '',
                selectPvz: ''
            }

            if(document.getElementById('terminalEsl') && document.getElementById('terminalEsl').value){
                esldata.selectPvz = document.getElementById('terminalEsl').value
            }

            if (response.comment) {
                esldata.comment = response.comment
            }

            if (response.deliveryMethods) {
                esldata.deliveryMethods = response.deliveryMethods
            }

            if (response.keyDelivery === 'postrf') {
                esldata.price = response.terminal.price
                esldata.time = response.terminal.time
                if (response.terminal.comment) {
                    esldata.comment += '<br>' + response.terminal.comment
                }
            } else {
                esldata.price = response[response.keyDelivery].price
                esldata.time = response[response.keyDelivery].time
                if (response[response.keyDelivery].comment) {
                    esldata.comment += '<br>' + response[response.keyDelivery].comment
                }
            }

            if (response.deliveryAddress) {
                esldata.address = response.deliveryAddress.code + ' ' + response.deliveryAddress.address
            } else {
                if (response.currentAddress) {
                    esldata.address = response.currentAddress
                }
            }

            await this.request(JSON.stringify(esldata))

        },
        setTerminal: function (response) {
            const terminal = document.getElementById('terminalEsl'),
                info = document.getElementById('eslogisticDescription')
            if (response.keyDelivery === 'terminal') {
                terminal.value = response.deliveryAddress.code + ' ' + response.deliveryAddress.address
                if (info) {
                    info.innerHTML = BX.message('ESHOP_LOGISTIC_FRAME_PVZ')+': ' + response.deliveryAddress.address
                }
            } else {
                if (info) {
                    info.parentElement.style.display = 'none'
                }
                terminal.value = ''
            }
        },
        error: function (response) {
            console.log('Ошибка виджета, включен дефолтный режим доставки', response)
        },
    }

    function eslRun() {
        const delivery = document.querySelector('input[name=DELIVERY_ID]')
        esl.run()
        initWidgetPopup(true)

        BX.addCustomEvent(window, 'onAjaxSuccess', function (e, t) {
            if (t.url === '/bitrix/components/bitrix/sale.location.selector.search/get.php'){
                search_city = true
                first_load = false
            }

            if (!init_esl && e.order && search_city) {
                esl.run('city')
                search_city = false
            } else if (!init_esl && e.order) {
                esl.run('payment')
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
                content: BX('eShopLogisticStatic'),
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
                                this.popupWindow.close(); // закрытие окна
                            }
                        }
                    })
                ]
            });
        } else {
            $('.container_widget_esl_button').click(function () {
                first_load = true
                add_frame_esl.show(); // появление окна
            });
        }
    }

    window.addEventListener('load', function (event) {
        eslRun()

    });

    BX.addCustomEvent(window, 'onAjaxSuccess', function (e, t) {
        if(!global_check){
            eslRun()
        }
    })

    document.addEventListener('eShopLogistic:ready', () => {
        eShopLogistic.onSelectedPVZ = function (response) {
            console.log('onSelectedPVZ', response)
            esl.setTerminal(response)
            validate()
            add_frame_esl.close()
        }
        eShopLogistic.onError = function (response) {
            console.log('onError', response)
            esl.error(response)
            document.dispatchEvent(new CustomEvent('esl2onError', {detail: response}))
        }
        eShopLogistic.onSelectedService = function (response) {
            console.log('onSelectedService', response)

            if(document.getElementById('terminalEsl')){
                document.getElementById('terminalEsl').value = ''
            }
            if(document.getElementById('eslogisticDescription')){
                document.getElementById('eslogisticDescription').innerHTML = ''
            }

            if(first_load){
                setTimeout(function() {
                    esl.confirm(response)
                    document.dispatchEvent(new CustomEvent('esl2onSelectedService', {detail: response}))
                    document.getElementById("container_widget_esl_button").style.display="block"
                }, 100)
                if(response.keyDelivery === 'door'){
                    document.getElementById('widget_esl_frame').getElementsByClassName('popup-window-buttons')[0].style.display = 'block'
                }else{
                    document.getElementById('widget_esl_frame').getElementsByClassName('popup-window-buttons')[0].style.display = 'none'
                }
            }

            if(isHidden(document.getElementById('widget_esl_frame'))){
                first_load = false
            }else{
                first_load = true
            }

            //if(!button_click)
            //first_load = false
        }
        eShopLogistic.onSelectedCity = function (response) {
            console.log('onSelectedCity', response)
            document.getElementById("container_widget_esl_button").style.display="none"
        }
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
