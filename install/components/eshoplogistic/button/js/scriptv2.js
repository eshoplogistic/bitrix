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

