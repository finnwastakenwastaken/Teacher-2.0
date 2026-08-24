<?php

namespace App\Console\Commands;

use App\Models\AccessPassword;
use App\Models\EducationLevel;
use App\Models\Image;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use App\Services\MediaLibrary;
use App\Support\AdminAccount;
use App\Support\ContentLanguage;
use App\Support\SiteSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The fixtures `tests/e2e` expects, written straight into the running site.
 *
 * Deliberately not reached from DatabaseSeeder: that runs from the container
 * entrypoint on every boot, including on the teacher's real install, and this
 * writes throwaway content and resets the admin password. It is a separate
 * command that refuses outside development for the same reason.
 *
 * Idempotent, and idempotent in the strong sense: it restores the fixture
 * state rather than merely declining to duplicate it. The ordering specs
 * reorder what they find, so a second run has to put it back or the next run
 * starts from wherever the last one finished.
 */
class SeedBrowserTestFixtures extends Command
{
    protected $signature = 'e2e:seed';

    protected $description = 'Create the deterministic fixtures the browser tests expect';

    /**
     * The root of everything this command writes. Hidden, so fixtures never
     * appear on the homepage grid or in navigation of a site somebody is
     * actually working on — they still resolve at their own URLs, which is
     * all the tests need.
     */
    public const ROOT_SLUG = 'e2e';

    public const ADMIN_EMAIL = 'e2e@teacher.test';

    public const ADMIN_PASSWORD = 'Playwright!2026#fixture';

    public const ACCESS_PASSWORD = 'e2e-unlock-2026';

    /**
     * Two throwaway education levels for the merge spec. Named rather than
     * reusing the seeded HAVO/VWO tracks, so a run of this command never
     * touches real content an owner may have already tagged with those.
     */
    public const LEVEL_SOURCE_NAME = 'E2E-Bron';

    public const LEVEL_TARGET_NAME = 'E2E-Doel';

    /** Created and deleted by the "creating a level" spec itself. Cleaned up
     *  here too, so a run that failed mid-test does not leave it behind for
     *  the next one to trip over. */
    public const LEVEL_PROBE_NAME = 'E2E-Nieuw';

    /**
     * A password of its own, separate from ACCESS_PASSWORD above — the
     * "changing a password invalidates the unlock cookie" spec has to change
     * this one's secret, and doing that to the password `gated-media.spec.ts`
     * also relies on would make the two specs order-dependent.
     */
    public const COOKIE_PASSWORD_NAME = 'E2E-Cookie';

    public const COOKIE_PASSWORD_SECRET = 'e2e-cookie-2026';

    /**
     * Root of a *visible* branch, deliberately outside ROOT_SLUG.
     *
     * Full-text search asks App\Support\ContentVisibility, which walks every
     * ancestor and excludes the page if any of them is hidden — so a page
     * under the hidden `e2e` root can never be a search result, no matter its
     * own is_hidden value. Testing search therefore needs at least one
     * fixture that is findable, which means it cannot hide under the same
     * root as everything else. It stays out of ROOT_SLUG's shadow but is
     * still named and slugged for what it is, so it reads as a fixture
     * rather than real content on a dev site.
     */
    public const SEARCH_ROOT_SLUG = 'e2e-zoekbaar';

    /**
     * The page whose body floats an image beside its text, and the heading
     * after it that must clear rather than sit in the gutter. Named here
     * because the spec asserts against both.
     */
    public const ASIDE_SLUG = 'aside';

    public const ASIDE_HEADING = 'Kop na de afbeelding';

    public const ASIDE_IMAGE_NAME = 'aside-fixture.png';

    public function handle(MediaLibrary $library): int
    {
        if (app()->isProduction()) {
            $this->components->error(
                'e2e:seed writes throwaway content and resets the admin password. It refuses to run in production.'
            );

            return self::FAILURE;
        }

        DB::transaction(function () use ($library) {
            $this->seedAdmin();
            $this->seedContent($library);
        });

        $this->components->info('Browser test fixtures are in place.');
        $this->components->warn('The admin password is now '.self::ADMIN_PASSWORD.' — this is a development site.');

        return self::SUCCESS;
    }

    /**
     * There is only ever one admin account and the tests have to log in as it,
     * so an existing one has its password reset rather than a second created.
     */
    private function seedAdmin(): void
    {
        $admin = User::query()->first();

        if ($admin === null) {
            AdminAccount::claim([
                'name' => 'E2E',
                'email' => self::ADMIN_EMAIL,
                'password' => self::ADMIN_PASSWORD,
            ]);

            return;
        }

        $admin->forceFill(['password' => self::ADMIN_PASSWORD])->save();
    }

    private function seedContent(MediaLibrary $library): void
    {
        $root = $this->topic(null, self::ROOT_SLUG, 'E2E fixtures', 0, ['is_hidden' => true]);

        // The shape that broke once: a list holding exactly one item, nested
        // inside a list holding several. The lone row rendered a drag handle
        // and registered as a drop target, and swallowed the drop meant for
        // the list around it.
        $ordering = $this->topic($root, 'ordering', 'Ordering', 0);
        $solo = $this->topic($ordering, 'solo', 'Solo', 0);

        $this->page($solo, 'only-child', 'Only child', 0);

        foreach (['page-a' => 1, 'page-b' => 2, 'page-c' => 3] as $slug => $order) {
            // Cleared rather than left: the downloads spec attaches one to
            // Page A, and a second run has to start where the first did.
            $this->page($ordering, $slug, 'Page '.strtoupper(substr($slug, -1)), $order)
                ->downloads()
                ->delete();
        }

        // A cross-origin iframe that needs its own referrer policy, because
        // the site-wide `same-origin` sends no Referer at all and YouTube
        // answers that with a player error rather than the video.
        $this->page($root, 'video', 'Video', 1, [], [
            'type' => 'doc',
            'content' => [[
                'type' => 'youtubeEmbed',
                'attrs' => ['videoId' => 'dQw4w9WgXcQ'],
            ]],
        ]);

        $open = $this->page($root, 'open', 'Open page', 2);
        $locked = $this->page($root, 'locked', 'Locked page', 3, [
            'access_password_id' => $this->accessPassword()->id,
        ], [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Alleen zichtbaar na het wachtwoord.']],
            ]],
        ]);

        $this->attachDownload($library, $open, 'open-handout');
        $this->attachDownload($library, $locked, 'locked-handout');

        $this->seedAsideFixture($library, $root);

        $this->seedLevelsFixture($library, $root);
        $this->seedCookiePasswordFixture($root);
        $this->seedSearchFixture();

        // Authoritative reset for the one setting that is site-wide rather
        // than scoped under ROOT_SLUG: the content-language spec changes it,
        // and a second run must not start from whatever the first left
        // behind. Reindexing here too, for the same reason the controller
        // does it on every change — a stale vector from a previous run's
        // 'english' setting would make the next run's baseline search
        // assertion (no match under 'dutch') fail for a reason that has
        // nothing to do with the code under test.
        if (ContentLanguage::current() !== ContentLanguage::DEFAULT) {
            SiteSettings::put(['content_language' => ContentLanguage::DEFAULT]);
        }
        Artisan::call('search:reindex');

        // A level created and deleted by its own spec cleans itself up; this
        // only repairs a run that failed before reaching the delete step.
        // Deleted model-by-model, not with a bulk query, so the `deleting`
        // guard still runs — this level should never have downloads
        // attached, and if one somehow does, that is worth failing loudly
        // for rather than silently orphaning a pivot row.
        EducationLevel::query()->where('name', self::LEVEL_PROBE_NAME)->get()->each->delete();
    }

    /**
     * Two levels and two downloads for the "merging a level retires it"
     * spec — one download tagged with the source level alone, one already
     * carrying both. The second is the case the technical reference warns about: merging
     * must re-tag it to one row, not collide with the pivot's unique pair.
     *
     * Re-created by name every run rather than left alone: the merge spec
     * deletes the source level, so `updateOrCreate` on the name is what
     * brings it back with a fresh id, and `sync()` below is what puts the
     * downloads back in their pre-merge state regardless of what the last
     * run's merge did to them.
     */
    private function seedLevelsFixture(MediaLibrary $library, Topic $root): void
    {
        $source = EducationLevel::query()->updateOrCreate(
            ['name' => self::LEVEL_SOURCE_NAME],
            ['slug' => Str::slug(self::LEVEL_SOURCE_NAME)],
        );
        $target = EducationLevel::query()->updateOrCreate(
            ['name' => self::LEVEL_TARGET_NAME],
            ['slug' => Str::slug(self::LEVEL_TARGET_NAME)],
        );

        $topic = $this->topic($root, 'levels', 'Levels', 4);
        $page = $this->page($topic, 'merge', 'Levels merge fixture', 0, [], [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Fixture voor het samenvoegen van niveaus.']],
            ]],
        ]);

        $this->taggedDownload($library, $page, 'e2e-level-solo', 'Solo doc', [$source->id]);
        $this->taggedDownload($library, $page, 'e2e-level-both', 'Both doc', [$source->id, $target->id]);
    }

    /**
     * Create the download if it is not already there, then set its levels to
     * exactly the given list either way — the resync is what makes this safe
     * to call on a page a previous run already tagged differently.
     *
     * @param  list<int>  $levelIds
     */
    private function taggedDownload(MediaLibrary $library, Page $page, string $slug, string $label, array $levelIds): void
    {
        $download = $page->downloads()
            ->whereHas('mediaFile', fn ($query) => $query->where('original_filename', $slug.'.pdf'))
            ->first();

        if ($download === null) {
            $source = sys_get_temp_dir().'/'.$slug.'.pdf';
            file_put_contents($source, $this->minimalPdf($label));

            $file = $library->ingest($source, $slug.'.pdf', moveSource: true);

            $download = $page->downloads()->create([
                'media_file_id' => $file->id,
                'label' => $label,
                'sort_order' => 0,
            ]);
        }

        $download->educationLevels()->sync($levelIds);
    }

    /**
     * A page protected by a password of its own, for the spec that changes a
     * password and expects a browser already holding its unlock cookie to be
     * asked again. Separate from the shared `accessPassword()` below so that
     * spec is the only one whose secret ever changes.
     */
    private function seedCookiePasswordFixture(Topic $root): void
    {
        $password = $this->resetPassword(self::COOKIE_PASSWORD_NAME, self::COOKIE_PASSWORD_SECRET);

        $this->page($root, 'cookie-test', 'Cookie test', 5, [
            'access_password_id' => $password->id,
        ], [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Inhoud na ontgrendelen.']],
            ]],
        ]);
    }

    /**
     * A branch the search spec can find. Deliberately not hidden and not
     * under ROOT_SLUG — see the constant's doc block for why a hidden
     * ancestor would make it unfindable no matter what.
     *
     * The body's word choice is the fixture: 'dutch' and 'english' stem
     * "running" differently. `to_tsvector('dutch', 'running')` leaves the
     * token unchanged, so a search for "run" does not match it; switching
     * `content_language` to English and reindexing reduces it to "run",
     * which is exactly the consequence the spec exists to prove — that an
     * *already-stored* page gets re-stemmed by the setting, not just pages
     * saved after it changes.
     */
    private function seedSearchFixture(): void
    {
        $root = $this->topic(null, self::SEARCH_ROOT_SLUG, 'E2E doorzoekbaar', 99);

        $this->page($root, 'stemtest', 'Stemtest', 0, [], [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'This fixture keeps running for the stemming test.']],
            ]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function topic(?Topic $parent, string $slug, string $title, int $order, array $extra = []): Topic
    {
        return Topic::query()->updateOrCreate(
            ['parent_id' => $parent?->id, 'slug' => $slug],
            [...$extra, 'title' => $title, 'sort_order' => $order],
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>|null  $document
     */
    private function page(Topic $topic, string $slug, string $title, int $order, array $extra = [], ?array $document = null): Page
    {
        $page = Page::query()->updateOrCreate(
            ['topic_id' => $topic->id, 'slug' => $slug],
            [...$extra, 'title' => $title, 'sort_order' => $order],
        );

        if ($document !== null) {
            $page->writeContent($document);
        }

        return $page;
    }

    private function accessPassword(): AccessPassword
    {
        return $this->resetPassword('E2E', self::ACCESS_PASSWORD);
    }

    /**
     * Find-or-create a named password and pin its secret to $secret either
     * way, so a run after a spec changed it — that is the whole point of the
     * password-change spec — still matches what the other specs type. Also
     * what keeps the fixture correct after somebody changes one by hand on a
     * shared dev instance.
     */
    private function resetPassword(string $name, string $secret): AccessPassword
    {
        $password = AccessPassword::query()->where('name', $name)->first();

        if ($password === null) {
            return AccessPassword::createWithPassword($name, $secret);
        }

        // Changing it also invalidates every unlock cookie issued under the
        // old one, which is the point — see App\Support\AccessControl.
        $password->changePassword($secret);

        return $password;
    }

    /**
     * A page whose body floats an image beside its text, for the spec that
     * covers what no PHP test can see: whether the text actually wraps, and
     * whether the float is contained rather than running out over what
     * follows.
     *
     * The image has a **real aspect ratio** on purpose. A 1×1 pixel proves a
     * float exists and never that anything wraps around it, which is the
     * thing worth protecting — so this is 400×300, wide enough at every
     * breakpoint to leave a measurably narrower line beside it.
     *
     * Two paragraphs, then a heading. The paragraphs are what wraps; the
     * heading is what must clear, because a picture taller than its text
     * otherwise goes on wrapping the next heading and reads as broken.
     */
    private function seedAsideFixture(MediaLibrary $library, Topic $root): void
    {
        $image = Image::query()->where('original_filename', self::ASIDE_IMAGE_NAME)->first();

        if ($image === null) {
            $source = sys_get_temp_dir().'/'.self::ASIDE_IMAGE_NAME;

            // Imagick rather than GD: the image has imagick and its HEIC and
            // WebP coders (the technical reference), and GD is not installed at all.
            $canvas = new \Imagick;
            $canvas->newImage(400, 300, new \ImagickPixel('#3355aa'));
            $canvas->setImageFormat('png');
            $canvas->writeImage($source);
            $canvas->clear();

            $ingested = $library->ingest(
                $source,
                self::ASIDE_IMAGE_NAME,
                // Required — the database, the service and the Form Request
                // all refuse an image without it.
                'Een blauw vlak van 400 bij 300',
                moveSource: true,
            );

            // ingest() returns Image|MediaFile by signature; a PNG is always
            // the first, and the seeder should say so rather than assume it.
            if (! $ingested instanceof Image) {
                throw new \RuntimeException('The aside fixture ingested as a file rather than an image.');
            }

            $image = $ingested;
        }

        $paragraph = fn (string $text) => [
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ];

        $this->page($root, self::ASIDE_SLUG, 'Aside', 4, [], [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'imageAside',
                    'attrs' => [
                        'ulid' => $image->ulid,
                        'side' => 'right',
                        'size' => 'medium',
                    ],
                ],
                $paragraph(str_repeat('Deze alinea loopt om de afbeelding heen. ', 12)),
                $paragraph(str_repeat('En deze alinea ook, zodat er genoeg tekst is. ', 12)),
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 2],
                    'content' => [['type' => 'text', 'text' => self::ASIDE_HEADING]],
                ],
            ],
        ]);
    }

    /**
     * Downloads need real bytes on the private disk: the whole point of the
     * media specs is that nginx streams them through the internal location.
     */
    private function attachDownload(MediaLibrary $library, Page $page, string $name): void
    {
        if ($page->downloads()->exists()) {
            return;
        }

        $source = sys_get_temp_dir().'/'.$name.'.pdf';
        file_put_contents($source, $this->minimalPdf($name));

        $file = $library->ingest($source, $name.'.pdf', moveSource: true);

        $page->downloads()->create([
            'media_file_id' => $file->id,
            'label' => 'Handout',
            'sort_order' => 0,
        ]);
    }

    private function minimalPdf(string $title): string
    {
        $body = "BT /F1 24 Tf 72 720 Td ({$title}) Tj ET";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($body)." >>\nstream\n{$body}\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $start = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$start}\n%%EOF";
    }
}
