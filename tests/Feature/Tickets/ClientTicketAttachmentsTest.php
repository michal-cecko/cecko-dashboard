<?php

namespace Tests\Feature\Tickets;

use App\Filament\Client\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Client\Resources\Tickets\TicketResource as ClientTicketResource;
use App\Models\Common\User;
use App\Models\Invoices\Customer;
use App\Services\Tickets\ClientTicketActions;
use App\Services\Tickets\ClientTickets;
use App\Services\Tickets\TicketAttachments;
use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class ClientTicketAttachmentsTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    private User $client;

    private User $staff;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();
        Storage::fake('private');

        $customer = Customer::factory()->create();
        $this->client = $this->makeClient($customer);
        $this->staff = $this->makeStaff();
        $this->ticket = $this->makeTicket($this->makeProject($customer), $this->client);
    }

    public function test_a_field_attachment_opens_with_a_signed_link(): void
    {
        $this->putTicketFile('screen.png', 'png-bytes');
        $this->ticket->update(['custom_fields' => [...$this->ticket->custom_fields, 'attachments' => [$this->path('screen.png')]]]);

        $response = $this->actingAs($this->client)->get(TicketAttachments::clientUrl($this->ticket, 'screen.png'));

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringNotContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('png-bytes', $response->streamedContent());
    }

    public function test_an_unsigned_or_expired_link_is_refused(): void
    {
        $this->putTicketFile('screen.png');
        $this->ticket->update(['custom_fields' => [...$this->ticket->custom_fields, 'attachments' => [$this->path('screen.png')]]]);
        $this->actingAs($this->client);

        $this->get(route('filament.podpora.ticket-attachment', ['ticket' => $this->ticket->id, 'filename' => 'screen.png']))
            ->assertForbidden();

        $url = TicketAttachments::clientUrl($this->ticket, 'screen.png');
        $this->travel(TicketAttachments::LINK_MINUTES + 1)->minutes();

        $this->get($url)->assertForbidden();
    }

    public function test_a_user_of_another_customer_gets_not_found(): void
    {
        $this->putTicketFile('screen.png');
        $this->ticket->update(['custom_fields' => [...$this->ticket->custom_fields, 'attachments' => [$this->path('screen.png')]]]);

        $this->actingAs($this->makeClient())
            ->get(TicketAttachments::clientUrl($this->ticket, 'screen.png'))
            ->assertNotFound();
    }

    public function test_a_file_linked_only_from_an_internal_note_is_not_found(): void
    {
        $this->putTicketFile('internal.pdf');
        $this->staffReply($this->ticket, $this->staff, '<a href="'.TicketAttachments::staffUrl($this->ticket, 'internal.pdf').'">odhad</a>', internal: true);

        $this->actingAs($this->client)
            ->get(TicketAttachments::clientUrl($this->ticket, 'internal.pdf'))
            ->assertNotFound();
    }

    public function test_a_file_from_a_public_reply_is_a_download_unless_it_is_a_raster_image(): void
    {
        $this->putTicketFile('logo.svg', '<svg onload="alert(1)"></svg>');
        $this->staffReply($this->ticket, $this->staff, '<a href="'.TicketAttachments::staffUrl($this->ticket, 'logo.svg').'">logo</a>');

        $response = $this->actingAs($this->client)->get(TicketAttachments::clientUrl($this->ticket, 'logo.svg'));

        $response->assertOk()->assertDownload('logo.svg');
    }

    public function test_the_plugin_attachment_route_is_closed_to_clients(): void
    {
        $this->putTicketFile('screen.png');

        $this->actingAs($this->client)
            ->get(TicketAttachments::staffUrl($this->ticket, 'screen.png'))
            ->assertForbidden();
    }

    public function test_client_links_are_signed_and_links_to_other_tickets_are_dropped(): void
    {
        $other = $this->makeTicket($this->makeProject(Customer::factory()->create()), $this->makeClient());
        $html = '<img src="'.TicketAttachments::staffUrl($this->ticket, 'a b.png').'">'
            .'<a href="'.TicketAttachments::staffUrl($other, 'secret.pdf').'">x</a>';

        $result = TicketAttachments::forClient($this->ticket, $html);

        $this->assertStringContainsString('/podpora/tikety/'.$this->ticket->id.'/prilohy/', $result);
        $this->assertStringContainsString('signature=', $result);
        $this->assertStringNotContainsString('/private/ticket-attachments/', $result);
        $this->assertStringNotContainsString('secret.pdf', $result);
        $this->assertStringContainsString('href="#"', $result);
    }

    public function test_a_client_reply_file_is_moved_into_the_ticket_and_opens_for_the_client(): void
    {
        Storage::disk('private')->put(TicketAttachments::TEMP_DIRECTORY.'/upload123.pdf', 'pdf-bytes');

        ClientTicketActions::reply(
            $this->client,
            $this->ticket,
            'Posielam export',
            [TicketAttachments::TEMP_DIRECTORY.'/upload123.pdf'],
            [TicketAttachments::TEMP_DIRECTORY.'/upload123.pdf' => 'export.pdf'],
        );

        Storage::disk('private')->assertMissing(TicketAttachments::TEMP_DIRECTORY.'/upload123.pdf');
        Storage::disk('private')->assertExists($this->path('upload123.pdf'));

        $html = ClientTickets::replyHtml($this->ticket, $this->ticket->publicReplies()->sole());
        $this->assertStringContainsString('export.pdf', $html);
        preg_match('/href="([^"]+)"/', $html, $match);

        $this->actingAs($this->client)
            ->get(html_entity_decode($match[1]))
            ->assertOk()
            ->assertDownload('upload123.pdf');
    }

    public function test_a_path_that_climbs_out_of_the_temp_folder_is_ignored(): void
    {
        $otherTicket = $this->makeTicket($this->makeProject(Customer::factory()->create()), $this->makeClient(Customer::factory()->create()));
        $otherPath = "ticket-attachments/{$otherTicket->id}/invoice.pdf";
        Storage::disk('private')->put($otherPath, 'secret');

        $reply = ClientTicketActions::reply(
            $this->client,
            $this->ticket,
            'Pozrite',
            [TicketAttachments::TEMP_DIRECTORY.'/../../'.$otherPath],
        );

        Storage::disk('private')->assertExists($otherPath);
        Storage::disk('private')->assertMissing($this->path('invoice.pdf'));
        $this->assertStringNotContainsString('invoice.pdf', $reply->content);
    }

    public function test_a_video_opens_in_the_browser_and_answers_range_requests(): void
    {
        $this->putTicketFile('clip.mp4', 'abcdefghij');
        $this->ticket->update(['custom_fields' => [...$this->ticket->custom_fields, 'attachments' => [$this->path('clip.mp4')]]]);

        $response = $this->actingAs($this->client)
            ->get(TicketAttachments::clientUrl($this->ticket, 'clip.mp4'), ['Range' => 'bytes=0-3']);

        $response->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-3/10')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringNotContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_videos_may_be_up_to_75_mb_and_other_files_up_to_10_mb(): void
    {
        $this->actingAs($this->client);
        Filament::setCurrentPanel('podpora');
        $form = [
            'project_id' => $this->ticket->department_id,
            'title' => 'Video',
            'description' => '<p>Pozrite video</p>',
        ];

        Livewire::test(CreateTicket::class)
            ->fillForm([...$form, 'files' => [UploadedFile::fake()->create('clip.mp4', 20 * 1024, 'video/mp4')]])
            ->call('create')
            ->assertHasNoFormErrors();

        $pdfErrors = Livewire::test(CreateTicket::class)
            ->fillForm([...$form, 'files' => [UploadedFile::fake()->create('export.pdf', 11 * 1024, 'application/pdf')]])
            ->call('create')
            ->errors();
        $this->assertContains('Súbor môže mať najviac 10 MB. Väčšie môžu byť len videá.', $pdfErrors->all());

        Livewire::test(CreateTicket::class)
            ->fillForm([...$form, 'files' => [UploadedFile::fake()->create('long.mp4', 76 * 1024, 'video/mp4')]])
            ->call('create')
            ->assertHasErrors();

        $this->assertSame(2, Ticket::query()->count());
    }

    public function test_images_and_videos_show_as_tiles_with_signed_links(): void
    {
        $this->putTicketFile('screen.png');
        $this->putTicketFile('clip.mp4');
        $this->putTicketFile('export.pdf');
        $this->ticket->update(['custom_fields' => [
            ...$this->ticket->custom_fields,
            'attachments' => [$this->path('screen.png'), $this->path('clip.mp4'), $this->path('export.pdf')],
        ]]);

        $html = TicketAttachments::clientFieldHtml($this->ticket->refresh());

        $this->assertMatchesRegularExpression('#<img src="[^"]+/prilohy/screen\.png\?[^"]*signature=#', $html);
        $this->assertMatchesRegularExpression('#<video src="[^"]+/prilohy/clip\.mp4\?[^"]*signature=#', $html);
        $this->assertMatchesRegularExpression('#<a href="[^"]+/prilohy/export\.pdf\?[^"]*signature=#', $html);
        $this->assertStringNotContainsString('/private/ticket-attachments/', $html);

        $this->actingAs($this->client)
            ->get(ClientTicketResource::getUrl('view', ['record' => $this->ticket], panel: 'podpora'))
            ->assertOk()
            ->assertSee('class="ticket-media', false)
            ->assertSee('clip.mp4');
    }

    private function path(string $filename): string
    {
        return "ticket-attachments/{$this->ticket->id}/{$filename}";
    }

    private function putTicketFile(string $filename, string $contents = 'bytes'): void
    {
        Storage::disk('private')->put($this->path($filename), $contents);
    }
}
