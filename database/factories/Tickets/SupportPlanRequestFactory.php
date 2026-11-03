<?php

namespace Database\Factories\Tickets;

use App\Enums\Tickets\PlanRequestStatusEnum;
use App\Enums\Tickets\SupportPlanEnum;
use App\Models\Tickets\Project;
use App\Models\Tickets\SupportPlanRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportPlanRequest> */
class SupportPlanRequestFactory extends Factory
{
    protected $model = SupportPlanRequest::class;

    public function definition(): array
    {
        return [
            'department_id' => Project::factory()->plan(SupportPlanEnum::START),
            'from_plan' => SupportPlanEnum::START,
            'to_plan' => SupportPlanEnum::STANDARD,
            'status' => PlanRequestStatusEnum::PENDING,
        ];
    }
}
