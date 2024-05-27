BX.namespace('EShopLogistic.Delivery.sale_order_ajax');

(function () {
    BX.EShopLogistic.Delivery.sale_order_ajax = {

        init: function () {
            var myMap;
            var popup;
        },

        calcFullAddress: function () {
            BX.Sale.OrderAjaxComponent.sendRequest();
        },

        getPvzList: function(profileId) {

            let locationFieldId = BX.Sale.OrderAjaxComponent.deliveryLocationInfo.loc;
            let locationInput = document.querySelector('input[name=ORDER_PROP_'+locationFieldId+']');
            let paymentInput = document.querySelector('input[name=PAY_SYSTEM_ID]:checked');

            if(!locationInput){
                var request = BX.ajax.runAction('eshoplogistic:delivery.api.ajaxhandler.getDefaultCity');

                request.then(function(response){
                    locationInput = response.data[0]
                    BX.EShopLogistic.Delivery.sale_order_ajax.initPvzList(locationFieldId, locationInput, paymentInput, profileId)
                });
            }else{
                BX.EShopLogistic.Delivery.sale_order_ajax.initPvzList(locationFieldId, locationInput.value, paymentInput, profileId)
            }

        },

        initPvzList: function (locationFieldId, locationInput, paymentInput, profileId) {
            let width = window.screen.width/2;
            let height = window.screen.height/2;

            let map_container = BX.create({
                tag:'div',
                props: {className: 'ymap-container'},
                children: [
                    BX.create({
                        tag:'div',
                        props: {id: 'eslogic-loader', className: 'loader loader-default is-active'}
                    }),
                    BX.create({
                        tag:'div',
                        props: {id: 'elog_pvz_map'},
                        style: {height: height+'px', width: width+'px'}
                    }),
                ]

            });

            popup = BX.PopupWindowManager.create("elog_pvz_popup", "", {
                content: map_container,
                width: 'auto',
                height: 'auto',
                zIndex: 100,
                closeIcon: {
                    opacity: 1
                },
                overlay : true,
                closeByEsc: true,
                darkMode: false,
                autoHide: true,
                draggable: false,
                resizable: false,
                min_height: 100,
                min_width: 100,
                lightShadow: false,
                angle: false,
                events: {
                    onPopupClose: function (PopupWindow) {
                        PopupWindow.destroy();
                    }
                }
            });

            popup.show();

            ymaps.ready(BX.EShopLogistic.Delivery.sale_order_ajax.initYMap);


            var request = BX.ajax.runAction('eshoplogistic:delivery.api.ajaxhandler.getPvzList', {
                data: {
                    profileId: profileId,
                    locationCode: locationInput,
                    paymentId: paymentInput.value
                }
            });

            request.then(function(response){
                geoObjects = [];
                let curPhone, curWorkTime, contentBody, contentFooter;
                let pvzList = response.data[0];

                if(pvzList !== null && pvzList.terminals.length > 0) {
                    pvzList.terminals.forEach(function(item, i, pvzList) {

                        if(item.phones) {
                            curPhone = item.phones;
                        } else {
                            curPhone = false;
                        }

                        if(item.workTime) {
                            curWorkTime = item.workTime;
                        } else {
                            curWorkTime = false;
                        }
                        contentBody = '';
                        if(curPhone) {
                            contentBody += '<div class="eslog-point-info">'
                                + BX.message('ESHOP_LOGISTIC_DELIVERY_PHONE') + curPhone + '</div>';
                        }

                        if(item.is_postamat === 1) {
                            contentBody += '<div class="eslog-point-postamat">'
                                + BX.message('ESHOP_LOGISTIC_DELIVERY_POSTAMAT') + '</div>';
                        }

                        if(curWorkTime) {
                            contentBody += '<div class="eslog-point-info">'
                                + BX.message('ESHOP_LOGISTIC_DELIVERY_WORK_TIME') + curWorkTime + '</div>'
                                + '<div class="eslog-point-info">'+item.note+'</div>';
                        }

                        if(item.surcharge === 1) {
                            contentBody += '<div class="eslog-point-add-payment">'
                                + BX.message('ESHOP_LOGISTIC_DELIVERY_ADDITIONAL_PAYMENT') +'</div>';
                        }

                        contentFooter = '<div class="eslog-point-info">' +
                            '<a' +
                            ' onclick="BX.EShopLogistic.Delivery.sale_order_ajax.getPvz(this)"' +
                            ' href="javascript:void(0)"' +
                            ' id="eslogistic-btn-choose-pvz"' +
                            ' class="eslog-btn-default"' +
                            ' data-code="'+item.code+'"'+
                            '>' + BX.message('ESHOP_LOGISTIC_DELIVERY_CHOOSE_BTN') +
                            '</a>' +
                            '</div>';
                        geoObjects[i] = new ymaps.Placemark([item.lat, item.lon], {
                            balloonContentHeader: '<span id="'+item.code+'" data-postamat="'+item.is_postamat+'">'+item.address+'</span>',
                            balloonContentBody: contentBody,
                            balloonContentFooter: contentFooter
                        }, {
                            preset: 'islands#darkGreenDotIcon',
                        });

                    });

                    clusterer = new ymaps.Clusterer({
                        preset: 'islands#darkGreenClusterIcons',
                    }),
                        clusterer.add(geoObjects);

                    myMap.geoObjects.add(clusterer);

                    myMap.setBounds(clusterer.getBounds(), {
                        checkZoomRange: true
                    });
                } else {
                    popup = BX.PopupWindowManager.create("eslog-popup-message", null, {
                        content: BX.message('ESHOP_LOGISTIC_POPUP_TEXT'),
                        darkMode: true,
                        autoHide: true,
                        buttons: [
                            new BX.PopupWindowButton({
                                text: BX.message('ESHOP_LOGISTIC_POPUP_BTN') ,
                                className: "popup-window-button-accept" ,
                                events: {
                                    click: function(){
                                        this.popupWindow.close();
                                    },
                                    onPopupClose: function (PopupWindow) {
                                        PopupWindow.destroy();
                                    }

                                }
                            }),
                        ]
                    });

                    popup.show();
                }


                BX.removeClass(BX('eslogic-loader'), 'is-active');
            });
        },

        initYMap: function() {
            myMap = new ymaps.Map('elog_pvz_map', {
                center: [55.76, 37.64],
                zoom: 5
            }, {
                searchControlProvider: 'yandex#search'
            })
        },

        getPvz: function (e) {
            let pvzTitle;
            let choosenPvz = BX(e.dataset.code);
            pvzTitle = choosenPvz.textContent;
            BX.adjust(BX('eslogistic-description'), { text: pvzTitle});

            if(choosenPvz.dataset.postamat == 1) {
                pvzTitle += pvzTitle + ' (POSTAMAT)';
            }
            BX.adjust(BX('eslogic-pvz-value'), {props: { value: e.dataset.code+', '+pvzTitle}});
            BX.adjust(BX('eslogistic-btn-choose-pvz'), {text: BX.message('ESHOP_LOGISTIC_CHANGE_PVZ_BTN')});

            myMap.destroy();
            popup.destroy();
        }

    };
    BX.EShopLogistic.Delivery.sale_order_ajax.init();
})();