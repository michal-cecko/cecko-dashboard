<?php

namespace Tests\Feature\Support;

use App\Enums\Support\SupportPlanEnum;
use App\Models\Support\PendingNotification;
use App\Models\Support\SupportRequest;
use App\Services\Support\SupportReportService;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SupportReportTest extends SupportTestCase
{
    public function test_draft_uses_public_completed_work_and_needs_staff_approval(): void
    {
        $this->start(SupportPlanEnum::Standard);
        $date = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        SupportRequest::factory()->create(['project_id' => $this->project->id, 'status' => 'accepted', 'type' => 'bug', 'title' => 'Public fix', 'internal_estimate_minutes' => 97531, 'accepted_at' => $date->addDays(3)]);
        $service = app(SupportReportService::class);
        $report = $service->draft($this->project, $date);
        $this->assertSame('Public fix', $report->sections['fixes']);
        $this->assertStringNotContainsString('97531', json_encode($report->sections));
        $this->assertSame($report->id, $service->draft($this->project, $date)->id);
        $this->actingAs($this->staff);
        $service->approve($report);
        $service->send($report->fresh());
        $service->send($report->fresh());
        $this->assertSame(1, PendingNotification::query()->where('type', 'report')->count());
    }

    public function test_empty_report_cannot_be_approved_or_sent(): void
    {
        $this->actingAs($this->staff);
        $report = app(SupportReportService::class)->draft($this->project, CarbonImmutable::now()->startOfMonth());
        $this->expectException(HttpException::class);
        app(SupportReportService::class)->approve($report);
    }
}
