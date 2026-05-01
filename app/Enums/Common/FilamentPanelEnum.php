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

    public function brand(): string
    {
        return match ($this) {
            self::SONGS => 'Kniha piesní',
            self::INVOICES => 'Faktúry',
            self::TOOLKIT => 'Toolkit',
            self::GARAZ => 'Garáž',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SONGS => 'Akordy, texty a playlisty',
            self::INVOICES => 'Vystavovanie a evidencia faktúr',
            self::TOOLKIT => 'Galérie a zdieľanie médií',
            self::GARAZ => 'Servis a prevádzka vozidiel',
        };
    }

    public function colorName(): string
    {
        return match ($this) {
            self::SONGS => 'sky',
            self::INVOICES => 'emerald',
            self::TOOLKIT => 'violet',
            self::GARAZ => 'amber',
        };
    }

    public function heroicon(): string
    {
        return match ($this) {
            self::SONGS => 'heroicon-o-musical-note',
            self::INVOICES => 'heroicon-o-document-text',
            self::TOOLKIT => 'heroicon-o-photo',
            self::GARAZ => 'heroicon-o-truck',
        };
    }
}
