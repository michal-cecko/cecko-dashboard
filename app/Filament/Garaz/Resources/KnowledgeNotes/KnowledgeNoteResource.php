<?php

namespace App\Filament\Garaz\Resources\KnowledgeNotes;

use App\Enums\Garaz\KnowledgeSourceEnum;
use App\Filament\Garaz\Resources\KnowledgeNotes\Pages\CreateKnowledgeNote;
use App\Filament\Garaz\Resources\KnowledgeNotes\Pages\EditKnowledgeNote;
use App\Filament\Garaz\Resources\KnowledgeNotes\Pages\ListKnowledgeNotes;
use App\Filament\Garaz\Resources\KnowledgeNotes\Pages\ViewKnowledgeNote;
use App\Models\Garaz\KnowledgeNote;
use App\Models\Garaz\Vehicle;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class KnowledgeNoteResource extends Resource
{
    protected static ?string $model = KnowledgeNote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Vozidlá';

    protected static ?string $label = 'Poznámka';

    protected static ?string $pluralLabel = 'Poznámky';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Poznámka')
                ->schema([
                    TextInput::make('title')->label('Názov')->required()->maxLength(255),
                    Select::make('vehicle_id')
                        ->label('Vozidlo (voliteľné)')
                        ->options(fn () => Vehicle::query()
                            ->where('user_id', auth()->id())
                            ->orderBy('nickname')
                            ->pluck('nickname', 'id')
                            ->all())
                        ->searchable()
                        ->placeholder('Bez priradenia'),
                    TextInput::make('source_url')
                        ->label('URL zdroja')
                        ->url()
                        ->maxLength(2048)
                        ->columnSpanFull(),
                    Textarea::make('body')->label('Text')->rows(8)->columnSpanFull(),
                    TagsInput::make('tags')->label('Tagy')->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Názov')->weight('medium')->searchable()->wrap(),
                TextColumn::make('vehicle.nickname')->label('Vozidlo')->placeholder('—'),
                TextColumn::make('source')
                    ->label('Zdroj')
                    ->badge()
                    ->formatStateUsing(fn (?KnowledgeSourceEnum $state): string => $state?->translation() ?? '—'),
                TextColumn::make('tags')
                    ->label('Tagy')
                    ->badge()
                    ->separator(','),
                TextColumn::make('captured_at')
                    ->label('Uložené')
                    ->since()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('source')->label('Zdroj')->options(KnowledgeSourceEnum::translations()),
                SelectFilter::make('vehicle_id')
                    ->label('Vozidlo')
                    ->options(fn () => Vehicle::query()
                        ->where('user_id', auth()->id())
                        ->orderBy('nickname')
                        ->pluck('nickname', 'id')
                        ->all()),
            ])
            ->defaultSort('captured_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKnowledgeNotes::route('/'),
            'create' => CreateKnowledgeNote::route('/create'),
            'view' => ViewKnowledgeNote::route('/{record}'),
            'edit' => EditKnowledgeNote::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
