<?php

namespace Tests\Feature\Admin;

use App\Models\EducationLevel;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\PageDownload;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EducationLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EducationLevelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function level(string $name, int $order = 0): EducationLevel
    {
        return EducationLevel::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => $order,
        ]);
    }

    private function downloadTaggedWith(EducationLevel ...$levels): PageDownload
    {
        Storage::fake('local');

        $topic = Topic::query()->firstOrCreate(
            ['parent_id' => null, 'slug' => 'natuurkunde'],
            ['title' => 'Natuurkunde']
        );
        $page = Page::query()->create([
            'title' => 'De Planeten',
            'slug' => 'de-planeten-'.Str::lower(Str::random(6)),
            'topic_id' => $topic->id,
        ]);

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        $file = MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 5,
            'original_filename' => 'werkblad.pdf',
        ]);

        $download = $page->downloads()->create(['media_file_id' => $file->id]);
        $download->educationLevels()->sync(collect($levels)->pluck('id')->all());

        return $download;
    }

    public function test_the_admin_can_add_a_level_and_the_slug_is_derived()
    {
        $this->actingAs($this->admin())
            ->from(route('admin.levels.index'))
            ->post(route('admin.levels.store'), ['name' => 'VMBO-GT'])
            ->assertSessionHas('status');

        $this->assertSame('vmbo-gt', EducationLevel::query()->first()->slug);
    }

    public function test_two_levels_cannot_share_a_name()
    {
        $this->level('HAVO');

        $this->actingAs($this->admin())
            ->from(route('admin.levels.index'))
            ->post(route('admin.levels.store'), ['name' => 'havo'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, EducationLevel::query()->count());
    }

    public function test_the_admin_can_rename_a_level()
    {
        $level = $this->level('HAVO');

        $this->actingAs($this->admin())
            ->from(route('admin.levels.index'))
            ->put(route('admin.levels.update', $level), ['name' => 'HAVO bovenbouw', 'sort_order' => 5])
            ->assertSessionHas('status');

        $level->refresh();

        $this->assertSame('HAVO bovenbouw', $level->name);
        $this->assertSame(5, $level->sort_order);
    }

    public function test_an_unused_level_can_be_deleted()
    {
        $level = $this->level('HAVO');

        $this->actingAs($this->admin())
            ->from(route('admin.levels.index'))
            ->delete(route('admin.levels.destroy', $level))
            ->assertSessionHas('status');

        $this->assertModelMissing($level);
    }

    public function test_a_level_in_use_cannot_simply_be_deleted()
    {
        $havo = $this->level('HAVO');
        $this->downloadTaggedWith($havo);

        $this->actingAs($this->admin())
            ->from(route('admin.levels.index'))
            ->delete(route('admin.levels.destroy', $havo))
            ->assertSessionHas('error');

        $this->assertModelExists($havo);
    }

    public function test_a_level_in_use_can_be_merged_into_another_one()
    {
        $havo = $this->level('HAVO', 2);
        $vwo = $this->level('VWO', 3);
        $download = $this->downloadTaggedWith($havo);

        $this->actingAs($this->admin())
            ->from(route('admin.levels.index'))
            ->delete(route('admin.levels.destroy', $havo), ['merge_into' => $vwo->id])
            ->assertSessionHas('status');

        $this->assertModelMissing($havo);
        $this->assertSame([$vwo->id], $download->fresh()->educationLevels->pluck('id')->all());
    }

    public function test_merging_into_a_level_a_download_already_has_leaves_one_tag()
    {
        $havo = $this->level('HAVO', 2);
        $vwo = $this->level('VWO', 3);
        $download = $this->downloadTaggedWith($havo, $vwo);

        $this->actingAs($this->admin())
            ->from(route('admin.levels.index'))
            ->delete(route('admin.levels.destroy', $havo), ['merge_into' => $vwo->id])
            ->assertSessionHas('status');

        // The unique pair on the pivot means the naive "re-point every row"
        // merge would fail here; the download must end up tagged once.
        $this->assertSame([$vwo->id], $download->fresh()->educationLevels->pluck('id')->all());
    }

    public function test_a_level_cannot_be_merged_into_itself()
    {
        $havo = $this->level('HAVO');
        $this->downloadTaggedWith($havo);

        $this->actingAs($this->admin())
            ->from(route('admin.levels.index'))
            ->delete(route('admin.levels.destroy', $havo), ['merge_into' => $havo->id])
            ->assertSessionHasErrors('merge_into');

        $this->assertModelExists($havo);
    }

    public function test_guests_cannot_manage_levels()
    {
        $level = $this->level('HAVO');

        $this->get(route('admin.levels.index'))->assertRedirect(route('login'));
        $this->post(route('admin.levels.store'), ['name' => 'x'])->assertRedirect(route('login'));
        $this->delete(route('admin.levels.destroy', $level))->assertRedirect(route('login'));
    }

    public function test_the_seeder_is_idempotent_and_does_not_resurrect_deleted_levels()
    {
        (new EducationLevelSeeder)->run();
        $this->assertSame(4, EducationLevel::query()->count());

        EducationLevel::query()->where('slug', 'vmbo-bk')->delete();
        EducationLevel::query()->where('slug', 'havo')->update(['name' => 'HAVO bovenbouw']);

        (new EducationLevelSeeder)->run();

        $this->assertSame(3, EducationLevel::query()->count());
        $this->assertSame('HAVO bovenbouw', EducationLevel::query()->where('slug', 'havo')->value('name'));
    }

    /**
     * The installer runs the seeders on a live deploy. A seeded user would
     * occupy the single admin slot before the owner ever reached the claim
     * screen — see the technical reference.
     */
    public function test_the_database_seeder_creates_no_user()
    {
        (new DatabaseSeeder)->setContainer($this->app)->run();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(4, EducationLevel::query()->count());
    }
}
