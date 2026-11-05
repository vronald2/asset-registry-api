<?php

declare(strict_types=1);

namespace App\Logging\ApiTraffic;

/**
 * Storage of the API traffic log. The application binds the database implementation;
 * a queue- or file-based implementation can be swapped in without touching the callers.
 */
interface ApiTrafficLogger
{
    public function log(ApiTrafficRecord $record): void;
}
