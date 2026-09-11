<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gas_meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gas_consumer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('previous_reading', 15, 4);
            $table->decimal('current_reading', 15, 4);
            $table->decimal('units_used', 15, 4);
            $table->date('reading_date');
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->string('photo')->nullable();
            $table->timestamps();

            $table->unique(['gas_consumer_id', 'billing_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_meter_readings');
    }
};
