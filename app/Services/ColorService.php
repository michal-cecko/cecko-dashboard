<?php

namespace App\Services;

class ColorService
{
    public static function translateStringColorToHex(string $color): string
    {
        return match ($color) {
            'danger' => '#ef4444', // red-500
            'info' => '#3b82f6',   // blue-500
            'success' => '#10b981', // green-500
            'warning' => '#f59e0b', // yellow-500
            default => '#6b7280',  // fallback to gray-500
        };
    }
}
