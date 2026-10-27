<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hall_bookings', function (Blueprint $table) {
            $table->id();
            // 'HB-000042', derived from this row's own id after insert (same idea as payments.receipt_no).
            $table->string('booking_no')->nullable()->unique();
            $table->foreignId('community_hall_id')->constrained('community_halls')->restrictOnDelete();
            // Set for a resident (owner/tenant) booking, null for an outside guest.
            $table->foreignId('flat_id')->nullable()->constrained('flats')->restrictOnDelete();
            $table->string('customer_name');
            $table->string('customer_mobile', 20);
            $table->string('event_type', 50);
            $table->unsignedInteger('guest_count')->default(0);
            $table->date('booking_date');
            $table->enum('slot', ['DAY', 'NIGHT', 'FULL_DAY']);
            // Snapshot of the slot's hours and money at booking time, so later rate edits never rewrite history.
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('rent_amount', 15, 4);
            $table->decimal('extra_charge', 15, 4)->default(0);
            $table->decimal('discount', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4);
            $table->decimal('security_deposit', 15, 4)->default(0);
            $table->enum('status', ['PENDING', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'REJECTED'])->default('PENDING');
            $table->text('notes')->nullable();
            $table->text('status_reason')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // availability + double-booking lookups
            $table->index(['community_hall_id', 'booking_date', 'status']);
            $table->index('flat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hall_bookings');
    }
};
