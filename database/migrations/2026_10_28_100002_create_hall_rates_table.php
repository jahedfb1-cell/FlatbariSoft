<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hall_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_hall_id')->constrained('community_halls')->cascadeOnDelete();
            // DAY and NIGHT can both be booked on one date; FULL_DAY blocks both.
            $table->enum('slot', ['DAY', 'NIGHT', 'FULL_DAY']);
            $table->time('start_time');
            $table->time('end_time');
            // NULL = not decided yet (booking refused); 0 = free. Residents are often free or charged a
            // reduced rate, so a zero rate is a real price, not a missing one.
            $table->decimal('resident_rate', 15, 4)->nullable();
            $table->decimal('outsider_rate', 15, 4)->nullable();
            $table->timestamps();

            $table->unique(['community_hall_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hall_rates');
    }
};
