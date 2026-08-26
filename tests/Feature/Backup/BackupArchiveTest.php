<?php

namespace Tests\Feature\Backup;

use App\Exceptions\BackupException;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use App\Services\BackupArchive;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An archive must contain both the database and the private media disk — one
 * without the other restores into a site full of broken links. These tests
 * run the real pg_dump and tar rather than mocking them.
 */
class BackupArchiveTest extends TestCase
{
    // DatabaseTruncation, not RefreshDatabase: pg_dump runs on its own
    // connection and would see nothing inside RefreshDatabase's open
    // transaction, and a restore's psql would deadlock against its locks.
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        // Both disks are faked, or a test deleting the image directory would
        // delete the developer's own media volume.
        Storage::fake('local');
        Storage::fake('backups');
    }

    /**
     * A restore commits on its own connection, outside any transaction this
     * process controls, so nothing rolls it back automatically. Later
     * RefreshDatabase tests assume an empty database; leaving rows here would
     * fail them instead of us.
     */
    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_an_archive_contains_a_manifest_a_dump_and_the_media()
    {
        $this->storedImage('images/2026/08/example.webp', 'pretend-webp');

        $name = app(BackupArchive::class)->create();

        $members = $this->members($name);

        $this->assertContains('manifest.json', $members);
        $this->assertContains('database.sql', $members);
        $this->assertContains('images/2026/08/example.webp', $members);
    }

    public function test_the_manifest_describes_what_was_archived()
    {
        Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $manifest = $this->manifest(app(BackupArchive::class)->create());

        $this->assertSame(config('backup.format'), $manifest['format']);
        $this->assertSame(1, $manifest['counts']['topics']);
        $this->assertNotEmpty($manifest['migration']);
    }

    /**
     * The whole point: a dump that only holds the database restores into a
     * site whose every image and download is a dead link.
     */
    public function test_the_dump_carries_real_content()
    {
        $topic = Topic::query()->create(['title' => 'Sterrenkunde', 'slug' => 'sterrenkunde']);
        Page::query()->create(['title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $topic->id]);

        $sql = $this->extract(app(BackupArchive::class)->create(), 'database.sql');

        $this->assertStringContainsString('Sterrenkunde', $sql);
        $this->assertStringContainsString('de-planeten', $sql);
    }

    /**
     * Chunk staging holds half-finished uploads. They are worth nothing to a
     * restore and can be enormous.
     */
    public function test_abandoned_upload_chunks_are_left_out()
    {
        $this->storedImage('chunks/abandoned/0', 'half an upload');
        $this->storedImage('images/2026/08/kept.webp', 'pretend-webp');

        $members = $this->members(app(BackupArchive::class)->create());

        $this->assertContains('images/2026/08/kept.webp', $members);
        $this->assertNotContains('chunks/abandoned/0', $members);
    }

    public function test_a_site_with_no_uploads_still_archives()
    {
        Storage::disk(config('media.disk'))->deleteDirectory(config('media.directories.images'));
        Storage::disk(config('media.disk'))->deleteDirectory(config('media.directories.media'));

        $members = $this->members(app(BackupArchive::class)->create());

        $this->assertContains('database.sql', $members);
    }

    /**
     * An archive is the entire database, password hashes included. The name in
     * a URL or on a command line may only ever be one of these files.
     */
    public function test_only_a_real_archive_name_resolves()
    {
        $archive = app(BackupArchive::class);

        foreach ([
            '../../.env',
            '/etc/passwd',
            'teacher-backup-2026-08-20-215244.tar.gz/../../.env',
            'not-a-backup.tar.gz',
            'teacher-backup-nonsense.tar.gz',
        ] as $attempt) {
            try {
                $archive->resolve($attempt);
                $this->fail("Resolved a name it should have refused: {$attempt}");
            } catch (BackupException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_valid_name_that_does_not_exist_is_refused_too()
    {
        $this->expectException(BackupException::class);

        app(BackupArchive::class)->resolve('teacher-backup-2020-01-01-000000.tar.gz');
    }

    public function test_pruning_keeps_the_newest_and_reports_what_went()
    {
        $disk = Storage::disk('backups');

        foreach (['2026-08-01-100000', '2026-08-02-100000', '2026-08-03-100000'] as $stamp) {
            $disk->put(config('backup.name_prefix').$stamp.'.tar.gz', 'x');
        }

        $removed = app(BackupArchive::class)->prune(1);

        $this->assertCount(2, $removed);
        $this->assertCount(1, app(BackupArchive::class)->all());
    }

    public function test_restoring_a_file_that_is_not_an_archive_is_refused_before_anything_changes()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        $path = storage_path('app/not-an-archive.tar.gz');
        File::put($path, gzencode('this is not a tar file'));

        try {
            app(BackupArchive::class)->restore($path);
            $this->fail('Restored something that was not an archive.');
        } catch (BackupException) {
            $this->addToAssertionCount(1);
        } finally {
            File::delete($path);
        }

        // The database is untouched: the failure happened while unpacking.
        $this->assertTrue($topic->fresh()->exists);
    }

    /**
     * A backup made by a newer version of the site must be refused with an
     * explanation, not half-applied. A partly-restored site is worse than one
     * that never started.
     */
    public function test_an_archive_from_a_future_version_is_refused()
    {
        $staging = storage_path('app/future-archive');
        File::ensureDirectoryExists($staging);
        File::put($staging.'/manifest.json', json_encode(['format' => 99]));
        File::put($staging.'/database.sql', '-- nothing');

        $path = storage_path('app/future.tar.gz');
        exec('tar -czf '.escapeshellarg($path).' -C '.escapeshellarg($staging).' manifest.json database.sql');

        try {
            app(BackupArchive::class)->restore($path);
            $this->fail('Restored an archive from a newer format.');
        } catch (BackupException $e) {
            $this->assertStringContainsString('nieuwere versie', $e->getMessage());
        } finally {
            File::delete($path);
            File::deleteDirectory($staging);
        }
    }

    /**
     * The round trip that matters: archive a site, change it, put the archive
     * back, and find the original content again — database and media both.
     */
    public function test_an_archive_restores_the_database_and_the_media()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $this->storedImage('images/2026/08/original.webp', 'the original bytes');

        $archive = app(BackupArchive::class);
        $name = $archive->create();
        $path = Storage::disk('backups')->path($name);

        // Now wreck the site: delete the topic, replace the media.
        $topic->delete();
        Storage::disk(config('media.disk'))->delete('images/2026/08/original.webp');
        $this->storedImage('images/2026/08/intruder.webp', 'should not survive');

        $manifest = $archive->restore($path);

        $this->assertSame(1, $manifest['counts']['topics']);
        $this->assertDatabaseHas('topics', ['slug' => 'natuurkunde']);

        $media = Storage::disk(config('media.disk'));
        $this->assertSame('the original bytes', $media->get('images/2026/08/original.webp'));

        // Replaced wholesale, not merged: a file the archive does not contain
        // is a file the restored database knows nothing about.
        $this->assertFalse($media->exists('images/2026/08/intruder.webp'));
    }

    public function test_the_restored_admin_account_is_the_one_from_the_archive()
    {
        $user = User::factory()->create(['email' => 'docent@school.test']);

        $archive = app(BackupArchive::class);
        $path = Storage::disk('backups')->path($archive->create());

        $user->forceFill(['email' => 'someone-else@example.test'])->save();

        $archive->restore($path);

        $this->assertDatabaseHas('users', ['email' => 'docent@school.test']);
        $this->assertDatabaseMissing('users', ['email' => 'someone-else@example.test']);
    }

    private function storedImage(string $path, string $contents): void
    {
        Storage::disk(config('media.disk'))->put($path, $contents);
    }

    /**
     * @return list<string>
     */
    private function members(string $name): array
    {
        $path = Storage::disk('backups')->path($name);

        exec('tar -tzf '.escapeshellarg($path), $output);

        return array_map(fn (string $line) => rtrim($line, '/'), $output);
    }

    private function extract(string $name, string $member): string
    {
        $path = Storage::disk('backups')->path($name);

        return (string) shell_exec(
            'tar -xzOf '.escapeshellarg($path).' '.escapeshellarg($member)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(string $name): array
    {
        return json_decode($this->extract($name, 'manifest.json'), true);
    }
}
