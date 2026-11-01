<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportProject;
use App\Models\Support\SupportRequest;
use App\Models\Support\SupportSyncOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportSyncOperation> */
class SupportSyncOperationFactory extends Factory
{
    protected $model = SupportSyncOperation::class;

    public function definition(): array
    {
        return ['project_id' => SupportProject::factory(), 'request_id' => SupportRequest::factory(), 'kind' => 'issue', 'payload' => ['title' => 'Request', 'body' => 'Public request'], 'operation_key' => fake()->uuid()];
    }
}
