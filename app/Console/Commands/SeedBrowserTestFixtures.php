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
use App\Support\ThemePalette;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The fixtures `tests/e2e` expects, written straight into the running site.
 * Not reached from DatabaseSeeder — that runs on every boot including a real
 * install, and this resets the admin password. Refuses outside development.
 * Idempotent in the strong sense: it *restores* fixture state (rather than
 * declining to duplicate it), since the ordering specs reorder what they
 * find and a second run must put it back.
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
     * Separate from ACCESS_PASSWORD: the "changing a password invalidates
     * the unlock cookie" spec changes this one's secret, which would make
     * specs order-dependent if it shared a password with gated-media.spec.ts.
     */
    public const COOKIE_PASSWORD_NAME = 'E2E-Cookie';

    public const COOKIE_PASSWORD_SECRET = 'e2e-cookie-2026';

    /**
     * Root of a *visible* branch, deliberately outside ROOT_SLUG. Search
     * excludes anything under a hidden ancestor regardless of its own
     * is_hidden value, so a findable fixture can't live under the hidden
     * `e2e` root.
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

    /**
     * A page holding a gallery of three images and a banner of its own.
     *
     * Three rather than two because the lightbox wraps at both ends, and a
     * pair cannot tell "next" apart from "previous". The banner is on the
     * same page so one spec can cover both a set and a set of one — the
     * banner must enlarge and must draw no arrows.
     */
    public const GALLERY_SLUG = 'gallery';

    /**
     * Distinct alt texts, because the whole point is that the alt travels
     * into the enlarged view: three copies of one string would pass whether
     * it did or not.
     *
     * @var list<array{name: string, alt: string, colour: string}>
     */
    public const GALLERY_IMAGES = [
        ['name' => 'gallery-one.png', 'alt' => 'Eerste vlak, rood', 'colour' => '#aa3333'],
        ['name' => 'gallery-two.png', 'alt' => 'Tweede vlak, groen', 'colour' => '#33aa55'],
        ['name' => 'gallery-three.png', 'alt' => 'Derde vlak, geel', 'colour' => '#ccaa33'],
    ];

    public const GALLERY_BANNER_NAME = 'gallery-banner.png';

    public const GALLERY_BANNER_ALT = 'Banner van de galerijpagina';

    /**
     * A TikTok and an Instagram reel on one page.
     *
     * Both ids are syntactically valid and neither has to resolve to a real
     * post: the spec asserts that nothing is requested from either platform
     * until a student presses the button, and blocks both hosts outright so
     * the run stays hermetic. What is under test is the shape of what the
     * renderer emits, not what TikTok would answer.
     */
    public const SOCIAL_SLUG = 'social';

    public const SOCIAL_TIKTOK_ID = '7234567890123456789';

    public const SOCIAL_INSTAGRAM_ID = 'C1a2B3c4D5e';

    /**
     * A page of its own for the version-history spec to publish over.
     *
     * It has to be its own page because that spec replaces the whole body
     * twice and then restores an older one — done to any page another spec
     * reads, that would be a body those specs no longer recognise. The
     * history it accumulates over runs is harmless: the spec writes text
     * unique to the run and works from the newest entry, so it never depends
     * on what earlier runs left behind, and the cap of ten keeps the pile
     * from growing.
     */
    public const HISTORY_SLUG = 'geschiedenis';

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
        $this->components->warn('The admin account is now '.self::ADMIN_EMAIL.' / '.self::ADMIN_PASSWORD.' — this is a development site.');

        return self::SUCCESS;
    }

    /**
     * There is only ever one admin account and the tests have to log in as it,
     * so an existing one is rewritten rather than a second created.
     *
     * The address is reset as well as the password: the specs log in as a
     * constant they share with this file, so leaving whatever address the
     * developer claimed their own instance with means the suite cannot log in
     * at all — the setup project fails and every spec after it is skipped.
     * Resetting only half the credential looked tidier and made this command
     * unable to do the one thing it exists for.
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

        $admin->forceFill([
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ])->save();
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

        // Two embeds that must contact nobody until a student asks. On a page
        // of its own so the spec can assert "no request to either host" about
        // the whole document rather than about one element.
        $this->page($root, self::SOCIAL_SLUG, 'Social', 5, [], [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'socialEmbed',
                    'attrs' => [
                        'platform' => 'tiktok',
                        'postId' => self::SOCIAL_TIKTOK_ID,
                    ],
                ],
                [
                    'type' => 'socialEmbed',
                    'attrs' => [
                        'platform' => 'instagram',
                        'postId' => self::SOCIAL_INSTAGRAM_ID,
                    ],
                ],
            ],
        ]);

        // Something for the version-history spec to publish over. Seeded with
        // a body rather than left empty, so the page has been published once
        // and the spec's own first publish has an outgoing body to record.
        $this->page($root, self::HISTORY_SLUG, 'Geschiedenis', 6, [], [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'De tekst waar de geschiedenistest overheen publiceert.']],
            ]],
        ]);

        $this->seedAsideFixture($library, $root);
        $this->seedGalleryFixture($library, $root);

        $this->seedLevelsFixture($library, $root);
        $this->seedCookiePasswordFixture($root);
        $this->seedSearchFixture();

        // Reset the one setting that is site-wide rather than scoped under
        // ROOT_SLUG: the content-language spec changes it, so a second run
        // must not inherit the previous run's value. Reindex too, or a
        // stale vector fails the next run's baseline search assertion.
        if (ContentLanguage::current() !== ContentLanguage::DEFAULT) {
            SiteSettings::put(['content_language' => ContentLanguage::DEFAULT]);
        }
        Artisan::call('search:reindex');

        // The palette, for the same reason: the theme spec overrides a colour
        // and resets it, so a run that stopped early leaves the next run's
        // baseline colour wrong rather than the code.
        SiteSettings::forget(ThemePalette::SETTING);

        // Repairs a run that failed before reaching its own delete step.
        // Deleted model-by-model, not via a bulk query, so the `deleting`
        // guard still runs if downloads were somehow attached.
        EducationLevel::query()->where('name', self::LEVEL_PROBE_NAME)->get()->each->delete();
    }

    /**
     * Two levels and two downloads for the "merging a level retires it"
     * spec — one tagged with the source level alone, one already carrying
     * both (the pivot-collision case merge must handle). Re-created by name
     * every run: the spec deletes the source level, so `updateOrCreate`
     * brings it back and `sync()` restores the pre-merge tag state.
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
     * A branch the search spec can find (not hidden, not under ROOT_SLUG —
     * see that constant's doc block). The body's wording is the fixture:
     * 'dutch' leaves "running" unstemmed but 'english' reduces it to "run",
     * proving an *already-stored* page gets re-stemmed when the setting
     * changes, not just pages saved afterward.
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
     * Find-or-create a named password and pin its secret either way, so a
     * run after the password-change spec ran still matches what other specs
     * type.
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
     * A page whose body floats an image beside its text, for the spec
     * covering what no PHP test can see: whether text actually wraps and the
     * float stays contained. The image is a real 400×300 (not 1×1 — a pixel
     * proves a float exists but never that anything wraps around it). Two
     * paragraphs (which wrap) then a heading (which must clear, or a tall
     * image goes on wrapping it).
     */
    private function seedAsideFixture(MediaLibrary $library, Topic $root): void
    {
        $image = $this->fixtureImage(
            $library,
            self::ASIDE_IMAGE_NAME,
            'Een blauw vlak van 400 bij 300',
            '#3355aa',
            400,
            300,
        );

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
     * A page with a gallery of three and a banner of its own.
     *
     * The lightbox spec needs both shapes on one page: a set the arrows move
     * through, and a set of one that must enlarge without drawing any. Three
     * images rather than two because the arrows wrap at both ends, and with a
     * pair "next" and "previous" land in the same place.
     */
    private function seedGalleryFixture(MediaLibrary $library, Topic $root): void
    {
        $ulids = [];

        foreach (self::GALLERY_IMAGES as $index => $fixture) {
            $ulids[] = $this->fixtureImage(
                $library,
                $fixture['name'],
                $fixture['alt'],
                $fixture['colour'],
                // Different sizes, so a spec that means "the enlarged one" is
                // not accidentally matching the thumbnail's own geometry.
                320 + ($index * 40),
                240 + ($index * 30),
            )->ulid;
        }

        $banner = $this->fixtureImage(
            $library,
            self::GALLERY_BANNER_NAME,
            self::GALLERY_BANNER_ALT,
            '#884499',
            1200,
            400,
        );

        $this->page($root, self::GALLERY_SLUG, 'Gallery', 6, [
            'hero_image_id' => $banner->id,
        ], [
            'type' => 'doc',
            'content' => [[
                'type' => 'imageGallery',
                'attrs' => ['ulids' => $ulids],
            ]],
        ]);
    }

    /**
     * Find-or-create a flat-colour PNG in the library, by filename.
     *
     * Imagick rather than GD: the image ships imagick with its HEIC and WebP
     * coders and GD is not installed at all. The dimensions are real ones
     * rather than a placeholder pixel — a 1×1 image proves an element exists
     * and never that anything was laid out around it.
     */
    private function fixtureImage(
        MediaLibrary $library,
        string $name,
        string $alt,
        string $colour,
        int $width,
        int $height,
    ): Image {
        $existing = Image::query()->where('original_filename', $name)->first();

        if ($existing !== null) {
            return $existing;
        }

        $source = sys_get_temp_dir().'/'.$name;

        $canvas = new \Imagick;
        $canvas->newImage($width, $height, new \ImagickPixel($colour));
        $canvas->setImageFormat('png');
        $canvas->writeImage($source);
        $canvas->clear();

        $ingested = $library->ingest(
            $source,
            $name,
            // Required — the database, the service and the Form Request all
            // refuse an image without it.
            $alt,
            moveSource: true,
        );

        // ingest() returns Image|MediaFile by signature; a PNG is always the
        // first, and the seeder should say so rather than assume it.
        if (! $ingested instanceof Image) {
            throw new \RuntimeException("The {$name} fixture ingested as a file rather than an image.");
        }

        return $ingested;
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
