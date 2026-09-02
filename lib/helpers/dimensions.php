<?php

namespace Eshoplogistic\Delivery\Helpers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use Bitrix\Catalog;
use Bitrix\Iblock;
use Eshoplogistic\Delivery\Config;

/** Resolves a product's box dimensions (width/height/length, in cm) for delivery
 * cost calculation, using a per-axis, admin-configurable priority list of sources:
 * the standard catalog WIDTH/HEIGHT/LENGTH fields and/or iblock element properties
 * (see the "Габариты" section in options.php / lib/view/settings/dimensionfields.php).
 *
 * Each axis is tried in order; the first source with a value > 0 wins, converted to
 * cm per that source's configured unit. A blank/missing config for an axis falls back
 * to that axis's single standard field, in mm — i.e. exactly today's behaviour.
 * @author negen
 */
class Dimensions
{
    public const AXES = ['width', 'height', 'length'];
    public const STANDARD_CODES = ['width' => 'WIDTH', 'height' => 'HEIGHT', 'length' => 'LENGTH'];
    public const OPTION_CODE = 'dimension_priority';

    private static $configCache = null;

    /** @return array<string, array<int, array{source:string, code:string, unit:string}>> */
    public static function getPriorityConfig(): array
    {
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        $raw = Option::get(Config::MODULE_ID, self::OPTION_CODE, '');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        return self::$configCache = self::sanitizeConfig(is_array($decoded) ? $decoded : []);
    }

    /** Drops malformed entries and fills in any axis left empty by the admin with the
     * single matching standard field, so a blank/partial config behaves exactly like
     * the module did before this setting existed.
     */
    public static function sanitizeConfig(array $raw): array
    {
        $result = [];
        foreach (self::AXES as $axis) {
            $list = [];
            if (!empty($raw[$axis]) && is_array($raw[$axis])) {
                foreach ($raw[$axis] as $entry) {
                    if (!is_array($entry)) continue;

                    $source = $entry['source'] ?? '';
                    $source = in_array($source, ['standard', 'property'], true) ? $source : '';
                    $code = trim((string)($entry['code'] ?? ''));
                    $unit = ($entry['unit'] ?? '') === 'cm' ? 'cm' : 'mm';
                    // Только для отображения в админке (см. dimensionfields.php/settings.js) —
                    // сама Dimensions::resolveForProducts эту метку не использует.
                    $name = trim((string)($entry['name'] ?? '')) ?: $code;

                    if ($source === '' || $code === '') continue;
                    if ($source === 'standard' && !in_array($code, self::STANDARD_CODES, true)) continue;

                    $list[] = ['source' => $source, 'code' => $code, 'unit' => $unit, 'name' => $name];
                }
            }

            if (!$list) {
                $list[] = ['source' => 'standard', 'code' => self::STANDARD_CODES[$axis], 'unit' => 'mm'];
            }

            $result[$axis] = $list;
        }

        return $result;
    }

    public static function saveConfig(array $rawConfig): void
    {
        Option::set(Config::MODULE_ID, self::OPTION_CODE, Json::encode(self::sanitizeConfig($rawConfig)));
        self::$configCache = null;
    }

    /** @param int[] $productIds
     * @return array<int, array{WIDTH: ?float, HEIGHT: ?float, LENGTH: ?float}> values in cm, keyed by product id
     */
    public static function resolveForProducts(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) return [];

        $config = self::getPriorityConfig();

        $needsStandard = false;
        $neededPropertyCodes = [];
        foreach ($config as $axisList) {
            foreach ($axisList as $entry) {
                if ($entry['source'] === 'standard') {
                    $needsStandard = true;
                } else {
                    $neededPropertyCodes[$entry['code']] = true;
                }
            }
        }

        $standardByProduct = $needsStandard ? self::fetchStandardFields($productIds) : [];
        $propertiesByProduct = $neededPropertyCodes ? self::fetchPropertyValues($productIds, array_keys($neededPropertyCodes)) : [];

        $result = [];
        foreach ($productIds as $productId) {
            $values = [];
            foreach (self::AXES as $axis) {
                $value = null;
                foreach ($config[$axis] as $entry) {
                    if ($entry['source'] === 'standard') {
                        $raw = $standardByProduct[$productId][$entry['code']] ?? null;
                    } else {
                        $raw = $propertiesByProduct[$productId][$entry['code']] ?? null;
                    }
                    $raw = (float)$raw;
                    if ($raw > 0) {
                        $value = $entry['unit'] === 'cm' ? $raw : $raw / 10;
                        break;
                    }
                }
                $values[$axis] = $value;
            }

            $result[$productId] = [
                'WIDTH' => $values['width'],
                'HEIGHT' => $values['height'],
                'LENGTH' => $values['length'],
            ];
        }

        return $result;
    }

    private static function fetchStandardFields(array $productIds): array
    {
        if (!Loader::includeModule('catalog')) return [];

        $byProduct = [];
        $rs = Catalog\ProductTable::getList([
            'filter' => ['=ID' => $productIds],
            'select' => ['ID', 'WIDTH', 'HEIGHT', 'LENGTH'],
        ]);
        while ($row = $rs->fetch()) {
            $byProduct[(int)$row['ID']] = $row;
        }

        return $byProduct;
    }

    /** Iblock element properties can't be batch-read across iblocks in one query (Bitrix
     * stores single-value properties in a table per iblock for "highload" catalogs), so
     * products are grouped by their iblock first, then read one iblock at a time.
     * @return array<int, array<string, mixed>> product id => [property CODE => value]
     */
    private static function fetchPropertyValues(array $productIds, array $codes): array
    {
        if (!Loader::includeModule('iblock')) return [];

        $iblockIdByProduct = [];
        $rs = Iblock\ElementTable::getList([
            'filter' => ['=ID' => $productIds],
            'select' => ['ID', 'IBLOCK_ID'],
        ]);
        while ($row = $rs->fetch()) {
            $iblockIdByProduct[(int)$row['IBLOCK_ID']][] = (int)$row['ID'];
        }

        $result = [];
        foreach ($iblockIdByProduct as $iblockId => $idsInIblock) {
            $propIdToCode = [];
            $propRs = Iblock\PropertyTable::getList([
                'filter' => ['=IBLOCK_ID' => $iblockId, '=CODE' => $codes, '=MULTIPLE' => 'N'],
                'select' => ['ID', 'CODE'],
            ]);
            while ($prop = $propRs->fetch()) {
                $propIdToCode[(int)$prop['ID']] = $prop['CODE'];
            }
            if (!$propIdToCode) continue;

            $valuesRs = \CIBlockElement::GetPropertyValues($iblockId, ['ID' => $idsInIblock], false, ['ID' => array_keys($propIdToCode)]);
            while ($row = $valuesRs->Fetch()) {
                $elementId = (int)($row['IBLOCK_ELEMENT_ID'] ?? 0);
                if (!$elementId) continue;
                foreach ($propIdToCode as $propId => $code) {
                    if (isset($row[$propId]) && $row[$propId] !== false && $row[$propId] !== '') {
                        $result[$elementId][$code] = $row[$propId];
                    }
                }
            }
        }

        return $result;
    }

    /** Candidate iblock element properties for the settings picker: active, single-value,
     * string/number properties across every catalog (and SKU/offer) iblock in the store,
     * deduplicated by CODE — first NAME encountered wins.
     * @return array<int, array{code:string, name:string}>
     */
    public static function getCandidateProperties(): array
    {
        if (!Loader::includeModule('iblock')) return [];

        $iblockIds = self::getCatalogIblockIds();
        if (!$iblockIds) return [];

        $byCode = [];
        $rs = Iblock\PropertyTable::getList([
            'filter' => [
                '=IBLOCK_ID' => $iblockIds,
                '=ACTIVE' => 'Y',
                '=MULTIPLE' => 'N',
                '=PROPERTY_TYPE' => [Iblock\PropertyTable::TYPE_STRING, Iblock\PropertyTable::TYPE_NUMBER],
            ],
            'select' => ['CODE', 'NAME'],
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ]);
        while ($row = $rs->fetch()) {
            $code = trim((string)$row['CODE']);
            if ($code === '' || isset($byCode[$code])) continue;
            $byCode[$code] = ['code' => $code, 'name' => trim((string)$row['NAME']) !== '' ? trim((string)$row['NAME']) : $code];
        }

        return array_values($byCode);
    }

    private static function getCatalogIblockIds(): array
    {
        $ids = [];
        if (Loader::includeModule('catalog') && class_exists(Catalog\CatalogIblockTable::class)) {
            $rs = Catalog\CatalogIblockTable::getList(['select' => ['IBLOCK_ID', 'PRODUCT_IBLOCK_ID']]);
            while ($row = $rs->fetch()) {
                if ((int)$row['IBLOCK_ID']) $ids[] = (int)$row['IBLOCK_ID'];
                if ((int)$row['PRODUCT_IBLOCK_ID']) $ids[] = (int)$row['PRODUCT_IBLOCK_ID'];
            }
        }

        if ($ids) return array_values(array_unique($ids));

        // Пустой список каталогов (например, свежая установка catalog-модуля без
        // зарегистрированных инфоблоков) — деградируем до всех активных инфоблоков,
        // чтобы пикер всё равно предложил что-то выбрать; администратор ищет нужное
        // свойство поиском, лишние совпадения не мешают.
        $rs = Iblock\IblockTable::getList(['filter' => ['=ACTIVE' => 'Y'], 'select' => ['ID']]);
        while ($row = $rs->fetch()) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }
}
