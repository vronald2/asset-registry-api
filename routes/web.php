<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The service has no UI: the root URL leads to the (Basic Auth protected) API documentation.
Route::redirect('/', '/docs/api');
