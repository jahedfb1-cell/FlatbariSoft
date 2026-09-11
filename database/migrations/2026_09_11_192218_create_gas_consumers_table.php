<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gas_consumers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('meter_no')->nullable();
            // The project office is itself a gas consumer whose bill is
            // never collected as income — it's booked as an expense.
            $table->boolean('is_internal')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_consumers');
    }
};
