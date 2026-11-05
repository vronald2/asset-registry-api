<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\ContractDeviceController;
use App\Http\Controllers\Api\V1\DeviceController;
use Illuminate\Support\Facades\Route;

/*
| Updates replace the whole representation (PUT); partial updates (PATCH) are not supported.
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::controller(DeviceController::class)->group(function (): void {
        Route::get('devices', 'index')->name('devices.index');
        Route::post('devices', 'store')->name('devices.store');
        Route::get('devices/{device}', 'show')->name('devices.show');
        Route::put('devices/{device}', 'update')->name('devices.update');
        Route::delete('devices/{device}', 'destroy')->name('devices.destroy');
    });

    Route::controller(ContractController::class)->group(function (): void {
        Route::get('contracts', 'index')->name('contracts.index');
        Route::post('contracts', 'store')->name('contracts.store');
        Route::get('contracts/{contract}', 'show')->name('contracts.show');
        Route::put('contracts/{contract}', 'update')->name('contracts.update');
        Route::delete('contracts/{contract}', 'destroy')->name('contracts.destroy');
    });

    // {contractDevice} is resolved through the contract (scoped binding): units of other contracts are 404.
    Route::controller(ContractDeviceController::class)->scopeBindings()->group(function (): void {
        Route::post('contracts/{contract}/devices', 'store')->name('contracts.devices.store');
        Route::delete('contracts/{contract}/devices/{contractDevice}', 'destroy')->name('contracts.devices.destroy');
    });
});
