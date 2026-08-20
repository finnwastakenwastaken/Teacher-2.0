<?php

namespace Tests\Feature\Admin;

use App\Models\AccessPassword;
use App\Models\EducationLevel;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The page editor screen is assembled from several independent features, so
 * the props it needs are easy to drop when one of them changes. A missing
 * prop is a blank section rather than an error, which is exactly the kind of
 * breakage nothing else notices.
 */
class PageEditorPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_editor_screen_receives_everything_it_renders()
    {
        Storage::fake('local');

        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $page = Page::query()->create([
            'title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $topic->id,
        ]);

        $level = EducationLevel::query()->create(['name' => 'HAVO', 'slug' => 'havo', 'sort_order' => 0]);
        AccessPassword::createWithPassword('5 VWO', 'geheim');

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        $file = MediaFile::query()->create([
            'path' => $path, 'kind' => MediaFile::KIND_DOCUMENT, 'mime' => 'application/pdf',
            'size_bytes' => 5, 'original_filename' => 'werkblad.pdf',
        ]);

        $download = $page->downloads()->create(['media_file_id' => $file->id, 'label' => 'Werkblad']);
        $download->educationLevels()->sync([$level->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.pages.edit', $page))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->component('admin/pages/edit')
                ->has('mediaLibrary.images')
                ->has('mediaLibrary.files')
                ->has('passwords', 1)
                ->has('educationLevels', 1)
                ->has('downloadFiles', 1)
                // The attachment endpoint keys on the numeric id, which the
                // editor's ULID-only media library deliberately does not carry.
                ->where('downloadFiles.0.id', $file->id)
                ->has('downloads', 1)
                ->where('downloads.0.label', 'Werkblad')
                ->where('downloads.0.educationLevelIds', [$level->id])
                ->where('downloads.0.mediaFileId', $file->id)
                // The editor uploads too, so it needs the same ceiling the
                // media screen shows.
                ->where('uploadMaxBytes', (int) config('media.max_bytes'))
            );
    }

    public function test_the_topic_and_page_forms_receive_the_password_list()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        AccessPassword::createWithPassword('5 VWO', 'geheim');

        $this->actingAs(User::factory()->create());

        $this->get(route('admin.topics.create'))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia->has('passwords', 1));

        $this->get(route('admin.topics.edit', $topic))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia->has('passwords', 1));

        $this->get(route('admin.pages.create'))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia->has('passwords', 1));
    }

    public function test_a_password_can_be_applied_to_a_topic_through_the_form()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $password = AccessPassword::createWithPassword('5 VWO', 'geheim');

        $this->actingAs(User::factory()->create())
            ->put(route('admin.topics.update', $topic), [
                'title' => 'Natuurkunde',
                'slug' => 'natuurkunde',
                'parent_id' => null,
                'sort_order' => 0,
                'is_hidden' => false,
                'access_password_id' => $password->id,
            ])
            ->assertRedirect(route('admin.topics.index'));

        $this->assertSame($password->id, $topic->fresh()->access_password_id);
    }

    public function test_choosing_no_password_clears_it_rather_than_failing()
    {
        $password = AccessPassword::createWithPassword('5 VWO', 'geheim');
        $topic = Topic::query()->create([
            'title' => 'Natuurkunde', 'slug' => 'natuurkunde',
            'access_password_id' => $password->id,
        ]);

        // The select submits an empty string for "geen wachtwoord"; that has
        // to become a real null, not an empty string cast to 0.
        $this->actingAs(User::factory()->create())
            ->put(route('admin.topics.update', $topic), [
                'title' => 'Natuurkunde',
                'slug' => 'natuurkunde',
                'parent_id' => null,
                'sort_order' => 0,
                'is_hidden' => false,
                'access_password_id' => '',
            ])
            ->assertRedirect(route('admin.topics.index'));

        $this->assertNull($topic->fresh()->access_password_id);
    }
}
