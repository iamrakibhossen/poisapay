<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Block-tree builder engine storage:
 *  - `sell_sales_pages.draft`  — the working document edited in the builder. The
 *    live public page keeps rendering `sections` (the published doc); publish
 *    copies draft → sections so in-progress edits never touch a live page.
 *  - `sell_page_revisions`     — version history (snapshot on publish + autosave
 *    checkpoints); restore loads a revision back into `draft`.
 *  - `sell_saved_blocks`       — a seller's reusable saved sections/components.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sell_sales_pages', function (Blueprint $table) {
            $table->jsonb('draft')->nullable()->after('sections'); // working v2 document
        });

        Schema::create('sell_page_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_page_id')->constrained('sell_sales_pages')->cascadeOnDelete();
            $table->foreignUuid('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('label', 80)->nullable();       // e.g. "Published", "Autosave"
            $table->jsonb('document');                     // full v2 document snapshot
            $table->timestamp('created_at')->nullable();

            $table->index(['sales_page_id', 'version']);
        });

        Schema::create('sell_saved_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('sell_sellers')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 24)->default('section'); // section | component
            $table->jsonb('node');                          // a serialised BuilderNode subtree
            $table->timestamps();

            $table->index(['seller_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_saved_blocks');
        Schema::dropIfExists('sell_page_revisions');
        Schema::table('sell_sales_pages', function (Blueprint $table) {
            $table->dropColumn('draft');
        });
    }
};
