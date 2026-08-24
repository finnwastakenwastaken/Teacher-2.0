<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_manage_topics()
    {
        $this->get(route('admin.topics.index'))->assertRedirect(route('login'));
        $this->post(route('admin.topics.store'), [])->assertRedirect(route('login'));
    }

    public function test_the_admin_can_create_a_root_topic()
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.topics.store'), [
            'title' => 'Natuurkunde',
            'slug' => 'natuurkunde',
        ]);

        $response->assertRedirect(route('admin.topics.index'));
        $this->assertDatabaseHas('topics', ['slug' => 'natuurkunde', 'depth' => 0]);
    }

    public function test_the_admin_can_create_a_child_topic()
    {
        $admin = User::factory()->create();
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $response = $this->actingAs($admin)->post(route('admin.topics.store'), [
            'title' => 'Sterrenkunde',
            'slug' => 'sterrenkunde',
            'parent_id' => $root->id,
        ]);

        $response->assertRedirect(route('admin.topics.index'));
        $this->assertDatabaseHas('topics', ['slug' => 'sterrenkunde', 'parent_id' => $root->id, 'depth' => 1]);
    }

    public function test_creating_a_topic_requires_a_title_and_slug()
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.topics.store'), []);

        $response->assertSessionHasErrors(['title', 'slug']);
    }

    public function test_a_conflicting_sibling_slug_is_rejected_with_a_friendly_message()
    {
        $admin = User::factory()->create();
        Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $response = $this->actingAs($admin)->post(route('admin.topics.store'), [
            'title' => 'Andere Natuurkunde',
            'slug' => 'natuurkunde',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertSame(1, Topic::query()->count());
    }

    public function test_a_topic_at_the_deepest_level_cannot_be_chosen_as_a_parent()
    {
        $admin = User::factory()->create();
        $level0 = Topic::query()->create(['title' => 'L0', 'slug' => 'l0']);
        $level1 = Topic::query()->create(['title' => 'L1', 'slug' => 'l1', 'parent_id' => $level0->id]);
        $level2 = Topic::query()->create(['title' => 'L2', 'slug' => 'l2', 'parent_id' => $level1->id]);

        $response = $this->actingAs($admin)->post(route('admin.topics.store'), [
            'title' => 'L3',
            'slug' => 'l3',
            'parent_id' => $level2->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    /**
     * Moving a topic under its own child used to be refused only by accident:
     * the depth cascade pushed the subtree past the cap and the database
     * trigger raised a message about depth — for something that is not a
     * depth problem. The owner read "maximaal 3 niveaus diep" about a move
     * between two levels, which explains nothing.
     */
    public function test_a_topic_cannot_be_moved_under_its_own_descendant()
    {
        $admin = User::factory()->create();
        $root = Topic::query()->create(['title' => 'L0', 'slug' => 'l0']);
        $child = Topic::query()->create(['title' => 'L1', 'slug' => 'l1', 'parent_id' => $root->id]);

        $response = $this->actingAs($admin)->put(route('admin.topics.update', $root), [
            'title' => 'L0',
            'slug' => 'l0',
            'parent_id' => $child->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
        // Asserted through the key rather than against a Dutch literal: the
        // interface has two languages, and the test client advertises
        // English, so a hard-coded string here would pin the wrong one.
        $this->assertSame(
            __('admin.topics.own_descendant'),
            session('errors')->first('parent_id')
        );
        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_the_admin_can_rename_a_topic()
    {
        $admin = User::factory()->create();
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $response = $this->actingAs($admin)->put(route('admin.topics.update', $topic), [
            'title' => 'Natuurkunde (nieuw)',
            'slug' => 'natuurkunde',
        ]);

        $response->assertRedirect(route('admin.topics.index'));
        $this->assertSame('Natuurkunde (nieuw)', $topic->fresh()->title);
    }

    public function test_deleting_a_topic_with_pages_is_blocked_with_a_message()
    {
        $admin = User::factory()->create();
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create(['title' => 'Overzicht', 'slug' => 'overzicht', 'topic_id' => $topic->id]);

        $response = $this->actingAs($admin)->delete(route('admin.topics.destroy', $topic));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertModelExists($topic);
    }

    public function test_deleting_an_empty_topic_succeeds()
    {
        $admin = User::factory()->create();
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $response = $this->actingAs($admin)->delete(route('admin.topics.destroy', $topic));

        $response->assertRedirect(route('admin.topics.index'));
        $this->assertModelMissing($topic);
    }

    public function test_creating_a_topic_with_a_blank_sort_order_defaults_to_zero()
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.topics.store'), [
            'title' => 'Natuurkunde',
            'slug' => 'natuurkunde',
            'sort_order' => '',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('topics', ['slug' => 'natuurkunde', 'sort_order' => 0]);
    }

    public function test_a_status_message_is_shared_as_an_inertia_prop_after_creating_a_topic()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.topics.store'), [
            'title' => 'Natuurkunde',
            'slug' => 'natuurkunde',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.topics.index'));

        $response->assertInertia(fn ($page) => $page->where('status', __('admin.topics.created')));
    }

    public function test_the_admin_can_give_a_topic_an_introduction()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.topics.store'), [
            'title' => 'Sterrenkunde',
            'slug' => 'sterrenkunde',
            'content' => $this->paragraph('Wat je hier leert.'),
        ])->assertSessionHasNoErrors();

        $topic = Topic::query()->where('slug', 'sterrenkunde')->sole();

        $this->assertSame('Wat je hier leert.', $topic->content['content'][0]['content'][0]['text']);
    }

    public function test_the_introduction_survives_an_edit_that_does_not_touch_it()
    {
        $admin = User::factory()->create();
        $topic = Topic::query()->create(['title' => 'Sterrenkunde', 'slug' => 'sterrenkunde']);
        $topic->writeContent($this->paragraph('Wat je hier leert.'));

        $this->actingAs($admin)->put(route('admin.topics.update', $topic), [
            'title' => 'Sterrenkunde',
            'slug' => 'sterrenkunde',
            'content' => $topic->fresh()->content,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'Wat je hier leert.',
            $topic->fresh()->content['content'][0]['content'][0]['text'],
        );
    }

    public function test_clearing_the_introduction_stores_null()
    {
        $admin = User::factory()->create();
        $topic = Topic::query()->create(['title' => 'Sterrenkunde', 'slug' => 'sterrenkunde']);
        $topic->writeContent($this->paragraph('Wat je hier leert.'));

        $this->actingAs($admin)->put(route('admin.topics.update', $topic), [
            'title' => 'Sterrenkunde',
            'slug' => 'sterrenkunde',
            'content' => null,
        ])->assertSessionHasNoErrors();

        $this->assertNull($topic->fresh()->content);
    }

    /**
     * The reason a topic body uses the without-embeds whitelist: a file is
     * published by walking from it to the *pages* showing it, and a topic is
     * not a page row. An embed here would render for the owner and 403 for
     * every student.
     */
    public function test_embeds_are_stripped_from_a_topic_introduction()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.topics.store'), [
            'title' => 'Sterrenkunde',
            'slug' => 'sterrenkunde',
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Uitleg']]],
                    ['type' => 'fileEmbed', 'attrs' => ['ulid' => '01hzzzzzzzzzzzzzzzzzzzzzzz']],
                    ['type' => 'youtubeEmbed', 'attrs' => ['videoId' => 'abc']],
                    ['type' => 'imageGallery', 'attrs' => ['ulids' => []]],
                ],
            ],
        ])->assertSessionHasNoErrors();

        $stored = Topic::query()->where('slug', 'sterrenkunde')->sole()->content;

        $this->assertCount(1, $stored['content']);
        $this->assertSame('paragraph', $stored['content'][0]['type']);
    }

    public function test_a_topic_introduction_is_whitelisted_like_a_page_body()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.topics.store'), [
            'title' => 'Sterrenkunde',
            'slug' => 'sterrenkunde',
            'content' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Klik hier', 'marks' => [
                            ['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']],
                        ]],
                    ],
                ]],
            ],
        ])->assertSessionHasNoErrors();

        $node = Topic::query()->where('slug', 'sterrenkunde')->sole()->content['content'][0]['content'][0];

        $this->assertSame('Klik hier', $node['text']);
        $this->assertArrayNotHasKey('marks', $node);
    }

    public function test_a_malformed_introduction_is_a_validation_error_not_a_crash()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.topics.store'), [
            'title' => 'Sterrenkunde',
            'slug' => 'sterrenkunde',
            'content' => 'not a document',
        ])->assertSessionHasErrors('content');

        $this->assertDatabaseMissing('topics', ['slug' => 'sterrenkunde']);
    }

    /**
     * @return array<string, mixed>
     */
    private function paragraph(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            ],
        ];
    }
}
