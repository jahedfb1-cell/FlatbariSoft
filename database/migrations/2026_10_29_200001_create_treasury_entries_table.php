<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The money movements that no other module creates: opening balances, putting cash in the bank or taking
        // it out, bank profit, and loans between funds (service charge <-> gas <-> project). Append-only: a
        // mistake is undone with a reversal row that points back at the original.
        Schema::create('treasury_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['OPENING', 'DEPOSIT_TO_BANK', 'WITHDRAW_FROM_BANK', 'BANK_PROFIT', 'FUND_LOAN', 'LOAN_REPAYMENT']);
            $table->enum('fund', ['OPERATING', 'GAS', 'CAPITAL', 'SINKING']);
            // a loan or repayment moves money from `fund` to `to_fund`
            $table->enum('to_fund', ['OPERATING', 'GAS', 'CAPITAL', 'SINKING'])->nullable();
            // CASH or BANK where it matters (opening, loans); contra entries and bank profit imply theirs
            $table->enum('medium', ['CASH', 'BANK'])->nullable();
            $table->decimal('amount', 15, 4);
            $table->date('entry_date');
            $table->string('notes', 300)->nullable();
            $table->string('receipt_no')->nullable()->unique();
            $table->foreignId('reverses_id')->nullable()->constrained('treasury_entries')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['fund', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_entries');
    }
};
