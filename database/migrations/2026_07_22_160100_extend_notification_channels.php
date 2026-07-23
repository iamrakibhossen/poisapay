<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 6 — WhatsApp + Telegram notification channels, a Telegram chat link, and a
 * device-token store for push. All additive; new channels default OFF so existing
 * delivery is unchanged until a user opts in and a template targets them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('whatsapp')->default(false)->after('push');
            $table->boolean('telegram')->default(false)->after('whatsapp');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_chat_id', 32)->nullable();
        });

        Schema::create('user_push_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 255);
            $table->string('platform', 16)->default('web'); // web | ios | android
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'token'], 'uq_push_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_push_tokens');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('telegram_chat_id'));
        Schema::table('notification_preferences', fn (Blueprint $table) => $table->dropColumn(['whatsapp', 'telegram']));
    }
};
