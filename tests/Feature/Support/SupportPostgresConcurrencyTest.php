<?php

namespace Tests\Feature\Support;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Support\SupportPlanEnum;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\InvoiceNumberSequence;
use App\Models\Support\SupportConfirmation;
use App\Models\Support\SupportContact;
use App\Models\Support\SupportPeriod;
use App\Models\Support\SupportProject;
use App\Models\Support\SupportRequest;
use App\Services\Support\SupportBillingService;
use App\Services\Support\SupportWorkflow;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SupportPostgresConcurrencyTest extends TestCase
{
    public function test_simultaneous_approvals_cannot_spend_the_same_credit(): void
    {
        if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'support_portal_tests') {
            $this->markTestSkipped('Run this test separately on the isolated support_portal_tests PostgreSQL database.');
        }
        $this->assertSame('support_portal_tests', config('database.connections.pgsql.database'));
        $this->assertTrue(function_exists('pcntl_fork'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        Mail::fake();
        Queue::fake();
        $company = Company::factory()->create(['is_vat_payer' => false]);
        $staff = $company->user;
        $staff->update(['capabilities' => [UserCapabilityEnum::MANAGE_SUPPORT, UserCapabilityEnum::MANAGE_INVOICES], 'active_company_id' => $company->id]);
        $this->actingAs($staff);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        InvoiceNumberSequence::factory()->create(['company_id' => $company->id]);
        $project = SupportProject::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        $contact = SupportContact::factory()->create();
        $project->contacts()->attach($contact, ['can_approve' => true]);
        app(SupportBillingService::class)->start($project, SupportPlanEnum::Pro);
        $workflow = app(SupportWorkflow::class);
        $confirmations = [];
        foreach ([1, 2] as $index) {
            $request = SupportRequest::factory()->create(['project_id' => $project->id]);
            $order = $workflow->propose($request, 'quote', 'Feature '.$index, ['amount' => 30000]);
            $confirmations[] = $workflow->confirmation($project, $contact, 'order', $request, $order)->id;
        }
        app('db')->disconnect();
        $children = [];
        foreach ($confirmations as $id) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            $this->assertGreaterThanOrEqual(0, $pid);
            if ($pid === 0) {
                fclose($sockets[0]);
                fread($sockets[1], 1);
                try {
                    app(SupportWorkflow::class)->consume(SupportConfirmation::query()->findOrFail($id), '127.0.0.1', 'Concurrency test');
                    fwrite($sockets[1], '200');
                } catch (HttpException $exception) {
                    fwrite($sockets[1], (string) $exception->getStatusCode());
                } catch (\Throwable $exception) {
                    fwrite($sockets[1], get_class($exception).': '.$exception->getMessage());
                }
                fclose($sockets[1]);
                exit(0);
            }
            fclose($sockets[1]);
            $children[] = [$pid, $sockets[0]];
        }
        foreach ($children as [$pid, $socket]) {
            fwrite($socket, '1');
        }
        $results = [];
        foreach ($children as [$pid, $socket]) {
            $results[] = stream_get_contents($socket);
            fclose($socket);
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        sort($results);
        $this->assertSame(['200', '409'], $results);
        $this->assertSame(20000, SupportPeriod::query()->where('project_id', $project->id)->firstOrFail()->used_credit_minor);
        $this->assertSame(1, $project->orders()->where('kind', 'quote')->where('status', 'confirmed')->count());
    }
}
