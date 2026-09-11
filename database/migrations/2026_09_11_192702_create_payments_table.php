<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 4);
            $table->date('payment_date');
            $table->enum('method', ['CASH', 'BANK', 'BKASH', 'NAGAD']);
            $table->string('reference_no')->nullable();
            // Derived from this row's own auto-increment id at approval
            // time ('MR-000042') — never a separately-tracked counter, so
            // it is unique and sequential with zero race-condition risk
            // even under concurrent approvals (BR-12: never manual, never
            // duplicated).
            $table->string('receipt_no')->nullable()->unique();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['PENDING', 'APPROVED'])->default('PENDING');
            $table->timestamps();

            $table->index('flat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
