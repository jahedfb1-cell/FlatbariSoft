<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['LIFT', 'SOLAR', 'SUBSTATION', 'PUMP', 'GENERATOR', 'OTHER']);
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 15, 4)->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_mobile', 20)->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->date('amc_expiry')->nullable();
            $table->date('next_service_date')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
