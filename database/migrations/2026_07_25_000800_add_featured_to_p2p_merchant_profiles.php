<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Featured merchants — an operator-granted, time-boxed promotion that floats a
 * merchant's ads to the top of the marketplace with a badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_merchant_profiles', function (Blueprint $table) {
            $table->timestamp('featured_until')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('p2p_merchant_profiles', function (Blueprint $table) {
            $table->dropColumn('featured_until');
        });
    }
};
