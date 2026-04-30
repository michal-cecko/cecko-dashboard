<?php

use App\Enums\Common\CountryEnum;
use App\Enums\Common\CurrencyEnum;
use App\Enums\Common\LocaleEnum;
use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Garaz\BikeCategoryEnum;
use App\Enums\Garaz\BikeTireTypeEnum;
use App\Enums\Garaz\BrakeTypeEnum;
use App\Enums\Garaz\DrivetrainEnum;
use App\Enums\Garaz\EmissionStandardEnum;
use App\Enums\Garaz\FrameMaterialEnum;
use App\Enums\Garaz\FuelTypeEnum;
use App\Enums\Garaz\MotorcycleCoolingEnum;
use App\Enums\Garaz\MotorcycleEngineLayoutEnum;
use App\Enums\Garaz\MotorcycleFinalDriveEnum;
use App\Enums\Garaz\OdometerSourceEnum;
use App\Enums\Garaz\SuspensionTypeEnum;
use App\Enums\Garaz\TransmissionEnum;
use App\Enums\Garaz\VehicleTypeEnum;
use App\Enums\Garaz\WheelSizeEnum;
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
        UserCapabilityEnum::VIEW_MEDIA->value => 'Prístup k médiám',
        UserCapabilityEnum::MANAGE_MEDIA->value => 'Spravovať médiá',
        UserCapabilityEnum::VIEW_ALL_MEDIA->value => 'Zobraziť médiá všetkých používateľov',
        UserCapabilityEnum::VIEW_GARAZ->value => 'Prístup do garáže',
        UserCapabilityEnum::MANAGE_GARAZ->value => 'Spravovať garáž',
    ],

    VehicleTypeEnum::class => [
        VehicleTypeEnum::CAR->value => 'Auto',
        VehicleTypeEnum::MOTORCYCLE->value => 'Motorka',
        VehicleTypeEnum::BICYCLE->value => 'Bicykel',
    ],

    FuelTypeEnum::class => [
        FuelTypeEnum::PETROL->value => 'Benzín',
        FuelTypeEnum::DIESEL->value => 'Diesel',
        FuelTypeEnum::HYBRID->value => 'Hybrid',
        FuelTypeEnum::PHEV->value => 'Plug-in hybrid',
        FuelTypeEnum::EV->value => 'Elektromobil',
        FuelTypeEnum::LPG->value => 'LPG',
        FuelTypeEnum::CNG->value => 'CNG',
    ],

    TransmissionEnum::class => [
        TransmissionEnum::MANUAL->value => 'Manuál',
        TransmissionEnum::AUTOMATIC->value => 'Automat',
        TransmissionEnum::DCT->value => 'DCT (dvojspojkový)',
        TransmissionEnum::CVT->value => 'CVT (variátor)',
    ],

    DrivetrainEnum::class => [
        DrivetrainEnum::FWD->value => 'Predný náhon',
        DrivetrainEnum::RWD->value => 'Zadný náhon',
        DrivetrainEnum::AWD->value => 'AWD (stály pohon všetkých kolies)',
        DrivetrainEnum::FOUR_WD->value => '4x4',
    ],

    EmissionStandardEnum::class => [
        EmissionStandardEnum::EURO4->value => 'Euro 4',
        EmissionStandardEnum::EURO5->value => 'Euro 5',
        EmissionStandardEnum::EURO6->value => 'Euro 6',
        EmissionStandardEnum::EURO6D->value => 'Euro 6d',
    ],

    MotorcycleEngineLayoutEnum::class => [
        MotorcycleEngineLayoutEnum::SINGLE->value => 'Jednovalec',
        MotorcycleEngineLayoutEnum::PARALLEL_TWIN->value => 'Paralelný dvojvalec',
        MotorcycleEngineLayoutEnum::V_TWIN->value => 'V-twin',
        MotorcycleEngineLayoutEnum::INLINE_3->value => 'Radový trojvalec',
        MotorcycleEngineLayoutEnum::INLINE_4->value => 'Radový štvorvalec',
        MotorcycleEngineLayoutEnum::BOXER->value => 'Boxer',
        MotorcycleEngineLayoutEnum::OTHER->value => 'Iné',
    ],

    MotorcycleCoolingEnum::class => [
        MotorcycleCoolingEnum::AIR->value => 'Vzduchom chladený',
        MotorcycleCoolingEnum::OIL->value => 'Olejom chladený',
        MotorcycleCoolingEnum::LIQUID->value => 'Kvapalinou chladený',
    ],

    MotorcycleFinalDriveEnum::class => [
        MotorcycleFinalDriveEnum::CHAIN->value => 'Reťaz',
        MotorcycleFinalDriveEnum::BELT->value => 'Remeň',
        MotorcycleFinalDriveEnum::SHAFT->value => 'Kardán',
    ],

    BikeCategoryEnum::class => [
        BikeCategoryEnum::ROAD->value => 'Cestný',
        BikeCategoryEnum::GRAVEL->value => 'Gravel',
        BikeCategoryEnum::MTB_HARDTAIL->value => 'MTB hardtail',
        BikeCategoryEnum::MTB_FULL->value => 'MTB celoodpružený',
        BikeCategoryEnum::TREKKING->value => 'Trekkingový',
        BikeCategoryEnum::CITY->value => 'Mestský',
        BikeCategoryEnum::KIDS->value => 'Detský',
        BikeCategoryEnum::CARGO->value => 'Cargo',
    ],

    FrameMaterialEnum::class => [
        FrameMaterialEnum::STEEL->value => 'Oceľ',
        FrameMaterialEnum::ALUMINUM->value => 'Hliník',
        FrameMaterialEnum::CARBON->value => 'Karbón',
        FrameMaterialEnum::TITANIUM->value => 'Titán',
    ],

    WheelSizeEnum::class => [
        WheelSizeEnum::INCH_24->value => '24"',
        WheelSizeEnum::INCH_26->value => '26"',
        WheelSizeEnum::INCH_27_5->value => '27.5"',
        WheelSizeEnum::INCH_28->value => '28"',
        WheelSizeEnum::INCH_29->value => '29"',
        WheelSizeEnum::SIZE_700C->value => '700C',
        WheelSizeEnum::SIZE_650B->value => '650B',
    ],

    BrakeTypeEnum::class => [
        BrakeTypeEnum::RIM->value => 'Ráfikové',
        BrakeTypeEnum::DISC_MECH->value => 'Mechanické kotúčové',
        BrakeTypeEnum::DISC_HYDRAULIC->value => 'Hydraulické kotúčové',
    ],

    SuspensionTypeEnum::class => [
        SuspensionTypeEnum::RIGID->value => 'Bez odpruženia',
        SuspensionTypeEnum::HARDTAIL->value => 'Hardtail (predné odpruženie)',
        SuspensionTypeEnum::FULL->value => 'Celoodpružený',
    ],

    BikeTireTypeEnum::class => [
        BikeTireTypeEnum::CLINCHER->value => 'Plášť s dušou',
        BikeTireTypeEnum::TUBELESS->value => 'Tubeless',
        BikeTireTypeEnum::TUBULAR->value => 'Galuska',
    ],

    OdometerSourceEnum::class => [
        OdometerSourceEnum::INITIAL->value => 'Počiatočný stav',
        OdometerSourceEnum::MANUAL->value => 'Manuálny záznam',
        OdometerSourceEnum::SERVICE->value => 'Servis',
        OdometerSourceEnum::DIY->value => 'DIY úkon',
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
