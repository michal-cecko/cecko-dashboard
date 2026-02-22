<?php

use App\Enums\Common\CountryEnum;
use App\Enums\Common\CurrencyEnum;
use App\Enums\Common\LocaleEnum;
use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Invoices\InvoiceItemVariableEnum;
use App\Enums\Invoices\InvoiceNumberVariableEnum;
use App\Enums\Invoices\InvoiceStatusEnum;
use App\Enums\Invoices\InvoiceThemeEnum;
use App\Enums\Invoices\PaymentMethodEnum;
use App\Enums\Invoices\VatTypeEnum;

return [
    CountryEnum::class => [
        CountryEnum::SK->value => 'Slovensko',
        CountryEnum::CZ->value => 'Česko',
    ],

    CurrencyEnum::class => [
        CurrencyEnum::EUR->value => 'EUR - Euro',
        CurrencyEnum::CZK->value => 'CZK - Česká koruna',
    ],

    UserCapabilityEnum::class => [
        UserCapabilityEnum::VIEW_SONGS->value => 'Prístup ku knihe piesní',
        UserCapabilityEnum::MANAGE_SONGS->value => 'Upravovať knihu piesní',
        UserCapabilityEnum::VIEW_MOBILE_APPS->value => 'Prístup k aplikáciam',
        UserCapabilityEnum::MANAGE_MOBILE_APPS->value => 'Upravovať aplikácie',
        UserCapabilityEnum::MANAGE_USERS->value => 'Upravovať používateľov',
        UserCapabilityEnum::VIEW_INVOICES->value => 'Prístup k faktúram',
        UserCapabilityEnum::MANAGE_INVOICES->value => 'Spravovať faktúry',
        UserCapabilityEnum::VIEW_ALL_INVOICES->value => 'Zobraziť faktúry všetkých používateľov',
    ],

    InvoiceThemeEnum::class => [
        InvoiceThemeEnum::Emerald->value => 'Emerald (zelená)',
        InvoiceThemeEnum::Amber->value => 'Amber (oranžová)',
        InvoiceThemeEnum::Blue->value => 'Blue (modrá)',
        InvoiceThemeEnum::Rose->value => 'Rose (ružová)',
        InvoiceThemeEnum::Violet->value => 'Violet (fialová)',
        InvoiceThemeEnum::Slate->value => 'Slate (sivá)',
    ],

    InvoiceStatusEnum::class => [
        InvoiceStatusEnum::NEW->value => 'Nová',
        InvoiceStatusEnum::SENT->value => 'Odoslaná',
        InvoiceStatusEnum::DELIVERED->value => 'Doručená',
        InvoiceStatusEnum::AFTER_DUE->value => 'Po splatnosti',
        InvoiceStatusEnum::PAID->value => 'Zaplatená',
        InvoiceStatusEnum::CANCELLED->value => 'Zrušená',
    ],

    VatTypeEnum::class => [
        VatTypeEnum::STANDARD->value => 'Štandardná',
        VatTypeEnum::ZERO_RATE->value => 'Nulová sadzba',
        VatTypeEnum::REVERSE_CHARGE->value => 'Prenesenie daňovej povinnosti',
    ],

    PaymentMethodEnum::class => [
        PaymentMethodEnum::CASH->value => 'Hotovosť',
        PaymentMethodEnum::BANK_TRANSFER->value => 'Bankový prevod',
        PaymentMethodEnum::CARD->value => 'Kartou',
        PaymentMethodEnum::PAYPAL->value => 'PayPal',
        PaymentMethodEnum::CRYPTO->value => 'Kryptomeny',
        PaymentMethodEnum::OTHER->value => 'Iné',
    ],

    InvoiceNumberVariableEnum::class => [
        InvoiceNumberVariableEnum::YEAR->value => 'Rok (4 číslice)',
        InvoiceNumberVariableEnum::YY->value => 'Rok (2 číslice)',
        InvoiceNumberVariableEnum::MONTH->value => 'Mesiac',
        InvoiceNumberVariableEnum::SEQ->value => 'Poradové číslo',
    ],

    LocaleEnum::class => [
        LocaleEnum::SK->value => 'Slovenčina',
        LocaleEnum::CZ->value => 'Čeština',
        LocaleEnum::EN->value => 'Angličtina',
    ],

    InvoiceItemVariableEnum::class => [
        InvoiceItemVariableEnum::MONTH->value => 'Mesiac (názov)',
        InvoiceItemVariableEnum::MONTH_NUM->value => 'Mesiac (číslo)',
        InvoiceItemVariableEnum::YEAR->value => 'Rok',
        InvoiceItemVariableEnum::PERIOD->value => 'Obdobie (Mesiac Rok)',
        InvoiceItemVariableEnum::PREV_MONTH->value => 'Predchádzajúci mesiac',
        InvoiceItemVariableEnum::QUARTER->value => 'Štvrťrok',
        InvoiceItemVariableEnum::QUARTER_PERIOD->value => 'Štvrťrok s rokom',
        InvoiceItemVariableEnum::DATE_RANGE->value => 'Rozsah dátumov',
    ],
];
