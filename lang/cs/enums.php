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
use App\Enums\Invoices\RecurringIntervalEnum;
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
        UserCapabilityEnum::VIEW_SONGS->value => 'Přístup ke knize písní',
        UserCapabilityEnum::MANAGE_SONGS->value => 'Spravovat knihu písní',
        UserCapabilityEnum::VIEW_MOBILE_APPS->value => 'Přístup k aplikacím',
        UserCapabilityEnum::MANAGE_MOBILE_APPS->value => 'Spravovat aplikace',
        UserCapabilityEnum::MANAGE_USERS->value => 'Spravovat uživatele',
        UserCapabilityEnum::VIEW_INVOICES->value => 'Přístup k fakturám',
        UserCapabilityEnum::MANAGE_INVOICES->value => 'Spravovat faktury',
        UserCapabilityEnum::MANAGE_ALL_INVOICES->value => 'Spravovat faktury všech uživatelů',
        UserCapabilityEnum::STRIDE_USER->value => 'Přístup do aplikace Stride',
    ],

    InvoiceThemeEnum::class => [
        InvoiceThemeEnum::Emerald->value => 'Emerald (zelená)',
        InvoiceThemeEnum::Amber->value => 'Amber (oranžová)',
        InvoiceThemeEnum::Blue->value => 'Blue (modrá)',
        InvoiceThemeEnum::Rose->value => 'Rose (růžová)',
        InvoiceThemeEnum::Violet->value => 'Violet (fialová)',
        InvoiceThemeEnum::Slate->value => 'Slate (šedá)',
    ],

    InvoiceStatusEnum::class => [
        InvoiceStatusEnum::NEW->value => 'Nová',
        InvoiceStatusEnum::SENT->value => 'Odeslaná',
        InvoiceStatusEnum::DELIVERED->value => 'Doručená',
        InvoiceStatusEnum::AFTER_DUE->value => 'Po splatnosti',
        InvoiceStatusEnum::PAID->value => 'Zaplacená',
        InvoiceStatusEnum::CANCELLED->value => 'Zrušená',
    ],

    VatTypeEnum::class => [
        VatTypeEnum::STANDARD->value => 'Standardní',
        VatTypeEnum::ZERO_RATE->value => 'Nulová sazba',
        VatTypeEnum::REVERSE_CHARGE->value => 'Přenesení daňové povinnosti',
    ],

    PaymentMethodEnum::class => [
        PaymentMethodEnum::CASH->value => 'Hotovost',
        PaymentMethodEnum::BANK_TRANSFER->value => 'Bankovní převod',
        PaymentMethodEnum::CARD->value => 'Kartou',
        PaymentMethodEnum::PAYPAL->value => 'PayPal',
        PaymentMethodEnum::CRYPTO->value => 'Kryptoměny',
        PaymentMethodEnum::OTHER->value => 'Jiné',
    ],

    RecurringIntervalEnum::class => [
        RecurringIntervalEnum::MONTHLY->value => 'Měsíčně',
        RecurringIntervalEnum::YEARLY->value => 'Ročně',
    ],

    InvoiceNumberVariableEnum::class => [
        InvoiceNumberVariableEnum::YEAR->value => 'Rok (4 číslice)',
        InvoiceNumberVariableEnum::YY->value => 'Rok (2 číslice)',
        InvoiceNumberVariableEnum::MONTH->value => 'Měsíc',
        InvoiceNumberVariableEnum::SEQ->value => 'Pořadové číslo',
    ],

    LocaleEnum::class => [
        LocaleEnum::SK->value => 'Slovenština',
        LocaleEnum::CZ->value => 'Čeština',
        LocaleEnum::EN->value => 'Angličtina',
    ],

    InvoiceItemVariableEnum::class => [
        InvoiceItemVariableEnum::MONTH->value => 'Měsíc (název)',
        InvoiceItemVariableEnum::MONTH_NUM->value => 'Měsíc (číslo)',
        InvoiceItemVariableEnum::YEAR->value => 'Rok',
        InvoiceItemVariableEnum::PERIOD->value => 'Období (Měsíc Rok)',
        InvoiceItemVariableEnum::PREV_MONTH->value => 'Předchozí měsíc',
        InvoiceItemVariableEnum::QUARTER->value => 'Čtvrtletí',
        InvoiceItemVariableEnum::QUARTER_PERIOD->value => 'Čtvrtletí s rokem',
        InvoiceItemVariableEnum::DATE_RANGE->value => 'Rozsah dat',
    ],
];
