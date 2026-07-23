<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Wave 4 — durable security signals (suspicious logins, velocity, whitelist blocks). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);      // new_device | new_location | impossible_travel | ip_flagged | velocity_exceeded | whitelist_block | address_added
            $table->string('severity', 16)->default('info'); // info | warning | critical
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
