<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete for P2P ads — lets a merchant retire an ad without losing the
 * historical orders that reference it (the order→ad relation reads withTrashed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_ads', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('p2p_ads', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
