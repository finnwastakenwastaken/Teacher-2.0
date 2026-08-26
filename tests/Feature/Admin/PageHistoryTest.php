<?php

namespace Tests\Feature\Admin;

use App\Models\Image;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The last ten published bodies of a page, and the way back to one.
 *
 * Three things here are the design rather than the implementation, and each
 * has a test that fails if it is undone:
 *
 *   - a **publish** is the unit, never an autosave. The debounce fires while
 *     the owner is typing, so a history of concepts would be ten seconds of
 *     one sentence and the version they actually want would already have been
 *     pruned out of it.
 *   - a revision holds the body being **replaced**, so restoring is itself a
 *     publish and the list is append-only. Looking at an old version can
 *     never cost you the one you were on.
 *   - restoring goes through Page::writeContent(), so `page_media_references`
 *     is rebuilt and the restored body's pictures work for **students**. That
 *     is the one assertion here that cannot be made while logged in: the owner
 *     is authenticated and MediaAccess lets them see everything regardless.
 */
class PageHistoryTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function body(string $text): array
    {
        return ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ]];
    }

    /**
     * The first paragraph of each stored version, newest first.
     *
     * @return list<string>
     */
    private function history(Page $page): array
    {
        return $page->revisions()->get()
            ->map(fn (PageRevision $revision) => (string) ($revision->content['content'][0]['content'][0]['text'] ?? ''))
            ->all();
    }

    /**
     * See PageDraftTest: actingAs() persists, and MediaAccess lets any
     * authenticated user through by design, so an "can an anonymous visitor
     * fetch this" assertion made without this passes whatever the code does.
     */
    private function stopBeingTheAdmin(): void
    {
        $this->post(route('logout'));
    }

    // ------------------------------------------------------------------
    // What counts as a version.
    // ------------------------------------------------------------------

    /**
     * A publish keeps the body it replaced — not the one it wrote.
     *
     * What the owner is reaching for is the version they just lost; the one
     * they have is on the page row. Recording the incoming body instead would
     * give a list in which every entry duplicated the live page and the thing
     * worth going back to was never kept at all.
     */
    public function test_publishing_records_the_body_it_replaced()
    {
        $page = $this->page();

        $page->writeContent($this->body('Eerste versie'));
        $page->writeContent($this->body('Tweede versie'));

        $this->assertSame(['Eerste versie'], $this->history($page));
        $this->assertSame(
            'Tweede versie',
            $page->fresh()->content['content'][0]['content'][0]['text'],
            'The live body must be the one just written, not a stored version.'
        );
    }

    /**
     * The plain-text copy travels with the document rather than being derived
     * a second time. It is what the search vector is built from, and two
     * derivations of one document in two places is one more thing that can
     * drift.
     */
    public function test_a_version_keeps_the_derived_text_that_was_published_with_it()
    {
        $page = $this->page();

        $page->writeContent($this->body('De verdamping begint bij de oceaan'));
        $page->writeContent($this->body('Iets heel anders'));

        $revision = $page->revisions()->sole();

        $this->assertStringContainsString('verdamping', (string) $revision->content_text);
    }

    /**
     * A page nobody has written yet has nothing worth going back to, so its
     * first publish records nothing. Without this every page in the site would
     * carry one entry holding an empty body.
     */
    public function test_the_first_publish_of_an_empty_page_records_nothing()
    {
        $page = $this->page();

        $page->writeContent($this->body('De eerste tekst'));

        $this->assertSame(0, $page->revisions()->count());
    }

    /**
     * The whole reason the unit is a publish.
     *
     * The autosave debounces on the document and fires while the owner is
     * still typing, so a history built on it would be a rolling window of the
     * last ten keystrokes — and the version actually worth restoring would
     * have been pruned out of it long before anybody looked.
     */
    public function test_autosaving_a_concept_records_no_version()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $page->writeContent($this->body('Gepubliceerd'));

        foreach (['Concept een', 'Concept twee', 'Concept drie'] as $text) {
            $this->actingAs($admin)
                ->postJson(route('admin.pages.draft.store', $page), ['content' => $this->body($text)])
                ->assertOk();
        }

        $this->assertSame(0, $page->revisions()->count());

        // And publishing that concept records exactly one — the body it
        // replaced, not the three drafts on the way to it.
        $this->actingAs($admin)
            ->put(route('admin.pages.content.update', $page), ['content' => $this->body('Concept drie')])
            ->assertRedirect();

        $this->assertSame(['Gepubliceerd'], $this->history($page));
    }

    /**
     * Discarding a concept is not a publish either: the published body never
     * changed, so there is nothing to have replaced.
     */
    public function test_discarding_a_concept_records_no_version()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $page->writeContent($this->body('Gepubliceerd'));
        $page->writeDraft($this->body('Toch maar niet'));

        $this->actingAs($admin)
            ->delete(route('admin.pages.draft.destroy', $page))
            ->assertRedirect();

        $this->assertSame(0, $page->revisions()->count());
    }

    // ------------------------------------------------------------------
    // The cap.
    // ------------------------------------------------------------------

    /**
     * Ten, and the eleventh publish drops the oldest.
     *
     * The cap is the design rather than a setting: every one of these rides
     * along in the `database.sql` inside every backup archive, so an uncapped
     * table would make every archive grow with the number of times somebody
     * pressed save. Pruned inside the same transaction as the write, so the
     * list can never be read above ten.
     */
    public function test_the_history_never_grows_past_ten_and_drops_the_oldest()
    {
        $page = $this->page();

        // Twelve publishes: the first records nothing (empty page), so
        // versions 1 through 11 are recorded and the oldest one falls off.
        for ($n = 1; $n <= 12; $n++) {
            $page->writeContent($this->body('Versie '.$n));
        }

        $this->assertSame(PageRevision::KEEP, $page->revisions()->count());

        $this->assertSame(
            ['Versie 11', 'Versie 10', 'Versie 9', 'Versie 8', 'Versie 7',
                'Versie 6', 'Versie 5', 'Versie 4', 'Versie 3', 'Versie 2'],
            $this->history($page),
            'Newest first, and "Versie 1" is the one that was dropped.'
        );
    }

    /**
     * The cap is per page. One busy page must not push another page's history
     * out from under it.
     */
    public function test_the_cap_is_counted_per_page()
    {
        $busy = $this->page('Les 1', 'les-1');
        $quiet = $this->page('Les 2', 'les-2');

        $quiet->writeContent($this->body('Rustig een'));
        $quiet->writeContent($this->body('Rustig twee'));

        for ($n = 1; $n <= 15; $n++) {
            $busy->writeContent($this->body('Druk '.$n));
        }

        $this->assertSame(PageRevision::KEEP, $busy->revisions()->count());
        $this->assertSame(['Rustig een'], $this->history($quiet));
    }

    // ------------------------------------------------------------------
    // Restoring.
    // ------------------------------------------------------------------

    /**
     * Restoring appends; it never rewinds.
     *
     * Version 7 goes back on the site and the body it displaced becomes the
     * newest entry, so the owner can look at an old version, put it back, and
     * still change their mind. Rewinding the list instead — dropping
     * everything after the entry restored — would make "have a look at the
     * old one" a way to lose the current one.
     */
    public function test_restoring_publishes_and_writes_a_new_version_rather_than_rewinding()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $page->writeContent($this->body('Eerste'));
        $page->writeContent($this->body('Tweede'));
        $page->writeContent($this->body('Derde'));

        $this->assertSame(['Tweede', 'Eerste'], $this->history($page));

        // The relation is newest first, so the oldest is the last of them.
        $oldest = $page->revisions()->get()->last();

        $this->actingAs($admin)
            ->post(route('admin.pages.revisions.restore', [$page, $oldest]))
            ->assertRedirect(route('admin.pages.edit', $page));

        $page->refresh();

        $this->assertSame('Eerste', $page->content['content'][0]['content'][0]['text']);
        $this->assertStringContainsString('Eerste', (string) $page->content_text);

        // The list grew by one and lost nothing: "Derde" is what the restore
        // replaced, and both earlier entries are still there.
        $this->assertSame(['Derde', 'Tweede', 'Eerste'], $this->history($page));
    }

    /**
     * The invariant the whole restore path exists to protect.
     *
     * A restored body's embedded media has to be fetchable by an anonymous
     * visitor again — which means `page_media_references` was rebuilt, which
     * means the restore went through writeContent() rather than copying a
     * column. A column copy would leave a page full of 403s that looks
     * perfectly correct to the owner, because they are authenticated.
     */
    public function test_a_restored_body_publishes_its_media_again()
    {
        $admin = User::factory()->create();
        $page = $this->page('Waterkringloop', 'waterkringloop');
        $image = $this->image();

        $withImage = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'De verdamping']]],
            ['type' => 'imageGallery', 'attrs' => ['ulids' => [$image->ulid]]],
        ]];

        $page->writeContent($withImage);

        $this->stopBeingTheAdmin();
        $this->get(route('images.show', $image))->assertOk();

        // The picture is taken out again, so nothing points at it any more.
        $page->writeContent($this->body('Zonder plaatje'));

        $this->assertSame(0, $page->fresh()->mediaReferences()->count());

        $this->stopBeingTheAdmin();
        $this->get(route('images.show', $image))->assertForbidden();

        // Put the old body back, and the file is published again.
        $revision = $page->revisions()->get()->last();

        $this->actingAs($admin)
            ->post(route('admin.pages.revisions.restore', [$page, $revision]))
            ->assertRedirect();

        $this->assertSame(1, $page->fresh()->mediaReferences()->count());

        $this->stopBeingTheAdmin();
        $this->get(route('images.show', $image))->assertOk();
    }

    /**
     * Restoring is a publish, and a publish ends the concept — the same rule
     * that makes "Opslaan en publiceren" clear it. The confirmation says so
     * before it happens (see components/editor/version-history.tsx); what is
     * pinned here is that the two do not disagree.
     */
    public function test_restoring_clears_any_concept()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $page->writeContent($this->body('Eerste'));
        $page->writeContent($this->body('Tweede'));
        $page->writeDraft($this->body('Onafgemaakt'));

        $this->actingAs($admin)
            ->post(route('admin.pages.revisions.restore', [$page, $page->revisions()->sole()]))
            ->assertRedirect();

        $this->assertFalse($page->fresh()->hasDraft());
    }

    /**
     * A restored body is stemmed into the search vector like any other
     * publish. It has to be: search would otherwise keep answering with the
     * body the site no longer serves.
     */
    public function test_a_restored_body_is_what_the_search_box_finds()
    {
        $admin = User::factory()->create();
        $page = $this->page('Waterkringloop', 'waterkringloop');

        $page->writeContent($this->body('De verdamping begint bij de oceaan'));
        $page->writeContent($this->body('Wrijving remt een voorwerp af'));

        $this->actingAs($admin)
            ->post(route('admin.pages.revisions.restore', [$page, $page->revisions()->sole()]))
            ->assertRedirect();

        $this->stopBeingTheAdmin();

        $this->get(route('search', ['q' => 'verdamping']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('results.0.title', 'Waterkringloop'));
    }

    // ------------------------------------------------------------------
    // Deleting, scoping, and who may reach any of this.
    // ------------------------------------------------------------------

    /**
     * A revision is a previous state of the page, not a dependent record — so
     * this is the one foreign key in the schema that cascades rather than
     * blocking. A page whose delete was refused because it had been published
     * twice would be undeletable for a reason nobody could act on.
     */
    public function test_deleting_a_page_takes_its_history_with_it()
    {
        $page = $this->page();

        $page->writeContent($this->body('Eerste'));
        $page->writeContent($this->body('Tweede'));

        $this->assertSame(1, $page->revisions()->count());

        $page->delete();

        $this->assertDatabaseCount('page_revisions', 0);
    }

    public function test_the_editor_is_sent_the_timestamps_and_not_the_bodies()
    {
        $admin = User::factory()->create();
        $page = $this->page();

        $page->writeContent($this->body('Eerste'));
        $page->writeContent($this->body('Tweede'));

        $response = $this->actingAs($admin)->get(route('admin.pages.edit', $page))->assertOk();

        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->has('revisions', 1)
            ->whereNot('revisions.0.savedAt', null)
            ->has('revisions.0.id'));

        // The stored bodies are not in the payload at all: ten copies of a
        // long lesson would be sent to draw a list of dates.
        $this->assertStringNotContainsString('Eerste', $response->content());
    }

    public function test_a_version_can_be_previewed_with_the_media_it_embeds()
    {
        $admin = User::factory()->create();
        $page = $this->page();
        $image = $this->image();

        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Met plaatje']]],
            ['type' => 'imageGallery', 'attrs' => ['ulids' => [$image->ulid]]],
        ]]);
        $page->writeContent($this->body('Zonder plaatje'));

        $revision = $page->revisions()->sole();

        $body = $this->actingAs($admin)
            ->getJson(route('admin.pages.revisions.show', [$page, $revision]))
            ->assertOk()
            ->json();

        $this->assertSame('Met plaatje', $body['content']['content'][0]['content'][0]['text']);

        // Resolved from the stored document, not from page_media_references —
        // those describe what the page shows *now*, and an old version
        // embedding something the current body dropped is exactly the case a
        // preview has to get right.
        $this->assertArrayHasKey($image->ulid, $body['media']);
        $this->assertSame('image', $body['media'][$image->ulid]['type']);

        // And the same file again in the editor's own shape. Restoring hands
        // this to the node views, which resolve an embed against what the
        // *current* body shows — without it a restored gallery draws "these
        // images no longer exist" over a block that is perfectly intact,
        // which invites the owner to delete it.
        $this->assertSame($image->ulid, $body['library']['images'][0]['ulid']);
        $this->assertSame($image->alt_text, $body['library']['images'][0]['alt_text']);
        $this->assertSame([], $body['library']['files']);
    }

    /**
     * The routes are scoped to their page, so a revision id belonging to
     * another page is a 404 — not another page's body appearing under this
     * one's history, and not a way to overwrite this page with it.
     */
    public function test_a_revision_of_another_page_is_not_reachable_through_this_one()
    {
        $admin = User::factory()->create();

        $mine = $this->page('Les 1', 'les-1');
        $theirs = $this->page('Les 2', 'les-2');

        $theirs->writeContent($this->body('Hun eerste'));
        $theirs->writeContent($this->body('Hun tweede'));

        $revision = $theirs->revisions()->sole();

        $this->actingAs($admin)
            ->getJson(route('admin.pages.revisions.show', [$mine, $revision]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.pages.revisions.restore', [$mine, $revision]))
            ->assertNotFound();

        $this->assertNull($mine->fresh()->content);
    }

    public function test_a_guest_can_neither_read_nor_restore_a_version()
    {
        $page = $this->page();

        $page->writeContent($this->body('Eerste'));
        $page->writeContent($this->body('Tweede'));

        $revision = $page->revisions()->sole();

        $this->get(route('admin.pages.revisions.show', [$page, $revision]))
            ->assertRedirect(route('login'));

        $this->post(route('admin.pages.revisions.restore', [$page, $revision]))
            ->assertRedirect(route('login'));

        $this->assertSame(
            'Tweede',
            $page->fresh()->content['content'][0]['content'][0]['text']
        );
    }
}
