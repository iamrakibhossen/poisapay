<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Usernames are gone — users are identified by their numeric ID, email or phone.
    public function up(): void
    {
        if (Schema::hasColumn('users', 'handle')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('handle');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'handle')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('handle')->nullable()->unique()->after('phone');
            });
        }
    }
};
