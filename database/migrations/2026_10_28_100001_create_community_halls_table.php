<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_halls', function (Blueprint $table) {
            $table->id();
            // Which building the hall belongs to - lets one installation serve several buildings later.
            $table->foreignId('building_id')->nullable()->constrained('buildings')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity')->default(0);
            $table->text('description')->nullable();
            // Refundable damage deposit taken per booking; 0 = none.
            $table->decimal('security_deposit', 15, 4)->default(0);
            // Share of the rent that must be paid to keep a booking (shown, not enforced).
            $table->decimal('advance_percent', 5, 2)->default(50);
            // Cancelling at least this many days ahead refunds all rent paid ...
            $table->unsignedSmallInteger('free_cancel_days')->default(7);
            // ... later than that, only this share of the rent paid is refunded.
            $table->decimal('late_cancel_refund_percent', 5, 2)->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_halls');
    }
};
