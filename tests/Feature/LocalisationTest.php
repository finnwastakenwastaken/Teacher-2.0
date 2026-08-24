<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * The two locale directories must stay the same shape.
 *
 * A key present in one locale and missing from the other does not fall back
 * to the other language — APP_FALLBACK_LOCALE is `nl`, and even if it were
 * not, a partial file is how a screen ends up showing the raw key path
 * ("validation.required") to a visitor. That reads as a crash, not as a
 * translation gap, so it is worth failing the build over.
 *
 * This is the only guard there is. Translations are data, and nothing else
 * in the pipeline type-checks them.
 */
class LocalisationTest extends TestCase
{
    /**
     * Every locale the application ships. Adding a directory without adding
     * it here means it is never checked; adding it here without the files
     * fails immediately, which is the right way round.
     */
    private const LOCALES = ['nl', 'en'];

    public function test_every_locale_ships_the_same_files()
    {
        $reference = null;

        foreach (self::LOCALES as $locale) {
            $files = collect(glob(lang_path($locale.'/*.php')) ?: [])
                ->map(fn (string $path) => basename($path))
                ->sort()
                ->values()
                ->all();

            $this->assertNotEmpty($files, "lang/{$locale} has no files at all.");

            $reference ??= $files;

            $this->assertSame(
                $reference,
                $files,
                "lang/{$locale} does not ship the same files as the other locales."
            );
        }
    }

    public function test_every_locale_defines_the_same_keys()
    {
        $byLocale = [];

        foreach (self::LOCALES as $locale) {
            $keys = [];

            foreach (glob(lang_path($locale.'/*.php')) ?: [] as $path) {
                $group = basename($path, '.php');
                $keys = [...$keys, ...$this->flatten(require $path, $group)];
            }

            sort($keys);
            $byLocale[$locale] = $keys;
        }

        $reference = array_shift($byLocale);

        foreach ($byLocale as $locale => $keys) {
            $missing = array_diff($reference, $keys);
            $extra = array_diff($keys, $reference);

            $this->assertSame(
                [],
                array_values($missing),
                "lang/{$locale} is missing keys the other locales define."
            );

            $this->assertSame(
                [],
                array_values($extra),
                "lang/{$locale} defines keys the other locales do not."
            );
        }
    }

    /**
     * No message may be left as the English original in the Dutch files, and
     * the other way round — the failure this whole directory exists to fix
     * was a Dutch screen reporting "The password field must be at least 12
     * characters." An identical string in both locales is either an
     * untranslated line or a placeholder, and both want looking at.
     *
     * Proper nouns and format strings would be false positives, so the list
     * of things allowed to be identical is explicit rather than heuristic.
     */
    public function test_no_message_is_identical_in_both_locales()
    {
        $allowed = [
            // The framework's own example rows, kept so the file still
            // documents how a per-field override is written.
            'validation.custom.attribute-name.rule-name',

            // Genuinely the same word in both languages. Listed rather than
            // detected, so a line that merely *looks* translated because
            // someone pasted the English over the Dutch still fails.
            //
            // Dutch has borrowed most of the computing vocabulary wholesale,
            // so these are the words themselves, not untranslated lines.
            'ui.public.downloads.heading',
            'ui.settings.passkeys.title',
            'ui.nav.dashboard',
            'ui.nav.media',
            'ui.dashboard.title',
            'ui.dashboard.media',
            'ui.dashboard.downloads',
            'ui.content.page.downloads_heading',
            'ui.forms.slug',
            'ui.uploader.queue_heading',
            'ui.library.kind_document',
            'ui.library.kind_video',
            'ui.media.title',
            'ui.site.logo',
            'ui.site.favicon',
            'ui.site.section_home',
            'ui.site.banner',
            'ui.editor.link',

            // Chemistry and physics notation, which is the same everywhere.
            'ui.editor.subscript',
            'ui.editor.superscript',

            // Format strings: a URL and a bare count.
            'ui.editor.youtube_dialog.placeholder',
            'ui.levels.download_count',
        ];

        $dutch = $this->messages('nl');
        $english = $this->messages('en');

        $identical = [];

        foreach ($dutch as $key => $message) {
            if (in_array($key, $allowed, true)) {
                continue;
            }

            if (isset($english[$key]) && $english[$key] === $message) {
                $identical[] = $key;
            }
        }

        $this->assertSame(
            [],
            $identical,
            'These lines are the same in Dutch and English, so at least one of them is untranslated: '
                .implode(', ', $identical)
        );
    }

    /**
     * Every key the application asks for must exist in every locale.
     *
     * `__()` returns the key unchanged when it is missing, so a typo or a
     * half-finished rename does not throw — it puts "admin.topics.creted" on
     * screen where a sentence belongs. Nothing else in the pipeline can catch
     * that: translations are data, and the key is a string.
     */
    public function test_every_key_the_application_uses_exists_in_every_locale()
    {
        // Only literal keys. A key built from a variable cannot be checked
        // here, which is why the places that do it (DashboardController's
        // steps, IconCatalogue's library labels, the locale switcher's two
        // options) interpolate a value from a fixed list.
        $scans = [
            [app_path(), ['php'], "/(?:__|trans)\(\s*'([a-z][a-z0-9_]*\.[a-z0-9_.]+)'/i"],

            // The front end is scanned from PHP because this project has no
            // JS test runner — the same arrangement the shared YouTube
            // referrer-policy constant relies on.
            [resource_path('js'), ['ts', 'tsx'], "/\bt\(\s*'([a-z][a-z0-9_]*\.[a-z0-9_.]+)'/"],
        ];

        $used = [];

        foreach ($scans as [$directory, $extensions, $pattern]) {
            foreach ($this->sourceFiles($directory, $extensions) as $path) {
                $source = $this->withoutComments((string) file_get_contents($path));

                preg_match_all($pattern, $source, $matches);

                foreach ($matches[1] as $key) {
                    $used[$key] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
                }
            }
        }

        $this->assertNotEmpty($used, 'Found no translation keys at all — the scan is broken, not the code.');

        $missing = [];

        foreach ($used as $key => $file) {
            foreach (self::LOCALES as $locale) {
                if (trans($key, [], $locale) === $key) {
                    $missing[] = "{$key} (used in {$file}, missing from lang/{$locale})";
                }
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
    }

    /**
     * Comments are stripped before scanning, because a doc block that shows
     * how to call t() is not a call — `lib/i18n.ts` documents itself with
     * three examples, and without this they were reported as missing keys.
     */
    private function withoutComments(string $source): string
    {
        return (string) preg_replace(
            ['#/\*.*?\*/#s', '#(^|\s)//[^\n]*#'],
            ' ',
            $source,
        );
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function sourceFiles(string $directory, array $extensions): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            // Wayfinder writes resources/js/{actions,routes,wayfinder} and
            // they are gitignored, so they may or may not exist. Nothing
            // generated ever calls t().
            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'wayfinder')) {
                continue;
            }

            if (in_array($file->getExtension(), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return array<string, string>
     */
    private function messages(string $locale): array
    {
        $messages = [];

        foreach (glob(lang_path($locale.'/*.php')) ?: [] as $path) {
            $group = basename($path, '.php');

            foreach ($this->flatten(require $path, $group) as $key) {
                $messages[$key] = trans($key, [], $locale);
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return list<string>
     */
    private function flatten(array $lines, string $prefix): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            $path = $prefix.'.'.$key;

            if (is_array($value)) {
                $keys = [...$keys, ...$this->flatten($value, $path)];

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }
}
