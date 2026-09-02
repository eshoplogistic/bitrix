<?php
namespace Eshoplogistic\Delivery\Logger;

use Bitrix\Main\Config\Option;
use Eshoplogistic\Delivery\Config;

/** Логирование модуля поверх стандартного журнала событий Bitrix (b_event_log).
 * Пишет через CEventLog::Add, поэтому записи видны в админке без доступа
 * к файловой системе: Настройки -> Инструменты -> Журнал событий,
 * фильтр "Модуль" = eshoplogistic.delivery.
 *
 * Class Logger
 * @package Eshoplogistic\Delivery\Logger
 */
class Logger
{
    public static function isEnabled(): bool
    {
        return Option::get(Config::MODULE_ID, 'api_log') === 'Y';
    }

    /**
     * @param string $auditType короткий код источника записи, например 'API_REQUEST'
     * @param mixed $description строка либо массив/объект (будет сохранён как JSON)
     * @param string $severity одна из \CEventLog::SEVERITY_*
     * @param int|string|false $itemId например ID заказа, к которому относится запись
     */
    public static function log(string $auditType, $description, string $severity = \CEventLog::SEVERITY_INFO, $itemId = false): void
    {
        if (!self::isEnabled()) {
            return;
        }

        \CEventLog::Add([
            'SEVERITY' => $severity,
            'AUDIT_TYPE_ID' => $auditType,
            'MODULE_ID' => Config::MODULE_ID,
            'ITEM_ID' => $itemId,
            'DESCRIPTION' => $description,
        ]);
    }

    /**
     * @param string $auditType
     * @param mixed $description
     * @param int|string|false $itemId
     */
    public static function error(string $auditType, $description, $itemId = false): void
    {
        self::log($auditType, $description, \CEventLog::SEVERITY_ERROR, $itemId);
    }

    /** Форматирует данные для читаемого отображения в списке "Журнал событий".
     * Массивы/объекты выводятся как JSON с отступами.
     *
     * Список сам прогоняет DESCRIPTION через htmlspecialchars и затем вручную
     * "распаковывает" обратно ТОЛЬКО тег <br> (см. bitrix/modules/main/admin/
     * event_log.php — там явный regex именно и только под него). Поэтому здесь
     * нельзя ни экранировать текст самим (будет задвоение), ни использовать
     * любую разметку кроме <br>: переносы строк заменяем на него, а отступы —
     * на настоящий символ неразрывного пробела (U+00A0), а не HTML-сущность
     * &nbsp;, которая точно так же "не переживёт" вывод, как любой другой тег.
     *
     * @param mixed $data
     */
    public static function pretty($data): string
    {
        $text = is_string($data)
            ? $data
            : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $text = preg_replace_callback('/^ +/m', static function ($m) {
            return str_repeat("\xC2\xA0", strlen($m[0]));
        }, $text);

        return str_replace("\n", '<br>', $text);
    }
}
