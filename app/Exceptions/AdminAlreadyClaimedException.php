<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when something tries to create a second admin account.
 *
 * Should be rare in practice — App\Support\AdminAccount::claim() closes the
 * race with a database-level advisory lock — but every caller of claim()
 * must still handle this rather than let it surface as a 500.
 */
class AdminAlreadyClaimedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The admin account has already been claimed.');
    }
}
