<?php

namespace Tests\Feature\Content;

use App\Models\EducationLevel;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\PageDownload;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Level-tagged downloads are the feature the whole project exists for, and
 * they are also a second way a private file becomes public — so both the
 * grouping and the access consequences are covered here.
 */
class PageDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function makePage(string $slug = 'de-planeten'): Page
    {
        $topic = Topic::query()->firstOrCreate(
            ['parent_id' => null, 'slug' => 'natuurkunde'],
            ['title' => 'Natuurkunde']
        );

        return Page::query()->create([
            'title' => 'De Planeten',
            'slug' => $slug,
            'topic_id' => $topic->id,
        ]);
    }

    private function makeFile(string $name = 'werkblad.pdf'): MediaFile
    {
        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');

        return MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 5,
            'original_filename' => $name,
        ]);
    }

    private function level(string $name, int $order): EducationLevel
    {
        return EducationLevel::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => $order,
        ]);
    }

    public function test_attaching_a_download_publishes_the_file_and_detaching_makes_it_private()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $file = $this->makeFile();

        // Nothing embeds it and nothing offers it: private.
        $this->get(route('media.show', $file))->assertForbidden();

        $download = $page->downloads()->create(['media_file_id' => $file->id]);

        $this->get(route('media.show', $file))->assertOk();

        $download->delete();

        $this->get(route('media.show', $file))->assertForbidden();
    }

    public function test_a_download_only_file_cannot_be_deleted_and_names_the_page()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $file = $this->makeFile();
        $page->downloads()->create(['media_file_id' => $file->id]);

        $response = $this->actingAs(User::factory()->create())
            ->from(route('admin.media.index'))
            ->delete(route('admin.media.files.destroy', $file));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('De Planeten', session('error'));
        $this->assertModelExists($file);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_the_counted_route_serves_the_file_and_increments_the_tally()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $download = $page->downloads()->create(['media_file_id' => $this->makeFile()->id]);

        $this->get(route('downloads.show', $download))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=werkblad.pdf');

        $this->assertSame(1, $download->fresh()->downloads_count);
    }

    public function test_the_owner_previewing_a_download_does_not_inflate_the_tally()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $download = $page->downloads()->create(['media_file_id' => $this->makeFile()->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('downloads.show', $download))
            ->assertOk();

        $this->assertSame(0, $download->fresh()->downloads_count);
    }

    /**
     * The tally is meant to read as "how often was this taken". A download
     * manager splitting a file into eight ranged requests, or a browser
     * prefetching a link nobody clicked, is not eight students and not one.
     *
     * What is deliberately absent is per-visitor deduplication: knowing that
     * two requests are the same student means identifying students, which
     * this site has no way to do and no intention of acquiring.
     */
    public function test_a_resumed_or_prefetched_request_does_not_count()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $download = $page->downloads()->create(['media_file_id' => $this->makeFile()->id]);

        $this->withHeaders(['Range' => 'bytes=500-'])
            ->get(route('downloads.show', $download));

        $this->withHeaders(['Sec-Purpose' => 'prefetch;anonymous-client-ip'])
            ->get(route('downloads.show', $download));

        $this->assertSame(0, $download->fresh()->downloads_count);

        // withHeaders() adds to the defaults for the whole test rather than
        // for one request, so without this the next call would still be
        // carrying the prefetch header set above.
        $this->flushHeaders();

        // A range starting at zero is the fetch itself, not a continuation.
        $this->withHeaders(['Range' => 'bytes=0-'])
            ->get(route('downloads.show', $download));

        $this->assertSame(1, $download->fresh()->downloads_count);
    }

    public function test_a_video_download_is_sent_as_an_attachment_not_inline()
    {
        Storage::fake('local');
        $page = $this->makePage();

        $path = 'media/2026/08/'.Str::ulid().'.mp4';
        Storage::disk('local')->put($path, 'bytes');
        $video = MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_VIDEO,
            'mime' => 'video/mp4',
            'size_bytes' => 5,
            'original_filename' => 'les.mp4',
        ]);

        $download = $page->downloads()->create(['media_file_id' => $video->id]);

        // The body's file embed plays video inline; the downloads section is
        // for ending up with the file.
        $this->get(route('downloads.show', $download))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=les.mp4');
    }

    public function test_downloads_render_grouped_by_level_and_a_multi_level_file_appears_in_both()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $havo = $this->level('HAVO', 2);
        $vwo = $this->level('VWO', 3);

        $shared = $page->downloads()->create([
            'media_file_id' => $this->makeFile('samen.pdf')->id,
            'label' => 'Werkblad',
        ]);
        $shared->educationLevels()->sync([$havo->id, $vwo->id]);

        $vwoOnly = $page->downloads()->create(['media_file_id' => $this->makeFile('verdieping.pdf')->id]);
        $vwoOnly->educationLevels()->sync([$vwo->id]);

        $this->get('/natuurkunde/de-planeten')->assertInertia(
            fn (AssertableInertia $inertia) => $inertia
                ->component('content/page')
                ->has('downloadGroups', 2)
                ->where('downloadGroups.0.label', 'HAVO')
                ->has('downloadGroups.0.downloads', 1)
                ->where('downloadGroups.0.downloads.0.label', 'Werkblad')
                ->where('downloadGroups.1.label', 'VWO')
                ->has('downloadGroups.1.downloads', 2)
        );
    }

    public function test_an_untagged_download_leads_in_a_group_of_its_own()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $havo = $this->level('HAVO', 2);

        $general = $page->downloads()->create(['media_file_id' => $this->makeFile('formuleblad.pdf')->id]);
        $tagged = $page->downloads()->create(['media_file_id' => $this->makeFile('havo.pdf')->id]);
        $tagged->educationLevels()->sync([$havo->id]);

        $this->get('/natuurkunde/de-planeten')->assertInertia(
            fn (AssertableInertia $inertia) => $inertia
                ->where('downloadGroups.0.key', 'all')
                ->where('downloadGroups.0.label', __('content.downloads.everyone'))
                // Falls back to the filename when the owner gave no label.
                ->where('downloadGroups.0.downloads.0.label', 'formuleblad.pdf')
                ->where('downloadGroups.1.label', 'HAVO')
        );

        $this->assertNotNull($general);
    }

    public function test_a_level_nothing_on_this_page_uses_produces_no_empty_group()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $havo = $this->level('HAVO', 2);
        $this->level('VMBO-BK', 0);

        $tagged = $page->downloads()->create(['media_file_id' => $this->makeFile()->id]);
        $tagged->educationLevels()->sync([$havo->id]);

        $this->get('/natuurkunde/de-planeten')->assertInertia(
            fn (AssertableInertia $inertia) => $inertia
                ->has('downloadGroups', 1)
                ->where('downloadGroups.0.label', 'HAVO')
        );
    }

    public function test_the_admin_can_attach_a_download_with_levels()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $file = $this->makeFile();
        $havo = $this->level('HAVO', 2);

        $this->actingAs(User::factory()->create())
            ->from(route('admin.pages.edit', $page))
            ->post(route('admin.pages.downloads.store', $page), [
                'media_file_id' => $file->id,
                'label' => 'Werkblad 3',
                'education_levels' => [$havo->id],
            ])
            ->assertSessionHas('status');

        $download = $page->downloads()->first();

        $this->assertSame('Werkblad 3', $download->label);
        $this->assertSame([$havo->id], $download->educationLevels->pluck('id')->all());
    }

    public function test_downloads_attached_without_an_order_queue_up_at_the_end()
    {
        // Uploading several worksheets from the page editor attaches them one
        // after another, and each request is sent before the previous
        // response has updated the editor's copy of the list — so the client
        // cannot count and the server has to.
        Storage::fake('local');
        $page = $this->makePage();
        $admin = User::factory()->create();

        foreach (['werkblad-1.pdf', 'werkblad-2.pdf', 'werkblad-3.pdf'] as $name) {
            $this->actingAs($admin)
                ->from(route('admin.pages.edit', $page))
                ->post(route('admin.pages.downloads.store', $page), [
                    'media_file_id' => $this->makeFile($name)->id,
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ['werkblad-1.pdf', 'werkblad-2.pdf', 'werkblad-3.pdf'],
            $page->downloads()->with('mediaFile')->orderBy('sort_order')->get()
                ->pluck('mediaFile.original_filename')->all()
        );
        $this->assertSame([0, 1, 2], $page->downloads()->orderBy('sort_order')
            ->pluck('sort_order')->all());
    }

    public function test_the_same_file_cannot_be_offered_twice_on_one_page()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $file = $this->makeFile();
        $page->downloads()->create(['media_file_id' => $file->id]);

        $this->actingAs(User::factory()->create())
            ->from(route('admin.pages.edit', $page))
            ->post(route('admin.pages.downloads.store', $page), ['media_file_id' => $file->id])
            ->assertSessionHasErrors('media_file_id');

        $this->assertSame(1, $page->downloads()->count());
    }

    public function test_the_same_file_can_be_offered_on_two_pages_with_different_levels()
    {
        Storage::fake('local');
        $first = $this->makePage('de-planeten');
        $second = $this->makePage('de-zon');
        $file = $this->makeFile();
        $havo = $this->level('HAVO', 2);
        $vwo = $this->level('VWO', 3);

        // Level tags hang off the attachment, not the file: neither page can
        // change what the other says about it.
        $a = $first->downloads()->create(['media_file_id' => $file->id]);
        $a->educationLevels()->sync([$havo->id, $vwo->id]);

        $b = $second->downloads()->create(['media_file_id' => $file->id]);
        $b->educationLevels()->sync([$vwo->id]);

        $this->assertCount(2, $a->fresh()->educationLevels);
        $this->assertCount(1, $b->fresh()->educationLevels);
    }

    public function test_deleting_a_page_releases_its_downloads_and_unpublishes_the_file()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $file = $this->makeFile();
        $page->downloads()->create(['media_file_id' => $file->id]);

        $page->delete();

        $this->assertSame(0, PageDownload::query()->count());
        $this->get(route('media.show', $file))->assertForbidden();
    }

    public function test_guests_cannot_manage_downloads()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $download = $page->downloads()->create(['media_file_id' => $this->makeFile()->id]);

        $this->post(route('admin.pages.downloads.store', $page), ['media_file_id' => 1])
            ->assertRedirect(route('login'));
        $this->patch(route('admin.downloads.update', $download))->assertRedirect(route('login'));
        $this->delete(route('admin.downloads.destroy', $download))->assertRedirect(route('login'));
    }
}
