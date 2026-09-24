<?php

namespace Eshoplogistic\Delivery\Helpers;

use Bitrix\Main\Localization\Loc;

class Table
{

    /**
     * @var array|mixed
     */
    private $items;

    function get_columns()
    {
        return $columns = array(
            'number' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_NUMBER"),
            'product_id' => 'ID',
            'name' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_NAME"),
            'quantity' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_QUANTITY"),
            'price' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_TOTAL"),
            'weight' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_WIGHT"),
            'width' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_WIDTH"),
            'length' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_LENGHT"),
            'height' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_HEIGHT"),
            'delete' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_DELETE"),
        );
    }

    /** Единицы измерения колонок — выводятся в заголовке после названия (", руб", ", см"). */
    function get_column_units()
    {
        return array(
            'price' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_UNIT_RUB"),
            'weight' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_UNIT_KG"),
            'width' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_UNIT_CM"),
            'length' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_UNIT_CM"),
            'height' => Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_UNIT_CM"),
        );
    }

    function prepare_items($items = array())
    {
        $this->items = $items;
    }

    /** Таблица мест — своя разметка (<table class="esl-places-table">), а не строки внутри
     * административной table.edit-table, как в остальных вкладках формы. Порт вида и разметки
     * из wp-content/plugins/eshoplogisticru/views/unloading-form.php (секция content4) —
     * тот же набор колонок/классов, чтобы кнопка "Добавить"/удаление строки/переиндексация
     * products[N][...] после удаления работали идентично (см. eslPlacesRenumber в
     * install/js/admin.js). Разметка новой (добавляемой кнопкой) строки строится в JS
     * (eslBuildPlaceRow), а не серверным <template>/скрытой table — на инсталляциях с
     * AJAX-подгрузкой вкладок браузер терял инертность вложенной разметки, и лишняя строка
     * просачивалась в живой DOM, сбивая нумерацию мест.
     */
    function display()
    {
        $records = $this->items;
        $columns = $this->get_columns();
        $units = $this->get_column_units();
        ?>
        <div class="esl-places__main">
            <button id="buttonModalUnloadAdd" type="button" class="esl-places__add"><?php
                echo Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_ADD_PLACE") ?></button>
            <div class="esl-table-scroll">
                <table class="esl-places-table">
                    <colgroup>
                        <?php foreach ($columns as $columnKey => $columnLabel): ?>
                            <col class="esl-col-<?php echo $columnKey ?>">
                        <?php endforeach; ?>
                    </colgroup>
                    <thead>
                    <tr>
                        <?php foreach ($columns as $columnKey => $columnLabel): ?>
                            <th><?php echo htmlspecialcharsbx((string)$columnLabel);
                                if (!empty($units[$columnKey])): ?><span class="esl-places-table__unit">, <?php
                                    echo htmlspecialcharsbx((string)$units[$columnKey]) ?></span><?php
                                endif ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($records)):
                        $rowIndex = 0;
                        foreach ($records as $rec):
                            if (!$rec) {
                                continue;
                            }
                            ?>
                            <tr data-number="<?php echo $rowIndex ?>">
                                <?php foreach ($columns as $columnKey => $columnLabel): ?>
                                    <?php if ($columnKey === 'delete'): ?>
                                        <td class="column-delete">
                                            <?php if ($rowIndex !== 0): ?>
                                                <button type="button" class="esl-delete_table_elem" title="<?php
                                                    echo Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_TABLE_DELETE") ?>">&times;</button>
                                            <?php endif; ?>
                                        </td>
                                    <?php elseif ($columnKey === 'number'): ?>
                                        <td class="column-number"><span class="esl-place-number"><?php
                                                echo $rowIndex + 1 ?></span></td>
                                    <?php else:
                                        $cellValue = $rec[$columnKey] ?? '';
                                        ?>
                                        <td class="column-<?php echo $columnKey ?>">
                                            <input type="text" data-field="<?php echo $columnKey ?>"
                                                   name="products[<?php echo $rowIndex ?>][<?php echo $columnKey ?>]"
                                                   value="<?php echo htmlspecialcharsbx(stripslashes((string)$cellValue)) ?>">
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                            <?php
                            $rowIndex++;
                        endforeach;
                    endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

}
