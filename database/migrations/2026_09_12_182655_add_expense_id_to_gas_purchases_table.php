<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * gas_purchases existed since Phase 1.5 but had no route/service to
     * actually create one — the quantity_kg it needs for system-loss %
     * was never capturable. Wiring that up (GasPurchaseService) also
     * posts a matching Expense (fund_type GAS) so the money side goes
     * through the same ledger as everything else — this column links
     * the two rows together.
     */
    public function up(): void
    {
        Schema::table('gas_purchases', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->after('total_cost')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gas_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
        });
    }
};
