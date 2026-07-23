<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Public, shareable numeric account ID (RedotPay-style). UUIDs stay the
        // non-enumerable PK; this 9-digit number is what users hand out to get paid.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('uid')->nullable()->unique()->after('phone');
        });

        User::whereNull('uid')->each(function (User $user) {
            $user->uid = User::generateUid();
            $user->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uid');
        });
    }
};
