<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A request that omits the body must not erase it — fixed differently on
 * each route. Pages: `PageController::updateContent`'s rule is `['present',
 * 'nullable', 'array']`, so a `nullable`-alone rule that let an absent key
 * validate as null would silently wipe the stored document. Topics:
 * `UpdateTopicRequest` stays plain `['nullable', 'array']`, and
 * `TopicController` instead checks `$request->has('content')` before calling
 * `writeContent()` at all.
 */
class ContentPreservationTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $text): array
    {
        return ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ]];
    }

    public function test_a_page_body_survives_a_request_that_omits_it()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $page = Page::query()->create([
            'title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $topic->id,
        ]);
        $page->writeContent($this->document('Zwaartekracht uitleg.'));

        $this->actingAs(User::factory()->create())
            ->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.content.update', $page), [])
            ->assertSessionHasErrors('content');

        $this->assertNotNull($page->fresh()->content);
        $this->assertStringContainsString('Zwaartekracht', (string) $page->fresh()->content_text);
    }

    /**
     * Clearing must still work. `present` makes absence an error without
     * making an explicit null one — that is the whole distinction.
     */
    public function test_a_page_body_can_still_be_cleared_deliberately()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $page = Page::query()->create([
            'title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $topic->id,
        ]);
        $page->writeContent($this->document('Zwaartekracht uitleg.'));

        $this->actingAs(User::factory()->create())
            ->put(route('admin.pages.content.update', $page), ['content' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($page->fresh()->content);
    }

    /**
     * The worst of the three: this one runs on *every* topic edit, so any
     * partial update of a topic wiped its introduction as a side effect.
     */
    public function test_a_topic_introduction_survives_an_edit_that_omits_it()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $topic->writeContent($this->document('Inleiding op de natuurkunde.'));

        $this->actingAs(User::factory()->create())
            ->put(route('admin.topics.update', $topic), [
                'title' => 'Natuurkunde en sterrenkunde',
                'slug' => 'natuurkunde',
                'parent_id' => null,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $topic->fresh();

        $this->assertSame('Natuurkunde en sterrenkunde', $fresh->title);
        $this->assertNotNull($fresh->content, 'The introduction was erased by an edit that never mentioned it.');
    }

    public function test_a_topic_introduction_can_still_be_cleared_deliberately()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $topic->writeContent($this->document('Inleiding op de natuurkunde.'));

        $this->actingAs(User::factory()->create())
            ->put(route('admin.topics.update', $topic), [
                'title' => 'Natuurkunde',
                'slug' => 'natuurkunde',
                'parent_id' => null,
                'content' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($topic->fresh()->content);
    }

    /**
     * Defence in depth: nothing currently sends a `content` key on this form,
     * but the model-level whitelist must hold regardless — pages carry embeds.
     */
    public function test_a_page_body_cannot_be_mass_assigned_past_the_whitelist()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $page = Page::query()->create([
            'title' => 'De Planeten',
            'slug' => 'de-planeten',
            'topic_id' => $topic->id,
            'content' => $this->document('Nooit opgeslagen.'),
            'content_text' => 'Ook niet.',
        ]);

        $this->assertNull($page->fresh()->content);
        $this->assertNull($page->fresh()->content_text);
    }
}
