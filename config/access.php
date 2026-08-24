<?php

return [

    /*
     * How long an unlock lasts, in days.
     *
     * Long on purpose: a class works through a topic over a term, and being
     * asked for the password every week trains students to treat it as noise
     * and to share it more widely, not less.
     */
    'unlock_days' => (int) env('ACCESS_UNLOCK_DAYS', 30),

    /*
     * Attempts per minute, per IP, per password. Rate limiting here depends
     * on trustProxies being set — see the technical reference, without
     * which every visitor shares one bucket behind the tunnel.
     */
    'attempts_per_minute' => (int) env('ACCESS_ATTEMPTS_PER_MINUTE', 5),

    /*
     * The IP-independent backstop, as a multiple of the per-IP limit.
     *
     * A per-IP limiter bounds one attacker's rate, not the total: addresses
     * are cheap, so N of them buy N times the attempts. This second bucket
     * counts every attempt against a given password regardless of who is
     * asking, which is the only thing that actually caps the search.
     *
     * Ten by default — fifty attempts a minute across the whole internet. A
     * class of thirty all fat-fingering the same password at once stays well
     * inside it; a script does not. Raise it if a large school genuinely
     * trips it, but understand that it is the ceiling on a brute-force
     * attempt and nothing else is.
     */
    'global_attempt_multiplier' => (int) env('ACCESS_GLOBAL_ATTEMPT_MULTIPLIER', 10),

];
