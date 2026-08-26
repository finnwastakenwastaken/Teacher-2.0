<?php

namespace Tests\Feature\Admin;

use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A concept is a page body that has been kept but not published.
 *
 * The trap this whole feature is built around, and what most of these tests
 * exist to pin: autosaving through Page::writeContent() would rebuild
 * `page_media_references` — the rows that make an embedded file fetchable by
 * an anonymous visitor — and re-derive `content_text`, which is one of the
 * three columns the `search_vector` trigger watches. So an autosave of a
 * half-written body would publish every image in it, including one the owner
 * pasted and then deleted, and would put an unfinished page in the public
 * search box.
 *
 * Neither failure is visible from the admin panel: the owner is authenticated,
 * so MediaAccess lets them see everything regardless, and nobody searches
 * their own site for a page they are in the middle of writing. That is why
 * these are asserted from the *anonymous* side.
 */
class PageDraftTest extends TestCase
{
    use RefreshDatabase;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->topic = Topic::query()->create([
            'title' => 'Hoofdstuk 1',
            'slug' => 'hoofdstuk-1',
        ]);
    }

    private function page(string $title = 'Les 1', string $slug = 'les-1'): Page
    {
        return Page::query()->create([
            'title' => $title,
            'slug' => $slug,
            'topic_id' => $this->topic->id,
        ]);
    }

    private function image(): Image
    {
        $path = 'images/2026/08/'.Str::ulid().'.png';
        Storage::disk('local')->put($path, 'image-bytes');

        return Image::query()->create([
            'path' => $path,
            'alt_text' => 'Een grafiek van de neerslag per maand',
            'size_bytes' => 11,
            'mime' => 'image/png',
            'original_filename' => 'neerslag.png',
        ]);
    }

    private function file(): MediaFile
    {
        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'file-bytes');

        return MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 10,
            'original_filename' => 'werkblad.pdf',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyShowing(Image $image, string $text): array
    {
        return ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            ['type' => 'imageGallery', 'attrs' => ['ulids' => [$image->ulid]]],
        ]];
    }

    /**
     * Stop being the administrator.
     *
     * actingAs() persists for the rest of the test, and MediaAccess::allows()
     * lets any authenticated user through by design — there is only ever one
     * account. So a "can an anonymous visitor fetch this" assertion made
     * without this is an assertion about the admin, and passes whatever the
     * draft column does. Both trap tests below were written without it first
     * and both went green against a deliberately published file.
     */
    private function stopBeingTheAdmin(): void
    {
        $this->post(route('logout'));
    }

    /**
     * @return list<string>
     */
    private function searchTitles(string $query): array
    {
        $titles = [];

        $this->get(route('search', ['q' => $query]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$titles) {
                $titles = collect($page->toArray()['props']['results'])
                    ->pluck('title')->all();
            });

        return $titles;
    }

    // ------------------------------------------------------------------
    // The trap.
    // ------------------------------------------------------------------

    /**
     * The single most important assertion in this file.
     *
     * Autosave a body that embeds an image, then ask the two questions an
     * anonymous visitor can ask — can I fetch the file, and can I find the
     * page — and get no to both. Then publish the very same document and get
     * yes to both, which is what proves the first half was the draft column
     * doing its job rather than the fixture being broken.
     */
    public function test_autosaving_a_body_publishes_neither_its_media_nor_the_page_itself()
    {
        $admin = User::factory()->create();
        $page = $this->page('Waterkringloop', 'waterkringloop');
        $image = $this->image();

        $document = $this->bodyShowing($image, 'De verdamping begint bij de oceaan.');

        $this->actingAs($admin)
            ->postJson(route('admin.pages.draft.store', $page), ['content' => $document])
            ->assertOk();

        $page->refresh();

        // Stored, and stored whitelisted — a concept is still a document that
        // came out of a browser.
        $this->assertNotNull($page->draft_content);
        $this->assertSame('doc', $page->draft_content['type']);
        $this->assertNotNull($page->draft_saved_at);

        // Nothing derived moved.
        $this->assertNull($page->content, 'The published body must be untouched.');
        $this->assertNull($page->content_text);
        $this->assertSame(
            0,
            $page->mediaReferences()->count(),
            'An autosave must not write page_media_references — those rows are what publish a file.'
        );

        // The trap, asked from outside.
        $this->stopBeingTheAdmin();

        $this->get(route('images.show', $image))
            ->assertForbidden();

        $this->assertSame(
            [],
            $this->searchTitles('verdamping'),
            'A concept must not reach the public search box.'
        );

        // Now publish the same document, and both answers flip.
        $this->actingAs($admin)
            ->put(route('admin.pages.content.update', $page), ['content' => $document])
            ->assertRedirect();

        $page->refresh();

        $this->assertNotNull($page->content);
        $this->assertStringContainsString('verdamping', (string) $page->content_text);
        $this->assertSame(1, $page->mediaReferences()->count());

        $this->stopBeingTheAdmin();

        $this->get(route('images.show', $image))->assertOk();

        $this->assertSame(['Waterkringloop'], $this->searchTitles('verdamping'));
    }

    /**
     * The half of the trap that is easiest to reopen by accident.
     *
     * An image pasted into a concept and then taken out again must never have
     * become fetchable in the meantime — there is no "unpublish" for a URL
     * that has already been handed out.
     */
    public function test_an_image_added_to_a_concept_and_removed_again_was_never_reachable()
    {
        $admin = User::factory()->create();
        $page = $this->page();
        $image = $this->image();

        $this->actingAs($admin)
            ->postJson(route('admin.pages.draft.store', $page), [
                'content' => $this->bodyShowing($image, 'Voorlopige tekst.'),
            ])
            ->assertOk();

        $this->stopBeingTheAdmin();
        $this->get(route('images.show', $image))->assertForbidden();

        // Thought better of it.
        $this->actingAs($admin)
            ->postJson(route('admin.pages.draft.store', $page), [
                'content' => ['type' => 'doc', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Voorlopige tekst.']]],
                ]],
            ])
            ->assertOk();

        $this->assertSame(0, $page->fresh()->mediaReferences()->count());

        $this->stopBeingTheAdmin();
        $this->get(route('images.show', $image))->assertForbidden();
    }

    /**
     * The search vector is maintained by a database trigger over title,
     * description and content_text. The trigger fires on the statement's SET
     * list, so a draft write that named content_text — even with an unchanged
     * value — would re-tokenise the body. It must not name it at all.
     */
    public function test_a_draft_write_leaves_the_search_vector_alone()
    {
        $admin = User::factory()->create();
        $page = $this->page('Krachten', 'krachten');

        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Een kracht verandert de beweging.']]],
        ]]);

        $before = DB::table('pages')->where('id', $page->id)->value('search_vector');

        $this->actingAs($admin)
            ->postJson(route('admin.pages.draft.store', $page), [
                'content' => ['type' => 'doc', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Wrijving remt een voorwerp af.']]],
                ]],
            ])
            ->assertOk();

        $this->assertSame(
            $before,
            DB::table('pages')->where('id', $page->id)->value('search_vector'),
        );

        $this->assertSame([], $this->searchTitles('wrijving'));
    }

    /**
     * The sitemap publishes `updated_at` as <lastmod>. An autosave that
     * touched it would tell every crawler a page had changed while the owner
     * was only typing — and would do it once every couple of seconds.
     */
    public function test_autosaving_does_not_touch_updated_at()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        DB::table('pages')->where('id', $page->id)
            ->update(['updated_at' => '2020-01-01 00:00:00']);

        $this->actingAs($admin)
            ->postJson(route('admin.pages.draft.store', $page), [
                'content' => ['type' => 'doc', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Iets nieuws.']]],
                ]],
            ])
            ->assertOk();

        $this->assertSame(
            '2020-01-01 00:00:00',
            (string) DB::table('pages')->where('id', $page->id)->value('updated_at'),
        );
    }

    /**
     * A concept is unpublished, not unconstrained. It arrives from a browser
     * like every other document, so the whitelist still applies on the way in
     * — otherwise the promote would be the first time anything looked at it,
     * and a stored document nobody has sanitised is exactly what "JSON, never
     * HTML" exists to prevent.
     */
    public function test_a_concept_is_whitelisted_on_the_way_in()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $this->actingAs($admin)
            ->postJson(route('admin.pages.draft.store', $page), [
                'content' => ['type' => 'doc', 'content' => [
                    ['type' => 'script', 'content' => [['type' => 'text', 'text' => 'alert(1)']]],
                    ['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'text' => 'Klik hier', 'marks' => [
                            ['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']],
                        ]],
                    ]],
                ]],
            ])
            ->assertOk();

        $stored = $page->fresh()->draft_content;

        $this->assertCount(1, $stored['content']);
        $this->assertSame('paragraph', $stored['content'][0]['type']);
        $this->assertArrayNotHasKey('marks', $stored['content'][0]['content'][0]);
    }

    // ------------------------------------------------------------------
    // Promote, discard, and the lifecycle around them.
    // ------------------------------------------------------------------

    public function test_promoting_publishes_the_concept_and_stops_it_being_one()
    {
        $page = $this->page();
        $file = $this->file();

        $page->writeDraft(['type' => 'doc', 'content' => [
            ['type' => 'fileEmbed', 'attrs' => ['ulid' => $file->ulid]],
        ]]);

        $this->assertTrue($page->promoteDraft());

        $page->refresh();

        $this->assertNull($page->draft_content);
        $this->assertNull($page->draft_saved_at);
        $this->assertFalse($page->hasDraft());
        $this->assertSame(1, $page->mediaReferences()->count());

        $this->get(route('media.show', $file))->assertOk();
    }

    public function test_promoting_without_a_concept_reports_that_it_did_nothing()
    {
        $this->assertFalse($this->page()->promoteDraft());
    }

    /**
     * `draft_content` is legitimately null for a page the owner emptied but
     * has not published yet, so "is there a concept" has to be answered by
     * the timestamp. Reading the document instead would make an emptied page
     * unpublishable — the promote would silently do nothing.
     */
    public function test_an_emptied_page_is_still_a_concept_and_still_publishes()
    {
        $page = $this->page();

        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Oude tekst.']]],
        ]]);

        $page->writeDraft(null);

        $this->assertTrue($page->hasDraft());
        $this->assertNull($page->draft_content);

        $this->assertTrue($page->promoteDraft());

        $this->assertNull($page->fresh()->content);
    }

    /**
     * Publishing is the one thing that ends a concept, and it ends it however
     * the body got published — the editor's own save included. A page left
     * offering a concept identical to its live body would be indistinguishable
     * from one with real unpublished work in it.
     */
    public function test_publishing_the_body_clears_any_concept()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $page->writeDraft(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Concept.']]],
        ]]);

        $this->actingAs($admin)
            ->put(route('admin.pages.content.update', $page), [
                'content' => ['type' => 'doc', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Gepubliceerd.']]],
                ]],
            ])
            ->assertRedirect();

        $page->refresh();

        $this->assertFalse($page->hasDraft());
        $this->assertStringContainsString('Gepubliceerd', (string) $page->content_text);
    }

    public function test_discarding_leaves_the_published_body_exactly_as_it_was()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'De echte tekst.']]],
        ]]);

        $published = $page->fresh()->content;

        $page->writeDraft(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Iets heel anders.']]],
        ]]);

        $this->actingAs($admin)
            ->delete(route('admin.pages.draft.destroy', $page))
            ->assertRedirect();

        $page->refresh();

        $this->assertFalse($page->hasDraft());
        $this->assertSame($published, $page->content);
    }

    /**
     * `is_hidden` is not the draft flag, and must never be repurposed as one.
     * A hidden page is a finished page the owner has not linked yet — it
     * renders at its direct URL on purpose, and DuplicatePage starts a copy
     * hidden. Keeping a concept must not change whether the page is visible.
     */
    public function test_keeping_a_concept_does_not_hide_the_page()
    {
        $page = $this->page();
        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Zichtbaar.']]],
        ]]);

        $page->writeDraft(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nog niet klaar.']]],
        ]]);

        $this->assertFalse($page->fresh()->is_hidden);

        // And the public page still shows the *published* body, not the one
        // being worked on.
        $this->get('/hoofdstuk-1/les-1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('page.content.content.0.content.0.text', 'Zichtbaar.'));
    }

    // ------------------------------------------------------------------
    // Where an outstanding concept is visible from.
    // ------------------------------------------------------------------

    /**
     * The content tree says which pages are holding one.
     *
     * Before this, the only way to find out was to open the page — so a page
     * could sit for weeks showing the owner one body and students another,
     * with nothing anywhere saying so.
     */
    public function test_the_content_tree_says_which_pages_have_a_concept()
    {
        $admin = User::factory()->create();

        $quiet = $this->page('Les 1', 'les-1');
        $working = $this->page('Les 2', 'les-2');

        $working->writeDraft(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nog niet klaar.']]],
        ]]);

        $this->actingAs($admin)
            ->get(route('admin.topics.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $inertia) use ($quiet, $working) {
                $pages = collect($inertia->toArray()['props']['tree'][0]['pages'])
                    ->keyBy('id');

                $this->assertFalse($pages[$quiet->id]['has_draft']);
                $this->assertTrue($pages[$working->id]['has_draft']);

                // The tree answers a boolean and ships neither the concept
                // nor the timestamp it was derived from. A jsonb body per row
                // to draw a badge is the expensive way to be told "yes".
                $this->assertArrayNotHasKey('draft_content', $pages[$working->id]);
                $this->assertArrayNotHasKey('draft_saved_at', $pages[$working->id]);
            });
    }

    /**
     * The same trap Page::hasDraft() documents, reached from the tree.
     *
     * A page the owner emptied and has not published yet has a null
     * `draft_content` and a real `draft_saved_at`. Deriving the badge from
     * the document would call that no concept at all — and it is precisely
     * the concept most worth flagging, because publishing it clears a page.
     */
    public function test_an_emptied_page_still_shows_as_having_a_concept_in_the_tree()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Gepubliceerd.']]],
        ]]);

        $page->writeDraft(null);

        $this->assertNull($page->fresh()->draft_content);

        $this->actingAs($admin)
            ->get(route('admin.topics.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('tree.0.pages.0.has_draft', true)
                ->etc());
    }

    // ------------------------------------------------------------------
    // The editor payload, and who may reach any of this.
    // ------------------------------------------------------------------

    public function test_the_editor_is_offered_the_concept_alongside_the_published_body()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Gepubliceerd.']]],
        ]]);

        $page->writeDraft(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Concept.']]],
        ]]);

        $this->actingAs($admin)
            ->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('page.content.content.0.content.0.text', 'Gepubliceerd.')
                ->where('draft.content.content.0.content.0.text', 'Concept.')
                ->whereNot('draft.savedAt', null));
    }

    public function test_a_page_without_a_concept_sends_none()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.pages.edit', $this->page()))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('draft', null));
    }

    /**
     * A serialised Page must never carry the concept along. The editor is
     * sent it deliberately, as its own prop; anything else handing a Page to
     * Inertia or to JSON would otherwise ship a body that has explicitly not
     * been published.
     */
    public function test_a_serialised_page_never_carries_the_concept()
    {
        $page = $this->page();

        $page->writeDraft(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Geheim concept.']]],
        ]]);

        $this->assertArrayNotHasKey('draft_content', $page->fresh()->toArray());
        $this->assertStringNotContainsString('Geheim concept', $page->fresh()->toJson());
    }

    public function test_a_guest_can_neither_write_nor_discard_a_concept()
    {
        $page = $this->page();

        $this->post(route('admin.pages.draft.store', $page), ['content' => null])
            ->assertRedirect(route('login'));

        $this->delete(route('admin.pages.draft.destroy', $page))
            ->assertRedirect(route('login'));

        $this->assertFalse($page->fresh()->hasDraft());
    }

    /**
     * An absent `content` key and an explicit null mean different things: null
     * is "the owner emptied this page", absence is a malformed request. With
     * `nullable` alone the two were the same, so a client that failed to send
     * the field would silently replace the concept with nothing.
     */
    public function test_a_request_without_a_content_key_is_refused()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $page->writeDraft(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bewaard.']]],
        ]]);

        $this->actingAs($admin)
            ->postJson(route('admin.pages.draft.store', $page), [])
            ->assertStatus(422);

        $this->assertNotNull($page->fresh()->draft_content);
    }
}
