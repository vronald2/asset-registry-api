<?php

declare(strict_types=1);

namespace App\Logging\ApiTraffic;

/**
 * Stores the API traffic log entries.
 */
interface ApiTrafficLogger
{
    public function log(ApiTrafficEntry $entry): void;
}
