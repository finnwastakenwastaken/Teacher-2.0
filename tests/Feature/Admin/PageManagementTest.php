<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_manage_pages()
    {
        $this->post(route('admin.pages.store'), [])->assertRedirect(route('login'));
    }

    public function test_the_admin_can_create_a_page_under_a_topic()
    {
        $admin = User::factory()->create();
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $response = $this->actingAs($admin)->post(route('admin.pages.store'), [
            'topic_id' => $topic->id,
            'title' => 'De Planeten',
            'slug' => 'de-planeten',
        ]);

        $response->assertRedirect(route('admin.topics.index'));
        $this->assertDatabaseHas('pages', ['slug' => 'de-planeten', 'topic_id' => $topic->id]);
    }

    public function test_a_page_slug_colliding_with_a_sibling_topic_is_rejected()
    {
        $admin = User::factory()->create();
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Topic::query()->create(['title' => 'Overzicht', 'slug' => 'overzicht', 'parent_id' => $topic->id]);

        $response = $this->actingAs($admin)->post(route('admin.pages.store'), [
            'topic_id' => $topic->id,
            'title' => 'Overzicht',
            'slug' => 'overzicht',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertSame(0, Page::query()->count());
    }

    public function test_the_admin_can_rename_a_page()
    {
        $admin = User::factory()->create();
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $page = Page::query()->create(['title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $topic->id]);

        $response = $this->actingAs($admin)->put(route('admin.pages.update', $page), [
            'topic_id' => $topic->id,
            'title' => 'De Planeten',
            'slug' => 'planeten',
        ]);

        $response->assertRedirect(route('admin.topics.index'));
        $this->assertSame('planeten', $page->fresh()->slug);
    }

    public function test_the_admin_can_delete_a_page()
    {
        $admin = User::factory()->create();
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $page = Page::query()->create(['title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $topic->id]);

        $response = $this->actingAs($admin)->delete(route('admin.pages.destroy', $page));

        $response->assertRedirect(route('admin.topics.index'));
        $this->assertModelMissing($page);
    }
}
