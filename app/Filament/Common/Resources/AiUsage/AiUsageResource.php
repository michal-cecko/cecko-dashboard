<?php

namespace App\Filament\Common\Resources\AiUsage;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Common\Resources\AiUsage\Pages\ListAiUsage;
use App\Filament\Common\Resources\AiUsage\Tables\AiUsageTable;
use App\Models\Common\AiUsageOverview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Shared read-only overview of AI spend across every panel (Stride, Garáž, …),
 * backed by the ai_usage_overview database view. Gated by VIEW_AI_USAGE and
 * scoped to the signed-in user's own calls.
 */
class AiUsageResource extends Resource
{
    protected static ?string $model = AiUsageOverview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|null|UnitEnum $navigationGroup = 'Ostatné';

    protected static ?string $label = 'AI útrata';

    protected static ?string $pluralLabel = 'AI útrata';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasCapability(UserCapabilityEnum::VIEW_AI_USAGE) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->ownedBy(auth()->user());
    }

    public static function table(Table $table): Table
    {
        return AiUsageTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiUsage::route('/'),
        ];
    }
}
