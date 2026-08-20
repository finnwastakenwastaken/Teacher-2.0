<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Something went wrong making or restoring a backup.
 *
 * The message is written for the site owner, in Dutch, because it is shown to
 * them: on the *Back-ups* screen, and on the console by `backup:run` and
 * `backup:restore`. A backup that failed must say so in words its reader can
 * act on — "pg_dump: error: connection failed" reaches nobody who can help.
 */
class BackupException extends RuntimeException {}
