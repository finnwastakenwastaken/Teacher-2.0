<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Models\Setting;
use App\Models\Topic;
use App\Support\ContentLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The stored search vector follows the owner's writing language, never the
 * visitor's interface locale. Assertions target the vector itself, not
 * rendered results — a page stemmed by stale rules still renders fine and
 * just stops being findable.
 */
class ContentLanguageTest extends TestCase
{
    use RefreshDatabase;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->topic = Topic::query()->create([
            'title' => 'Science',
            'slug' => 'science',
        ]);
    }

    private function page(string $title): Page
    {
        return Page::query()->create([
            'title' => $title,
            'slug' => 'p-'.str()->random(6),
            'topic_id' => $this->topic->id,
        ]);
    }

    private function vector(Page $page): string
    {
        return (string) DB::table('pages')
            ->where('id', $page->id)
            ->value(DB::raw('search_vector::text'));
    }

    private function setLanguage(string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => ContentLanguage::SETTING],
            ['value' => $value],
        );
    }

    public function test_the_default_is_dutch_with_no_setting_stored()
    {
        $this->assertSame('dutch', ContentLanguage::current());

        $configuration = DB::selectOne('select content_search_config()::text as name');

        $this->assertSame('dutch', $configuration->name);
    }

    /**
     * The whole point of the setting: the same title stems differently, so a
     * search for the singular finds the plural.
     */
    public function test_the_setting_decides_how_a_page_is_stemmed()
    {
        $dutch = $this->page('Krachten');

        $this->setLanguage('english');

        $english = $this->page('Forces');

        // Dutch stems "krachten" to "kracht"; English stems "forces" to
        // "forc" — neither happens under the other configuration.
        $this->assertStringContainsString('kracht', $this->vector($dutch));
        $this->assertStringContainsString('forc', $this->vector($english));
        $this->assertStringNotContainsString('forces', $this->vector($english));
    }

    /**
     * Without this, changing the setting appears to work and search quietly
     * keeps missing words on every page written before the change.
     */
    public function test_reindexing_re_derives_pages_written_under_the_old_setting()
    {
        $page = $this->page('Forces');

        // The Dutch stemmer has no rule for this word and stores it whole.
        $this->assertStringContainsString("'forces'", $this->vector($page));

        $this->setLanguage('english');

        // Still the old stemming: the trigger only fires on a write.
        $this->assertStringContainsString("'forces'", $this->vector($page));

        $this->artisan('search:reindex')->assertSuccessful();

        $this->assertStringContainsString("'forc'", $this->vector($page));
        $this->assertStringNotContainsString("'forces'", $this->vector($page));
    }

    /**
     * A regconfig cast would raise on every page save if the stored value
     * were ever wrong, so the lookup falls back to `dutch` instead.
     */
    public function test_an_unknown_stored_value_falls_back_instead_of_throwing()
    {
        $this->setLanguage('klingon');

        $configuration = DB::selectOne('select content_search_config()::text as name');

        $this->assertSame('dutch', $configuration->name);

        $page = $this->page('Krachten');

        $this->assertStringContainsString('kracht', $this->vector($page));

        // And PHP agrees, so the settings screen never shows a value it
        // would then refuse to save back.
        $this->assertSame('dutch', ContentLanguage::current());
    }

    public function test_the_search_query_uses_the_same_configuration_as_the_index()
    {
        $this->setLanguage('english');

        $page = $this->page('Forces');
        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Gravity pulls objects towards each other.'],
            ]],
        ]]);

        // Singular in, plural indexed: only a matching stemmer finds it.
        $response = $this->get('/zoeken?q=force');

        $response->assertOk();
        $response->assertSee($page->title);
    }

    public function test_a_visitor_s_interface_language_does_not_change_the_index()
    {
        $page = $this->page('Krachten');
        $before = $this->vector($page);

        // An English-reading visitor searching a Dutch site still gets the
        // Dutch stemmer: the corpus follows the owner, not the visitor.
        $this->withCookie('locale', 'en')->get('/zoeken?q=kracht')->assertOk();

        $this->assertSame($before, $this->vector($page->fresh()));
        $this->assertSame('dutch', ContentLanguage::current());
    }
}
