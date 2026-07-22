<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 6: card controls (nickname, spend toggles, geo/MCC locks, PIN, replacement). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('nickname', 48)->nullable()->after('last4');
            $table->boolean('online_enabled')->default(true)->after('nickname');
            $table->boolean('atm_enabled')->default(true)->after('online_enabled');
            $table->boolean('contactless_enabled')->default(true)->after('atm_enabled');
            $table->json('allowed_countries')->nullable()->after('contactless_enabled'); // null = all
            $table->json('blocked_mccs')->nullable()->after('allowed_countries');
            $table->text('pin_hash')->nullable()->after('blocked_mccs');
            $table->uuid('replaced_by')->nullable()->after('pin_hash');
            $table->timestamp('closed_at')->nullable()->after('replaced_by');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'nickname', 'online_enabled', 'atm_enabled', 'contactless_enabled',
                'allowed_countries', 'blocked_mccs', 'pin_hash', 'replaced_by', 'closed_at',
            ]);
        });
    }
};
