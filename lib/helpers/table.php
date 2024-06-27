<?php

namespace Eshoplogistic\Delivery\Helpers;

use Bitrix\Main\Localization\Loc;

class Table
{

    /**
     * @var array|mixed
     */
    private $items;

    function extra_tablenav()
    {
        echo '<input id="buttonModalUnloadAdd" type="button" class="button button-primary" value="Добавить место">';
    }

    function get_columns()
    {
        return $columns = array(
            'product_id' => 'ID',
            'name' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_NAME"),
            'quantity' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_QUANTITY"),
            'total' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_TOTAL"),
            'weight' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_WIGHT"),
            'width' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_WIDTH"),
            'length' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_LENGHT"),
            'height' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_HEIGHT"),
            'delete' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_DELETE"),
        );
    }

    public function get_sortable_columns()
    {
        return $sortable = array(
            'col_link_id' => 'link_id',
            'col_link_name' => 'link_name',
            'col_link_count' => 'link_price'
        );
    }

    function prepare_items($items = array())
    {
        $this->items = $items;
    }

    function display()
    {

        $records = $this->items;
        $columns = $this->get_columns();

        echo '<thead><tr>';
        foreach ($columns as $column_name => $column_display_name) {
            echo '<th>'.$column_display_name.'</th>';
        }
        echo '</tr></thead>';

        echo '<tbody class="mainTbody">';
        if (!empty($records)) {
            $i = 0;
            foreach ($records as $key => $rec) {
                if (!$rec)
                    continue;

                echo '<tr id="record_' . $rec['product_id'] . '" class="esl_tr_style">';
                foreach ($columns as $column_name => $column_display_name) {

                    $class = "class='column-$column_name' name='$column_name'";
                    $style = "";

                    $attributes = $class . $style;

                    if($column_name == 'delete'){
                        if($i != 0){
                            echo '<td ' . $attributes . '><div class="esl-delete_table_elem">&#65794;</div></td>';
                        }
                    }else{
                        echo '<td ' . $attributes . '><input type="text" data-count="' . $i . '" name="products[' . $i . '][' . $column_name . ']" value="' . stripslashes($rec[$column_name]) . '"/></td>';
                    }
                }

                echo '</tr>';
                $i++;
            }
        }
        echo '</tbody>';

        $this->extra_tablenav();
    }

}