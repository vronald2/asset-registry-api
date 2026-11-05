<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// REST API only: the home page leads to the API documentation.
Route::redirect('/', '/docs/api');
