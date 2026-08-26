<?php

return [

    /*
     * How long an unlock lasts, in days. Long on purpose: re-prompting a
     * class weekly trains students to treat the password as noise and share
     * it more widely, not less.
     */
    'unlock_days' => (int) env('ACCESS_UNLOCK_DAYS', 30),

    /*
     * Attempts per minute, per IP, per password. Rate limiting here depends
     * on trustProxies being set — see the technical reference, without
     * which every visitor shares one bucket behind the tunnel.
     */
    'attempts_per_minute' => (int) env('ACCESS_ATTEMPTS_PER_MINUTE', 5),

    /*
     * IP-independent backstop, as a multiple of the per-IP limit — a per-IP
     * limiter alone doesn't cap total attempts since addresses are cheap.
     * This bucket counts every attempt against a password regardless of
     * source. Default 10 (50/min total) comfortably covers a class
     * fat-fingering a password at once; raise only if a large school trips
     * it legitimately.
     */
    'global_attempt_multiplier' => (int) env('ACCESS_GLOBAL_ATTEMPT_MULTIPLIER', 10),

];
