<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A tripwire for the one confirmation surface.
 *
 * `components/ui/confirm-dialog.tsx` replaced eight native `window.confirm()`
 * calls, and the technical reference records the rule that came out of it: a native confirm()
 * is now a bug rather than a shortcut. Nothing in the pipeline enforces that —
 * `confirm` is a browser global, so a reintroduced call type-checks, lints and
 * runs perfectly. It just answers in the browser's voice, on the screens that
 * destroy things.
 *
 * The module-level `confirm()` needs `<ConfirmProvider>` mounted to have
 * anything to ask. It is mounted once, in `app.tsx`, and it fails closed and
 * says so if it ever is not — but "handled gracefully at runtime" is a worse
 * guarantee than "cannot be shipped", and this is cheap enough to have both.
 *
 * Two assertions, both about source text rather than behaviour. That is the
 * point: the browser suite covers what the dialog *does* (tests/e2e/
 * confirm-dialog.spec.ts), and neither suite can see a rule being quietly
 * dropped from a file.
 */
class ConfirmationSurfaceTest extends TestCase
{
    private const ROOT = 'resources/js';

    private const MODULE = '@/components/ui/confirm-dialog';

    /**
     * The provider is mounted at the application root.
     *
     * `app.tsx`'s `withApp` is the only place every Inertia page passes
     * through. Unmounting it does not break a build or a type — every
     * confirmation simply starts refusing, which reads as eight unrelated
     * buttons having stopped working.
     */
    public function test_the_provider_is_mounted_at_the_application_root()
    {
        $app = file_get_contents(base_path(self::ROOT.'/app.tsx'));

        $this->assertIsString($app);
        $this->assertStringContainsString(
            "from '".self::MODULE."'",
            $app,
            'app.tsx no longer imports the confirmation provider.'
        );
        $this->assertStringContainsString(
            '<ConfirmProvider>',
            $app,
            'ConfirmProvider is no longer mounted, so every confirmation will refuse.'
        );
    }

    /**
     * Nothing asks the browser to do the asking.
     *
     * Checked two ways, because the two failure modes look nothing alike. An
     * explicit `window.confirm(` is somebody reaching for the old shortcut; a
     * bare `confirm(` in a file that never imported the module is the subtler
     * one, and it is what every call site looked like before the conversion.
     */
    public function test_no_screen_falls_back_to_the_browsers_own_dialog()
    {
        $offenders = ['window' => [], 'unimported' => []];

        foreach ($this->sourceFiles() as $path) {
            $source = (string) file_get_contents($path);
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

            // The component defines the replacement and its doc block explains
            // what it replaced, so it is the one file allowed to name either
            // form. Skipped before both checks rather than after the first,
            // which is a distinction this test got wrong on its first run.
            if (str_ends_with($relative, 'confirm-dialog.tsx')) {
                continue;
            }

            if (str_contains($source, 'window.confirm(')) {
                $offenders['window'][] = $relative;
            }

            if (preg_match('/(?<![\w.])confirm\s*\(/', $source) !== 1) {
                continue;
            }

            if (! str_contains($source, "from '".self::MODULE."'")) {
                $offenders['unimported'][] = $relative;
            }
        }

        $this->assertSame([], $offenders['window'], 'window.confirm() is not the confirmation surface here.');
        $this->assertSame(
            [],
            $offenders['unimported'],
            'These files call confirm() without importing the shared dialog, so they are asking the browser.'
        );
    }

    /**
     * Every .ts/.tsx file under resources/js.
     *
     * Wayfinder writes `resources/js/actions` and `resources/js/routes` at
     * build time and they are gitignored, so they are skipped — generated code
     * is not somebody's decision to reintroduce a browser dialog.
     *
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $directory = new \RecursiveDirectoryIterator(
            base_path(self::ROOT),
            \FilesystemIterator::SKIP_DOTS
        );

        $files = [];

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            $path = $file->getPathname();

            if (! preg_match('/\.tsx?$/', $path)) {
                continue;
            }

            if (preg_match('#[\\\\/]js[\\\\/](actions|routes)[\\\\/]#', $path) === 1) {
                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }
}
