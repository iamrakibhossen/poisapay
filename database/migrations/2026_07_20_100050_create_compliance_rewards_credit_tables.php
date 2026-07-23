<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** KYC/AML (§10), rewards/referral (§F5). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('requested_tier', 16);         // basic | full
            $table->string('status', 16)->default('pending');
            $table->string('document_type', 24)->nullable(); // nid | passport
            $table->string('document_number', 64)->nullable();
            $table->string('full_name', 128)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('country', 2)->default('BD');
            $table->text('address')->nullable();
            $table->json('document_paths')->nullable();    // S3 keys (front/back/selfie)
            $table->boolean('liveness_passed')->default(false);
            $table->foreignUuid('reviewed_by')->nullable()->constrained('admins');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('screening_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('context', 24);                 // onboarding | withdrawal | remittance
            $table->uuid('subject_id')->nullable();        // e.g. withdrawal id
            $table->string('provider', 32)->default('internal');
            $table->string('result', 12)->default('clear'); // clear | review | hit
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('matches')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'context']);
        });

        // §F5.2
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('referee_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('status', 16)->default('pending');
            $table->uuid('reward_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('reward_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->unique(['referrer_id', 'referee_id'], 'uq_referral_pair');
        });
        DB::statement('ALTER TABLE referrals ADD CONSTRAINT ck_no_self_referral CHECK (referrer_id <> referee_id)');

        Schema::create('reward_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32);                    // welcome | referral | cashback | daily
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('amount', 78, 0);
            $table->string('idempotency_key', 160)->unique(); // reward:{type}:{user}:{period}
            $table->uuid('entry_id')->nullable();
            $table->timestamps();

            $table->foreign('entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_grants');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('screening_results');
        Schema::dropIfExists('kyc_profiles');
    }
};
