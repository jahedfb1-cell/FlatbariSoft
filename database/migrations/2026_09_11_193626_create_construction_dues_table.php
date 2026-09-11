<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->restrictOnDelete();
            $table->string('category');
            $table->enum('source_type', ['INITIAL_CONTRACT', 'LEVY_RESOLUTION']);
            // References `resolutions` (Phase 2.3) — deferred FK, see expenses.
            $table->unsignedBigInteger('resolution_id')->nullable();
            $table->decimal('total_amount', 15, 4);
            $table->decimal('paid_amount', 15, 4)->default(0);
            $table->decimal('balance', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_dues');
    }
};
