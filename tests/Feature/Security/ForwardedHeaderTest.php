<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Without deliberate handling, a visitor's own X-Forwarded-For/-Host would be
 * believed by nginx's passthrough — previously exploitable to defeat the
 * login limiter and to rewrite robots.txt's Sitemap: line to another domain.
 * This suite runs PHP directly with no nginx in front, so it can only prove
 * what Laravel refuses to trust; what nginx overwrites is pinned by the
 * config assertion at the bottom and confirmed against real nginx separately.
 */
class ForwardedHeaderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * X-Forwarded-Host is in Laravel's default trusted set with no
     * trustHosts() to narrow it, so this header used to redirect every
     * absolute URL the site generates at an attacker's domain.
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
     * Cloudflare terminates TLS and cloudflared reaches nginx over plain
     * HTTP, so without trusting this header every generated URL comes out
     * as http://.
     */
    public function test_forwarded_proto_is_still_honoured()
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/');

        $this->assertTrue(request()->isSecure());
    }

    /**
     * The half PHP cannot exercise: `at: '*'` trusts whatever nginx sends in
     * X-Forwarded-For, which is safe only because nginx overwrites it with
     * what it observed rather than forwarding the visitor's own. Without
     * those config lines every per-IP limiter silently stops bounding
     * anything, so this is a tripwire on the file itself.
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
