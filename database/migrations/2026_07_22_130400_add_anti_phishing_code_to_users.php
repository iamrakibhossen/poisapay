<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 4 — anti-phishing code. A user-chosen phrase echoed in genuine platform
 * emails so recipients can distinguish real messages from phishing. Nullable and
 * additive; existing users are unaffected until they set one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('anti_phishing_code', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anti_phishing_code');
        });
    }
};
