<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A media upload or import could not be completed. Carries a Dutch,
 * owner-facing message — these surface directly in the admin UI, so they
 * explain what to do rather than what broke internally.
 */
class MediaUploadException extends RuntimeException {}
