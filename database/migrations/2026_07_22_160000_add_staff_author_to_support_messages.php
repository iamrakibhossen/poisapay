<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 6 — allow staff (operator) replies on support tickets. author_id becomes
 * nullable (staff replies aren't tied to a user row) and author_name records the
 * display name of whoever replied. Backward compatible: existing user messages
 * keep their author_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->uuid('author_id')->nullable()->change();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->string('author_name', 120)->nullable()->after('author_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropColumn('author_name');
            $table->uuid('author_id')->nullable(false)->change();
            $table->foreign('author_id')->references('id')->on('users');
        });
    }
};
