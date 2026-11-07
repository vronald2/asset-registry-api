<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * One row per /api/* request with its response (see App\Http\Middleware\LogApiTraffic).
     * Sensitive values are masked and large bodies truncated before they are written.
     */
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('method', 10);
            $table->string('path', 2048);
            $table->string('route')->nullable()->comment('Route URI pattern; null for unknown routes');
            $table->json('query');
            $table->json('request_headers');
            $table->mediumText('request_body')->nullable();
            $table->unsignedSmallInteger('response_status');
            $table->json('response_headers');
            $table->mediumText('response_body')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
