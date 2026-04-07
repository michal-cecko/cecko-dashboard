<?php

namespace Tests\Unit;

use App\Models\Toolkit\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_token_is_auto_generated_on_creation(): void
    {
        $gallery = Gallery::factory()->create(['share_token' => null]);

        $this->assertNotNull($gallery->share_token);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $gallery->share_token
        );
    }

    public function test_is_expired_returns_true_when_past(): void
    {
        $gallery = Gallery::factory()->expired()->create();

        $this->assertTrue($gallery->isExpired());
    }

    public function test_is_expired_returns_false_when_future(): void
    {
        $gallery = Gallery::factory()->expiresInFuture()->create();

        $this->assertFalse($gallery->isExpired());
    }

    public function test_is_expired_returns_false_when_null(): void
    {
        $gallery = Gallery::factory()->create(['expires_at' => null]);

        $this->assertFalse($gallery->isExpired());
    }

    public function test_is_accessible_returns_false_when_inactive(): void
    {
        $gallery = Gallery::factory()->inactive()->create();

        $this->assertFalse($gallery->isAccessible());
    }

    public function test_is_accessible_returns_true_when_active_and_not_expired(): void
    {
        $gallery = Gallery::factory()->create();

        $this->assertTrue($gallery->isAccessible());
    }

    public function test_is_accessible_returns_false_when_expired(): void
    {
        $gallery = Gallery::factory()->expired()->create();

        $this->assertFalse($gallery->isAccessible());
    }
}
