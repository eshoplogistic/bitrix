BX.ready(function() {
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
            console.log(tbodyTdArray)
            tbodyTdArray.forEach(element => {
                let td = document.createElement('td');
                let input = document.createElement('input');
                input.name = 'products[' + tbodyTrArrayCount + '][' + element.getAttribute('name') + ']';
                input.type = 'text';
                td.appendChild(input);
                tr.appendChild(td);
            });
            table.querySelector('.mainTbody').appendChild(tr);
        });
    }
});