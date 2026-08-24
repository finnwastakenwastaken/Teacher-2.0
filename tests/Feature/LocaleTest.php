<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Choosing the interface language.
 *
 * The rule is: an explicit choice wins, a browser's advertised preference
 * decides for anyone who has not made one, and Dutch decides for anyone left.
 *
 * Asserted on the rendered `<html lang>` rather than on app()->getLocale(),
 * because the failure that matters is a visible one — the attribute a screen
 * reader picks its pronunciation from, written by Blade before hydration.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The header is cleared explicitly, because Symfony's Request::create()
     * — which the test client goes through — injects a default
     * `Accept-Language: en-us,en;q=0.5` of its own. Without this the test
     * would be asserting the opposite of what it says.
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
     * The whole point of storing a choice: it has to beat what the browser
     * advertises, or a Dutch-speaking visitor on an English phone can never
     * make the site stay Dutch.
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
        // The value reaches a JavaScript string literal in app.blade.php,
        // where Blade's escaping is for HTML and therefore the wrong context.
        // It is an enum, so it is treated as one.
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
     * The dictionary is handed over with the document rather than as an
     * Inertia shared prop, so that switching — which is a full page load —
     * cannot leave the chrome one language behind.
     */
    public function test_the_active_dictionary_is_sent_with_the_document()
    {
        $this->withHeader('Accept-Language', 'en')
            ->get('/')
            ->assertSee('window.__locale = "en"', false)
            ->assertSee('common.locale.label', false);
    }

    /**
     * Validation and authentication messages reach the browser already
     * rendered, in the error bag. Shipping the rule table as well would be
     * about 170 lines of dictionary on every document for nobody to read.
     */
    public function test_server_only_messages_are_not_shipped_to_the_browser()
    {
        $response = $this->get('/');

        $response->assertDontSee('validation.required', false);
        $response->assertDontSee('auth.failed', false);
    }

    /**
     * Content is never translated — that is the boundary this whole feature
     * sits behind. A page keeps its title in whichever language it was
     * written in, whoever is looking at it.
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
