var array_data;
document.addEventListener("DOMContentLoaded", function () {

    BX.addCustomEvent('onCatalogStoreProductChange', function (changeID){
        $('.esl-button_data').attr("data-article", changeID);
        var element_id = $('.esl-button_data').data("id");
        var price = '';
        $.get("/bitrix/components/eshoplogistic/button/ajax.php?type=get_offers_array&element_id=" + element_id, function (array_data_l) {
            if (typeof (array_data_l) == "string") {
                try {
                    array_data = JSON.parse(array_data_l);
                    if(array_data['offers']['offers'][changeID]['price']){
                        price = array_data['offers']['offers'][changeID]['price'];
                    }
                    $('.esl-button_data').attr("data-article", changeID);
                    if(price){
                        $('.esl-button_data').attr("data-price", price);
                    }
                } catch (e) {
                    console.log(e);
                    return false;
                }
            } else {
                array_data = array_data_l;
            }
        })
    });
})

document.addEventListener("DOMContentLoaded", function () {
    if(document.getElementById('eShopLogisticStatic')){
        initJsEsl('static')
    }
    if(document.getElementById('eShopLogisticApp')){
        initJsEsl('modal')
    }
}, false);

function initJsEsl(type = 'modal'){
    let css = ['https://api.eshoplogistic.ru/widget/'+type+'/v1/css/app.css'],
        js = ['https://api.eshoplogistic.ru/widget/'+type+'/v1/js/chunk-vendors.js','https://api.eshoplogistic.ru/widget/'+type+'/v1/js/app.js'];

    for(const path of css){
        let style = document.createElement('link');
        style.rel="stylesheet"
        style.href = path
        document.body.appendChild(style)
    }
    for(const path of js){
        let script = document.createElement('script');
        script.src = path
        document.body.appendChild(script)
    }
}
