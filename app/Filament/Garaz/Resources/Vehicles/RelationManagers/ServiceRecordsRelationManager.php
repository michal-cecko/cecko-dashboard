<?php

namespace App\Filament\Garaz\Resources\Vehicles\RelationManagers;

use App\Enums\Garaz\ServiceCategoryEnum;
use App\Enums\Garaz\ServiceSourceEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ServiceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'serviceRecords';

    protected static ?string $title = 'História servisu';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Záznam servisu')
                    ->schema([
                        DateTimePicker::make('performed_at')
                            ->label('Dátum a čas')
                            ->required()
                            ->default(now()),

                        TextInput::make('mileage_km')
                            ->label('Stav km')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('km')
                            ->helperText('Pri uložení sa automaticky vytvorí záznam stavu km.'),

                        Select::make('category')
                            ->label('Kategória')
                            ->options(ServiceCategoryEnum::translations())
                            ->required(),

                        Select::make('source')
                            ->label('Vykonal')
                            ->options(ServiceSourceEnum::translations())
                            ->required()
                            ->default(ServiceSourceEnum::SHOP->value),

                        TextInput::make('shop_name')
                            ->label('Servis / dealer')
                            ->placeholder('napr. Auto Becca s.r.o'),

                        TextInput::make('technician')
                            ->label('Technik'),

                        Textarea::make('work_summary')
                            ->label('Vykonané práce')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Náklady')
                    ->schema([
                        TextInput::make('parts_cost_eur')->label('Diely (€)')->numeric()->step(0.01)->prefix('€'),
                        TextInput::make('labor_hours')->label('Práca (hod.)')->numeric()->step(0.25),
                        TextInput::make('labor_cost_eur')->label('Práca (€)')->numeric()->step(0.01)->prefix('€'),
                        TextInput::make('total_eur')->label('Celkom (€)')->numeric()->step(0.01)->prefix('€'),
                    ])->columns(4)->collapsed(),

                Section::make('Prílohy')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('attachments')
                            ->collection('attachments')
                            ->disk('public')
                            ->multiple()
                            ->reorderable()
                            ->columnSpanFull(),
                    ])->collapsed(),

                Section::make('Poznámka')
                    ->schema([
                        Textarea::make('notes')->label('Poznámka')->rows(3)->columnSpanFull(),
                    ])->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('performed_at')->label('Dátum')->date('d.m.Y')->sortable(),
                TextColumn::make('mileage_km')
                    ->label('km')
                    ->numeric(thousandsSeparator: ' ')
                    ->placeholder('—'),
                TextColumn::make('category')
                    ->label('Kategória')
                    ->badge()
                    ->formatStateUsing(fn (?ServiceCategoryEnum $state): string => $state?->translation() ?? '—'),
                TextColumn::make('source')
                    ->label('Vykonal')
                    ->badge()
                    ->color(fn (?ServiceSourceEnum $state): string => match ($state) {
                        ServiceSourceEnum::DIY => 'success',
                        ServiceSourceEnum::SHOP => 'gray',
                        ServiceSourceEnum::IMPORTED => 'info',
                        ServiceSourceEnum::ASSESSMENT => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?ServiceSourceEnum $state): string => $state?->translation() ?? '—'),
                TextColumn::make('shop_name')->label('Servis')->placeholder('—'),
                TextColumn::make('total_eur')->label('Celkom')->money('eur')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('category')->label('Kategória')->options(ServiceCategoryEnum::translations()),
                SelectFilter::make('source')->label('Vykonal')->options(ServiceSourceEnum::translations()),
            ])
            ->defaultSort('performed_at', 'desc')
            ->headerActions([
                CreateAction::make()->label('Pridať záznam'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
