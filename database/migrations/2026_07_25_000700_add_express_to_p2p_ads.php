<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Express trade — a merchant commitment to fast release on an ad. Eligibility
 * (a fast release record) is enforced in the app layer; this only stores the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_ads', function (Blueprint $table) {
            $table->boolean('is_express')->default(false)->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('p2p_ads', function (Blueprint $table) {
            $table->dropColumn('is_express');
        });
    }
};
