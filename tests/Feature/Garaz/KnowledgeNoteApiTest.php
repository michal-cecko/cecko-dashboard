<?php

namespace Tests\Feature\Garaz;

use App\Models\Common\User;
use App\Models\Common\UserApiToken;
use App\Models\Garaz\KnowledgeNote;
use App\Models\Garaz\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeNoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookmarklet_post_creates_note_with_valid_token(): void
    {
        [$user, $rawToken] = $this->makeTokenWithAbility(['knowledge:write']);

        $response = $this->postJson('/api/garaz/notes', [
            'title' => 'Cold start rattle on Astra K',
            'body' => 'Symptoms reported by 3 owners around 95k km.',
            'source_url' => 'https://www.motor-talk.de/forum/astra-k-rattle.html',
            'tags' => ['rattle', 'astra-k', 'b14xft'],
        ], ['Authorization' => 'Bearer '.$rawToken]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'message']);

        $this->assertDatabaseHas('knowledge_notes', [
            'user_id' => $user->id,
            'title' => 'Cold start rattle on Astra K',
            'source' => 'bookmarklet',
        ]);
    }

    public function test_bookmarklet_post_rejects_without_token(): void
    {
        $response = $this->postJson('/api/garaz/notes', [
            'title' => 'no auth',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('knowledge_notes', 0);
    }

    public function test_bookmarklet_post_rejects_revoked_token(): void
    {
        [, $rawToken, $token] = $this->makeTokenWithAbility(['knowledge:write']);
        $token->update(['revoked_at' => now()]);

        $response = $this->postJson('/api/garaz/notes', [
            'title' => 'revoked',
        ], ['Authorization' => 'Bearer '.$rawToken]);

        $response->assertUnauthorized();
    }

    public function test_bookmarklet_post_rejects_token_without_ability(): void
    {
        [, $rawToken] = $this->makeTokenWithAbility(['some:other']);

        $response = $this->postJson('/api/garaz/notes', [
            'title' => 'no ability',
        ], ['Authorization' => 'Bearer '.$rawToken]);

        $response->assertUnauthorized();
    }

    public function test_bookmarklet_post_validates_required_title(): void
    {
        [, $rawToken] = $this->makeTokenWithAbility(['knowledge:write']);

        $response = $this->postJson('/api/garaz/notes', [
            'body' => 'no title',
        ], ['Authorization' => 'Bearer '.$rawToken]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_bookmarklet_post_attaches_to_owned_vehicle_if_given(): void
    {
        [$user, $rawToken] = $this->makeTokenWithAbility(['knowledge:write']);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson('/api/garaz/notes', [
            'title' => 'attached',
            'vehicle_id' => $vehicle->id,
        ], ['Authorization' => 'Bearer '.$rawToken]);

        $response->assertCreated();
        $this->assertDatabaseHas('knowledge_notes', [
            'vehicle_id' => $vehicle->id,
            'title' => 'attached',
        ]);
    }

    public function test_bookmarklet_post_rejects_vehicle_owned_by_someone_else(): void
    {
        [, $rawToken] = $this->makeTokenWithAbility(['knowledge:write']);
        $other = User::factory()->create();
        $foreignVehicle = Vehicle::factory()->create(['user_id' => $other->id]);

        $response = $this->postJson('/api/garaz/notes', [
            'title' => 'not yours',
            'vehicle_id' => $foreignVehicle->id,
        ], ['Authorization' => 'Bearer '.$rawToken]);

        $response->assertNotFound();
        $this->assertDatabaseCount('knowledge_notes', 0);
    }

    public function test_token_last_used_at_updated_after_successful_request(): void
    {
        [, $rawToken, $token] = $this->makeTokenWithAbility(['knowledge:write']);
        $this->assertNull($token->last_used_at);

        $this->postJson('/api/garaz/notes', [
            'title' => 'usage tracked',
        ], ['Authorization' => 'Bearer '.$rawToken])->assertCreated();

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_owned_by_scope_on_note(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        KnowledgeNote::create(['user_id' => $alice->id, 'title' => 'A']);
        KnowledgeNote::create(['user_id' => $alice->id, 'title' => 'AA']);
        KnowledgeNote::create(['user_id' => $bob->id, 'title' => 'B']);

        $this->assertEquals(2, KnowledgeNote::ownedBy($alice)->count());
        $this->assertEquals(1, KnowledgeNote::ownedBy($bob)->count());
    }

    /** @return array{0: User, 1: string, 2: UserApiToken} */
    private function makeTokenWithAbility(array $abilities): array
    {
        $user = User::factory()->create();
        $raw = UserApiToken::generateRaw();
        $token = UserApiToken::create([
            'user_id' => $user->id,
            'name' => 'test token',
            'token' => hash('sha256', $raw),
            'abilities' => $abilities,
        ]);

        return [$user, $raw, $token];
    }
}
