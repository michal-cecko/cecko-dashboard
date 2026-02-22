<?php

namespace Tests\Unit;

use App\Services\Songs\ColorService;
use PHPUnit\Framework\TestCase;

class ColorServiceTest extends TestCase
{
    public function test_danger_returns_red(): void
    {
        $this->assertEquals('#ef4444', ColorService::translateStringColorToHex('danger'));
    }

    public function test_info_returns_blue(): void
    {
        $this->assertEquals('#3b82f6', ColorService::translateStringColorToHex('info'));
    }

    public function test_success_returns_green(): void
    {
        $this->assertEquals('#10b981', ColorService::translateStringColorToHex('success'));
    }

    public function test_warning_returns_yellow(): void
    {
        $this->assertEquals('#f59e0b', ColorService::translateStringColorToHex('warning'));
    }

    public function test_null_returns_gray(): void
    {
        $this->assertEquals('#6b7280', ColorService::translateStringColorToHex(null));
    }

    public function test_unknown_color_returns_gray(): void
    {
        $this->assertEquals('#6b7280', ColorService::translateStringColorToHex('unknown'));
    }
}
