<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Wave 4 — per-user login history (audit gap: users previously had none). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->boolean('new_device')->default(false);
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
