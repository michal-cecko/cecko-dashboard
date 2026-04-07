<?php

namespace Tests\Feature;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Toolkit\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithCapabilities(array $capabilities): User
    {
        return User::factory()->create([
            'capabilities' => $capabilities,
        ]);
    }

    public function test_user_with_view_media_can_view_any(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_MEDIA]);

        $this->assertTrue($user->can('viewAny', Gallery::class));
    }

    public function test_user_without_view_media_cannot_view_any(): void
    {
        $user = $this->createUserWithCapabilities([]);

        $this->assertFalse($user->can('viewAny', Gallery::class));
    }

    public function test_owner_can_view_own_gallery(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_MEDIA]);
        $gallery = Gallery::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('view', $gallery));
    }

    public function test_user_cannot_view_others_gallery(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_MEDIA]);
        $otherUser = User::factory()->create();
        $gallery = Gallery::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($user->can('view', $gallery));
    }

    public function test_view_all_media_can_view_any_gallery(): void
    {
        $user = $this->createUserWithCapabilities([
            UserCapabilityEnum::VIEW_MEDIA,
            UserCapabilityEnum::VIEW_ALL_MEDIA,
        ]);
        $otherUser = User::factory()->create();
        $gallery = Gallery::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($user->can('view', $gallery));
    }

    public function test_shared_user_can_view_gallery(): void
    {
        $owner = User::factory()->create();
        $sharedUser = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_MEDIA]);
        $gallery = Gallery::factory()->create(['user_id' => $owner->id]);
        $gallery->sharedUsers()->attach($sharedUser->id, ['permission' => 'view']);

        $this->assertTrue($sharedUser->can('view', $gallery));
    }

    public function test_user_with_manage_media_can_create(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_MEDIA]);

        $this->assertTrue($user->can('create', Gallery::class));
    }

    public function test_user_without_manage_media_cannot_create(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_MEDIA]);

        $this->assertFalse($user->can('create', Gallery::class));
    }

    public function test_owner_with_manage_can_update(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_MEDIA]);
        $gallery = Gallery::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('update', $gallery));
    }

    public function test_shared_manage_user_can_update(): void
    {
        $owner = User::factory()->create();
        $sharedUser = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_MEDIA]);
        $gallery = Gallery::factory()->create(['user_id' => $owner->id]);
        $gallery->sharedUsers()->attach($sharedUser->id, ['permission' => 'manage']);

        $this->assertTrue($sharedUser->can('update', $gallery));
    }

    public function test_shared_view_user_cannot_update(): void
    {
        $owner = User::factory()->create();
        $sharedUser = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_MEDIA]);
        $gallery = Gallery::factory()->create(['user_id' => $owner->id]);
        $gallery->sharedUsers()->attach($sharedUser->id, ['permission' => 'view']);

        $this->assertFalse($sharedUser->can('update', $gallery));
    }

    public function test_owner_with_manage_can_delete(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_MEDIA]);
        $gallery = Gallery::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('delete', $gallery));
    }

    public function test_non_owner_cannot_delete(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_MEDIA]);
        $otherUser = User::factory()->create();
        $gallery = Gallery::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($user->can('delete', $gallery));
    }

    public function test_view_all_media_can_delete(): void
    {
        $user = $this->createUserWithCapabilities([
            UserCapabilityEnum::MANAGE_MEDIA,
            UserCapabilityEnum::VIEW_ALL_MEDIA,
        ]);
        $otherUser = User::factory()->create();
        $gallery = Gallery::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($user->can('delete', $gallery));
    }
}
