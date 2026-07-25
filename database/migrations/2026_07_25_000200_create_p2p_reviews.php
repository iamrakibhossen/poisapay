<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer↔seller feedback for a completed P2P trade. One review per rater per
 * order; the ratee's cached aggregates (rating, review_count, positive_count)
 * on p2p_merchant_profiles are recomputed from this table on each write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('p2p_orders')->cascadeOnDelete();
            $table->foreignUuid('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');          // 1–5 stars
            $table->boolean('is_positive');                 // rating >= 4 (feeds positive-feedback %)
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'rater_id'], 'uq_p2p_review_order_rater');
            $table->index(['ratee_id', 'created_at'], 'ix_p2p_review_ratee');
        });

        Schema::table('p2p_merchant_profiles', function (Blueprint $table) {
            $table->unsignedInteger('review_count')->default(0)->after('rating');
            $table->unsignedInteger('positive_count')->default(0)->after('review_count');
        });
    }

    public function down(): void
    {
        Schema::table('p2p_merchant_profiles', function (Blueprint $table) {
            $table->dropColumn(['review_count', 'positive_count']);
        });
        Schema::dropIfExists('p2p_reviews');
    }
};
