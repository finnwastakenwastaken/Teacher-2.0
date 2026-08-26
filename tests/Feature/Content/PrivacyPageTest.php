<?php

namespace Tests\Feature\Content;

use App\Models\Topic;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_reachable_without_logging_in()
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('content/privacy'));
    }

    /**
     * A fresh install has configured nothing, and the page has to read
     * correctly anyway — the statement is the application's own words, so it
     * does not depend on the owner having written anything.
     */
    public function test_the_owner_section_is_absent_until_the_owner_writes_one()
    {
        $this->get('/privacy')->assertInertia(fn ($page) => $page
            ->where('ownerContent', null)
        );
    }

    public function test_the_owner_can_add_a_section_of_their_own()
    {
        SiteSettings::put([
            'privacy_content' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Vragen? Mail de school.']],
                ]],
            ],
        ]);

        $this->get('/privacy')->assertInertia(fn ($page) => $page
            ->where('ownerContent.content.0.content.0.text', 'Vragen? Mail de school.')
        );
    }

    /**
     * Declared ahead of the `/{path}` catch-all, so it answers even when a
     * topic claims the same slug. Worth pinning: the failure would be a
     * privacy statement silently replaced by whatever the owner happened to
     * name a topic.
     */
    public function test_the_route_wins_against_a_topic_of_the_same_slug()
    {
        Topic::query()->create(['title' => 'Privacy', 'slug' => 'privacy', 'sort_order' => 1]);

        $this->get('/privacy')->assertInertia(fn ($page) => $page
            ->component('content/privacy')
        );
    }
}
