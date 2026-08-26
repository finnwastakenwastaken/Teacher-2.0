<?php

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\Page;
use App\Models\PageDownload;
use App\Models\Topic;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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
        $page = Page::query()->create([
            'title' => $title,
            'slug' => $slug,
            'topic_id' => $topic->id,
        ]);

        // writeContent(), not mass assignment: it's the only writer that whitelists
        // the document and derives content_text.
        if ($content !== null) {
            $page->writeContent($content);
        }

        return $page;
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
                // A download attachment implies the media step too.
                $this->assertTrue($steps['media']);

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

    /*
     * An unpublished concept is otherwise discoverable only by opening the
     * page it is on, which is what item 28 was about. These four pin the
     * three decisions worth pinning: which pages are reported, that the
     * *timestamp* is what decides, that the concept itself never rides along,
     * and that none of it touches the setup checklist.
     */

    public function test_a_page_with_an_unpublished_concept_is_reported_and_one_without_is_not()
    {
        $topic = $this->topic('Natuurkunde', 'natuurkunde');

        $published = $this->page($topic, 'Zwaartekracht', 'zwaartekracht', [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Gepubliceerd']]],
            ],
        ]);

        $concept = $this->page($topic, 'De Planeten', 'de-planeten');
        $concept->writeDraft([
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nog niet af']]],
            ],
        ]);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $assert) => $assert
                ->has('draftPages', 1)
                ->where('draftPages.0.id', $concept->id)
                ->where('draftPages.0.title', 'De Planeten')
                // The list carries a title and when it was saved, never the
                // document — draft_content is hidden on the model for exactly
                // this reason, and the dashboard has no business shipping a
                // body the owner has not published.
                ->where('draftPages.0.savedAt', fn ($savedAt) => is_string($savedAt))
                ->where(
                    'draftPages.0',
                    fn (Collection $row) => $row->keys()->sort()->values()->all()
                        === ['id', 'savedAt', 'title'],
                )
                ->etc()
            );

        $this->assertFalse($published->fresh()->hasDraft());
    }

    public function test_a_concept_that_empties_the_page_is_still_a_concept()
    {
        // The one case reading draft_content instead of draft_saved_at gets
        // wrong: the owner has cleared the body and not published it yet, so
        // the document is legitimately null and there is still something
        // unpublished waiting. See Page::hasDraft().
        $topic = $this->topic('Natuurkunde', 'natuurkunde');
        $page = $this->page($topic, 'De Planeten', 'de-planeten', [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Gepubliceerd']]],
            ],
        ]);

        $page->writeDraft(null);

        $this->assertNull($page->fresh()->draft_content);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $assert) => $assert
                ->has('draftPages', 1)
                ->where('draftPages.0.id', $page->id)
                ->etc()
            );
    }

    public function test_a_concept_does_not_become_a_setup_step()
    {
        // The checklist is a first-run affordance that disappears once every
        // step is done. A concept comes and goes for the life of the site, so
        // folding it in would keep resurrecting a finished list — hence its
        // own prop.
        $topic = $this->topic('Natuurkunde', 'natuurkunde');
        $page = $this->page($topic, 'De Planeten', 'de-planeten');
        $page->writeDraft(['type' => 'doc', 'content' => []]);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(function (Assert $assert) {
                $steps = collect($assert->toArray()['props']['nextSteps']);

                $this->assertSame(
                    ['branding', 'topic', 'page', 'content', 'media', 'download'],
                    $steps->pluck('key')->all(),
                );
            });
    }

    public function test_publishing_a_concept_takes_the_page_off_the_list()
    {
        $topic = $this->topic('Natuurkunde', 'natuurkunde');
        $page = $this->page($topic, 'De Planeten', 'de-planeten');
        $page->writeDraft([
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bijna af']]],
            ],
        ]);

        $this->assertTrue($page->fresh()->promoteDraft());

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $assert) => $assert->has('draftPages', 0)->etc());
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
