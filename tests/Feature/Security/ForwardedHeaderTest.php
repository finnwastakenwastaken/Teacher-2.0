<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who gets to say where a request came from.
 *
 * nginx passes every client header through to PHP-FPM as HTTP_*, so without
 * deliberate handling a visitor can simply type their own X-Forwarded-For and
 * X-Forwarded-Host and be believed. Both were exploitable and both were
 * demonstrated against the running stack before this was written: a rotating
 * forged X-Forwarded-For made the login limiter unreachable, and one
 * X-Forwarded-Host rewrote the Sitemap: line in robots.txt to another domain.
 *
 * The fix has two halves and only one of them is testable here. This suite
 * runs PHP directly, with no nginx in front, so it can prove what Laravel
 * refuses to trust — but it cannot prove what nginx overwrites. That half is
 * pinned by the config assertion at the bottom, and confirmed the same way
 * gated media is: by fetching a real URL through a real nginx.
 */
class ForwardedHeaderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The one that was live. X-Forwarded-Host is in Laravel's default trusted
     * set, Symfony's getHost() takes the first value, and no trustHosts()
     * narrows it — so this header used to redirect every absolute URL the
     * site generates at an attacker's domain.
     */
    public function test_a_forged_forwarded_host_cannot_change_generated_urls()
    {
        $response = $this->withHeaders(['X-Forwarded-Host' => 'evil.example'])
            ->get('/robots.txt');

        $response->assertOk();
        $response->assertDontSee('evil.example');
        $response->assertSee(config('app.url').'/sitemap.xml');
    }

    public function test_a_forged_forwarded_host_cannot_change_the_request_host()
    {
        $this->withHeaders(['X-Forwarded-Host' => 'evil.example'])->get('/');

        $this->assertNotSame('evil.example', request()->getHost());
    }

    /**
     * X-Forwarded-Proto stays trusted, and should: Cloudflare terminates TLS
     * and cloudflared reaches nginx over plain HTTP, so without it every
     * generated URL on an HTTPS site comes out as http://. nginx only passes
     * a value through from a proxy inside the private ranges.
     */
    public function test_forwarded_proto_is_still_honoured()
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/');

        $this->assertTrue(request()->isSecure());
    }

    /**
     * The half PHP cannot exercise.
     *
     * `at: '*'` trusts the calling IP, which in production is nginx — so
     * whatever nginx sends in X-Forwarded-For is believed, by design. What
     * makes that safe is nginx overwriting the header with what it actually
     * observed rather than forwarding the visitor's own. Delete those lines
     * and every per-IP limiter in the application silently stops bounding
     * anything, with no test failing and nothing in any log.
     *
     * Hence a tripwire on the file itself, in the same spirit as
     * MediaAccessTest asserting the framework's storage routes stay absent.
     */
    public function test_nginx_still_overwrites_the_forwarded_headers()
    {
        $conf = file_get_contents(base_path('docker/nginx/app.conf'));

        $this->assertIsString($conf);

        foreach ([
            'fastcgi_param HTTP_X_FORWARDED_FOR   $remote_addr;',
            'fastcgi_param HTTP_X_FORWARDED_PROTO $forwarded_proto;',
            'fastcgi_param HTTP_X_FORWARDED_HOST  $host;',
            'real_ip_header   CF-Connecting-IP;',
        ] as $line) {
            $this->assertStringContainsString(
                $line,
                $conf,
                "docker/nginx/app.conf must keep: {$line} — without it a visitor can forge their own client IP and every per-IP rate limit becomes unbounded."
            );
        }
    }
}
