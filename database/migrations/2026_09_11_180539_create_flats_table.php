<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('flats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('flat_no');
            $table->string('floor');
            $table->decimal('size_sqft', 10, 2)->nullable();
            $table->enum('occupancy_status', ['OWNER_OCCUPIED', 'TENANTED', 'EMPTY'])->default('EMPTY');
            $table->decimal('service_charge_rate', 15, 4)->default(3500);
            $table->decimal('empty_flat_rate', 15, 4)->default(2500);
            $table->string('gas_meter_no')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['building_id', 'flat_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flats');
    }
};
