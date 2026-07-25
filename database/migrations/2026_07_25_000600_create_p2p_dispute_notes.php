<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator-only internal notes on a P2P dispute — an audit trail of the review
 * that is never shown to the trading parties.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_dispute_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dispute_id')->constrained('p2p_disputes')->cascadeOnDelete();
            $table->foreignUuid('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['dispute_id', 'created_at'], 'ix_p2p_dispute_note');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_dispute_notes');
    }
};
