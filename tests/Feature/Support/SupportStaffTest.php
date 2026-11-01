<?php

namespace Tests\Feature\Support;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Support\Resources\SupportProjects\Pages\CreateSupportProject;
use App\Filament\Support\Resources\SupportProjects\Pages\EditSupportProject;
use App\Filament\Support\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Filament\Support\Resources\SupportRequests\Pages\ViewSupportRequest;
use App\Models\Support\SupportRequest;
use Filament\Facades\Filament;
use Livewire\Livewire;

class SupportStaffTest extends SupportTestCase
{
    public function test_staff_capability_gates_the_panel_and_private_fields(): void
    {
        $record = SupportRequest::factory()->create(['project_id' => $this->project->id, 'internal_estimate_minutes' => 97531]);
        $record->internalNotes()->create(['body' => 'Staff-only note']);
        Filament::setCurrentPanel(Filament::getPanel('support'));
        $this->actingAs($this->staff);
        $this->get(route('filament.support.resources.support-requests.index'))->assertOk();
        Livewire::test(ListSupportRequests::class)->assertCanSeeTableRecords([$record]);
        Livewire::test(ViewSupportRequest::class, ['record' => $record->id])->assertSee('97531')->assertSee('Staff-only note');
        $this->staff->update(['capabilities' => [UserCapabilityEnum::VIEW_INVOICES]]);
        $this->assertFalse($this->staff->canAccessPanel(Filament::getPanel('support')));
    }

    public function test_staff_can_edit_project_and_see_contact_approval_controls(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('support'));
        $this->actingAs($this->staff);
        Livewire::test(EditSupportProject::class, ['record' => $this->project->getRouteKey()])->assertHasNoErrors();
        $this->get(route('filament.support.resources.support-projects.view', $this->project->getRouteKey()))->assertOk();
    }

    public function test_staff_can_create_project_with_authorized_accounting_company(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('support'));
        $this->actingAs($this->staff);
        Livewire::test(CreateSupportProject::class)->fillForm(['name' => 'Another app', 'slug' => 'another-app', 'company_id' => $this->project->company_id, 'customer_id' => $this->project->customer_id, 'locale' => 'cs', 'currency' => 'EUR', 'is_active' => true])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('support_projects', ['slug' => 'another-app', 'locale' => 'cs']);
    }
}
