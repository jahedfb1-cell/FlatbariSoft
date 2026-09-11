<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gas_purchases', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('supplier_name');
            $table->decimal('quantity_kg', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('transport_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4);
            $table->string('invoice_file')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_purchases');
    }
};
