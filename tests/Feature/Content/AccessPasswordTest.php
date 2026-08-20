<?php

namespace Tests\Feature\Content;

use App\Models\AccessPassword;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The "no side door" guarantee: material on a protected page must not be
 * reachable by guessing a URL, and that has to hold for the media it shows
 * as much as for the page itself.
 */
class AccessPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function makePassword(string $name, string $secret): AccessPassword
    {
        return AccessPassword::createWithPassword($name, $secret);
    }

    private function tree(): array
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $child = Topic::query()->create([
            'title' => 'Sterrenkunde', 'slug' => 'sterrenkunde', 'parent_id' => $root->id,
        ]);
        $page = Page::query()->create([
            'title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $child->id,
        ]);

        return [$root, $child, $page];
    }

    private function unlockCookie(AccessPassword $password): array
    {
        return ['unlock_'.$password->id => substr(hash('sha256', $password->password_hash), 0, 32)];
    }

    public function test_an_unprotected_page_renders_normally()
    {
        [, , $page] = $this->tree();

        $this->get('/natuurkunde/sterrenkunde/de-planeten')
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia->component('content/page'));
    }

    public function test_a_protected_page_shows_the_prompt_instead_of_its_content()
    {
        [, , $page] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $page->update(['access_password_id' => $password->id]);
        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Geheime uitleg']]],
        ]]);

        $response = $this->get('/natuurkunde/sterrenkunde/de-planeten');

        $response->assertOk()->assertInertia(
            fn ($inertia) => $inertia->component('content/locked')->where('passwordName', '5 VWO')
        );

        // The body must not be in the payload at all — a prompt drawn over
        // content that was already sent is not protection.
        $response->assertDontSee('Geheime uitleg');
    }

    public function test_a_protected_topic_shows_the_prompt_instead_of_its_introduction()
    {
        [, $child] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $child->update(['access_password_id' => $password->id]);
        $child->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Geheime inleiding']]],
        ]]);

        $response = $this->get('/natuurkunde/sterrenkunde');

        $response->assertOk()->assertInertia(
            fn ($inertia) => $inertia->component('content/locked')->where('passwordName', '5 VWO')
        );

        $response->assertDontSee('Geheime inleiding');
    }

    public function test_a_password_on_a_topic_protects_its_whole_subtree()
    {
        [$root, $child, $page] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $root->update(['access_password_id' => $password->id]);

        foreach (['/natuurkunde', '/natuurkunde/sterrenkunde', '/natuurkunde/sterrenkunde/de-planeten'] as $path) {
            $this->get($path)->assertInertia(
                fn ($inertia) => $inertia->component('content/locked'),
                "Expected {$path} to be locked."
            );
        }
    }

    public function test_the_nearest_password_wins_over_an_ancestor()
    {
        [$root, , $page] = $this->tree();
        $outer = $this->makePassword('Onderbouw', 'buiten');
        $inner = $this->makePassword('5 VWO', 'binnen');
        $root->update(['access_password_id' => $outer->id]);
        $page->update(['access_password_id' => $inner->id]);

        $this->get('/natuurkunde/sterrenkunde/de-planeten')->assertInertia(
            fn ($inertia) => $inertia->where('passwordName', '5 VWO')
        );

        // The ancestor's password must not open the page that overrides it.
        $this->withCookies($this->unlockCookie($outer))
            ->get('/natuurkunde/sterrenkunde/de-planeten')
            ->assertInertia(fn ($inertia) => $inertia->component('content/locked'));

        $this->withCookies($this->unlockCookie($inner))
            ->get('/natuurkunde/sterrenkunde/de-planeten')
            ->assertInertia(fn ($inertia) => $inertia->component('content/page'));
    }

    public function test_entering_the_password_unlocks_and_the_content_then_renders()
    {
        [, , $page] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $page->update(['access_password_id' => $password->id]);

        $response = $this->from('/natuurkunde/sterrenkunde/de-planeten')
            ->post(route('unlock.store'), [
                'path' => 'natuurkunde/sterrenkunde/de-planeten',
                'password' => 'zwaartekracht',
            ]);

        $response->assertRedirect('/natuurkunde/sterrenkunde/de-planeten')
            ->assertCookie('unlock_'.$password->id);

        $this->withCookies($this->unlockCookie($password))
            ->get('/natuurkunde/sterrenkunde/de-planeten')
            ->assertInertia(fn ($inertia) => $inertia->component('content/page'));
    }

    public function test_a_wrong_password_is_refused()
    {
        [, , $page] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $page->update(['access_password_id' => $password->id]);

        $this->from('/natuurkunde/sterrenkunde/de-planeten')
            ->post(route('unlock.store'), [
                'path' => 'natuurkunde/sterrenkunde/de-planeten',
                'password' => 'fout',
            ])
            ->assertSessionHasErrors('password')
            ->assertCookieMissing('unlock_'.$password->id);
    }

    public function test_one_password_unlocks_everything_it_guards()
    {
        [$root, , $page] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $root->update(['access_password_id' => $password->id]);

        $other = Topic::query()->create([
            'title' => 'Scheikunde', 'slug' => 'scheikunde', 'access_password_id' => $password->id,
        ]);

        $cookies = $this->unlockCookie($password);

        $this->withCookies($cookies)->get('/natuurkunde')
            ->assertInertia(fn ($inertia) => $inertia->component('content/topic'));
        $this->withCookies($cookies)->get('/scheikunde')
            ->assertInertia(fn ($inertia) => $inertia->component('content/topic'));

        $this->assertNotNull($other);
    }

    public function test_changing_the_password_invalidates_cookies_issued_under_the_old_one()
    {
        [$root] = $this->tree();
        $password = $this->makePassword('5 VWO', 'oud');
        $root->update(['access_password_id' => $password->id]);

        $stale = $this->unlockCookie($password);

        $this->withCookies($stale)->get('/natuurkunde')
            ->assertInertia(fn ($inertia) => $inertia->component('content/topic'));

        $this->actingAs(User::factory()->create())
            ->put(route('admin.passwords.update', $password), ['name' => '5 VWO', 'password' => 'nieuw'])
            ->assertSessionHas('status');

        // actingAs sticks for the rest of the test, and the admin sees
        // everything — so drop back to being a visitor before checking.
        $this->app['auth']->forgetGuards();

        // The cookie carries a fingerprint of the hash, so it stops matching
        // the moment the hash changes — that is the only revocation there is.
        $this->withCookies($stale)->get('/natuurkunde')
            ->assertInertia(fn ($inertia) => $inertia->component('content/locked'));
    }

    public function test_unlock_attempts_are_rate_limited_per_ip_and_password()
    {
        [, , $page] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $page->update(['access_password_id' => $password->id]);

        for ($i = 0; $i < config('access.attempts_per_minute'); $i++) {
            $this->post(route('unlock.store'), [
                'path' => 'natuurkunde/sterrenkunde/de-planeten',
                'password' => 'fout',
            ]);
        }

        $this->from('/natuurkunde/sterrenkunde/de-planeten')
            ->post(route('unlock.store'), [
                'path' => 'natuurkunde/sterrenkunde/de-planeten',
                'password' => 'zwaartekracht',
            ])
            ->assertSessionHasErrors('password');

        // Even the correct password is refused while the limiter is closed.
        $this->assertTrue(
            RateLimiter::tooManyAttempts('unlock:127.0.0.1:'.$password->id, config('access.attempts_per_minute'))
        );
    }

    public function test_the_admin_sees_protected_content_without_a_cookie()
    {
        [$root, , $page] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $root->update(['access_password_id' => $password->id]);

        $this->actingAs(User::factory()->create())
            ->get('/natuurkunde/sterrenkunde/de-planeten')
            ->assertInertia(fn ($inertia) => $inertia->component('content/page'));
    }

    /**
     * The guarantee from the requirements: "a video on a protected page
     * cannot be fetched by guessing its URL".
     */
    public function test_media_on_a_protected_page_is_refused_without_the_cookie()
    {
        Storage::fake('local');
        [$root, , $page] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $root->update(['access_password_id' => $password->id]);

        $path = 'media/2026/08/'.Str::ulid().'.mp4';
        Storage::disk('local')->put($path, 'bytes');
        $video = MediaFile::query()->create([
            'path' => $path, 'kind' => MediaFile::KIND_VIDEO, 'mime' => 'video/mp4',
            'size_bytes' => 5, 'original_filename' => 'les.mp4',
        ]);

        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'fileEmbed', 'attrs' => ['ulid' => $video->ulid]],
        ]]);

        $this->get(route('media.show', $video))->assertForbidden();

        $this->withCookies($this->unlockCookie($password))
            ->get(route('media.show', $video))
            ->assertOk();
    }

    public function test_a_download_on_a_protected_page_is_refused_without_the_cookie()
    {
        Storage::fake('local');
        [, , $page] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $page->update(['access_password_id' => $password->id]);

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        $file = MediaFile::query()->create([
            'path' => $path, 'kind' => MediaFile::KIND_DOCUMENT, 'mime' => 'application/pdf',
            'size_bytes' => 5, 'original_filename' => 'toets.pdf',
        ]);

        $download = $page->downloads()->create(['media_file_id' => $file->id]);

        $this->get(route('downloads.show', $download))->assertForbidden();
        $this->get(route('media.show', $file))->assertForbidden();

        $this->withCookies($this->unlockCookie($password))
            ->get(route('downloads.show', $download))
            ->assertOk();
    }

    public function test_a_file_also_shown_on_an_open_page_stays_public()
    {
        Storage::fake('local');
        [$root, $child, $protectedPage] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $protectedPage->update(['access_password_id' => $password->id]);

        $openPage = Page::query()->create([
            'title' => 'Oefenen', 'slug' => 'oefenen', 'topic_id' => $child->id,
        ]);

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        $file = MediaFile::query()->create([
            'path' => $path, 'kind' => MediaFile::KIND_DOCUMENT, 'mime' => 'application/pdf',
            'size_bytes' => 5, 'original_filename' => 'werkblad.pdf',
        ]);

        $protectedPage->downloads()->create(['media_file_id' => $file->id]);
        $openPage->downloads()->create(['media_file_id' => $file->id]);

        // Already published elsewhere: refusing it here would protect nothing
        // and would break the open page. Passwords guard pages, not files.
        $this->get(route('media.show', $file))->assertOk();
    }

    public function test_a_password_in_use_cannot_be_deleted_and_says_where()
    {
        [$root] = $this->tree();
        $password = $this->makePassword('5 VWO', 'zwaartekracht');
        $root->update(['access_password_id' => $password->id]);

        $this->actingAs(User::factory()->create())
            ->from(route('admin.passwords.index'))
            ->delete(route('admin.passwords.destroy', $password))
            ->assertSessionHas('error');

        $this->assertModelExists($password);
        $this->assertStringContainsString('Natuurkunde', session('error'));
    }

    public function test_an_unused_password_can_be_deleted()
    {
        $password = $this->makePassword('Oud', 'x');

        $this->actingAs(User::factory()->create())
            ->from(route('admin.passwords.index'))
            ->delete(route('admin.passwords.destroy', $password))
            ->assertSessionHas('status');

        $this->assertModelMissing($password);
    }

    public function test_guests_cannot_manage_passwords()
    {
        $password = $this->makePassword('5 VWO', 'x');

        $this->get(route('admin.passwords.index'))->assertRedirect(route('login'));
        $this->post(route('admin.passwords.store'), ['name' => 'x', 'password' => 'xxxx'])
            ->assertRedirect(route('login'));
        $this->delete(route('admin.passwords.destroy', $password))->assertRedirect(route('login'));
    }

    public function test_the_password_hash_is_never_serialised()
    {
        $password = $this->makePassword('5 VWO', 'zwaartekracht');

        $this->assertArrayNotHasKey('password_hash', $password->toArray());
    }
}
