<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flat_id')->constrained()->restrictOnDelete();
            $table->decimal('service_charge', 15, 4)->default(0);
            $table->decimal('gas_amount', 15, 4)->default(0);
            $table->decimal('other_charges', 15, 4)->default(0);
            $table->decimal('late_fee', 15, 4)->default(0);
            $table->decimal('previous_due', 15, 4)->default(0);
            $table->decimal('total_payable', 15, 4)->default(0);
            // Running total of approved payments applied to this bill — a
            // maintained cache (like bank_accounts.current_balance), kept
            // in sync by the payment service, not user-editable directly.
            $table->decimal('paid_amount', 15, 4)->default(0);
            $table->date('due_date');
            $table->enum('status', ['UNPAID', 'PARTIAL', 'PAID'])->default('UNPAID');
            $table->timestamps();

            $table->unique(['billing_period_id', 'flat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
