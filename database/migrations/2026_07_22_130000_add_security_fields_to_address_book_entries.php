<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 4 — withdrawal address whitelist + cooldown. Existing rows default to
 * `active` (they predate the feature, so nothing a user already saved is blocked);
 * new rows are created `pending` with a cooldown window before they can be used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('address_book_entries', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->after('address'); // active | pending | blocked
            $table->timestamp('cooldown_until')->nullable()->after('status');
            $table->timestamp('whitelisted_at')->nullable()->after('cooldown_until');
            $table->timestamp('blocked_at')->nullable()->after('whitelisted_at');
            $table->index(['user_id', 'status'], 'ix_addressbook_user_status');
        });
    }

    public function down(): void
    {
        Schema::table('address_book_entries', function (Blueprint $table) {
            $table->dropIndex('ix_addressbook_user_status');
            $table->dropColumn(['status', 'cooldown_until', 'whitelisted_at', 'blocked_at']);
        });
    }
};
