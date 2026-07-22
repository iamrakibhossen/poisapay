<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform deposit fee (the admin's cut). `amount` stays the gross detected
 * amount; `fee` is the portion booked to fee:income, and the user is credited
 * amount − fee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->decimal('fee', 78, 0)->default(0)->after('amount'); // base units
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn('fee');
        });
    }
};
