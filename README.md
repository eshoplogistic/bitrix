# eshoplogistic.delivery

Модуль доставки для «1С-Битрикс: Управление сайтом», подключающий к модулю `sale` расчёт стоимости и сроков доставки через несколько транспортных компаний в рамках единой интеграции с сервисом [eShopLogistic](https://eshoplogistic.ru).

## Возможности

- Расчёт стоимости и сроков доставки «до двери» и «до пункта выдачи» одновременно по нескольким ТК: СДЭК, DPD, Почта России, IML, Деловые Линии, ПЭК, Dostavista, PickPoint, 5Post, Яндекс.Доставка, СберЛогистика, Байкал Сервис, GTD, Vozovoz, Grastin, Halva, Kit, Logsis, Magnit, Energija, Zde и др. (см. `lib/profile/`).
- Виджет и кнопка выбора пункта выдачи заказа (ПВЗ) на карте — компоненты `eshoplogistic:widget_easy` и `eshoplogistic:button`.
- Автоматическое определение города покупателя и сравнение соответствия городов (`lib/helpers/comparisoncities.php`, `lib/helpers/locationhandler.php`).
- Хранение выбранного ПВЗ, полного адреса и данных ТК в свойствах заказа (`ESHOPLOGISTIC_PVZ`, `ESHOPLOGISTIC_FULL_ADDRESS`, `ESHOPLOGISTIC_SHIPPING_METHODS`, `ESHOPLOGISTIC_CHOSE_FRAME`).
- Выгрузка заказов в ТК и работа со статусами отправлений: создание, проверка, обновление и очистка статуса, печать документов (`lib/view/unloading/`).
- Дополнительные услуги и настраиваемые габариты/размеры отправлений (`lib/view/settings/`).
- Фоновые агенты: очистка кеша тарифов (`Agent\CacheHandler`) и периодическое обновление статусов выгрузки (`Agent\UnloadingHandler`).
- AJAX-контроллер (`Controller\AjaxHandler`) для получения списка ПВЗ, города по умолчанию и данных виджета на фронте.
- Логирование обращений к API служб доставки (`lib/logger/logger.php`).

## Требования

- «1С-Битрикс: Управление сайтом», модуль `main` версии не ниже `16.00.28`.
- Установленный и активный модуль `sale`.
- Доступ в интернет с сервера к API eShopLogistic и API подключаемых транспортных компаний.

## Установка

1. Скопировать модуль в `/bitrix/modules/eshoplogistic.delivery/`.
2. В административной панели: **Настройки → Настройки продукта → Модули** → найти «Расчет доставки: Почта, СДЭК, DPD, Ozon Rocket, PickPoint, IML, Деловые Линии, ПЭК и др.» → **Установить**.
3. При установке модуль:
   - создаёт свойства заказа, перечисленные выше;
   - регистрирует обработчики событий модуля `sale` (расчёт способов доставки, обработка свойств заказа, письма с данными о доставке) и модуля `main` (пункт контекстного меню в карточке заказа);
   - копирует компоненты, JS/CSS и административные страницы (`InstallFiles`);
   - регистрирует агенты очистки кеша и обновления статусов выгрузки.
4. Настроить учётные данные и параметры интеграции на странице настроек модуля (`options.php`): API-ключи транспортных компаний, город отправления, габариты по умолчанию, кеширование тарифов и т. д.
5. В настройках способов доставки заказа (**Магазин → Настройки → Способы доставки**) добавить и настроить нужные профили ТК (двери/терминалы) из `lib/profile/`.

## Структура модуля

```
install/            установочные файлы: компоненты, JS/CSS, административные страницы
lang/ru/            локализация (русский)
lib/
  agent/            фоновые агенты (кеш, обновление статусов)
  api/               клиенты для обращения к API ТК и сервиса eShopLogistic
  controller/        AJAX-контроллер для фронтенда
  engine/            инициализация служб доставки в модуле sale
  event/             обработчики событий sale/main
  helpers/           вспомогательная логика: адреса, габариты, локации, экспорт и др.
  logger/            логирование запросов к внешним API
  profile/           профили служб доставки (дверь/терминал) для каждой ТК
  view/settings/     административные страницы настроек (доп. услуги, габариты, поиск терминалов)
  view/unloading/    административные страницы выгрузки заказов в ТК
options.php          страница настроек модуля в админке
```

## Расширение через события

Модуль публикует собственные события, которые позволяют разработчику скорректировать данные заказа перед их отправкой во внешний API — без правки кода самого модуля. Подписка оформляется как на обычное событие Битрикс, через `EventManager::addEventHandler()`, обычно в `init.php` кастомного/локального модуля проекта.

Идентификатор модуля и имена событий заданы константами `Eshoplogistic\Delivery\Config::MODULE_ID`, `Config::EVENT_BEFORE_CALCULATE` и `Config::EVENT_BEFORE_EXPORT`.

### `onBeforeCalculate` — перед расчётом стоимости/сроков доставки

Вызывается в `CalculateHandler::getDefaultCalculateDelivery()` перед обращением к API ТК за расчётом тарифа, когда состав заказа и адрес получения уже сформированы модулем.

Регистрация обработчика:

```php
use Bitrix\Main\EventManager;
use Eshoplogistic\Delivery\Config;

EventManager::getInstance()->addEventHandler(
    Config::MODULE_ID,
    Config::EVENT_BEFORE_CALCULATE,
    ['MyDeliveryHandlers', 'onBeforeCalculate']
);
```

Обработчик:

```php
use Bitrix\Main\EventResult;

class MyDeliveryHandlers
{
    public static function onBeforeCalculate(\Bitrix\Main\Event $event): EventResult
    {
        $params = $event->getParameters();
        $order = $params['order'];
        $to = $params['to'];
        $orderData = $params['orderData'];

        // Пример: если в заказе есть тяжёлый товар, скорректировать вес в запросе к ТК
        $orderData['offers'][0]['weight'] = 25000; // граммы

        return new EventResult(
            EventResult::SUCCESS,
            [
                'to' => $to,               // адрес/пункт назначения расчёта
                'orderData' => $orderData, // состав заказа, отправляемый в запрос расчёта
            ]
        );
    }
}
```

Изменить результат расчёта можно, только вернув `EventResult::SUCCESS` с параметрами `to` и/или `orderData` — именно эти два значения модуль подхватит и использует вместо своих. Любые другие ключи в результате игнорируются. Если ни один обработчик не вернул `SUCCESS`, данные уходят в API без изменений.

### `onBeforeExport` — перед выгрузкой заказа в ТК

Вызывается в `Unloading::prepareFields()` (`lib/event/unloading.php`) перед отправкой сформированного запроса на создание отправления — когда все поля (`places`, `receiver`, `sender`, `delivery` и т. д.) уже собраны из формы выгрузки и настроек модуля.

Регистрация обработчика:

```php
use Bitrix\Main\EventManager;
use Eshoplogistic\Delivery\Config;

EventManager::getInstance()->addEventHandler(
    Config::MODULE_ID,
    Config::EVENT_BEFORE_EXPORT,
    ['MyDeliveryHandlers', 'onBeforeExport']
);
```

Обработчик:

```php
use Bitrix\Main\EventResult;

class MyDeliveryHandlers
{
    public static function onBeforeExport(\Bitrix\Main\Event $event): EventResult
    {
        $params = $event->getParameters();
        $order = $params['order'];
        $fields = $params['fields'];

        // Пример: если у ТК нет настройки комментария, взять его из заказа
        if (empty($fields['order']['comment']) && $order) {
            $fields['order']['comment'] = $order->getField('USER_DESCRIPTION');
        }

        return new EventResult(EventResult::SUCCESS, ['fields' => $fields]);
    }
}
```
Чтобы изменить итоговый запрос, верните `EventResult::SUCCESS` с параметром `fields`, содержащим полный (не частичный) массив данных для выгрузки — модуль полностью заменит `fields` на то, что вы вернули. Изменения полей `fields` (кроме `key`) попадают в лог модуля (**Настройки → Журнал событий** или встроенный логгер модуля) с пометкой `EVENT_BEFORE_EXPORT`, что удобно для отладки обработчика.

### Общие замечания

- Обработчики можно регистрировать сразу для нескольких событий и подключать через `init.php` любого модуля проекта (в т. ч. `main` через `local/php_interface/init.php`).
- Если обработчик не должен ничего менять — просто не подписывайтесь на событие или возвращайте `EventResult::ERROR`/`EventResult::UNDEFINED`, тогда его результат будет проигнорирован модулем.
- Оба события — синхронные, вызываются в момент запроса пользователя (расчёт доставки в корзине/оформлении заказа или выгрузка заказа администратором), поэтому не стоит выполнять в обработчиках длительные операции — это увеличит время ответа.

## Автор

[eShopLogistic](https://eshoplogistic.ru)
