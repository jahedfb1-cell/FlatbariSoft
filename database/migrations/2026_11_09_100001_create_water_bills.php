<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The monthly Dhaka WASA water bill, recorded as printed on the bill (account, meter readings, water + sewerage + VAT ...).
 * No tariff is stored or computed here - WASA changes its rates - only what the bill says. Each bill is posted as an expense
 * (through the usual approval), so the money side is never touched directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('bill_year');
            $table->unsignedTinyInteger('bill_month');
            $table->string('account_no', 50)->default('');
            $table->string('bill_no', 50)->nullable();
            $table->decimal('previous_reading', 15, 4)->nullable();
            $table->decimal('current_reading', 15, 4)->nullable();
            $table->decimal('consumption', 15, 4)->nullable(); // derived from the two readings
            $table->decimal('water_charge', 15, 4)->default(0);
            $table->decimal('sewerage_charge', 15, 4)->default(0);
            $table->decimal('vat', 15, 4)->default(0);
            $table->decimal('other_charge', 15, 4)->default(0);   // arrears, meter rent ...
            $table->decimal('late_fee', 15, 4)->default(0);
            $table->decimal('total', 15, 4);                       // always the sum of the lines above
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('expense_id')->constrained('expenses')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bill_year', 'bill_month', 'account_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_bills');
    }
};
