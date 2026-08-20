<?php

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\Page;
use App\Models\PageDownload;
use App\Models\Topic;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function topic(string $title, string $slug, bool $hidden = false): Topic
    {
        return Topic::query()->create([
            'title' => $title,
            'slug' => $slug,
            'is_hidden' => $hidden,
        ]);
    }

    private function page(Topic $topic, string $title, string $slug, ?array $content = null): Page
    {
        return Page::query()->create([
            'title' => $title,
            'slug' => $slug,
            'topic_id' => $topic->id,
            'content' => $content,
        ]);
    }

    private function download(Page $page, int $count = 0): PageDownload
    {
        Storage::fake('local');

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');

        $file = MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 5,
            'original_filename' => 'werkblad.pdf',
        ]);

        $download = $page->downloads()->create(['media_file_id' => $file->id]);

        if ($count > 0) {
            $download->forceFill(['downloads_count' => $count])->saveQuietly();
        }

        return $download->fresh();
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($this->admin());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    }

    public function test_a_fresh_install_has_every_setup_step_outstanding()
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(function (Assert $page) {
                $steps = collect($page->toArray()['props']['nextSteps']);

                $this->assertSame(
                    ['branding', 'topic', 'page', 'content', 'media', 'download'],
                    $steps->pluck('key')->all(),
                );
                $this->assertTrue($steps->every(fn (array $step) => $step['done'] === false));
            });
    }

    public function test_setup_steps_complete_as_the_site_is_filled_in()
    {
        $topic = $this->topic('Natuurkunde', 'natuurkunde');
        $page = $this->page($topic, 'De Planeten', 'de-planeten');
        $this->download($page);
        SiteSettings::put(['site_title' => 'Natuurkunde bij De Vries']);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(function (Assert $assert) {
                $steps = collect($assert->toArray()['props']['nextSteps'])
                    ->keyBy('key')
                    ->map(fn (array $step) => $step['done']);

                $this->assertTrue($steps['branding']);
                $this->assertTrue($steps['topic']);
                $this->assertTrue($steps['page']);
                $this->assertTrue($steps['download']);
                // Offering a file as a download necessarily put it in the
                // library, so that step is done as a side effect.
                $this->assertTrue($steps['media']);

                // The page exists, but its body is still null.
                $this->assertFalse($steps['content']);
            });
    }

    public function test_the_content_step_completes_once_a_page_has_a_body()
    {
        $topic = $this->topic('Natuurkunde', 'natuurkunde');
        $this->page($topic, 'De Planeten', 'de-planeten', [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hallo']]],
            ],
        ]);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $assert) => $assert
                ->where('stats.emptyPages', 0)
                ->where(
                    'nextSteps',
                    fn ($steps) => collect($steps)->firstWhere('key', 'content')['done'] === true,
                )
            );
    }

    public function test_the_statistics_count_what_is_on_the_site()
    {
        $topic = $this->topic('Natuurkunde', 'natuurkunde');
        $this->topic('Scheikunde', 'scheikunde', hidden: true);

        $page = $this->page($topic, 'De Planeten', 'de-planeten');
        $this->page($topic, 'Zwaartekracht', 'zwaartekracht');

        $this->download($page, count: 12);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $assert) => $assert
                ->where('stats.topics', 2)
                ->where('stats.hiddenTopics', 1)
                ->where('stats.pages', 2)
                ->where('stats.emptyPages', 2)
                ->where('stats.documents', 1)
                ->where('stats.videos', 0)
                ->where('stats.downloads', 1)
                ->where('stats.downloadsServed', 12)
                ->etc()
            );
    }

    public function test_recently_edited_pages_are_listed_newest_first_with_their_path()
    {
        $topic = $this->topic('Natuurkunde', 'natuurkunde');
        $child = Topic::query()->create([
            'title' => 'Sterrenkunde',
            'slug' => 'sterrenkunde',
            'parent_id' => $topic->id,
        ]);

        $older = $this->page($topic, 'Zwaartekracht', 'zwaartekracht');
        $older->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        $this->page($child, 'De Planeten', 'de-planeten');

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $assert) => $assert
                ->where('recentPages.0.title', 'De Planeten')
                ->where('recentPages.0.path', '/natuurkunde/sterrenkunde/de-planeten')
                ->where('recentPages.0.isEmpty', true)
                ->where('recentPages.1.title', 'Zwaartekracht')
                ->etc()
            );
    }

    public function test_only_downloads_that_were_actually_taken_are_listed()
    {
        $topic = $this->topic('Natuurkunde', 'natuurkunde');
        $page = $this->page($topic, 'De Planeten', 'de-planeten');

        $this->download($page, count: 0);
        $taken = $this->download($page, count: 7);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $assert) => $assert
                ->has('popularDownloads', 1)
                ->where('popularDownloads.0.id', $taken->id)
                ->where('popularDownloads.0.count', 7)
                ->where('popularDownloads.0.page', 'De Planeten')
                ->etc()
            );
    }
}
