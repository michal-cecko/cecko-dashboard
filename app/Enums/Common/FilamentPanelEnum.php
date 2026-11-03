<?php

namespace App\Enums\Common;

use App\Models\Common\User;
use App\Traits\Common\EnumHelper;
use Filament\Facades\Filament;
use Filament\Panel;

enum FilamentPanelEnum: string
{
    use EnumHelper;

    case SONGS = 'songs';
    case INVOICES = 'invoices';
    case TOOLKIT = 'toolkit';
    case GARAZ = 'garaz';
    case TICKETS = 'tikety';
    case CLIENT = 'podpora';

    public static function firstAccessibleBy(User $user): ?Panel
    {
        foreach (self::cases() as $case) {
            $panel = Filament::getPanel($case->value);

            if ($user->canAccessPanel($panel)) {
                return $panel;
            }
        }

        return null;
    }

    public function brand(): string
    {
        return match ($this) {
            self::SONGS => 'Kniha piesní',
            self::INVOICES => 'Faktúry',
            self::TOOLKIT => 'Toolkit',
            self::GARAZ => 'Garáž',
            self::TICKETS => 'Tikety',
            self::CLIENT => 'Podpora',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SONGS => 'Akordy, texty a playlisty',
            self::INVOICES => 'Vystavovanie a evidencia faktúr',
            self::TOOLKIT => 'Galérie a zdieľanie médií',
            self::GARAZ => 'Servis a prevádzka vozidiel',
            self::TICKETS => 'Požiadavky klientov',
            self::CLIENT => 'Vaše požiadavky a projekty',
        };
    }

    public function colorName(): string
    {
        return match ($this) {
            self::SONGS => 'sky',
            self::INVOICES => 'emerald',
            self::TOOLKIT => 'violet',
            self::GARAZ => 'amber',
            self::TICKETS => 'rose',
            self::CLIENT => 'indigo',
        };
    }

    public function heroicon(): string
    {
        return match ($this) {
            self::SONGS => 'heroicon-o-musical-note',
            self::INVOICES => 'heroicon-o-document-text',
            self::TOOLKIT => 'heroicon-o-photo',
            self::GARAZ => 'heroicon-o-truck',
            self::TICKETS => 'heroicon-o-ticket',
            self::CLIENT => 'heroicon-o-lifebuoy',
        };
    }
}
