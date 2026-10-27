<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only: a mistake is corrected with a refund row, never an edit or delete.
        Schema::create('hall_booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hall_booking_id')->constrained('hall_bookings')->restrictOnDelete();
            // RENT in, DEPOSIT in, RENT_REFUND out, DEPOSIT_REFUND out
            $table->enum('type', ['RENT', 'DEPOSIT', 'RENT_REFUND', 'DEPOSIT_REFUND']);
            $table->decimal('amount', 15, 4);
            $table->enum('method', ['CASH', 'BANK', 'BKASH', 'NAGAD']);
            $table->date('paid_on');
            $table->string('reference_no')->nullable();
            $table->string('receipt_no')->nullable()->unique();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('hall_booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hall_booking_payments');
    }
};
