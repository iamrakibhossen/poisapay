<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialised daily analytics rollups. One row per (day, metric); USD-valued
 * figures are pre-aggregated by the hourly rollup job so the dashboard's
 * time-series charts never rescan the ledger. Cheap key-value shape keeps new
 * metrics additive — no schema change per metric.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('metric', 48);
            $table->decimal('value', 38, 2)->default(0);
            $table->jsonb('meta')->nullable();   // optional dimensional breakdown
            $table->timestamps();

            $table->unique(['day', 'metric']);
            $table->index('metric');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_metrics');
    }
};
