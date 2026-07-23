<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 5 — structured SAR/STR fields. The case already stored a free-text
 * reference + summary; this adds the structured elements a regulator filing needs
 * (activity type, narrative, subject amount, filing timestamp). All nullable/additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_cases', function (Blueprint $table) {
            $table->string('sar_activity_type', 48)->nullable()->after('sar_reference');
            $table->text('sar_narrative')->nullable()->after('sar_activity_type');
            $table->decimal('sar_amount', 38, 0)->nullable()->after('sar_narrative');
            $table->timestamp('sar_filed_at')->nullable()->after('sar_amount');
        });
    }

    public function down(): void
    {
        Schema::table('compliance_cases', function (Blueprint $table) {
            $table->dropColumn(['sar_activity_type', 'sar_narrative', 'sar_amount', 'sar_filed_at']);
        });
    }
};
