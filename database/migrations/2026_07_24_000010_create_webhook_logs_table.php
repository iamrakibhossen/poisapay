<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic inbound-webhook request/response log (audit + debug + replay). Every
 * request hitting a webhook route is recorded by the WebhookLogger middleware:
 * method, url, normalized payload, redacted headers, response status/body, and a
 * content hash for dedup. Distinct from webhook_deliveries (outbound) and
 * card_webhooks (the deduped, business-processed inbound card events).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 40)->nullable();       // {provider}/{driver} route segment
            $table->string('method', 8)->default('POST');
            $table->string('url', 512);
            $table->string('route', 100)->nullable();         // matched route name
            $table->json('payload')->nullable();
            $table->json('headers')->nullable();              // sensitive values redacted
            $table->string('ip', 45)->nullable();
            $table->string('hash', 32)->index();              // md5(payload) for dedup
            $table->unsignedSmallInteger('status')->default(0);
            $table->text('response')->nullable();             // truncated response body
            $table->unsignedInteger('retries')->default(0);
            $table->boolean('resolved')->default(false);
            $table->timestamps();

            $table->index(['provider', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['resolved', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
