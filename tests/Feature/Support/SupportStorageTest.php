<?php

namespace Tests\Feature\Support;

use App\Models\Support\SupportRequest;
use App\Services\Support\SupportWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class SupportStorageTest extends SupportTestCase
{
    public function test_attachment_needs_signature_and_current_project_access(): void
    {
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id]);
        $media = $request->addMediaFromString("%PDF-1.4\n%test\n")->usingFileName('private.pdf')->toMediaCollection('attachments');
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
        $url = URL::temporarySignedRoute('support.attachment', now()->addMinutes(5), ['project' => $this->project->slug, 'reference' => $request->reference, 'media' => $media->id]);
        $this->get($url)->assertForbidden();
        $this->loginClient();
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->project->contacts()->detach($this->contact);
        $this->get($url)->assertForbidden();
        $this->project->contacts()->attach($this->contact);
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_invalid_attachment_is_rejected_before_creating_request(): void
    {
        $file = UploadedFile::fake()->create('script.html', 1, 'text/html');
        $this->expectException(ValidationException::class);
        app(SupportWorkflow::class)->create($this->project, $this->contact, ['title' => 'Test', 'description' => 'Public', 'type' => 'bug', 'is_critical' => false], [$file]);
    }
}
