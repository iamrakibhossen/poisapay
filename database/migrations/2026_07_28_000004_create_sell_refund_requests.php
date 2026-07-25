<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer refund requests. A buyer opens a request (full or partial) against a paid
 * order; the seller approves (→ ledger refund) or rejects; a rejected or ignored
 * request escalates to an operator. One order can have several requests (multiple
 * partials), but only one open at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sell_refund_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('sell_orders')->cascadeOnDelete();
            $table->foreignUuid('seller_id')->constrained('sell_sellers')->cascadeOnDelete(); // denormalised for seller queries
            $table->foreignUuid('buyer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 12)->default('full');            // full | partial
            $table->bigInteger('amount_requested');                  // minor units
            $table->bigInteger('amount_refunded')->nullable();       // set when approved
            $table->text('reason')->nullable();
            $table->string('status', 16)->default('requested');      // RefundRequestStatus
            $table->string('resolver_type', 12)->nullable();         // seller | admin | system
            $table->uuid('resolver_id')->nullable();
            $table->text('resolution_note')->nullable();
            $table->uuid('ledger_entry_id')->nullable();             // the refund entry (loose ref, like orders)
            $table->timestamp('sla_due_at')->nullable();             // seller-response deadline → auto-escalate
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index('buyer_user_id');
        });

        // The SLA scan: only ever looks at still-open, un-actioned requests.
        DB::statement("CREATE INDEX sell_refund_requests_sla ON sell_refund_requests (sla_due_at) WHERE status = 'requested'");
        // At most one open request per order (guards double-submits / races).
        DB::statement("CREATE UNIQUE INDEX sell_refund_requests_one_open ON sell_refund_requests (order_id) WHERE status IN ('requested','escalated')");
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_refund_requests');
    }
};
