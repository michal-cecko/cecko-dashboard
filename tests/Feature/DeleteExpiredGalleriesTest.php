<?php

namespace Tests\Feature;

use App\Models\Toolkit\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteExpiredGalleriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_gallery_with_auto_delete_is_deleted(): void
    {
        $gallery = Gallery::factory()->expired()->autoDelete()->create();

        $this->artisan('toolkit:delete-expired-galleries')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('galleries', ['id' => $gallery->id]);
    }

    public function test_expired_gallery_without_auto_delete_is_not_deleted(): void
    {
        $gallery = Gallery::factory()->expired()->create([
            'auto_delete_on_expire' => false,
        ]);

        $this->artisan('toolkit:delete-expired-galleries')
            ->assertExitCode(0);

        $this->assertDatabaseHas('galleries', ['id' => $gallery->id]);
    }

    public function test_non_expired_gallery_with_auto_delete_is_not_deleted(): void
    {
        $gallery = Gallery::factory()->expiresInFuture()->autoDelete()->create();

        $this->artisan('toolkit:delete-expired-galleries')
            ->assertExitCode(0);

        $this->assertDatabaseHas('galleries', ['id' => $gallery->id]);
    }

    public function test_command_outputs_count(): void
    {
        Gallery::factory()->expired()->autoDelete()->count(3)->create();

        $this->artisan('toolkit:delete-expired-galleries')
            ->expectsOutputToContain('Deleted 3')
            ->assertExitCode(0);
    }
}
