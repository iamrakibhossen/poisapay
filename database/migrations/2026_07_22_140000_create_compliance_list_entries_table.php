<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 5 — persistent sanctions/PEP denylist, watchlist and whitelist. Replaces
 * the settings-string lists with durable rows that carry a reason, source and
 * optional expiry, so every block is auditable (who/when/why). Screening and KYT
 * consult these in addition to any provider result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_list_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('list', 16);   // denylist | watchlist | whitelist
            $table->string('kind', 16);   // name | address | country | user | email
            $table->string('value', 191);
            $table->string('reason', 255)->nullable();
            $table->string('source', 64)->nullable(); // manual | ofac | un | eu | ...
            $table->uuid('added_by')->nullable();     // admin id
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['list', 'kind']);
            $table->index('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_list_entries');
    }
};
