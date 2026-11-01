<?php

namespace App\Filament\Support\Resources\SupportProjects;

use App\Filament\Support\Resources\SupportProjects\Pages\CreateSupportProject;
use App\Filament\Support\Resources\SupportProjects\Pages\EditSupportProject;
use App\Filament\Support\Resources\SupportProjects\Pages\ListSupportProjects;
use App\Filament\Support\Resources\SupportProjects\Pages\ViewSupportProject;
use App\Filament\Support\Resources\SupportProjects\RelationManagers\ContactsRelationManager;
use App\Filament\Support\Resources\SupportProjects\Schemas\SupportProjectForm;
use App\Filament\Support\Resources\SupportProjects\Schemas\SupportProjectInfolist;
use App\Filament\Support\Resources\SupportProjects\Tables\SupportProjectsTable;
use App\Models\Support\SupportProject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportProjectResource extends Resource
{
    protected static ?string $pluralLabel = 'Projekty';

    protected static ?string $label = 'Projekt';

    protected static ?string $model = SupportProject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    public static function form(Schema $schema): Schema
    {
        return SupportProjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportProjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ContactsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportProjects::route('/'),
            'create' => CreateSupportProject::route('/create'),
            'view' => ViewSupportProject::route('/{record}'),
            'edit' => EditSupportProject::route('/{record}/edit'),
        ];
    }
}
