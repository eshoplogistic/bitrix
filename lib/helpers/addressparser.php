<?php

namespace Eshoplogistic\Delivery\Helpers;

use Bitrix\Main\Localization\Loc;

/**
 * Разбор произвольных строк адреса (поле "Адрес" заказа Bitrix) на улицу/дом/квартиру/район.
 * Покупатели пишут туда что угодно, в любом порядке, с запятыми или без, поэтому парсер работает
 * по принципу "нашли уверенный признак — заполнили поле, не нашли — оставили пустым", а не
 * пытается угадать во что бы то ни стало.
 */
class AddressParser
{
    // Словари шаблонов (улица/дом/квартира/район, префиксы населённых пунктов, номер дома)
    // лежат в lang/ru/lib/helpers/addressparser.php, а не константами здесь: пакет модуля
    // в cp1251, и Маркетплейс перекодирует под UTF-8 только языковые файлы — кириллица
    // в регулярке вне lang/ на UTF-8 сайте ломает preg_* с модификатором /u (null).
    // Язык фиксирован 'ru': разбираются российские адреса при любом языке админки.
    private const LABEL_TYPES = array('district', 'room', 'extra', 'building', 'street');

    private static $patterns = array();

    private static function pattern(string $name): string
    {
        if (!isset(self::$patterns[$name])) {
            self::$patterns[$name] = (string)Loc::getMessage('ESHOP_LOGISTIC_ADDRESS_PARSER_' . strtoupper($name), null, 'ru');
        }

        return self::$patterns[$name];
    }

    public static function parse(string $address1, string $address2 = '', string $knownCity = '', string $knownRegion = ''): array
    {
        $result = array(
            'street' => '',
            'building' => '',
            'room' => '',
            'district' => '',
        );

        $text = trim($address1 . "\n" . $address2);
        if ($text === '') {
            return $result;
        }

        $labelMatches = self::findLabelMatches($text);

        if (!$labelMatches) {
            foreach (self::dropKnownLocation(self::splitSegments($text), $knownCity, $knownRegion) as $segment) {
                self::resolveUnlabeled($segment, $result);
            }

            return self::trimResult($result);
        }

        $leading = trim(substr($text, 0, $labelMatches[0]['start']));
        $leadingSegments = self::dropKnownLocation(self::splitSegments($leading), $knownCity, $knownRegion);
        foreach ($leadingSegments as $segment) {
            self::resolveUnlabeled($segment, $result);
        }

        foreach ($labelMatches as $i => $match) {
            $end = isset($labelMatches[$i + 1]) ? $labelMatches[$i + 1]['start'] : strlen($text);
            $span = substr($text, $match['valueStart'], $end - $match['valueStart']);
            list($value, $tail) = self::splitHeadTail($span);

            if ($value !== '') {
                switch ($match['type']) {
                    case 'district':
                        if ($result['district'] === '') {
                            $result['district'] = $value;
                        }
                        break;
                    case 'room':
                        if ($result['room'] === '') {
                            $result['room'] = $value;
                        }
                        break;
                    case 'building':
                        $result['building'] = self::appendBuilding($result['building'], $value);
                        break;
                    case 'extra':
                        // "корп."/"стр."/"литер" и т.п. - сохраняем саму метку рядом со значением
                        // ("корп. 1"), иначе после склейки с домом остаётся голая цифра ("15, 1"),
                        // непонятно к чему относящаяся.
                        $result['building'] = self::appendBuilding($result['building'], trim($match['label']) . ' ' . $value);
                        break;
                    case 'street':
                        list($name, $house) = self::splitTrailingHouseNumber($value);
                        if ($result['street'] === '') {
                            $result['street'] = $name;
                        }
                        if ($house !== null && $result['building'] === '') {
                            $result['building'] = $house;
                        }
                        break;
                }
            }

            // То, что осталось после первой запятой в этом же куске (например "10" в
            // "ул. Ленина, 10" без метки "д.") - не относится к текущей метке, но может
            // быть домом/районом и т.п. без метки, поэтому прогоняем через тот же разбор.
            if ($tail !== '') {
                foreach (self::dropKnownLocation(self::splitSegments($tail), $knownCity, $knownRegion) as $segment) {
                    self::resolveUnlabeled($segment, $result);
                }
            }
        }

        return self::trimResult($result);
    }

    private static function findLabelMatches(string $text): array
    {
        $pattern = '/';
        $parts = array();
        foreach (self::LABEL_TYPES as $type) {
            $parts[] = '(?<![\p{L}])(?P<' . $type . '>' . self::pattern($type) . ')(?![\p{L}])';
        }
        $pattern .= implode('|', $parts) . '/iu';

        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return array();
        }

        $result = array();
        foreach ($matches as $matchSet) {
            foreach (self::LABEL_TYPES as $type) {
                if (isset($matchSet[$type]) && $matchSet[$type][1] !== -1) {
                    $result[] = array(
                        'type' => $type,
                        'label' => $matchSet[$type][0],
                        'start' => $matchSet[$type][1],
                        'valueStart' => $matchSet[$type][1] + strlen($matchSet[$type][0]),
                    );
                    break;
                }
            }
        }

        usort($result, static function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        return $result;
    }

    private static function splitHeadTail(string $span): array
    {
        $parts = preg_split('/[,;\n]/u', $span, 2);

        $head = trim($parts[0], " \t\n\r\0\x0B.");
        $tail = isset($parts[1]) ? trim($parts[1]) : '';

        return array($head, $tail);
    }

    private static function resolveUnlabeled(string $segment, array &$result): void
    {
        if ($segment === '') {
            return;
        }

        if (preg_match('/^' . self::pattern('location_prefix') . '(?![\p{L}])\s*\S/iu', $segment)) {
            return;
        }

        list($name, $house) = self::splitTrailingHouseNumber($segment);

        if ($name !== '' && $house !== null) {
            if ($result['street'] === '') {
                $result['street'] = $name;
            }
            if ($result['building'] === '') {
                $result['building'] = $house;
            }
            return;
        }

        if (preg_match('/^' . self::pattern('house_number') . '$/iu', $segment)) {
            if ($result['street'] !== '' && $result['building'] === '') {
                $result['building'] = $segment;
            }
            return;
        }

        // Ограничение по числу слов отсекает случайные свободные комментарии без адреса
        // ("просто текст без адреса вообще") - настоящие названия улиц короче.
        // str_word_count() тут не подходит - он не считает кириллицу словами.
        if ($result['street'] === '' && count(preg_split('/\s+/u', trim($segment))) <= 4
            && preg_match('/^[\p{L}][\p{L}\.\-\s]*$/u', $segment)
        ) {
            $result['street'] = $segment;
        }

        // Ничего не подошло (мусор вроде "12.15 555 ПРИМЕР ТЕСТА") - оставляем как есть,
        // не вставляем предположения в поля заявки.
    }

    private static function splitSegments(string $text): array
    {
        $parts = preg_split('/[,;\n]+/u', $text);
        if ($parts === false) {
            return array();
        }

        $parts = array_map('trim', $parts);

        return array_values(array_filter($parts, static function ($segment) {
            return $segment !== '';
        }));
    }

    private static function dropKnownLocation(array $segments, string $knownCity, string $knownRegion): array
    {
        $known = array_filter(array(
            self::normalizeLocation($knownCity),
            self::normalizeLocation($knownRegion),
        ), static function ($value) {
            return $value !== '';
        });

        if (!$known) {
            return $segments;
        }

        return array_values(array_filter($segments, static function ($segment) use ($known) {
            return !in_array(self::normalizeLocation($segment), $known, true);
        }));
    }

    private static function normalizeLocation(string $value): string
    {
        $value = preg_replace('/^' . self::pattern('location_prefix') . '(?![\p{L}])\s*/iu', '', trim($value));

        return mb_strtolower(trim((string) $value));
    }

    private static function splitTrailingHouseNumber(string $text): array
    {
        $text = trim($text);

        if (!preg_match('/^(.+?)\s+(' . self::pattern('house_number') . ')$/iu', $text, $matches)) {
            return array($text, null);
        }

        $name = trim($matches[1]);
        if ($name === '' || !preg_match('/\p{L}/u', $name)) {
            return array($text, null);
        }

        return array($name, $matches[2]);
    }

    private static function appendBuilding(string $existing, string $value): string
    {
        if ($existing === '') {
            return $value;
        }

        return $existing . ', ' . $value;
    }

    private static function trimResult(array $result): array
    {
        foreach ($result as $key => $value) {
            $result[$key] = trim($value);
        }

        return $result;
    }
}
