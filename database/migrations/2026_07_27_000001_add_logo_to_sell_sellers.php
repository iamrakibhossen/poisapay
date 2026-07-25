<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sell_sellers', function (Blueprint $table) {
            $table->string('logo_path', 255)->nullable()->after('brand_name');
        });
    }

    public function down(): void
    {
        Schema::table('sell_sellers', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
