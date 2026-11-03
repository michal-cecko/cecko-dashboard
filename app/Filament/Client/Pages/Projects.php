<?php

namespace App\Filament\Client\Pages;

use App\Enums\Tickets\SupportPlanEnum;
use App\Filament\Client\Resources\Tickets\TicketResource;
use App\Models\Tickets\Project;
use App\Services\Tickets\ClientTickets;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class Projects extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $title = 'Projekty';

    protected static ?string $slug = 'projekty';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return TicketResource::client() !== null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ClientTickets::projectsOf(TicketResource::client())
                ->withCount([
                    'tickets',
                    'tickets as open_tickets_count' => fn (Builder $query): Builder => $query->whereHas(
                        'status',
                        fn (Builder $status): Builder => $status->where('is_closing_status', false),
                    ),
                ]))
            ->defaultSort('name')
            ->paginated(false)
            ->recordUrl(fn (Project $record): string => ProjectPlan::getUrl(['project' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Projekt')
                    ->description(fn ($record): ?string => $record->description),

                TextColumn::make('plan')
                    ->label('Balík podpory')
                    ->badge()
                    ->formatStateUsing(fn (SupportPlanEnum $state): string => $state->translation())
                    ->description('Výhody a zmena balíka'),

                TextColumn::make('open_tickets_count')
                    ->label('Otvorené tikety'),

                TextColumn::make('tickets_count')
                    ->label('Všetky tikety'),
            ])
            ->emptyStateHeading('Zatiaľ nemáte žiadny projekt');
    }
}
