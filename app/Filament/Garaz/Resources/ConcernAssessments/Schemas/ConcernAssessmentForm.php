<?php

namespace App\Filament\Garaz\Resources\ConcernAssessments\Schemas;

use App\Enums\Garaz\CheckOutcomeEnum;
use App\Enums\Garaz\ConcernCheckInputEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConcernAssessmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kontrola — kroky')
                    ->description('Pre každý krok zaznamenaj vstup a označ výsledok.')
                    ->schema([
                        Repeater::make('results')
                            ->relationship('results')
                            ->orderColumn('order')
                            ->label('Kroky')
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Krok')
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('input_type')
                                    ->label('Typ vstupu')
                                    ->disabled()
                                    ->dehydrated()
                                    ->formatStateUsing(fn ($state): ?string => is_string($state)
                                        ? (ConcernCheckInputEnum::tryFrom($state)?->translation() ?? $state)
                                        : $state?->translation()
                                    ),

                                Textarea::make('user_input.text')
                                    ->label('Tvoja odpoveď')
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('input_type') === ConcernCheckInputEnum::TEXT->value),

                                TextInput::make('user_input.number')
                                    ->label('Hodnota')
                                    ->numeric()
                                    ->visible(fn (Get $get): bool => $get('input_type') === ConcernCheckInputEnum::NUMBER->value),

                                TextInput::make('user_input.rating')
                                    ->label('Hodnotenie 0–5')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(5)
                                    ->visible(fn (Get $get): bool => $get('input_type') === ConcernCheckInputEnum::RATING->value),

                                Textarea::make('user_input.codes')
                                    ->label('OBD kódy (oddelené čiarkou)')
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('input_type') === ConcernCheckInputEnum::OBD_CODES->value),

                                Select::make('user_input.choice')
                                    ->label('Voľba')
                                    ->options(fn (Get $get) => collect((array) $get('user_input.options'))->mapWithKeys(fn ($v) => [$v => $v])->all())
                                    ->visible(fn (Get $get): bool => $get('input_type') === ConcernCheckInputEnum::CHOICE->value),

                                Textarea::make('user_notes')
                                    ->label('Poznámka ku kroku')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Select::make('outcome')
                                    ->label('Výsledok')
                                    ->options(CheckOutcomeEnum::translations())
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->columns(2),
                    ]),

                Section::make('Záver')
                    ->schema([
                        Select::make('verdict')
                            ->label('Verdikt')
                            ->options([
                                'open' => 'Prebieha',
                                'clear' => 'V poriadku — preskočiť servis',
                                'shop' => 'Do servisu — s briefingom',
                                'monitor' => 'Sledovať — preveriť o 4 týždne',
                            ])
                            ->helperText('Môžeš nechať na "Prebieha" a verdikt sa vypočíta z výsledkov pri uložení.'),

                        Textarea::make('verdict_summary')
                            ->label('Zhrnutie / poznámka pre teba alebo servis')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(1),
            ]);
    }
}
