<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_visible_root_topics()
    {
        Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde', 'sort_order' => 1]);
        Topic::query()->create(['title' => 'Verborgen', 'slug' => 'verborgen', 'is_hidden' => true, 'sort_order' => 2]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('topics', 1)
            ->where('topics.0.slug', 'natuurkunde')
        );
    }

    public function test_homepage_excludes_child_topics_from_the_top_level_grid()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Topic::query()->create(['title' => 'Sterrenkunde', 'slug' => 'sterrenkunde', 'parent_id' => $root->id]);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page->has('topics', 1));
    }

    public function test_a_topic_page_lists_its_visible_children_and_breadcrumbs()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $child = Topic::query()->create(['title' => 'Sterrenkunde', 'slug' => 'sterrenkunde', 'parent_id' => $root->id]);
        Topic::query()->create(['title' => 'Verborgen', 'slug' => 'verborgen', 'parent_id' => $child->id, 'is_hidden' => true]);
        Page::query()->create(['title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $child->id]);

        $response = $this->get('/natuurkunde/sterrenkunde');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('content/topic')
            ->where('topic.title', 'Sterrenkunde')
            ->has('childTopics', 0)
            ->has('pages', 1)
            ->has('breadcrumbs', 2)
            ->where('breadcrumbs.0.title', 'Natuurkunde')
            ->where('breadcrumbs.1.title', 'Sterrenkunde')
        );
    }

    public function test_a_topic_page_carries_its_introduction()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $topic->writeContent([
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Wat je hier leert.']]],
            ],
        ]);

        $this->get('/natuurkunde')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('content/topic')
                ->where('topic.content.content.0.content.0.text', 'Wat je hier leert.')
            );
    }

    public function test_a_topic_without_an_introduction_sends_null_rather_than_an_empty_document()
    {
        Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $this->get('/natuurkunde')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('topic.content', null));
    }

    public function test_a_page_renders_with_its_breadcrumbs()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create(['title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $root->id]);

        $response = $this->get('/natuurkunde/de-planeten');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('content/page')
            ->where('page.title', 'De Planeten')
            ->has('breadcrumbs', 2)
            ->where('breadcrumbs.1.title', 'De Planeten')
        );
    }

    public function test_a_hidden_page_still_renders_at_its_direct_url()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create(['title' => 'Concept', 'slug' => 'concept', 'topic_id' => $root->id, 'is_hidden' => true]);

        $response = $this->get('/natuurkunde/concept');

        $response->assertOk();
    }

    public function test_a_hidden_page_is_excluded_from_its_topics_listing()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create(['title' => 'Concept', 'slug' => 'concept', 'topic_id' => $root->id, 'is_hidden' => true]);

        $response = $this->get('/natuurkunde');

        $response->assertInertia(fn ($page) => $page->has('pages', 0));
    }

    public function test_an_unknown_path_returns_a_404()
    {
        $response = $this->get('/does-not-exist');

        $response->assertNotFound();
    }

    public function test_renaming_a_topic_slug_leaves_a_301_redirect_from_the_old_path()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $topic->update(['slug' => 'natuurkunde-nieuw']);

        $response = $this->get('/natuurkunde');

        $response->assertRedirect('/natuurkunde-nieuw');
        $response->assertStatus(301);
    }

    public function test_renaming_an_ancestor_topic_leaves_a_redirect_for_descendant_pages_too()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $child = Topic::query()->create(['title' => 'Sterrenkunde', 'slug' => 'sterrenkunde', 'parent_id' => $root->id]);
        Page::query()->create(['title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $child->id]);

        $root->update(['slug' => 'physica']);

        $response = $this->get('/natuurkunde/sterrenkunde/de-planeten');

        $response->assertRedirect('/physica/sterrenkunde/de-planeten');
    }

    /**
     * A 301 caches indefinitely in browsers, so a corrected typo would stay
     * broken for anyone who already visited. Stays 301 for crawlers; the
     * max-age bounds how long a person is stuck with a stale redirect.
     */
    public function test_a_slug_redirect_is_permanent_but_not_cached_forever()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $topic->update(['slug' => 'natuurkunde-nieuw']);

        $this->get('/natuurkunde')
            ->assertStatus(301)
            ->assertHeader('Cache-Control', 'max-age=86400, public');
    }

    /**
     * A vacated path can be claimed again — `firstOrCreate` would keep the
     * first claimant's row forever and never record the second rename.
     */
    public function test_a_reused_path_redirects_to_whoever_had_it_last()
    {
        $first = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $first->update(['slug' => 'physica']);

        $second = Topic::query()->create(['title' => 'Natuurkunde 2', 'slug' => 'natuurkunde']);
        $second->update(['slug' => 'natuurwetenschappen']);

        $this->get('/natuurkunde')->assertRedirect('/natuurwetenschappen');
    }

    public function test_a_redirect_to_a_since_deleted_target_404s()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $topic->update(['slug' => 'natuurkunde-nieuw']);
        $topic->delete();

        $response = $this->get('/natuurkunde');

        $response->assertNotFound();
    }

    public function test_moving_a_page_to_a_different_topic_leaves_a_redirect_from_the_old_path()
    {
        $oldTopic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $newTopic = Topic::query()->create(['title' => 'Scheikunde', 'slug' => 'scheikunde']);
        $page = Page::query()->create(['title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $oldTopic->id]);

        $page->update(['topic_id' => $newTopic->id]);

        $response = $this->get('/natuurkunde/de-planeten');

        $response->assertRedirect('/scheikunde/de-planeten');
    }

    public function test_a_trailing_slash_still_resolves()
    {
        Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $response = $this->get('/natuurkunde/');

        $response->assertOk();
    }
}
