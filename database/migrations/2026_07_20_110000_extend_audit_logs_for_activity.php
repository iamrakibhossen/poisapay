<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generalise audit_logs into the platform activity/audit trail (DollarHub pattern). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('actor_id')->nullable()->after('actor_type');   // user OR admin id
            $table->string('actor_name')->nullable()->after('actor_id');
            $table->string('description')->nullable()->after('action');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_id', 'actor_name', 'description', 'user_agent']);
        });
    }
};
