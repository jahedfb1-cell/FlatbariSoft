<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('branch')->nullable();
            $table->string('account_no');
            $table->string('account_title');
            $table->string('mandate_rule')->nullable();
            $table->decimal('opening_balance', 15, 4)->default(0);
            // Derived (NFR: balances are always derived) — but caching the
            // running total here avoids re-summing bank_transactions on
            // every page load. Recomputed, never hand-edited, whenever a
            // transaction posts (see AccountingService).
            $table->decimal('current_balance', 15, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
