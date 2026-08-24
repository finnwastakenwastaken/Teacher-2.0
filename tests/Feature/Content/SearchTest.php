<?php

namespace Tests\Feature\Content;

use App\Models\AccessPassword;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
    }

    private function page(string $title, string $slug, ?string $body = null, array $attributes = []): Page
    {
        $page = Page::query()->create([
            'title' => $title,
            'slug' => $slug,
            'topic_id' => $this->topic->id,
            ...$attributes,
        ]);

        if ($body !== null) {
            $page->writeContent(['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $body]]],
            ]]);
        }

        return $page->fresh();
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function search(string $query, array $cookies = []): AssertableInertia
    {
        $inertia = null;

        $this->withCookies($cookies)
            ->get(route('search', ['q' => $query]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$inertia) {
                $inertia = $page;
            });

        return $inertia;
    }

    /**
     * @param  array<string, string>  $cookies
     * @return list<string>
     */
    private function titles(string $query, array $cookies = []): array
    {
        return collect($this->search($query, $cookies)->toArray()['props']['results'])
            ->pluck('title')->all();
    }

    public function test_the_vector_is_maintained_by_the_database_not_the_application()
    {
        $page = $this->page('Zwaartekracht', 'zwaartekracht');

        $this->assertNotNull(
            DB::table('pages')->where('id', $page->id)->value('search_vector'),
            'Inserting a page should have populated search_vector via the trigger.'
        );
    }

    public function test_a_page_is_found_by_a_word_in_its_title()
    {
        $this->page('Zwaartekracht', 'zwaartekracht');
        $this->page('Elektriciteit', 'elektriciteit');

        $this->assertSame(['Zwaartekracht'], $this->titles('zwaartekracht'));
    }

    public function test_a_page_is_found_by_a_word_in_its_body()
    {
        $this->page('De Planeten', 'de-planeten', 'Jupiter is de grootste planeet van ons zonnestelsel.');

        $this->assertSame(['De Planeten'], $this->titles('Jupiter'));
    }

    /**
     * The reason for the `dutch` configuration rather than `simple`: Dutch
     * inflects and compounds heavily, so a literal match finds far too little.
     */
    public function test_search_is_stemmed_so_a_plural_finds_the_singular()
    {
        $this->page('Krachten', 'krachten', 'Een kracht verandert de beweging van een voorwerp.');

        $this->assertSame(['Krachten'], $this->titles('kracht'));
        $this->assertSame(['Krachten'], $this->titles('bewegingen'));
    }

    public function test_a_title_match_outranks_a_body_mention()
    {
        $this->page('Magnetisme', 'magnetisme', 'Over polen en velden.');
        $this->page('Elektriciteit', 'elektriciteit', 'Elektriciteit en magnetisme hangen samen.');

        $this->assertSame(['Magnetisme', 'Elektriciteit'], $this->titles('magnetisme'));
    }

    public function test_results_carry_a_snippet_and_a_link()
    {
        $this->page('De Planeten', 'de-planeten', 'Jupiter is de grootste planeet van ons zonnestelsel.');

        $result = $this->search('Jupiter')->toArray()['props']['results'][0];

        $this->assertSame('/natuurkunde/de-planeten', $result['href']);
        $this->assertStringContainsString('Jupiter', $result['snippet']);
    }

    public function test_hidden_pages_are_never_in_the_results()
    {
        $this->page('Concept', 'concept', 'Zwaartekracht uitleg.', ['is_hidden' => true]);

        $this->assertSame([], $this->titles('zwaartekracht'));

        // Still reachable by direct link — hidden is not deleted.
        $this->get('/natuurkunde/concept')->assertOk();
    }

    /**
     * Hiding a topic has to hide what is under it.
     *
     * This was the gap: the query filters pages.is_hidden, so a page that is
     * itself visible stayed fully searchable — title and snippet — while its
     * topic was hidden from every menu. Hiding a retired subject therefore
     * did not retire it. The sitemap had always walked the ancestor chain;
     * search now uses the same function rather than its own rule.
     */
    public function test_pages_under_a_hidden_topic_are_never_in_the_results()
    {
        $retired = Topic::query()->create([
            'title' => 'Oud programma',
            'slug' => 'oud-programma',
            'is_hidden' => true,
        ]);

        $page = Page::query()->create([
            'title' => 'Oude toetsstof',
            'slug' => 'oude-toetsstof',
            'topic_id' => $retired->id,
        ]);

        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Zwaartekracht uitleg.']]],
        ]]);

        $this->assertSame([], $this->titles('zwaartekracht'));

        // And the same for a page two levels down, because the walk has to
        // reach the whole chain rather than only the immediate parent.
        $sub = Topic::query()->create([
            'title' => 'Hoofdstuk 3',
            'slug' => 'hoofdstuk-3',
            'parent_id' => $retired->id,
        ]);

        $deep = Page::query()->create([
            'title' => 'Nog dieper',
            'slug' => 'nog-dieper',
            'topic_id' => $sub->id,
        ]);

        $deep->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Zwaartekracht uitleg.']]],
        ]]);

        $this->assertSame([], $this->titles('zwaartekracht'));

        // Hidden is still not deleted: the direct link works.
        $this->get('/oud-programma/oude-toetsstof')->assertOk();
    }

    public function test_a_protected_page_is_absent_until_it_is_unlocked()
    {
        $password = AccessPassword::createWithPassword('5 VWO', 'geheim');
        $page = $this->page('Toetsstof', 'toetsstof', 'Zwaartekracht en beweging.');
        $page->update(['access_password_id' => $password->id]);

        // The title alone would leak what the password is there to withhold.
        $this->assertSame([], $this->titles('zwaartekracht'));

        $cookie = ['unlock_'.$password->id => substr(hash('sha256', $password->password_hash), 0, 32)];

        $this->assertSame(['Toetsstof'], $this->titles('zwaartekracht', $cookie));
    }

    public function test_the_admin_sees_protected_pages_in_search()
    {
        $password = AccessPassword::createWithPassword('5 VWO', 'geheim');
        $page = $this->page('Toetsstof', 'toetsstof', 'Zwaartekracht en beweging.');
        $page->update(['access_password_id' => $password->id]);

        $this->actingAs(User::factory()->create());

        $this->assertSame(['Toetsstof'], $this->titles('zwaartekracht'));
    }

    public function test_an_empty_query_returns_nothing_rather_than_everything()
    {
        $this->page('Zwaartekracht', 'zwaartekracht');

        $this->assertSame([], $this->titles(''));
        $this->assertSame([], $this->titles('   '));
    }

    public function test_punctuation_in_the_query_does_not_break_the_search()
    {
        $this->page('Zwaartekracht', 'zwaartekracht', 'Uitleg over vallen.');

        // to_tsquery would throw on these; websearch_to_tsquery must not.
        foreach (['zwaartekracht & | ! ( )', "'zwaartekracht", 'zwaartekracht:*!'] as $query) {
            $this->get(route('search', ['q' => $query]))->assertOk();
        }
    }

    public function test_a_quoted_phrase_matches_only_the_phrase()
    {
        $this->page('Eerste wet', 'eerste-wet', 'Een voorwerp blijft in rust.');
        $this->page('Tweede wet', 'tweede-wet', 'Rust is niet hetzelfde als een voorwerp zonder massa.');

        $this->assertSame(['Eerste wet'], $this->titles('"voorwerp blijft in rust"'));
    }

    public function test_editing_a_page_body_updates_what_it_is_found_by()
    {
        $page = $this->page('Les 1', 'les-1', 'Over zwaartekracht.');

        $this->assertSame(['Les 1'], $this->titles('zwaartekracht'));

        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Over magnetisme.']]],
        ]]);

        $this->assertSame([], $this->titles('zwaartekracht'));
        $this->assertSame(['Les 1'], $this->titles('magnetisme'));
    }
}
