<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The many-to-many assignment of devices to contracts. Every assignment is a concrete unit, identified by its
     * serial number, which is unique across the whole registry.
     */
    public function up(): void
    {
        Schema::create('contract_device', function (Blueprint $table): void {
            $table->id();
            // Deleting a contract removes its assignments; the devices themselves are kept.
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            // A device that is assigned to a contract cannot be deleted.
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->string('serial_number', 100)->unique();
            $table->timestamps();

            // A device type appears at most once on a contract.
            $table->unique(['contract_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_device');
    }
};
