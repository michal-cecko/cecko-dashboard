<?php

namespace App\Filament\Tickets\Resources\Projects;

use App\Filament\Tickets\Resources\ClientAccounts\RelationManagers\ClientAccountsRelationManager;
use App\Filament\Tickets\Resources\Projects\Pages\CreateProject;
use App\Filament\Tickets\Resources\Projects\Pages\EditProject;
use App\Filament\Tickets\Resources\Projects\Pages\ListProjects;
use App\Filament\Tickets\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Tickets\Resources\Projects\Tables\ProjectsTable;
use App\Models\Tickets\Project;
use BackedEnum;
use daacreators\CreatorsTicketing\Filament\Resources\Departments\RelationManagers\AgentsRelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|null|UnitEnum $navigationGroup = 'Tikety';

    protected static ?string $slug = 'projekty';

    protected static ?string $label = 'Projekt';

    protected static ?string $pluralLabel = 'Projekty';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AgentsRelationManager::class,
            ClientAccountsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
