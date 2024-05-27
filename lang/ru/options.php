<?
$MESS["ESHOP_LOGISTIC_OPTIONS_TAB_NAME"] = "Настройки модуля";
$MESS["ESHOP_LOGISTIC_OPTIONS_TITLE_NAME"] = "Общие настройки";
$MESS["ESHOP_LOGISTIC_OPTIONS_CLEAR_CACHE_BTN"] = "Очистить кэш";
$MESS["ESHOP_LOGISTIC_AUTH_STATUS"] = "Статус: #BLOCKED#, баланс: #BALANSE#, количество дней до блокировки: #PAID_DAYS#";
$MESS["ESHOP_LOGISTIC_CURRENT_CITY"] = "Город отправления: <strong>#CITY#</strong></br>. В случае изменения настроек в личном кабинете eShopLogistic требуется сбросить кеш модуля";
$MESS["ESHOP_LOGISTIC_CURRENT_CITY_EMPTY"] = "Город отправления не определен";
$MESS["ESHOP_LOGISTIC_CURRENT_CITY_V2"] = "В случае изменения настроек в личном кабинете eShopLogistic требуется сбросить кеш модуля";
$MESS["ESHOP_LOGISTIC_OPTIONS_ACTIVE"] = "активен";
$MESS["ESHOP_LOGISTIC_OPTIONS_BLOCKED"] = "заблокирован";
$MESS["ESHOP_LOGISTIC_UNAUTHORIZED"] = "Авторизация не выполнена";
$MESS["ESHOP_LOGISTIC_OPTIONS_API_KEY"] = "Ключ API";
$MESS["ESHOP_LOGISTIC_OPTIONS_API_LOG"] = "Включить логирование запросов к API";
$MESS["ESHOP_LOGISTIC_OPTIONS_FRAME_LIB"] = "Режим отображения \"Модальное окно\"";
$MESS["ESHOP_LOGISTIC_OPTIONS_WIDGET_KEY"] = "Ключ widget";
$MESS["ESHOP_LOGISTIC_OPTIONS_REQUARY_PVZ"] = "Отключить проверку поля с адресом ПВЗ";
$MESS["ESHOP_LOGISTIC_OPTIONS_REQUARY_PVZ_ADDRESS"] = "Сохранять адрес ПВЗ в качестве адреса доставки";
$MESS["ESHOP_LOGISTIC_OPTIONS_API_YAMAP_KEY"] = "Ключ API для Яндекс карты";
$MESS["ESHOP_LOGISTIC_OPTIONS_API_YAMAP_KEY_DESC"] = "Ключ API для Яндекс карты - необязательный параметр, может использоваться для получения статистики использования карты в личном кабинете Яндекс. Для работы поиска на Яндекс-карте при выборе ПВЗ, необходимо указать API-ключ Яндекса.";
$MESS["ESHOP_LOGISTIC_OPTIONS_PAYMENT_DESCRIPTION"] = "Выберите способы оплаты Bitrix, соответствующие указанным ниже способам оплаты eShopLogistic:";
$MESS["ESHOP_LOGISTIC_OPTIONS_PAYMENT_CARD"] = "Вариант оплаты \"Оплата по карте\"";
$MESS["ESHOP_LOGISTIC_OPTIONS_PAYMENT_CACHE"] = "Вариант оплаты \"Наличные\"";
$MESS["ESHOP_LOGISTIC_OPTIONS_PAYMENT_CASHLESS"] = "Вариант оплаты \"Безналичный расчет\"";
$MESS["ESHOP_LOGISTIC_OPTIONS_PAYMENT_PREPAY"] = "Вариант оплаты \"Предоплата\"";
$MESS["ESHOP_LOGISTIC_OPTIONS_PAYMENT_RECEIPT"] = "Вариант оплаты \"При получении\"";
$MESS["ESHOP_LOGISTIC_OPTIONS_INPUT_APPLY"] = "Применить";
$MESS["ESHOP_LOGISTIC_OPTIONS_API_V2"] = "Включить новую версию API";

$MESS["ESHOP_LOGISTIC_OPTIONS_TAB2_NAME"] = "Описание установки";

$MESS['ESHOP_LOGISTIC_OPTIONS_INSTALL_TITLE'] = 'Настройка модуля';
$MESS['ESHOP_LOGISTIC_OPTIONS_INSTALL_DESC'] = "После установки модуля необходимо перейти на страницу настроек модуля «Настройки / Настройки модулей / Калькулятор доставки eShopLogistic» после чего ввести данные для авторизации (Ключ API), который берется в личном кабинете eShopLogistic.</br>
В случае успешной авторизации будет выведен статус и текущий баланс учетной записи. 
Ключ API для Яндекс карты является не обязательным параметром, однако может быть использован для получения статистики использования карты в личном кабинете Яндекс.</br>
Режим отображения «Модальное окно» - новый тип отображения списка доступных служб доставки. Для активации, переключите параметр в настройках модуля.
Выберите способы оплаты Bitrix, соответствующие способам оплаты eShopLogistic: «Оплата по карте», «Наличные», «Безналичный расчет», «Предоплата». (Названия типов оплат можно сменить в ЛК EshopLogistic)
";

$MESS['ESHOP_LOGISTIC_OPTIONS_SETTING_TITLE'] = 'Настройка службы доставки';
$MESS['ESHOP_LOGISTIC_OPTIONS_SETTING_DESC'] = "После установи модуля станет доступна для создания служба доставки «Калькулятор доставки eShopLogistic».</br>
Для этого необходимо перейти в меню сайта «Магазин / Службы доставки» и в открывшемся окне в верхнем правом углу в выпадающем меню кнопки «Добавить» выбрать службу доставки «Калькулятор доставки eShopLogistic». </br>
После сохранения доставки появится вкладка «Профили». В данной вкладке при нажатии на кнопку «Добавить профиль» появится список доступных служб доставки. Для вывода <span style='color: red'>нужных</span> служб доставки на странице оформления заказа необходимо установить соответствующие профили. </n>
При установке модуля создается поле заказа «eShopLogistic ПВЗ» в котором сохраняется выбранный ПВЗ. Для добавления параметра ПВЗ в почтовый шаблон, используйте данный символьный код: #ESHOPLOGISTIC_PVZ#.</br>
Для расчёта доставки сразу при загрузке корзины, нужно включить режим правки и нажать на редактирование поля оформления заказа (Редактировать параметры компонента). В появившемся окне найти поле: 'Когда рассчитывать доставки с внешними системами расчета' и выбрать в выпадающем списке значение: 'Рассчитывать сразу'.</br>
Для скрытия отображение доставок с ошибками расчета, отредактируете параметры компонента (включите режим правки, на странице оформления заказа и выберите редактировать параметры компонента). Найдите параметр отображение доставок с ошибками расчета и выберете поле: 'не показывать'.
";

$MESS['ESHOP_LOGISTIC_OPTIONS_MOMENTS_TITLE'] = 'Важные моменты';
$MESS['ESHOP_LOGISTIC_OPTIONS_MOMENTS_DESC'] = "В работе модуля используется кэширование результатов расчёта стоимости и сроков, поэтому в случае, если вы произвели какие-то изменения в личном кабинете eShopLogistic, необходимо очистить кэш.</br>
 Настройки / Настройки продукта / Настройки модулей / Калькулятор доставки eShopLogistic: кнопка «Очистить кэш».</br>
 Для корректной работы виджета в режиме «Модальное окно», настройте службы доставки из пункта выше и обязательно добавьте ключ виджета в соответсвующие поле (Ключ widget).
 </br></br>";

$MESS["ESHOP_LOGISTIC_OPTIONS_UNLOADING_TITLE"] = "Настройки выгрузки заказов";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_SDEK"] = "Код терминала (СДЭК)";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_BOXBERRY"] = "Код терминала (Boxberry)";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_YANDEX"] = "Код терминала (Яндекс)";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_FIVEPOST"] = "Код терминала (5POST)";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_DELLINE"] = "Код терминала (Деловые линии)";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_UID_DELLINE"] = "Заказчик перевозки (Деловые линии)";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_COUNTER_DELLINE"] = "Отправитель (Деловые линии)";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_KIT"] = "Код терминала (KIT)";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_UID_KIT"] = "Заказчик перевозки (KIT)";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_POSTRF"] = "Код терминала (Почта России)";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_NAME"] = "Имя отправителя";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_PHONE"] = "Телефон отправителя";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_REGION"] = "Регион";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_CITY"] = "Населённый пункт";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_STREET"] = "Улица";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_HOUSE"] = "Здание";
$MESS["ESHOP_LOGISTIC_OPTIONS_S_ROOM"] = "Квартира / офис";
$MESS["ESHOP_LOGISTIC_OPTIONS_STATUS_ORDER"] = "Настройка статусов";
$MESS["ESHOP_LOGISTIC_OPTIONS_STATUS_TRANSLATE"] = [
    'accepted'   => 'Загружен в ЛК перевозчика',
    'need_check' => 'Загружен в ЛК перевозчика, но требуется уточнения',
    'created'    => 'Загружен в ЛК перевозчика и проверен',
    'received'   => 'Принят на склад перевозчика',
    'delivered'   => 'В доставке у перевозчика',
    'awaiting'   => 'Ожидает самовывоза из ПВЗ/постамата',
    'courier'   => 'Передан курьеру',
    'taken'   => 'Доставлен',
    'canceled'   => 'Отменен',
    'return'   => 'Возвращается отправителю',
    'returned'   => 'Возвращен отправителю',
    'n/a'   => 'Не определён',
];