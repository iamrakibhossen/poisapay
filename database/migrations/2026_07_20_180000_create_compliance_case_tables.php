<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 8: AML alerting + compliance case management (TDD §10.2/§10.4). */
return new class extends Migration
{
    public function up(): void
    {
        // A single flagged event (velocity, threshold, sanctions hit, …).
        Schema::create('aml_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);                    // rule key: large_amount | velocity | sanctions_hit | ...
            $table->string('severity', 12)->default('medium'); // low|medium|high|critical
            $table->string('context', 24)->default('withdrawal');
            $table->string('subject_type', 48)->nullable(); // model class of the triggering entity
            $table->uuid('subject_id')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('reasons')->nullable();
            $table->string('status', 16)->default('open'); // open|cleared|escalated
            $table->foreignUuid('case_id')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('admins');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // An investigation grouping one or more alerts for a user.
        Schema::create('compliance_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('open'); // open|investigating|closed
            $table->string('risk_level', 12)->default('medium');
            $table->string('reason', 48);                  // why the case was opened
            $table->text('summary')->nullable();
            $table->boolean('sar_filed')->default(false);
            $table->string('sar_reference', 64)->nullable();
            $table->foreignUuid('assigned_to')->nullable()->constrained('admins');
            $table->foreignUuid('opened_by')->nullable()->constrained('admins');
            $table->timestamp('closed_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index(['status', 'risk_level', 'created_at']);
        });

        Schema::table('aml_alerts', function (Blueprint $table) {
            $table->foreign('case_id')->references('id')->on('compliance_cases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aml_alerts', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
        });
        Schema::dropIfExists('compliance_cases');
        Schema::dropIfExists('aml_alerts');
    }
};
