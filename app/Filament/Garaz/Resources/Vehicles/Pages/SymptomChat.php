<?php

namespace App\Filament\Garaz\Resources\Vehicles\Pages;

use App\Filament\Garaz\Resources\Vehicles\VehicleResource;
use App\Models\Garaz\Vehicle;
use App\Services\Garaz\SymptomTriageService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;

class SymptomChat extends Page
{
    protected static string $resource = VehicleResource::class;

    protected string $view = 'filament.garaz.symptom-chat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    public Vehicle $record;

    public string $symptom = '';

    public ?string $reply = null;

    public bool $isLoading = false;

    public function mount(Vehicle $record): void
    {
        abort_unless($record->user_id === auth()->id(), 403);

        $this->record = $record;
    }

    public function getTitle(): string
    {
        return 'AI triáž — '.$this->record->nickname;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Späť na vozidlo')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => VehicleResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function ask(SymptomTriageService $service): void
    {
        if (trim($this->symptom) === '') {
            return;
        }

        if (! $service->isConfigured()) {
            Notification::make()
                ->title('AI nie je nakonfigurovaná')
                ->body('Nastav ANTHROPIC_API_KEY v .env súbore a vyčisti config cache.')
                ->danger()
                ->send();

            return;
        }

        $this->isLoading = true;

        try {
            $this->reply = $service->ask($this->record, $this->symptom);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Chyba pri volaní AI')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isLoading = false;
        }
    }
}
