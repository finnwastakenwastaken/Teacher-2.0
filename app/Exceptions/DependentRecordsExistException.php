<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a delete would orphan data that depends on the record being
 * removed. Per the technical reference's conventions, these deletes block and report what
 * depends on them rather than cascading. Reused across topics now, and by
 * education levels, images and media files in later tasks.
 */
class DependentRecordsExistException extends RuntimeException {}
