<?php

namespace WPStaging\Pro\Backup\Exceptions;

use WPStaging\Framework\Exceptions\WPStagingException;

class ProcessLockedException extends WPStagingException
{
    public static function processAlreadyLocked($timeLeft)
    {
        return new self(__(sprintf('Another backup/restore is already running. Please wait %d seconds and try again. If you continue to see this error, please contact the WP STAGING support.', absint($timeLeft)), 'wp-staging'), 423);
    }
}
