<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gas_rate_settings', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->enum('method', ['FIXED', 'ACTUAL'])->default('FIXED');
            $table->decimal('rate', 15, 4);
            $table->decimal('m3_per_kg', 8, 4)->default(0.54);
            $table->decimal('system_loss_percent', 8, 4)->nullable();
            $table->decimal('minimum_charge', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_rate_settings');
    }
};
