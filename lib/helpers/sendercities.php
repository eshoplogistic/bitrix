<?php

namespace Eshoplogistic\Delivery\Helpers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json;
use Eshoplogistic\Delivery\Config;

Loc::loadMessages(__FILE__);

/** Additional sender cities (портировано из МойСклад: sender-address-pvz-multi).
 * The admin lists cities in the module options ("Города отправки"), then picks one of them
 * in a delivery profile's "Город-отправитель" parameter. Two profiles of the same carrier
 * with different sender cities show both prices at checkout and the buyer picks one.
 *
 * A profile left at "По умолчанию" keeps calculating from the city set on the key in the
 * eShopLogistic account — `from` is not sent to the API at all, exactly as before.
 * @author negen
 */
class SenderCities
{
    public const OPTION_CODE = 'sender_cities';
    public const CONFIG_SECTION = 'SENDER';
    public const CONFIG_ITEM = 'CITY';

    private static $listCache = null;

    /** @return array<int, array{fias:string, name:string, region:string}> */
    public static function getList(): array
    {
        if (self::$listCache !== null) {
            return self::$listCache;
        }

        $raw = Option::get(Config::MODULE_ID, self::OPTION_CODE, '');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        return self::$listCache = self::sanitizeList(is_array($decoded) ? $decoded : []);
    }

    public static function sanitizeList(array $raw): array
    {
        $result = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) continue;

            $fias = trim((string)($entry['fias'] ?? ''));
            $name = trim((string)($entry['name'] ?? ''));
            if ($fias === '' || $name === '' || isset($result[$fias])) continue;

            $result[$fias] = [
                'fias' => $fias,
                'name' => $name,
                'region' => trim((string)($entry['region'] ?? '')),
            ];
        }

        return array_values($result);
    }

    public static function saveList(array $rawList): void
    {
        Option::set(Config::MODULE_ID, self::OPTION_CODE, Json::encode(self::sanitizeList($rawList)));
        self::$listCache = null;
    }

    /** @return array{fias:string, name:string, region:string}|null */
    public static function getByFias($fias): ?array
    {
        if (!$fias) return null;

        foreach (self::getList() as $city) {
            if ($city['fias'] === $fias) {
                return $city;
            }
        }

        return null;
    }

    /** "Город-отправитель" section of a delivery profile's config structure.
     * @param array|null $configValues current config of the profile: a city since removed from
     *        the module options stays among the options (marked), otherwise Bitrix rejects
     *        re-saving such a profile with a validation error
     * @return array
     */
    public static function getConfigStructure($configValues = null): array
    {
        $options = ['' => Loc::getMessage('ESHOP_LOGISTIC_SENDER_CITIES_DEFAULT')];
        foreach (self::getList() as $city) {
            $options[$city['fias']] = $city['region'] !== ''
                ? $city['name'] . ' (' . $city['region'] . ')'
                : $city['name'];
        }

        $current = is_array($configValues) ? (string)($configValues[self::CONFIG_SECTION][self::CONFIG_ITEM] ?? '') : '';
        if ($current !== '' && !isset($options[$current])) {
            $options[$current] = Loc::getMessage('ESHOP_LOGISTIC_SENDER_CITIES_REMOVED');
        }

        return [
            self::CONFIG_SECTION => [
                'TITLE' => Loc::getMessage('ESHOP_LOGISTIC_SENDER_CITIES_SECTION'),
                'DESCRIPTION' => Loc::getMessage('ESHOP_LOGISTIC_SENDER_CITIES_SECTION_DESC'),
                'ITEMS' => [
                    self::CONFIG_ITEM => [
                        'TYPE' => 'ENUM',
                        'NAME' => Loc::getMessage('ESHOP_LOGISTIC_SENDER_CITIES_FIELD'),
                        'OPTIONS' => $options,
                        'DEFAULT' => '',
                    ],
                ],
            ],
        ];
    }

    /** Sender city chosen in a profile's config. A city removed from the module options
     * falls back to the account's city instead of quietly calculating from a stale one.
     * @param array|null $configValues Sale\Delivery\Services\Base::getConfigValues()
     * @return array{fias:string, name:string, region:string}|null
     */
    public static function getFromConfig($configValues): ?array
    {
        $fias = is_array($configValues) ? ($configValues[self::CONFIG_SECTION][self::CONFIG_ITEM] ?? '') : '';

        return self::getByFias((string)$fias);
    }

    /** Sender city of a delivery service by its ID (for already placed orders, e.g. export form).
     * @param int $deliveryId
     * @return array{fias:string, name:string, region:string}|null
     */
    public static function getByDeliveryId($deliveryId): ?array
    {
        if (!$deliveryId) return null;

        try {
            $service = \Bitrix\Sale\Delivery\Services\Manager::getObjectById((int)$deliveryId);
        } catch (\Exception $e) {
            return null;
        }

        return $service ? self::getFromConfig($service->getConfigValues()) : null;
    }
}
