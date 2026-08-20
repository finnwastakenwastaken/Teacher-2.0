<?php

namespace Tests\Feature\Admin;

use App\Models\EducationLevel;
use App\Models\Page;
use App\Models\SlugRedirect;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReorderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function topic(string $title, ?int $parentId = null, int $order = 0): Topic
    {
        return Topic::query()->create([
            'title' => $title,
            'slug' => Str::slug($title),
            'parent_id' => $parentId,
            'sort_order' => $order,
        ]);
    }

    private function page(Topic $topic, string $title, int $order = 0): Page
    {
        return Page::query()->create([
            'title' => $title,
            'slug' => Str::slug($title),
            'topic_id' => $topic->id,
            'sort_order' => $order,
        ]);
    }

    public function test_guests_cannot_reorder_anything()
    {
        $this->post(route('admin.topics.reorder'), ['ids' => [1]])->assertRedirect(route('login'));
        $this->post(route('admin.pages.reorder'), ['ids' => [1]])->assertRedirect(route('login'));
        $this->post(route('admin.levels.reorder'), ['ids' => [1]])->assertRedirect(route('login'));
    }

    public function test_sibling_topics_are_renumbered_in_the_order_given()
    {
        $first = $this->topic('Natuurkunde', order: 0);
        $second = $this->topic('Scheikunde', order: 1);
        $third = $this->topic('Wiskunde', order: 2);

        $this->actingAs($this->admin())
            ->post(route('admin.topics.reorder'), [
                'ids' => [$third->id, $first->id, $second->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $third->fresh()->sort_order);
        $this->assertSame([$third->id, $first->id, $second->id], Topic::query()
            ->orderBy('sort_order')
            ->pluck('id')
            ->all());
    }

    public function test_a_reorder_cannot_mix_topics_from_different_parents()
    {
        // This is the line between "reorder" and "move". A drag must never be
        // able to reparent: that path has to handle the depth cap, sibling
        // slug uniqueness and 301 redirects, and it lives in the edit form.
        $parent = $this->topic('Natuurkunde');
        $child = $this->topic('Sterrenkunde', parentId: $parent->id);
        $otherRoot = $this->topic('Scheikunde');

        $this->actingAs($this->admin())
            ->from(route('admin.topics.index'))
            ->post(route('admin.topics.reorder'), [
                'ids' => [$child->id, $otherRoot->id],
            ])
            ->assertSessionHasErrors('ids');

        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertSame(null, $otherRoot->fresh()->parent_id);
    }

    public function test_a_reorder_cannot_mix_pages_from_different_topics()
    {
        $one = $this->topic('Natuurkunde');
        $two = $this->topic('Scheikunde');
        $pageOne = $this->page($one, 'De Planeten');
        $pageTwo = $this->page($two, 'Atomen');

        $this->actingAs($this->admin())
            ->from(route('admin.topics.index'))
            ->post(route('admin.pages.reorder'), [
                'ids' => [$pageTwo->id, $pageOne->id],
            ])
            ->assertSessionHasErrors('ids');

        $this->assertSame($one->id, $pageOne->fresh()->topic_id);
    }

    public function test_reordering_writes_no_slug_redirects()
    {
        // Order is not part of a URL, so moving an item up or down must not
        // leave a 301 behind. If it did, every drag would litter the
        // redirect table.
        $first = $this->topic('Natuurkunde', order: 0);
        $second = $this->topic('Scheikunde', order: 1);

        $this->actingAs($this->admin())
            ->post(route('admin.topics.reorder'), ['ids' => [$second->id, $first->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, SlugRedirect::query()->count());
    }

    public function test_an_unknown_id_is_refused_and_changes_nothing()
    {
        $topic = $this->topic('Natuurkunde', order: 3);

        $this->actingAs($this->admin())
            ->from(route('admin.topics.index'))
            ->post(route('admin.topics.reorder'), ['ids' => [$topic->id, 99999]])
            ->assertSessionHasErrors('ids');

        $this->assertSame(3, $topic->fresh()->sort_order);
    }

    public function test_levels_are_a_flat_list_and_reorder_freely()
    {
        $havo = EducationLevel::query()->create(['name' => 'HAVO', 'slug' => 'havo', 'sort_order' => 0]);
        $vwo = EducationLevel::query()->create(['name' => 'VWO', 'slug' => 'vwo', 'sort_order' => 1]);

        $this->actingAs($this->admin())
            ->post(route('admin.levels.reorder'), ['ids' => [$vwo->id, $havo->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['VWO', 'HAVO'], EducationLevel::query()
            ->orderBy('sort_order')
            ->pluck('name')
            ->all());
    }

    public function test_a_new_level_is_added_at_the_end()
    {
        // The numeric order field is gone from the form, so the default has
        // to be "last" rather than 0 — otherwise every new level would land
        // at the top.
        EducationLevel::query()->create(['name' => 'HAVO', 'slug' => 'havo', 'sort_order' => 7]);

        $this->actingAs($this->admin())
            ->post(route('admin.levels.store'), ['name' => 'VWO'])
            ->assertSessionHasNoErrors();

        $this->assertSame(8, EducationLevel::query()->where('slug', 'vwo')->value('sort_order'));
    }

    public function test_saving_an_unrelated_edit_keeps_the_dragged_order()
    {
        // The edit form no longer carries an order field, so the request must
        // keep what dragging set. Defaulting it to 0 here would silently
        // reshuffle the list every time the owner fixed a typo.
        $topic = $this->topic('Natuurkunde', order: 3);

        $this->actingAs($this->admin())
            ->put(route('admin.topics.update', $topic), [
                'title' => 'Natuurkunde en techniek',
                'slug' => 'natuurkunde',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $topic->fresh()->sort_order);
    }

    public function test_a_page_moved_to_another_topic_lands_at_the_end_of_it()
    {
        // Its old number means nothing under the new parent, and 0 would drop
        // it at the top of a list it has just joined.
        $from = $this->topic('Natuurkunde');
        $to = $this->topic('Scheikunde');
        $this->page($to, 'Atomen', order: 6);
        $page = $this->page($from, 'De Planeten', order: 0);

        $this->actingAs($this->admin())
            ->put(route('admin.pages.update', $page), [
                'topic_id' => $to->id,
                'title' => 'De Planeten',
                'slug' => 'de-planeten',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(7, $page->fresh()->sort_order);
    }

    public function test_a_new_topic_is_added_at_the_end_of_its_own_parent()
    {
        $parent = $this->topic('Natuurkunde');
        $this->topic('Sterrenkunde', parentId: $parent->id, order: 4);
        $this->topic('Scheikunde', order: 9);

        $this->actingAs($this->admin())
            ->post(route('admin.topics.store'), [
                'title' => 'Optica',
                'slug' => 'optica',
                'parent_id' => $parent->id,
            ])
            ->assertSessionHasNoErrors();

        // 5, not 10: the end of *this* parent's list, not of the whole table.
        $this->assertSame(5, Topic::query()->where('slug', 'optica')->value('sort_order'));
    }
}
