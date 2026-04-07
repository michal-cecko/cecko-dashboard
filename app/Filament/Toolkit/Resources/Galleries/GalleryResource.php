<?php

namespace App\Filament\Toolkit\Resources\Galleries;

use App\Filament\Toolkit\Resources\Galleries\Pages\CreateGallery;
use App\Filament\Toolkit\Resources\Galleries\Pages\EditGallery;
use App\Filament\Toolkit\Resources\Galleries\Pages\ListGalleries;
use App\Filament\Toolkit\Resources\Galleries\Pages\ViewGallery;
use App\Filament\Toolkit\Resources\Galleries\Schemas\GalleryForm;
use App\Filament\Toolkit\Resources\Galleries\Tables\GalleriesTable;
use App\Models\Toolkit\Gallery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|null|UnitEnum $navigationGroup = 'Médiá';

    protected static ?string $label = 'Galéria';

    protected static ?string $pluralLabel = 'Galérie';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return GalleryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GalleriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGalleries::route('/'),
            'create' => CreateGallery::route('/create'),
            'view' => ViewGallery::route('/{record}'),
            'edit' => EditGallery::route('/{record}/edit'),
        ];
    }
}
