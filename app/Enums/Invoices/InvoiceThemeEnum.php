<?php

namespace App\Enums\Invoices;

use App\Traits\Common\EnumHelper;

enum InvoiceThemeEnum: string
{
    use EnumHelper;

    case Emerald = 'emerald';
    case Amber = 'amber';
    case Blue = 'blue';
    case Rose = 'rose';
    case Violet = 'violet';
    case Slate = 'slate';

    /**
     * Primary color used in PDF (headings, table header, accents).
     */
    public function primaryColor(): string
    {
        return match ($this) {
            self::Emerald => '#059669',
            self::Amber => '#d97706',
            self::Blue => '#2563eb',
            self::Rose => '#e11d48',
            self::Violet => '#7c3aed',
            self::Slate => '#475569',
        };
    }

    /**
     * Light background color used for bank info section.
     */
    public function lightBg(): string
    {
        return match ($this) {
            self::Emerald => '#f0fdf4',
            self::Amber => '#fffbeb',
            self::Blue => '#eff6ff',
            self::Rose => '#fff1f2',
            self::Violet => '#f5f3ff',
            self::Slate => '#f8fafc',
        };
    }

    /**
     * Border color for bank info section.
     */
    public function lightBorder(): string
    {
        return match ($this) {
            self::Emerald => '#bbf7d0',
            self::Amber => '#fde68a',
            self::Blue => '#bfdbfe',
            self::Rose => '#fecdd3',
            self::Violet => '#ddd6fe',
            self::Slate => '#e2e8f0',
        };
    }
}
