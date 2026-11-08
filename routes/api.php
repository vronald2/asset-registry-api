<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\ContractDeviceController;
use App\Http\Controllers\Api\V1\DeviceController;
use Illuminate\Support\Facades\Route;

// Identifiers are numeric. Anything else must not reach the route model binding: MySQL would cast "1abc" to 1.
Route::pattern('device', '[0-9]+');
Route::pattern('contract', '[0-9]+');

Route::prefix('v1')->name('api.v1.')->group(static function (): void {
    Route::apiResource('devices', DeviceController::class);
    Route::apiResource('contracts', ContractController::class);

    // POST contracts/{contract}/devices, DELETE contracts/{contract}/devices/{device}
    Route::apiResource('contracts.devices', ContractDeviceController::class)->only(['store', 'destroy']);
});
