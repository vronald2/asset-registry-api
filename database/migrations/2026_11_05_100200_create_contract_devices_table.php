<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Association entity of the contract <-> device (type) many-to-many relationship.
// Every row is one physical unit, identified by its serial number.
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('contract_devices', function (Blueprint $table): void {
            $table->id();
            // Removing a contract removes its line items as well.
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            // A device type cannot be deleted while it is assigned to a contract.
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->string('serial_number', 100);
            $table->timestamps();

            // A physical unit (device type + serial number) can be on one contract at a time.
            $table->unique(['device_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_devices');
    }
};
