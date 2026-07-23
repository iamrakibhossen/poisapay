<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks when a failed withdrawal's reserve (locked → available) was released back to
 * the user. Additive + nullable so it is a zero-downtime, backward-compatible change:
 * existing rows keep NULL and behave exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->timestamp('reserve_released_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('reserve_released_at');
        });
    }
};
