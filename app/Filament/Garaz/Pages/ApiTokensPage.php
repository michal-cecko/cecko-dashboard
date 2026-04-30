<?php

namespace App\Filament\Garaz\Pages;

use App\Models\Common\UserApiToken;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ApiTokensPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $title = 'API tokeny a bookmarklet';

    protected static ?string $navigationLabel = 'API & bookmarklet';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.garaz.api-tokens';

    public ?string $newRawToken = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(UserApiToken::query()->where('user_id', auth()->id()))
            ->columns([
                TextColumn::make('name')->label('Názov'),
                TextColumn::make('abilities')->label('Práva')->badge()->separator(','),
                TextColumn::make('last_used_at')->label('Naposledy použitý')->since()->placeholder('—'),
                TextColumn::make('revoked_at')
                    ->label('Stav')
                    ->formatStateUsing(fn ($state): string => $state ? 'Zrušený' : 'Aktívny')
                    ->color(fn ($state): string => $state ? 'danger' : 'success')
                    ->badge(),
                TextColumn::make('created_at')->label('Vytvorený')->date('d.m.Y'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Vytvoriť token')
                    ->modalSubmitActionLabel('Vytvoriť')
                    ->schema([
                        TextInput::make('name')
                            ->label('Pre čo (názov)')
                            ->required()
                            ->placeholder('napr. Bookmarklet — laptop'),
                    ])
                    ->action(function (array $data): void {
                        $raw = UserApiToken::generateRaw();

                        UserApiToken::create([
                            'user_id' => auth()->id(),
                            'name' => $data['name'],
                            'token' => hash('sha256', $raw),
                            'abilities' => ['knowledge:write'],
                        ]);

                        $this->newRawToken = $raw;

                        Notification::make()
                            ->title('Token vytvorený — skopíruj ho TERAZ')
                            ->body('Po opustení stránky token už nezobrazíme.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Zrušiť')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record->revoked_at === null)
                    ->action(fn ($record) => $record->update(['revoked_at' => now()])),
                DeleteAction::make()->visible(fn ($record): bool => $record->revoked_at !== null),
            ]);
    }

    public function getFormSchema(): Schema
    {
        return Schema::make($this);
    }

    public function bookmarkletJs(?string $rawToken = null): string
    {
        $apiUrl = url('/api/garaz/notes');
        $token = $rawToken ?? '__YOUR_TOKEN__';

        return 'javascript:(function(){var s=window.getSelection().toString();var t=prompt("Názov poznámky:",document.title);if(!t)return;fetch("'.$apiUrl.'",{method:"POST",headers:{"Content-Type":"application/json","Authorization":"Bearer '.$token.'"},body:JSON.stringify({title:t,body:s,source_url:location.href})}).then(r=>r.ok?alert("✓ Uložené do garáže."):alert("Chyba: "+r.status));})();';
    }
}
