<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Removes API traffic log entries older than the retention period (config/api.php).
Schedule::command('model:prune')->daily();
