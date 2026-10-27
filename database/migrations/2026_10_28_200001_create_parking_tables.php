<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->restrictOnDelete();
            $table->string('code', 30)->unique();               // 'G-01'
            $table->string('zone', 60)->nullable();             // 'Ground floor', 'Basement 1'
            $table->enum('vehicle_type', ['CAR', 'MOTORBIKE', 'BICYCLE'])->default('CAR');
            // The usual monthly charge for this slot; each allocation copies it, so editing it later
            // never rewrites what an existing holder was promised.
            $table->decimal('monthly_fee', 15, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Who holds a slot, from when to when. A slot has at most one open (to_date NULL) allocation.
        Schema::create('parking_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_slot_id')->constrained('parking_slots')->restrictOnDelete();
            $table->foreignId('flat_id')->constrained('flats')->restrictOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('vehicle_no', 40)->nullable();
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->decimal('monthly_fee', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['parking_slot_id', 'to_date']);
            $table->index('flat_id');
        });

        Schema::create('parking_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_allocation_id')->constrained('parking_allocations')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 15, 4);
            $table->date('due_date');
            $table->timestamps();

            // generating the same month twice must never double-charge
            $table->unique(['parking_allocation_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });

        // Append-only. What is still owed is always charge.amount minus the sum of these rows.
        Schema::create('parking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_charge_id')->constrained('parking_charges')->restrictOnDelete();
            $table->decimal('amount', 15, 4);
            $table->enum('method', ['CASH', 'BANK', 'BKASH', 'NAGAD']);
            $table->date('paid_on');
            $table->string('reference_no')->nullable();
            $table->string('receipt_no')->nullable()->unique();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('parking_charge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_payments');
        Schema::dropIfExists('parking_charges');
        Schema::dropIfExists('parking_allocations');
        Schema::dropIfExists('parking_slots');
    }
};
