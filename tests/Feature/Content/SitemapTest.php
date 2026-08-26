<?php

namespace Tests\Feature\Content;

use App\Models\AccessPassword;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_names_the_sitemap_with_an_absolute_url()
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Sitemap: '.route('sitemap'));
        $this->assertStringContainsString('http', $response->getContent());
    }

    public function test_the_sitemap_lists_the_homepage_topics_and_pages()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create(['title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $root->id]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $body = $response->getContent();

        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/natuurkunde').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/natuurkunde/de-planeten').'</loc>', $body);
    }

    public function test_the_sitemap_is_well_formed_xml()
    {
        $root = Topic::query()->create(['title' => 'Natuur & Techniek', 'slug' => 'natuurkunde']);
        Page::query()->create(['title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $root->id]);

        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

        $this->assertNotFalse($xml);
        $this->assertCount(3, $xml->url);
    }

    public function test_hidden_content_is_left_out()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create([
            'title' => 'Concept', 'slug' => 'concept', 'topic_id' => $root->id, 'is_hidden' => true,
        ]);

        $body = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('concept', $body);
    }

    /**
     * A hidden topic keeps its children out too. Publishing the pages under
     * it would route around the only thing hiding is for.
     */
    public function test_a_page_under_a_hidden_topic_is_left_out()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $hidden = Topic::query()->create([
            'title' => 'Oud', 'slug' => 'oud', 'parent_id' => $root->id, 'is_hidden' => true,
        ]);
        Page::query()->create(['title' => 'Vorig jaar', 'slug' => 'vorig-jaar', 'topic_id' => $hidden->id]);

        $body = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('vorig-jaar', $body);
        $this->assertStringNotContainsString('/oud', $body);
    }

    public function test_password_protected_content_is_left_out_including_the_whole_branch()
    {
        $password = AccessPassword::createWithPassword('5 VWO', 'zwaartekracht');

        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $protected = Topic::query()->create([
            'title' => 'Toetsstof', 'slug' => 'toetsstof', 'parent_id' => $root->id,
            'access_password_id' => $password->id,
        ]);
        Page::query()->create(['title' => 'Proefwerk', 'slug' => 'proefwerk', 'topic_id' => $protected->id]);

        $body = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('toetsstof', $body);
        $this->assertStringNotContainsString('proefwerk', $body);
    }

    /**
     * The sitemap describes what is public, not what the fetcher may see —
     * AccessControl::allows() would leak every protected path the moment the
     * owner loaded it while logged in.
     */
    public function test_the_admin_gets_the_same_sitemap_as_anyone_else()
    {
        $password = AccessPassword::createWithPassword('5 VWO', 'zwaartekracht');

        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create([
            'title' => 'Proefwerk', 'slug' => 'proefwerk', 'topic_id' => $root->id,
            'access_password_id' => $password->id,
        ]);

        $anonymous = $this->get('/sitemap.xml')->getContent();
        $asAdmin = $this->actingAs(User::factory()->create())->get('/sitemap.xml')->getContent();

        $this->assertSame($anonymous, $asAdmin);
        $this->assertStringNotContainsString('proefwerk', $asAdmin);
    }

    /**
     * An unlocked visitor must not get a bigger sitemap either — the file is
     * public regardless of who asked for it.
     */
    public function test_an_unlocked_visitor_gets_the_same_sitemap()
    {
        $password = AccessPassword::createWithPassword('5 VWO', 'zwaartekracht');

        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create([
            'title' => 'Proefwerk', 'slug' => 'proefwerk', 'topic_id' => $root->id,
            'access_password_id' => $password->id,
        ]);

        $this->post('/unlock', ['path' => 'natuurkunde/proefwerk', 'password' => 'zwaartekracht']);

        $this->assertStringNotContainsString('proefwerk', $this->get('/sitemap.xml')->getContent());
    }

    public function test_the_catch_all_route_does_not_swallow_the_crawler_routes()
    {
        Topic::query()->create(['title' => 'Sitemap', 'slug' => 'sitemap.xml']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }
}
