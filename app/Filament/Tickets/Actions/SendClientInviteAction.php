<?php

namespace App\Filament\Tickets\Actions;

use App\Models\Common\User;
use App\Services\Tickets\ClientInvites;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Sends a fresh link for setting the password. Used on the client account list and edit page.
 */
class SendClientInviteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendInvite';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Poslať pozvánku')
            ->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => "Na {$record->email} pošleme odkaz na nastavenie hesla. Platí 3 dni.")
            ->action(function (User $record): void {
                ClientInvites::send($record);

                Notification::make()
                    ->title('Pozvánka odoslaná')
                    ->success()
                    ->send();
            });
    }
}
