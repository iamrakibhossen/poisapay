<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Auth security (OTP/devices §9.2), audit (§9.5), webhooks (§8.3),
 *  support tickets, reconciliation runs (§5.4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('identifier', 128);            // email or phone
            $table->string('channel', 8);                 // email | sms
            $table->string('purpose', 24);                // login | withdrawal | verify
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['identifier', 'purpose']);
        });

        Schema::create('user_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 96)->nullable();
            $table->string('fingerprint', 128);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 16)->default('user'); // user | operator | system
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete(); // merchant owner
            $table->string('url', 255);
            $table->string('secret');                     // HMAC signing secret
            $table->json('events');                       // subscribed event names
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('event', 48);
            $table->json('payload');
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('status', 16)->default('pending'); // pending | delivered | failed
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 160);
            $table->string('category', 32)->default('general');
            $table->string('priority', 12)->default('normal');
            $table->string('status', 16)->default('open'); // open | pending | resolved | closed
            $table->foreignUuid('assigned_to')->nullable()->constrained('admins');
            $table->timestamps();

            $table->index(['status', 'priority']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users');
            $table->text('body');
            $table->boolean('is_staff')->default(false);
            $table->timestamps();
        });

        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('onchain_controlled', 78, 0)->default(0);
            $table->decimal('ledger_treasury', 38, 0)->default(0);
            $table->decimal('ledger_liability', 38, 0)->default(0);
            $table->decimal('drift', 78, 0)->default(0);
            $table->boolean('is_solvent')->default(true);
            $table->string('status', 16)->default('ok');  // ok | drift | insolvent
            $table->timestamps();

            $table->index(['asset_id', 'created_at']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->json('value')->nullable();
            $table->string('group', 32)->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('otp_codes');
    }
};
