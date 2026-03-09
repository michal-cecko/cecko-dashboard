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
        CountryEnum::SK->value => 'Slovakia',
        CountryEnum::CZ->value => 'Czech Republic',
    ],

    CurrencyEnum::class => [
        CurrencyEnum::EUR->value => 'EUR - Euro',
        CurrencyEnum::CZK->value => 'CZK - Czech Koruna',
    ],

    UserCapabilityEnum::class => [
        UserCapabilityEnum::VIEW_SONGS->value => 'Access songbook',
        UserCapabilityEnum::MANAGE_SONGS->value => 'Manage songbook',
        UserCapabilityEnum::VIEW_MOBILE_APPS->value => 'Access apps',
        UserCapabilityEnum::MANAGE_MOBILE_APPS->value => 'Manage apps',
        UserCapabilityEnum::MANAGE_USERS->value => 'Manage users',
        UserCapabilityEnum::VIEW_INVOICES->value => 'Access invoices',
        UserCapabilityEnum::MANAGE_INVOICES->value => 'Manage invoices',
        UserCapabilityEnum::VIEW_ALL_INVOICES->value => 'View all users\' invoices',
    ],

    InvoiceThemeEnum::class => [
        InvoiceThemeEnum::Emerald->value => 'Emerald (green)',
        InvoiceThemeEnum::Amber->value => 'Amber (orange)',
        InvoiceThemeEnum::Blue->value => 'Blue',
        InvoiceThemeEnum::Rose->value => 'Rose (pink)',
        InvoiceThemeEnum::Violet->value => 'Violet (purple)',
        InvoiceThemeEnum::Slate->value => 'Slate (grey)',
    ],

    InvoiceStatusEnum::class => [
        InvoiceStatusEnum::NEW->value => 'New',
        InvoiceStatusEnum::SENT->value => 'Sent',
        InvoiceStatusEnum::DELIVERED->value => 'Delivered',
        InvoiceStatusEnum::AFTER_DUE->value => 'Overdue',
        InvoiceStatusEnum::PAID->value => 'Paid',
        InvoiceStatusEnum::CANCELLED->value => 'Cancelled',
    ],

    VatTypeEnum::class => [
        VatTypeEnum::STANDARD->value => 'Standard',
        VatTypeEnum::ZERO_RATE->value => 'Zero rate',
        VatTypeEnum::REVERSE_CHARGE->value => 'Reverse charge',
    ],

    PaymentMethodEnum::class => [
        PaymentMethodEnum::CASH->value => 'Cash',
        PaymentMethodEnum::BANK_TRANSFER->value => 'Bank transfer',
        PaymentMethodEnum::CARD->value => 'Card',
        PaymentMethodEnum::PAYPAL->value => 'PayPal',
        PaymentMethodEnum::CRYPTO->value => 'Cryptocurrency',
        PaymentMethodEnum::OTHER->value => 'Other',
    ],

    InvoiceNumberVariableEnum::class => [
        InvoiceNumberVariableEnum::YEAR->value => 'Year (4 digits)',
        InvoiceNumberVariableEnum::YY->value => 'Year (2 digits)',
        InvoiceNumberVariableEnum::MONTH->value => 'Month',
        InvoiceNumberVariableEnum::SEQ->value => 'Sequence number',
    ],

    LocaleEnum::class => [
        LocaleEnum::SK->value => 'Slovak',
        LocaleEnum::CZ->value => 'Czech',
        LocaleEnum::EN->value => 'English',
    ],

    InvoiceItemVariableEnum::class => [
        InvoiceItemVariableEnum::MONTH->value => 'Month (name)',
        InvoiceItemVariableEnum::MONTH_NUM->value => 'Month (number)',
        InvoiceItemVariableEnum::YEAR->value => 'Year',
        InvoiceItemVariableEnum::PERIOD->value => 'Period (Month Year)',
        InvoiceItemVariableEnum::PREV_MONTH->value => 'Previous month',
        InvoiceItemVariableEnum::QUARTER->value => 'Quarter',
        InvoiceItemVariableEnum::QUARTER_PERIOD->value => 'Quarter with year',
        InvoiceItemVariableEnum::DATE_RANGE->value => 'Date range',
    ],
];
