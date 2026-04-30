<?php

namespace App\Filament\Garaz\Resources\ConcernAssessments;

use App\Filament\Garaz\Resources\ConcernAssessments\Pages\EditConcernAssessment;
use App\Filament\Garaz\Resources\ConcernAssessments\Pages\ListConcernAssessments;
use App\Filament\Garaz\Resources\ConcernAssessments\Pages\ViewConcernAssessment;
use App\Filament\Garaz\Resources\ConcernAssessments\Schemas\ConcernAssessmentForm;
use App\Filament\Garaz\Resources\ConcernAssessments\Tables\ConcernAssessmentsTable;
use App\Models\Garaz\ConcernAssessment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ConcernAssessmentResource extends Resource
{
    protected static ?string $model = ConcernAssessment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Vozidlá';

    protected static ?string $label = 'Kontrola';

    protected static ?string $pluralLabel = 'Kontroly';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ConcernAssessmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConcernAssessmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConcernAssessments::route('/'),
            'view' => ViewConcernAssessment::route('/{record}'),
            'edit' => EditConcernAssessment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('vehicle', fn (Builder $query): Builder => $query->where('user_id', auth()->id()));
    }
}
