<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 10: user notification preferences + admin-editable templates (§F4). */
return new class extends Migration
{
    public function up(): void
    {
        // Per-user, per-category channel opt-in/out.
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 32);                // security | money | marketing | product | ...
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(true);
            $table->boolean('sms')->default(false);
            $table->boolean('push')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'category'], 'uq_pref_user_category');
        });

        // Admin-editable message templates rendered per event key.
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 64);                     // event key: deposit.credited, kyc.approved, ...
            $table->string('locale', 8)->default('en');
            $table->string('name', 120);
            $table->string('category', 32)->default('product');
            $table->json('channels');                      // ['in_app','email',...] channels this template targets
            $table->string('subject', 160)->nullable();
            $table->text('body');                          // supports {{placeholder}} tokens
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key', 'locale'], 'uq_template_key_locale');
        });

        // A one-to-many broadcast/announcement an operator sends to a segment.
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 160);
            $table->text('body');
            $table->string('segment', 24)->default('all'); // all | kyc_full | merchants
            $table->string('category', 32)->default('product');
            $table->json('channels')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->foreignUuid('sent_by')->nullable()->constrained('admins');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_preferences');
    }
};
