<?php

namespace Tests\Feature\Stride;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrideAuthCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_the_stride_user_capability(): void
    {
        User::factory()->create(['email' => 'panel-only@example.test', 'password' => 'secret-pass']);

        $this->postJson('/api/stride/auth/login', [
            'email' => 'panel-only@example.test',
            'password' => 'secret-pass',
        ])->assertForbidden();
    }

    public function test_login_succeeds_with_the_stride_user_capability(): void
    {
        User::factory()->strideUser()->create(['email' => 'athlete@example.test', 'password' => 'secret-pass']);

        $this->postJson('/api/stride/auth/login', [
            'email' => 'athlete@example.test',
            'password' => 'secret-pass',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_revoking_the_capability_locks_out_existing_tokens(): void
    {
        $user = User::factory()->strideUser()->create(['email' => 'revoked@example.test', 'password' => 'secret-pass']);

        $token = $this->postJson('/api/stride/auth/login', [
            'email' => 'revoked@example.test',
            'password' => 'secret-pass',
        ])->json('token');

        $this->getJson('/api/stride/auth/me', ['Authorization' => "Bearer {$token}"])->assertOk();

        $user->update(['capabilities' => []]);

        $this->getJson('/api/stride/auth/me', ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }

    public function test_other_capabilities_do_not_grant_stride_access(): void
    {
        User::factory()->create([
            'email' => 'invoicer@example.test',
            'password' => 'secret-pass',
            'capabilities' => [UserCapabilityEnum::VIEW_INVOICES, UserCapabilityEnum::MANAGE_INVOICES],
        ]);

        $this->postJson('/api/stride/auth/login', [
            'email' => 'invoicer@example.test',
            'password' => 'secret-pass',
        ])->assertForbidden();
    }
}
