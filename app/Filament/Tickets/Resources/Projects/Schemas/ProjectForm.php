<?php

namespace App\Filament\Tickets\Resources\Projects\Schemas;

use App\Enums\Tickets\PlanBillingEnum;
use App\Enums\Tickets\SupportPlanEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Názov')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),

                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('code')
                    ->label('Skratka')
                    ->required()
                    ->minLength(2)
                    ->maxLength(6)
                    ->regex('/^[A-Z0-9]+$/')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Set $set): mixed => $set('code', strtoupper((string) $state)))
                    ->unique(ignoreRecord: true)
                    ->helperText('Začiatok čísla tiketu, napr. FF → FF-261103-01. Len písmená a číslice.'),

                Select::make('customer_id')
                    ->label('Odberateľ')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('plan')
                    ->label('Balík podpory')
                    ->options(SupportPlanEnum::translations())
                    ->default(SupportPlanEnum::NONE->value)
                    ->required()
                    ->live()
                    ->helperText('Určuje, v ktoré dni prídu nové tikety a odpovede klienta do Telegramu.'),

                Select::make('plan_billing')
                    ->label('Platba balíka')
                    ->options(PlanBillingEnum::translations())
                    ->default(PlanBillingEnum::MONTHLY->value)
                    ->required()
                    ->visible(fn (Get $get): bool => self::hasPaidPlan($get)),

                DatePicker::make('plan_started_on')
                    ->label('Začiatok balíka')
                    ->required(fn (Get $get): bool => self::hasPaidPlan($get))
                    ->visible(fn (Get $get): bool => self::hasPaidPlan($get))
                    ->helperText('Od tohto dňa sa počítajú platobné obdobia. Nižší balík, ktorý klient zvolí, platí od ďalšieho obdobia.'),

                Textarea::make('description')
                    ->label('Popis')
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Aktívny')
                    ->default(true)
                    ->helperText('Klient môže zakladať tikety len v aktívnom projekte.'),
            ]);
    }

    private static function hasPaidPlan(Get $get): bool
    {
        $plan = $get('plan');

        return filled($plan) && ($plan instanceof SupportPlanEnum ? $plan : SupportPlanEnum::from($plan)) !== SupportPlanEnum::NONE;
    }
}
