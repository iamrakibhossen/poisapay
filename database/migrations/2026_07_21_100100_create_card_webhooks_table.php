<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Inbound provider events — deduped by (driver, provider_event_id), processed async. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('driver', 24);
            $table->string('provider_event_id', 128);
            $table->string('event_type', 40);
            $table->string('provider_card_ref', 128)->nullable();
            $table->string('provider_tx_ref', 128)->nullable();
            $table->json('payload')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('status', 16)->default('pending'); // pending|processed|ignored|failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['driver', 'provider_event_id'], 'uq_card_webhook_event');
            $table->index(['status', 'created_at']);
            $table->index('provider_tx_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_webhooks');
    }
};
