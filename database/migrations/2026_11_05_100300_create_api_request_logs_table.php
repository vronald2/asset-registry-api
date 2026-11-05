<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Append-only log of the API traffic (requests and responses).
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('request_id', 64)->index();
            $table->string('method', 10);
            $table->text('url');
            $table->string('route')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_headers');
            $table->longText('request_body')->nullable();
            $table->unsignedSmallInteger('response_status')->index();
            $table->json('response_headers');
            $table->longText('response_body')->nullable();
            $table->decimal('duration_ms', 10, 2);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
