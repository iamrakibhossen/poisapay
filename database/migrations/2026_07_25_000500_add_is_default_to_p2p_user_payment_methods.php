<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A merchant's preferred payout account. Surfaced first to the buyer on an open
 * order; at most one default per user (enforced in the app layer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_user_payment_methods', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('p2p_user_payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
