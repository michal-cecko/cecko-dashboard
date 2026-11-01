<?php

namespace App\Enums\Common;

use App\Traits\Common\EnumHelper;

enum FilamentPanelEnum: string
{
    use EnumHelper;

    case SONGS = 'songs';
    case INVOICES = 'invoices';
    case TOOLKIT = 'toolkit';
    case GARAZ = 'garaz';
    case SUPPORT = 'support';

    public function brand(): string
    {
        return match ($this) {
            self::SONGS => 'Kniha piesní',
            self::INVOICES => 'Faktúry',
            self::TOOLKIT => 'Toolkit',
            self::GARAZ => 'Garáž',
            self::SUPPORT => 'Podpora',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SONGS => 'Akordy, texty a playlisty',
            self::INVOICES => 'Vystavovanie a evidencia faktúr',
            self::TOOLKIT => 'Galérie a zdieľanie médií',
            self::GARAZ => 'Servis a prevádzka vozidiel',
            self::SUPPORT => 'Klientske požiadavky a spolupráca',
        };
    }

    public function colorName(): string
    {
        return match ($this) {
            self::SONGS => 'sky',
            self::INVOICES => 'emerald',
            self::TOOLKIT => 'violet',
            self::GARAZ => 'amber',
            self::SUPPORT => 'cyan',
        };
    }

    public function heroicon(): string
    {
        return match ($this) {
            self::SONGS => 'heroicon-o-musical-note',
            self::INVOICES => 'heroicon-o-document-text',
            self::TOOLKIT => 'heroicon-o-photo',
            self::GARAZ => 'heroicon-o-truck',
            self::SUPPORT => 'heroicon-o-lifebuoy',
        };
    }
}
