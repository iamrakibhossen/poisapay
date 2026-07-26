<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the last time a merchant was active on P2P, so presence can flip to
 * offline automatically after a period of inactivity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_merchant_profiles', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('is_online');
        });
    }

    public function down(): void
    {
        Schema::table('p2p_merchant_profiles', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
