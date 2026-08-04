<?php
$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_1"] = array(
    0 => 'Посылка',
    2 => 'Курьер Онлайн',
    3 => 'Посылка Онлайн',
    5 => 'Посылка 1й класс'
);
$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_2"] = array(
    1 => 'упаковка ИМ',
    2 => 'упаковка Boxberry',
);
$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_BOXBERRY_3"] = array(
    0 => 'выдача без вскрытия',
    1 => 'выдача со вскрытием и проверкой комплектности',
    2 => 'выдача части вложения'
);

$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_SDEK_1"] = array(
    1 => 'Интернет-магазин',
    2 => 'Доставка',
);


$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_DELLINE_1"] = array(
    0 => 'Нет',
    1 => 'Да',
);
$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_DELLINE_2"] = array(
    'auto'    => 'Автодоставка',
    'express' => 'Экспресс-доставка',
    'letter'  => 'Письмо',
    'avia'    => 'Авиадоставка',
    'small'   => 'Доставка малогабаритного груза',
);

$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_KIT_1"] = array(
    1   => 'Физическое лицо',
    2   => 'ИП',
    3   => 'Юридическое лицо',
);

$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_KIT_2"] = array(
    1 => 'стандарт',
    3 => 'экспресс',
);


$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_KIT_1"] = array(
    1   => 'Физическое лицо',
    2   => 'ИП',
    3   => 'Юридическое лицо',
);

$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_PECOM_1"] = [
    10 => 'ПАСПОРТ ГРАЖДАНИНА РФ',
    1 => 'ПАСПОРТ ИНОСТРАННОГО ГРАЖДАНИНА',
    2 => 'РАЗРЕШЕННИЕ НА ВРЕМЕННОЕ ПРОЖИВАНИЕ',
    3 => 'ВОДИТЕЛЬСКОЕ УДОСТОВЕРЕНИЕ',
    4 => 'ВИД НА ЖИТЕЛЬСТВО',
    5 => 'ЗАГРАНИЧНЫЙ ПАСПОРТ',
    6 => 'УДОСТОВЕРЕНИЕ БЕЖЕНЦА',
    7 => 'ВРЕМЕННОЕ УДОСТОВЕРЕНИЕ ЛИЧНОСТИ ГРАЖДАНИНА РФ',
    8 => 'СВИДЕТЕЛЬСТВО О ПРЕДОСТАВЛЕНИИ ВРЕМЕННОГО УБЕЖИЩА НА ТЕРРИТОРИИ РФ',
    9 => 'ПАСПОРТ МОРЯКА',
    11 => 'СВИДЕТЕЛЬСТВО О РАССМОТРЕНИИ ХОДАТАЙСТВА О ПРИЗНАНИИ БЕЖЕНЦЕМ',
    12 => 'ВОЕННЫЙ БИЛЕТ',
];

$MESS["ESHOP_LOGISTIC_HELPERS_ORG_TYPE_PECOM"] = array(
    1 => 'Юридическое лицо',
    2 => 'Индивидуальный предприниматель',
    3 => 'Физическое лицо',
);

$MESS["ESHOP_LOGISTIC_HELPERS_DOCUMENT_TYPE_PECOM"] = array(
    'passport' => 'Паспорт',
    'drivingLicence' => 'Водительские права',
    'foreignPassport' => 'Заграничный паспорт',
);

$MESS["ESHOP_LOGISTIC_HELPERS_LEGAL_TYPE_BAIKAL"] = array(
    1   => 'Юридическое лицо',
    2   => 'Физическое лицо',
);

$MESS["ESHOP_LOGISTIC_HELPERS_TYPE_BAIKAL_1"] = array(
    1   => 'Физическое лицо',
    5   => 'ООО',
    9   => 'ИП',
    12   => 'АО',
);

$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_PAYER_SENDER"] = 'Отправитель';
$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_PAYER_RECEIVER"] = 'Получатель';

// Подписи полей выгрузки заказа для конкретных ТК (см. lib/view/unloading/form.php,
// eslFieldLabel()) — у МС одно и то же имя поля ("type", "inn", "company"...) подписано
// по-разному в разных службах, общей ADDFIELDS_<name> для всех случаев недостаточно.
$MESS["ADDFIELDS_TYPE_BOXBERRY"] = 'Тип отправления';
$MESS["ADDFIELDS_TYPE_SDEK"] = 'Тип заказа';
$MESS["ADDFIELDS_NAME_DELLINE"] = 'Название организации';
$MESS["ADDFIELDS_INN_DELLINE"] = 'ИНН организации';
$MESS["ADDFIELDS_TIME_FROM_DELLINE"] = 'Интервал для забора груза c';
$MESS["ADDFIELDS_TIME_TO_DELLINE"] = 'Интервал для забора груза до';
$MESS["ADDFIELDS_REQUESTER_KIT"] = 'Название профиля отправителя';
$MESS["ADDFIELDS_INN_KIT"] = 'ИНН для юридического лица';
$MESS["ADDFIELDS_KPP_KIT"] = 'КПП для юридического лица';
$MESS["ADDFIELDS_TYPE_PECOM_SENDER"] = 'Тип документа отправителя';
$MESS["ADDFIELDS_DATE_PECOM"] = 'Дата выдачи';
$MESS["ADDFIELDS_LAST_NAME_PECOM_SENDER"] = 'Фамилия';
$MESS["ADDFIELDS_TYPE_PECOM_RECEIVER"] = 'Тип получателя';
$MESS["ADDFIELDS_PASSPORT_SERIES_PECOM"] = 'Серия документа';
$MESS["ADDFIELDS_PASSPORT_NUMBER_PECOM"] = 'Номер документа';
$MESS["ADDFIELDS_INN_PECOM_RECEIVER"] = 'ИНН получателя';
$MESS["ADDFIELDS_LEGAL_BAIKAL"] = 'Тип отправителя';
$MESS["ADDFIELDS_COMPANY_BAIKAL"] = 'Наименование организации';
$MESS["ADDFIELDS_TYPE_BAIKAL"] = 'Тип получателя';
$MESS["ADDFIELDS_TYPE_BAIKAL_SENDER"] = 'Правовая форма (ОПФ)';
$MESS["ADDFIELDS_PASSPORT_SERIES_BAIKAL"] = 'Серия паспорта';
$MESS["ADDFIELDS_PASSPORT_NUMBER_BAIKAL"] = 'Номер паспорта';
$MESS["ADDFIELDS_PAYER_BAIKAL"] = 'Плательщик за доставку';
$MESS["ADDFIELDS_COMPANY_DPD"] = 'Наименование компании';
$MESS["ADDFIELDS_EMAIL_RECEIVER"] = 'Email получателя';

$MESS["ESHOP_LOGISTIC_HELPERS_PRODUCE_TIME_DPD"] = array(
    '9-18' => '9-18',
    '9-13' => '9-13',
    '13-18' => '13-18',
);
$MESS["ESHOP_LOGISTIC_HELPERS_EXPORT_PAYER_THIRD"] = 'Заказчик перевозки';