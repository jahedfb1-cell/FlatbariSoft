<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner-requested dedicated tracking for construction material
     * purchases — history/reporting only, not a stock/inventory module
     * (no consumption or remaining-quantity tracking, unlike raw fabric
     * on the sibling WBL project). Every purchase is also an Expense
     * (fund_type CAPITAL) so it still goes through the one real ledger —
     * this table only carries the extra item/quantity detail an Expense
     * row alone doesn't have room for.
     */
    public function up(): void
    {
        Schema::create('construction_material_purchases', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('item_name');
            $table->decimal('quantity', 15, 4);
            $table->string('unit');
            $table->decimal('unit_rate', 15, 4);
            $table->string('supplier_name')->nullable();
            $table->decimal('transport_cost', 15, 4)->default(0);
            // Server-computed (quantity * unit_rate + transport_cost) —
            // never trust a total the frontend sent.
            $table->decimal('total_cost', 15, 4);
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_material_purchases');
    }
};
