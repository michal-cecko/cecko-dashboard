<?php

namespace Tests\Feature;

use App\Models\Toolkit\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_public_gallery_page_loads_with_valid_token(): void
    {
        $gallery = Gallery::factory()->create();

        $response = $this->get(route('gallery.public', $gallery->share_token));

        $response->assertOk();
        $response->assertSee($gallery->title);
    }

    public function test_public_gallery_returns_404_for_invalid_token(): void
    {
        $response = $this->get('/gallery/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    public function test_expired_gallery_returns_410(): void
    {
        $gallery = Gallery::factory()->expired()->create();

        $response = $this->get(route('gallery.public', $gallery->share_token));

        $response->assertStatus(410);
        $response->assertSee('Platnosť tohto odkazu vypršala');
    }

    public function test_inactive_gallery_returns_410(): void
    {
        $gallery = Gallery::factory()->inactive()->create();

        $response = $this->get(route('gallery.public', $gallery->share_token));

        $response->assertStatus(410);
    }

    public function test_gallery_with_null_expires_at_is_accessible(): void
    {
        $gallery = Gallery::factory()->create(['expires_at' => null]);

        $response = $this->get(route('gallery.public', $gallery->share_token));

        $response->assertOk();
    }

    public function test_gallery_with_future_expires_at_is_accessible(): void
    {
        $gallery = Gallery::factory()->expiresInFuture()->create();

        $response = $this->get(route('gallery.public', $gallery->share_token));

        $response->assertOk();
    }
}
