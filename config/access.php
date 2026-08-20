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

];
