<?php

namespace Tests\Feature\Content;

use App\Exceptions\DependentRecordsExistException;
use App\Models\Page;
use App\Models\Topic;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the invariants enforced by the Postgres triggers in
 * 2026_08_09_000003_create_topic_tree_integrity_triggers.php: depth is always
 * derived, never trusted, and slugs are unique among siblings across topics
 * and pages. These write through Eloquent with no Form Request, since the
 * point is that the database itself refuses corruption.
 */
class TopicTreeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_root_topic_has_depth_zero()
    {
        // depth is never fillable — the trigger sets it server-side, so the
        // model must be re-read after create() to observe it.
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $this->assertSame(0, $topic->fresh()->depth);
    }

    public function test_depth_is_derived_from_the_parent_regardless_of_client_input()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $child = Topic::query()->create(['title' => 'Sterrenkunde', 'slug' => 'sterrenkunde', 'parent_id' => $root->id, 'depth' => 99]);

        $this->assertSame(1, $child->fresh()->depth);
    }

    public function test_a_fourth_level_topic_is_rejected()
    {
        $level0 = Topic::query()->create(['title' => 'L0', 'slug' => 'l0']);
        $level1 = Topic::query()->create(['title' => 'L1', 'slug' => 'l1', 'parent_id' => $level0->id]);
        $level2 = Topic::query()->create(['title' => 'L2', 'slug' => 'l2', 'parent_id' => $level1->id]);

        $this->expectException(QueryException::class);

        Topic::query()->create(['title' => 'L3', 'slug' => 'l3', 'parent_id' => $level2->id]);
    }

    public function test_moving_a_topic_cascades_depth_to_its_descendants()
    {
        $a = Topic::query()->create(['title' => 'A', 'slug' => 'a']);
        $b = Topic::query()->create(['title' => 'B', 'slug' => 'b', 'parent_id' => $a->id]);
        $c = Topic::query()->create(['title' => 'C', 'slug' => 'c', 'parent_id' => $b->id]);

        // Detach B (and its child C) from A so B becomes a root; C must
        // cascade from depth 2 down to depth 1.
        $b->update(['parent_id' => null]);

        $this->assertSame(0, $b->fresh()->depth);
        $this->assertSame(1, $c->fresh()->depth);
    }

    public function test_moving_a_topic_that_would_push_a_descendant_past_the_depth_cap_is_rejected()
    {
        $a = Topic::query()->create(['title' => 'A', 'slug' => 'a']);
        $b = Topic::query()->create(['title' => 'B', 'slug' => 'b', 'parent_id' => $a->id]);
        $target = Topic::query()->create(['title' => 'Target', 'slug' => 'target', 'parent_id' => $b->id]);

        // Reparenting $a's other child under $target (already depth 2) would
        // push it to depth 3, past the cap.
        $other = Topic::query()->create(['title' => 'Other', 'slug' => 'other', 'parent_id' => $a->id]);

        $this->expectException(QueryException::class);

        $other->update(['parent_id' => $target->id]);
    }

    public function test_two_root_topics_cannot_share_a_slug()
    {
        Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $this->expectException(QueryException::class);

        Topic::query()->create(['title' => 'Andere Natuurkunde', 'slug' => 'natuurkunde']);
    }

    public function test_a_child_topic_and_a_page_under_the_same_parent_cannot_share_a_slug()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create(['title' => 'Overzicht', 'slug' => 'overzicht', 'topic_id' => $topic->id]);

        $this->expectException(QueryException::class);

        Topic::query()->create(['title' => 'Overzicht', 'slug' => 'overzicht', 'parent_id' => $topic->id]);
    }

    public function test_two_pages_under_the_same_topic_cannot_share_a_slug()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create(['title' => 'Overzicht', 'slug' => 'overzicht', 'topic_id' => $topic->id]);

        $this->expectException(QueryException::class);

        Page::query()->create(['title' => 'Ander Overzicht', 'slug' => 'overzicht', 'topic_id' => $topic->id]);
    }

    public function test_two_pages_under_different_topics_may_share_a_slug()
    {
        $topicA = Topic::query()->create(['title' => 'A', 'slug' => 'a']);
        $topicB = Topic::query()->create(['title' => 'B', 'slug' => 'b']);

        Page::query()->create(['title' => 'Overzicht', 'slug' => 'overzicht', 'topic_id' => $topicA->id]);
        $pageB = Page::query()->create(['title' => 'Overzicht', 'slug' => 'overzicht', 'topic_id' => $topicB->id]);

        $this->assertSame('overzicht', $pageB->slug);
    }

    public function test_deleting_a_topic_with_child_topics_is_blocked()
    {
        $parent = Topic::query()->create(['title' => 'A', 'slug' => 'a']);
        Topic::query()->create(['title' => 'B', 'slug' => 'b', 'parent_id' => $parent->id]);

        $this->expectException(DependentRecordsExistException::class);

        $parent->delete();
    }

    public function test_deleting_a_topic_with_pages_is_blocked()
    {
        $topic = Topic::query()->create(['title' => 'A', 'slug' => 'a']);
        Page::query()->create(['title' => 'Overzicht', 'slug' => 'overzicht', 'topic_id' => $topic->id]);

        $this->expectException(DependentRecordsExistException::class);

        $topic->delete();
    }

    public function test_deleting_an_empty_topic_succeeds()
    {
        $topic = Topic::query()->create(['title' => 'A', 'slug' => 'a']);

        $topic->delete();

        $this->assertModelMissing($topic);
    }

    public function test_full_path_walks_the_ancestor_chain()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $child = Topic::query()->create(['title' => 'Sterrenkunde', 'slug' => 'sterrenkunde', 'parent_id' => $root->id]);
        $page = Page::query()->create(['title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $child->id]);

        $this->assertSame('natuurkunde/sterrenkunde', $child->fullPath());
        $this->assertSame('natuurkunde/sterrenkunde/de-planeten', $page->fullPath());
    }
}
