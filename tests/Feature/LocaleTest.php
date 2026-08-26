<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resolution order: cookie, then Accept-Language, then Dutch.
 *
 * Asserted on rendered `<html lang>`, not app()->getLocale() — it's the attribute
 * a screen reader uses, written by Blade before hydration.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Header cleared explicitly: Symfony's Request::create() (used by the test
     * client) injects its own default `Accept-Language`, which would defeat this test.
     */
    public function test_dutch_is_served_when_nothing_is_asked_for()
    {
        $this->withHeader('Accept-Language', '')
            ->get('/')
            ->assertSee('<html lang="nl"', false);
    }

    public function test_an_unsupported_browser_language_falls_back_to_dutch()
    {
        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
            ->get('/')
            ->assertSee('<html lang="nl"', false);
    }

    public function test_a_supported_browser_language_is_honoured()
    {
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
            ->get('/')
            ->assertSee('<html lang="en"', false);
    }

    /**
     * The stored cookie must beat the browser's Accept-Language, or a visitor can
     * never make the site stay in their chosen language.
     */
    public function test_the_cookie_beats_the_browser_preference()
    {
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
            ->withCookie(Locale::COOKIE, 'nl')
            ->get('/')
            ->assertSee('<html lang="nl"', false);
    }

    public function test_a_tampered_cookie_is_ignored_rather_than_trusted()
    {
        // The value reaches a JS string literal in app.blade.php, where Blade's
        // HTML-escaping is the wrong context — so it's validated as an enum instead.
        $this->withHeader('Accept-Language', '')
            ->withCookie(Locale::COOKIE, '../../etc/passwd')
            ->get('/')
            ->assertSee('<html lang="nl"', false);
    }

    public function test_the_language_can_be_switched()
    {
        $this->from('/')
            ->post(route('locale.store'), ['locale' => 'en'])
            ->assertRedirect('/')
            ->assertCookie(Locale::COOKIE, 'en');
    }

    public function test_an_unsupported_language_cannot_be_set()
    {
        $this->from('/')
            ->post(route('locale.store'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }

    /**
     * The dictionary rides with the document, not as an Inertia shared prop, since
     * switching languages is a full page load anyway.
     */
    public function test_the_active_dictionary_is_sent_with_the_document()
    {
        $this->withHeader('Accept-Language', 'en')
            ->get('/')
            ->assertSee('window.__locale = "en"', false)
            ->assertSee('common.locale.label', false);
    }

    /**
     * Validation/auth messages arrive already rendered in the error bag; shipping
     * their rule tables too would be ~170 unused lines per document.
     */
    public function test_server_only_messages_are_not_shipped_to_the_browser()
    {
        $response = $this->get('/');

        $response->assertDontSee('validation.required', false);
        $response->assertDontSee('auth.failed', false);
    }

    /**
     * Content is never translated — a page keeps its title in whichever language it
     * was written in, regardless of interface locale.
     */
    public function test_content_is_not_translated()
    {
        $topic = Topic::query()->create([
            'title' => 'Natuurkunde',
            'slug' => 'natuurkunde',
        ]);

        foreach (['nl', 'en'] as $locale) {
            $this->withCookie(Locale::COOKIE, $locale)
                ->get('/'.$topic->slug)
                ->assertOk()
                ->assertSee('Natuurkunde', false);
        }
    }

    public function test_every_supported_locale_has_a_dictionary()
    {
        foreach (Locale::SUPPORTED as $locale) {
            $this->assertNotEmpty(
                Locale::dictionary($locale),
                "lang/{$locale} produced an empty interface dictionary."
            );
        }
    }
}
