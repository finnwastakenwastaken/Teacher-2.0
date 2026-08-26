<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * nginx's add_header does not merge: a location declaring any add_header of
 * its own silently discards every header inherited from the server block.
 * /build/, /__media/ and /__backup/ each declare one, so the header set is
 * repeated verbatim in each rather than pulled from an include (which would
 * stop nginx starting, since dev bind-mounts this one file) — this test
 * counts the copies. Reads the file rather than requesting it, as with
 * ForwardedHeaderTest: no nginx in front of this suite.
 */
class SecurityHeaderTest extends TestCase
{
    /**
     * Every header that must survive in each location that sets one of its
     * own. /__media/ and /__backup/ carry a shorter CSP, checked separately.
     */
    private const REPEATED = [
        'X-Content-Type-Options',
        'X-Frame-Options',
        'Referrer-Policy',
        'X-XSS-Protection',
        'Permissions-Policy',
        'Content-Security-Policy',
    ];

    public function test_every_location_that_sets_a_header_repeats_the_whole_set()
    {
        $conf = $this->conf();

        foreach (self::REPEATED as $header) {
            $this->assertSame(
                4,
                substr_count($conf, "add_header {$header} "),
                "docker/nginx/app.conf must carry {$header} in the server block and in each of /build/, /__media/ and /__backup/ — nginx drops every inherited add_header from a location that declares one of its own."
            );
        }
    }

    /**
     * The two hosts the site frames, and no third. Dropping one breaks every
     * block of that kind, which is the kind of thing to find in a test rather
     * than from a teacher whose lesson has a blank rectangle in it. The
     * exact-string assertion also fails if a host is *added* — so widening
     * frame-src stays a decision someone made, not something that drifted in.
     *
     * instagram.com in particular must stay out: nothing on the site frames
     * it (its embed carries no video, so the block links out instead), and
     * putting it back would silently re-enable a renderer change that hands
     * Meta every student who opens the page.
     */
    public function test_the_policy_allows_the_embed_hosts_and_nothing_else_third_party()
    {
        $conf = $this->conf();

        $this->assertStringContainsString(
            'frame-src https://www.youtube-nocookie.com https://www.tiktok.com;',
            $conf
        );

        // Asked of the policies rather than the file, because the comment
        // above them explains at length why instagram.com is absent — and
        // that explanation is the thing most worth keeping.
        foreach ($this->policies() as $policy) {
            $this->assertStringNotContainsString(
                'instagram.com',
                $policy,
                'Nothing on the site frames Instagram; its embed carries no video.'
            );
        }

        // The two media locations carry a minimal policy with no frame-src and
        // no script-src at all, so this asks the question that holds for all
        // four: a third-party host may appear in frame-src and nowhere else.
        //
        // That is the distinction worth pinning rather than the host list. A
        // framed document is isolated from this origin; a script is not, which
        // is exactly why both platforms' own blockquote-plus-script embed was
        // refused in favour of their plain iframe endpoint.
        foreach ($this->policies() as $policy) {
            $withoutFrames = preg_replace('/frame-src [^;]*/', '', $policy);

            $this->assertStringNotContainsString(
                '//',
                (string) $withoutFrames,
                'Only frame-src may name a third-party origin.'
            );
        }

        $this->assertStringContainsString("object-src 'none'", $conf);
        $this->assertStringContainsString("base-uri 'self'", $conf);
        $this->assertStringContainsString("frame-ancestors 'self'", $conf);
    }

    /**
     * A CSP origin is resolved by the visitor's browser, so `localhost:5173`
     * in a shipped policy would name the *student's* machine, not the
     * server. The dev origin reaches the policy only via a variable that's
     * empty outside the development stack. Reads the parsed policies, not
     * the raw file, since the file's own comment mentions "localhost".
     */
    public function test_no_policy_names_a_development_origin()
    {
        $policies = $this->policies();

        $this->assertCount(4, $policies);

        foreach ($policies as $policy) {
            $this->assertStringNotContainsString(
                'localhost',
                $policy,
                'A CSP names an origin the visitor resolves. localhost here is the visitor\'s own machine — put the dev server behind ${CSP_DEV_SRC} instead.'
            );
        }
    }

    /**
     * With the config installed straight into conf.d, envsubst never runs
     * and the header ships the literal `${CSP_DEV_SRC}`.
     * NGINX_ENVSUBST_FILTER is equally load-bearing: envsubst substitutes
     * `$VAR` as well as `${VAR}`, so without it any env var sharing a name
     * with an nginx variable (`$uri`, `$request_uri`) gets substituted away.
     */
    public function test_the_config_is_installed_as_a_template_in_both_stacks()
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $this->assertIsString($dockerfile);

        $this->assertStringContainsString(
            '/etc/nginx/templates/default.conf.template',
            $dockerfile,
            'The web image must install app.conf as a template, or envsubst never runs and the CSP ships a literal ${CSP_DEV_SRC}.'
        );

        $this->assertStringContainsString(
            'NGINX_ENVSUBST_FILTER="^CSP_"',
            $dockerfile,
            'Without the filter, envsubst would substitute nginx\'s own $uri and $request_uri away.'
        );

        $dev = file_get_contents(base_path('compose.dev.yaml'));
        $this->assertIsString($dev);

        $this->assertStringContainsString(
            '/etc/nginx/templates/default.conf.template',
            $dev,
            'The development stack bind-mounts this file; mounting it over conf.d would shadow the templated copy with an untemplated one.'
        );

        $this->assertStringContainsString(
            'CSP_DEV_SRC',
            $dev,
            'Development needs the Vite origin the shipped policy no longer carries, or every module request is blocked.'
        );
    }

    /**
     * @return list<string>
     */
    private function policies(): array
    {
        preg_match_all(
            '/add_header Content-Security-Policy "([^"]*)"/',
            $this->conf(),
            $matches
        );

        return $matches[1];
    }

    /**
     * Deliberately absent: nginx never sees the TLS connection (Cloudflare
     * terminates it) and this same file serves http://localhost:8080 in
     * development, where HSTS would apply to every port on localhost and
     * lock the operator out of their own dev site.
     */
    public function test_hsts_is_not_set_here()
    {
        $this->assertStringNotContainsString(
            'add_header Strict-Transport-Security',
            $this->conf(),
            'HSTS belongs at the Cloudflare edge, not on a server block that also answers http://localhost in development.'
        );
    }

    private function conf(): string
    {
        $conf = file_get_contents(base_path('docker/nginx/app.conf'));

        $this->assertIsString($conf);

        return $conf;
    }
}
