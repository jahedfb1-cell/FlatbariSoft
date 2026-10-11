<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same column gas_purchases has had since Phase 1.5 (`invoice_file`):
     * a path on the private invoice disk, filled in by InvoiceImageService.
     */
    public function up(): void
    {
        Schema::table('construction_material_purchases', function (Blueprint $table) {
            $table->string('invoice_file')->nullable()->after('total_cost');
        });
    }

    public function down(): void
    {
        Schema::table('construction_material_purchases', function (Blueprint $table) {
            $table->dropColumn('invoice_file');
        });
    }
};
