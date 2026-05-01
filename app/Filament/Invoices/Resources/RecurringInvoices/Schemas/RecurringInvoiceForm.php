<?php

namespace App\Filament\Invoices\Resources\RecurringInvoices\Schemas;

use App\Enums\Common\CurrencyEnum;
use App\Enums\Common\LocaleEnum;
use App\Enums\Invoices\InvoiceItemVariableEnum;
use App\Enums\Invoices\PaymentMethodEnum;
use App\Enums\Invoices\RecurringIntervalEnum;
use App\Enums\Invoices\VatTypeEnum;
use App\Models\Invoices\Customer;
use App\Models\Invoices\InvoiceNumberSequence;
use App\Models\Invoices\ServiceCatalogItem;
use App\Models\Invoices\VatRate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RecurringInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Základné údaje')
                    ->schema([
                        TextInput::make('name')
                            ->label('Názov')
                            ->helperText('Interný názov tohto pravidelného úkonu, napr. "Hosting ACME mesačne"')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktívne')
                            ->default(true),

                        Toggle::make('auto_send')
                            ->label('Automaticky odoslať klientovi')
                            ->default(true),
                    ])->columns(2),

                Section::make('Plán generovania')
                    ->schema([
                        Select::make('interval')
                            ->label('Interval')
                            ->options(RecurringIntervalEnum::translations())
                            ->default(RecurringIntervalEnum::MONTHLY->value)
                            ->required()
                            ->live(),

                        Select::make('month_of_year')
                            ->label('Mesiac v roku')
                            ->options([
                                1 => 'Január', 2 => 'Február', 3 => 'Marec',
                                4 => 'Apríl', 5 => 'Máj', 6 => 'Jún',
                                7 => 'Júl', 8 => 'August', 9 => 'September',
                                10 => 'Október', 11 => 'November', 12 => 'December',
                            ])
                            ->visible(fn (Get $get): bool => $get('interval') === RecurringIntervalEnum::YEARLY->value)
                            ->required(fn (Get $get): bool => $get('interval') === RecurringIntervalEnum::YEARLY->value),

                        TextInput::make('day_of_month')
                            ->label('Deň v mesiaci')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(28)
                            ->required()
                            ->default(1)
                            ->helperText('1–28 (vyššie hodnoty sa orežú podľa dĺžky mesiaca)'),

                        DatePicker::make('start_date')
                            ->label('Prvé generovanie')
                            ->required()
                            ->default(now())
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if ($state) {
                                    $set('next_generation_date', $state);
                                }
                            }),

                        DatePicker::make('end_date')
                            ->label('Ukončiť po')
                            ->helperText('Voliteľné – po tomto dátume sa už generovať nebude'),

                        DatePicker::make('next_generation_date')
                            ->label('Najbližšie generovanie')
                            ->helperText('Aktualizuje sa po každom úspešnom generovaní'),
                    ])->columns(3),

                Section::make('Faktúra – základ')
                    ->schema([
                        Select::make('invoice_number_sequence_id')
                            ->label('Číselná rada')
                            ->options(fn () => InvoiceNumberSequence::query()->pluck('name', 'id'))
                            ->required()
                            ->default(fn () => InvoiceNumberSequence::query()->where('is_default', true)->value('id')),

                        Select::make('customer_id')
                            ->label('Odberateľ')
                            ->options(fn () => Customer::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Select::make('currency')
                            ->label('Mena')
                            ->options(CurrencyEnum::translations())
                            ->default(fn () => auth()->user()->activeCompany?->default_currency ?? 'EUR')
                            ->required(),

                        Select::make('payment_method')
                            ->label('Spôsob platby')
                            ->options(PaymentMethodEnum::translations()),

                        TextInput::make('due_days')
                            ->label('Splatnosť (dni)')
                            ->numeric()
                            ->default(14)
                            ->minValue(0)
                            ->required(),

                        TextInput::make('order_number')
                            ->label('Číslo objednávky')
                            ->maxLength(100)
                            ->helperText(self::variableHelp()),

                        Textarea::make('description')
                            ->label('Popis')
                            ->rows(2)
                            ->helperText(self::variableHelp())
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Texty pred/za položkami')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Tabs::make('text_before_items_tabs')
                                    ->label('Text pred položkami')
                                    ->schema([
                                        Tab::make('SK')->schema([
                                            RichEditor::make('text_before_items.sk')->hiddenLabel(),
                                        ]),
                                        Tab::make('CZ')->schema([
                                            RichEditor::make('text_before_items.cs')->hiddenLabel(),
                                        ]),
                                        Tab::make('EN')->schema([
                                            RichEditor::make('text_before_items.en')->hiddenLabel(),
                                        ]),
                                    ]),

                                Tabs::make('text_after_items_tabs')
                                    ->label('Text za položkami')
                                    ->schema([
                                        Tab::make('SK')->schema([
                                            RichEditor::make('text_after_items.sk')->hiddenLabel(),
                                        ]),
                                        Tab::make('CZ')->schema([
                                            RichEditor::make('text_after_items.cs')->hiddenLabel(),
                                        ]),
                                        Tab::make('EN')->schema([
                                            RichEditor::make('text_after_items.en')->hiddenLabel(),
                                        ]),
                                    ]),
                            ]),
                    ])->collapsed(),

                Section::make('Položky šablóny')
                    ->compact()
                    ->schema([
                        Repeater::make('items_template')
                            ->label('Položky')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('service_catalog_item_id')
                                    ->label('Z katalógu')
                                    ->options(fn () => ServiceCatalogItem::with('translations')
                                        ->get()
                                        ->mapWithKeys(fn ($item) => [$item->id => $item->translated('name', app()->getLocale()) ?? '—']))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                        if (! $state) {
                                            return;
                                        }
                                        $item = ServiceCatalogItem::with(['translations', 'defaultVatRate'])->find($state);
                                        if (! $item) {
                                            return;
                                        }
                                        $invoiceCurrency = $get('../../currency') ?? auth()->user()->activeCompany?->default_currency ?? 'EUR';
                                        $set('unit_price', $item->getPriceForCurrency($invoiceCurrency) ?? collect($item->prices)->first());
                                        $set('quantity', $item->default_quantity ?? 1);
                                        $set('unit', $item->unit);
                                        $set('vat_rate_id', $item->default_vat_rate_id);
                                        if ($item->defaultVatRate) {
                                            $set('vat_rate_value', $item->defaultVatRate->rate);
                                        }
                                        $translations = $item->translations->map(fn ($t) => [
                                            'locale' => $t->locale,
                                            'description' => $t->description ?? $t->name,
                                        ])->toArray();
                                        $set('translations', $translations);
                                    })
                                    ->columnSpan(2),

                                TextInput::make('quantity')
                                    ->label('Množstvo')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),

                                Select::make('unit')
                                    ->label('Jednotka')
                                    ->options([
                                        'ks' => 'ks', 'hod' => 'hod', 'mes' => 'mes',
                                        'km' => 'km', 'kg' => 'kg', 'm2' => 'm²',
                                    ]),

                                TextInput::make('unit_price')
                                    ->label('Cena za jednotku')
                                    ->numeric()
                                    ->required(),

                                Select::make('vat_rate_id')
                                    ->label('Sadzba DPH')
                                    ->options(fn () => VatRate::query()
                                        ->whereIn('country_code', [auth()->user()->activeCompany?->country_code ?? 'SK', 'XX'])
                                        ->get()
                                        ->mapWithKeys(fn ($r) => [$r->id => "{$r->name} ({$r->rate}%)"])
                                        ->toArray())
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set): void {
                                        if ($state) {
                                            $rate = VatRate::find($state);
                                            if ($rate) {
                                                $set('vat_rate_value', $rate->rate);
                                            }
                                        }
                                    }),

                                TextInput::make('vat_rate_value')
                                    ->label('% DPH')
                                    ->numeric()
                                    ->default(20),

                                Select::make('vat_type')
                                    ->label('Typ DPH')
                                    ->options(VatTypeEnum::translations())
                                    ->default(VatTypeEnum::STANDARD->value),

                                Repeater::make('translations')
                                    ->label('Popis podľa jazyka')
                                    ->schema([
                                        Select::make('locale')
                                            ->label('Jazyk')
                                            ->options(LocaleEnum::translations())
                                            ->required(),
                                        TextInput::make('description')
                                            ->label('Popis')
                                            ->required()
                                            ->helperText(self::variableHelp()),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(1)
                                    ->collapsible()
                                    ->collapsed()
                                    ->columnSpanFull(),
                            ])
                            ->columns(7)
                            ->defaultItems(1)
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('E-mail klientovi')
                    ->description('Použije sa pri automatickom odoslaní vygenerovanej faktúry')
                    ->schema([
                        TextInput::make('email_recipient')
                            ->label('Príjemca')
                            ->email()
                            ->placeholder('Predvolene: e-mail odberateľa'),

                        Select::make('email_locale')
                            ->label('Jazyk PDF a šablóny')
                            ->options(LocaleEnum::translations())
                            ->default(fn () => auth()->user()->activeCompany?->default_locale ?? 'sk'),

                        TagsInput::make('email_cc')
                            ->label('CC')
                            ->nestedRecursiveRules(['email:rfc'])
                            ->splitKeys(['Tab', ',', ' ']),

                        TagsInput::make('email_bcc')
                            ->label('BCC')
                            ->nestedRecursiveRules(['email:rfc'])
                            ->splitKeys(['Tab', ',', ' ']),

                        TextInput::make('email_subject')
                            ->label('Predmet')
                            ->placeholder('Faktúra {PERIOD}')
                            ->helperText(self::variableHelp())
                            ->columnSpanFull(),

                        RichEditor::make('email_body')
                            ->label('Telo e-mailu')
                            ->helperText(self::variableHelp())
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Poznámky')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Interné poznámky')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->collapsed(),
            ]);
    }

    private static function variableHelp(): string
    {
        $tokens = array_map(
            fn (InvoiceItemVariableEnum $v) => $v->value,
            InvoiceItemVariableEnum::cases(),
        );

        return 'Premenné: '.implode(' · ', $tokens);
    }
}
