<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 4 — tamper-evident audit trail. Each row carries a monotonic sequence, the
 * previous row's hash, and its own hash over (sequence|prev_hash|payload). Any
 * edit/deletion of an earlier row breaks the chain, which the verifier detects.
 * A Postgres sequence guarantees a gap-free, concurrency-safe ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS audit_logs_seq');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('sequence')->nullable()->after('id');
            $table->string('prev_hash', 64)->nullable()->after('sequence');
            $table->string('hash', 64)->nullable()->after('prev_hash');
            $table->index('sequence', 'ix_audit_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('ix_audit_sequence');
            $table->dropColumn(['sequence', 'prev_hash', 'hash']);
        });

        DB::statement('DROP SEQUENCE IF EXISTS audit_logs_seq');
    }
};
