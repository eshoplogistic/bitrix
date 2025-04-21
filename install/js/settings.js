BX.ready(function() {

    sortable('.sortable', {
        connectWith: 'js-connected',
    });
    sortable('.sortable-copy', {
        copy: true,
        connectWith: 'js-connected'
    });

    let lenghtSort = document.getElementsByClassName('sortable')
    for (let i = 0; i < lenghtSort.length; i++) {
        sortable('.sortable')[i].addEventListener('sortupdate', function(e) {
            saveStatusForm()
        })
    }

    function saveStatusForm(){
        let inputSave = document.getElementsByName("status-form")[0]

        if(!inputSave)
            return false;

        let form = sortable('.sortable', 'serialize')
        let length = form.length-1,
            element = null,
            elementParent,
            elementParentName,
            elementLength,
            elementItems,
            result = {}

        for (let i = 0; i <= length; i++) {
            let item,
                itemName,
                itemDesc

            element = form[i]
            elementParent = element.container.node
            elementParentName = elementParent.getAttribute("name")
            elementItems = element.items
            elementLength = elementItems.length
            result[elementParentName] = []

            for (let i = 0; i < elementLength; i++) {
                item = elementItems[i].node
                itemName = item.getAttribute("name")
                itemDesc = item.getAttribute("data-desc")
                result[elementParentName][i] = {'name': itemName, 'desc': itemDesc}
            }
        }

        inputSave.value = JSON.stringify(result)

    }
});

