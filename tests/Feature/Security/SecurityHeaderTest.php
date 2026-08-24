<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * nginx's add_header does not merge, and losing one is silent.
 *
 * A location block that declares any add_header of its own discards every
 * header inherited from the server block. Three locations here declare one —
 * /build/ sets Cache-Control, /__media/ and /__backup/ set nosniff — so all
 * three were serving without X-Frame-Options, Referrer-Policy and the rest.
 * Nothing about such a response looks wrong; you only find it by asking for a
 * header and not getting one.
 *
 * The set is therefore repeated verbatim in each block rather than included
 * from a second file, because the development stack bind-mounts this one file
 * and an unmounted include would stop nginx from starting. Repetition is only
 * safe if something counts the copies, which is what this does.
 *
 * It reads the file rather than making a request for the same reason
 * ForwardedHeaderTest does: the suite runs PHP directly, with no nginx in
 * front of it.
 */
class SecurityHeaderTest extends TestCase
{
    /**
     * Every location that sets a header of its own, and every header that
     * must survive there. /__media/ and /__backup/ carry a shorter CSP —
     * neither serves the application — so the policy itself is checked
     * separately below.
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

        // The server block plus the three locations that override.
        foreach (self::REPEATED as $header) {
            $this->assertSame(
                4,
                substr_count($conf, "add_header {$header} "),
                "docker/nginx/app.conf must carry {$header} in the server block and in each of /build/, /__media/ and /__backup/ — nginx drops every inherited add_header from a location that declares one of its own."
            );
        }
    }

    /**
     * The one third party the site embeds. Dropping it from frame-src breaks
     * every YouTube block on the site, which is the kind of thing to find in
     * a test rather than from a teacher whose lesson has a blank rectangle
     * in it.
     */
    public function test_the_policy_allows_the_youtube_embed_and_nothing_else_third_party()
    {
        $conf = $this->conf();

        $this->assertStringContainsString(
            'frame-src https://www.youtube-nocookie.com;',
            $conf
        );

        $this->assertStringContainsString("object-src 'none'", $conf);
        $this->assertStringContainsString("base-uri 'self'", $conf);
        $this->assertStringContainsString("frame-ancestors 'self'", $conf);
    }

    /**
     * A CSP origin is resolved by the visitor's browser, so `localhost:5173`
     * in the shipped policy names the *student's* machine, not the server —
     * every deployed site was permitting scripts and styles from whatever was
     * listening on their loopback. The dev server's origin therefore reaches
     * the policy through a variable that is empty everywhere but the
     * development stack.
     *
     * This reads the policies rather than the file because the file explains
     * the reasoning in a comment, and matching on the whole thing would fail
     * the test for saying why it exists — the same care test_hsts_is_not_set
     * takes below.
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
     * The other half of that fix, and the half that fails loudly rather than
     * quietly: with the config installed straight into conf.d, envsubst never
     * runs and the header goes out containing the literal `${CSP_DEV_SRC}`.
     *
     * NGINX_ENVSUBST_FILTER is equally load-bearing. envsubst substitutes
     * `$VAR` as well as `${VAR}`, so without it any environment variable
     * sharing a name with an nginx variable — `$uri`, `$request_uri`,
     * `$document_root` — would be substituted out of the config.
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
     * Deliberately absent. nginx listens on plain 80 behind the tunnel and
     * never sees the TLS connection, and this same file serves
     * http://localhost:8080 in development — where an HSTS header would apply
     * to every port on localhost and lock the operator out of their own dev
     * site until they cleared it by hand. It belongs at the edge that
     * terminates TLS, and the maintenance guides say so.
     */
    public function test_hsts_is_not_set_here()
    {
        // The header, not the word: the reasoning above is written into the
        // file as a comment, and matching on that would make this test fail
        // for explaining itself.
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
