<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('expense_head_id')->constrained()->restrictOnDelete();
            $table->string('payee');
            $table->decimal('amount', 15, 4);
            $table->text('description')->nullable();
            $table->enum('treasury_medium', ['BANK', 'CASH']);
            // Confirmed rule #9: Sinking Fund is spendable only for
            // lift/motor/substation emergencies, with its own approval
            // ladder — every other expense is OPERATING by default.
            $table->enum('fund_type', ['OPERATING', 'GAS', 'CAPITAL', 'SINKING'])->default('OPERATING');
            // resolution_id references `resolutions` (Phase 2.3, doesn't
            // exist yet) — FK constraint added once that table exists,
            // same deferred-FK pattern as users.person_id in Phase 0.5.
            $table->unsignedBigInteger('resolution_id')->nullable();
            $table->string('voucher_no')->nullable()->unique();
            $table->string('voucher_image')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['PENDING', 'APPROVED'])->default('PENDING');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
