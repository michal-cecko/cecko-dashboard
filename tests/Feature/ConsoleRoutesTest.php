<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConsoleRoutesTest extends TestCase
{
    public function test_console_routes_are_valid(): void
    {
        $this->artisan('schedule:list')
            ->assertSuccessful();
    }
}