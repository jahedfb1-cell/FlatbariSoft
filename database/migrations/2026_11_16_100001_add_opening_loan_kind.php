<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // a fund-to-fund debt that already existed when the system started (only the debt is recorded, no cash moves).
        // Fresh databases get the kind from the table's own migration; this widens one that was built before.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `treasury_entries` MODIFY `kind` ENUM('OPENING','DEPOSIT_TO_BANK','WITHDRAW_FROM_BANK','BANK_PROFIT','FUND_LOAN','LOAN_REPAYMENT','OPENING_LOAN') NOT NULL");
        }
    }

    public function down(): void
    {
        // nothing: the wider list also holds every earlier kind
    }
};
