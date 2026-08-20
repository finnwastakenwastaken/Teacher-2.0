<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Back-ups screen.
 *
 * An archive is the entire database — every password hash, every setting — so
 * the interesting assertions here are all about who cannot reach one. Unlike
 * every other route that serves a private file, this one never consults
 * App\Support\MediaAccess: that class answers "may this visitor see this
 * file", and its answer is yes for anything a public page shows.
 */
class BackupScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('backups');
    }

    public function test_guests_cannot_reach_any_of_it()
    {
        $name = $this->existingArchive();

        $this->get(route('admin.backups.index'))->assertRedirect(route('login'));
        $this->post(route('admin.backups.store'))->assertRedirect(route('login'));
        $this->get(route('admin.backups.download', $name))->assertRedirect(route('login'));
        $this->delete(route('admin.backups.destroy', $name))->assertRedirect(route('login'));
    }

    public function test_the_admin_sees_the_archives_newest_first()
    {
        $this->existingArchive('2026-08-01-100000');
        $this->existingArchive('2026-08-03-100000');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/backups/index')
                ->has('backups', 2)
                ->where('backups.0.name', 'teacher-backup-2026-08-03-100000.tar.gz')
            );
    }

    public function test_downloading_an_archive_streams_it_as_an_attachment()
    {
        $name = $this->existingArchive();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.backups.download', $name));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/gzip');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString($name, $response->headers->get('Content-Disposition'));
    }

    /**
     * The route pattern allows dots, because `.tar.gz` needs them. What keeps
     * it safe is App\Services\BackupArchive::resolve(), not the pattern.
     */
    public function test_a_name_that_is_not_an_archive_is_a_404_not_a_file()
    {
        $admin = User::factory()->create();

        foreach (['.env', 'database.sql', 'teacher-backup-nonsense.tar.gz'] as $attempt) {
            $this->actingAs($admin)
                ->get('/admin/back-ups/'.$attempt)
                ->assertNotFound();
        }
    }

    public function test_the_owner_can_delete_an_archive()
    {
        $name = $this->existingArchive();

        $this->actingAs(User::factory()->create())
            ->from(route('admin.backups.index'))
            ->delete(route('admin.backups.destroy', $name))
            ->assertRedirect(route('admin.backups.index'));

        Storage::disk('backups')->assertMissing($name);
    }

    /**
     * The archive must not be reachable through the media disk's rules. That
     * separation is why it lives on its own disk with its own nginx location
     * — see config/backup.php.
     */
    public function test_an_archive_is_not_on_the_media_disk()
    {
        $this->existingArchive();

        $this->assertEmpty(
            Storage::disk(config('media.disk'))->allFiles(''),
            'A backup archive must never be written where gated media is served from.'
        );
    }

    private function existingArchive(string $stamp = '2026-08-20-215244'): string
    {
        $name = config('backup.name_prefix').$stamp.'.tar.gz';

        Storage::disk('backups')->put($name, 'pretend-archive');

        return $name;
    }
}
