<?php

namespace App\Filament\Songs\Resources\Songs;

use App\Filament\Songs\Resources\Songs\Pages\CreateSong;
use App\Filament\Songs\Resources\Songs\Pages\EditSong;
use App\Filament\Songs\Resources\Songs\Pages\ListSongs;
use App\Filament\Songs\Resources\Songs\Pages\ViewSong;
use App\Filament\Songs\Resources\Songs\Schemas\SongForm;
use App\Filament\Songs\Resources\Songs\Schemas\SongInfolist;
use App\Filament\Songs\Resources\Songs\Tables\SongsTable;
use App\Models\Songs\Song;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SongResource extends Resource
{
    protected static ?string $model = Song::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMusicalNote;

    protected static string|null|UnitEnum $navigationGroup = 'Piesne';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Pieseň';
    protected static ?string $pluralLabel = 'Piesne';

    public static function form(Schema $schema): Schema
    {
        return SongForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SongInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SongsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSongs::route('/'),
            'create' => CreateSong::route('/create'),
            'view' => ViewSong::route('/{record}'),
            'edit' => EditSong::route('/{record}/edit'),
        ];
    }
}
