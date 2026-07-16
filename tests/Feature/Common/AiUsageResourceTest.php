<?php

namespace Tests\Feature\Common;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Common\Resources\AiUsage\AiUsageResource;
use App\Filament\Common\Resources\AiUsage\Pages\ListAiUsage;
use App\Models\Common\AiUsageOverview;
use App\Models\Common\User;
use App\Models\Garaz\AiUsage as GarazAiUsage;
use App\Models\Stride\AiUsage as StrideAiUsage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiUsageResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_capability_sees_only_own_usage_from_both_panels(): void
    {
        $user = User::factory()->create([
            'capabilities' => [UserCapabilityEnum::VIEW_GARAZ, UserCapabilityEnum::VIEW_AI_USAGE],
        ]);
        $other = User::factory()->create();

        StrideAiUsage::create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-6',
            'purpose' => 'chat',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'cost_usd' => 0.001,
        ]);
        GarazAiUsage::create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-6',
            'purpose' => 'symptom_triage',
            'input_tokens' => 200,
            'output_tokens' => 80,
            'cost_usd' => 0.002,
        ]);
        GarazAiUsage::create([
            'user_id' => $other->id,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-6',
            'purpose' => 'symptom_triage',
            'input_tokens' => 999,
            'output_tokens' => 999,
            'cost_usd' => 0.009,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel('garaz');

        $this->assertTrue(AiUsageResource::canAccess());

        $own = AiUsageOverview::ownedBy($user)->get();
        $this->assertCount(2, $own);

        Livewire::test(ListAiUsage::class)
            ->assertCanSeeTableRecords($own)
            ->assertCountTableRecords(2);
    }

    public function test_user_without_capability_cannot_access_resource(): void
    {
        $user = User::factory()->create([
            'capabilities' => [UserCapabilityEnum::VIEW_GARAZ],
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel('garaz');

        $this->assertFalse(AiUsageResource::canAccess());
    }
}
