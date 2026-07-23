<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Merchant invoices/QR (§8.2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('amount', 78, 0);
            $table->string('reference', 64);               // merchant-side order id
            $table->string('memo', 160)->nullable();
            $table->string('status', 16)->default('pending'); // pending|paid|expired|cancelled
            $table->foreignUuid('payer_id')->nullable()->constrained('users');
            $table->uuid('entry_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->unique(['merchant_id', 'reference'], 'uq_invoice_reference'); // idempotent creation
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_invoices');
    }
};
