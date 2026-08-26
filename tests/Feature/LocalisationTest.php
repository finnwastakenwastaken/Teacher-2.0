<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * The two locale directories must stay the same shape: a missing key does not fall
 * back to the other language, it renders the raw key path on screen. This is the
 * only guard translations get — nothing else in the pipeline type-checks them.
 */
class LocalisationTest extends TestCase
{
    /** Every locale the application ships — a directory not listed here is never checked. */
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
     * A message identical in both locales is presumed untranslated. Proper nouns
     * and format strings are false positives, so the exemption list is explicit.
     */
    public function test_no_message_is_identical_in_both_locales()
    {
        $allowed = [
            // The framework's own example row, kept to document per-field overrides.
            'validation.custom.attribute-name.rule-name',

            // Genuinely identical words in both languages (Dutch borrows this vocabulary).
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

            // Chemistry/physics notation, same everywhere.
            'ui.editor.subscript',
            'ui.editor.superscript',

            // Format strings: URLs and a bare count. The TikTok placeholder
            // does differ, because the handle in it is a word.
            'ui.editor.youtube_dialog.placeholder',
            'ui.editor.social_dialog.instagram_placeholder',
            'ui.levels.download_count',

            // The wiki is one English doc set, so its URL is the same in both locales.
            'ui.backups.restore_doc',
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
     * `__()` returns the key unchanged when missing rather than throwing, so a typo
     * puts the raw key on screen instead of a sentence — nothing else catches that.
     */
    public function test_every_key_the_application_uses_exists_in_every_locale()
    {
        // Only literal keys; a key built from a variable can't be checked here (see
        // the fixed-list callers: DashboardController, IconCatalogue, locale switcher).
        $scans = [
            [app_path(), ['php'], "/(?:__|trans)\(\s*'([a-z][a-z0-9_]*\.[a-z0-9_.]+)'/i"],

            // No JS test runner in this project, so the front end is scanned from PHP.
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

    /** Comments are stripped first — a doc block showing how to call t() is not a call. */
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

            // Wayfinder's gitignored generated files never call t().
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
