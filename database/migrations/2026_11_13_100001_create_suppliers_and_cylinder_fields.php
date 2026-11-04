<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers are created once (name, phone, address) and picked on every purchase; a gas purchase is entered the way gas is
 * bought - cylinders, kilograms in each, and the price of a cylinder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind', 12)->default('GAS'); // GAS, MATERIALS, OTHER
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['kind', 'is_active']);
        });

        Schema::table('gas_purchases', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('supplier_name')->constrained('suppliers')->nullOnDelete();
            $table->unsignedSmallInteger('cylinders')->nullable()->after('supplier_id');
            $table->decimal('cylinder_kg', 8, 2)->nullable()->after('cylinders');
            $table->decimal('price_per_cylinder', 15, 4)->nullable()->after('cylinder_kg');
        });

        Schema::table('construction_material_purchases', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('supplier_name')->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('construction_material_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
        Schema::table('gas_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['cylinders', 'cylinder_kg', 'price_per_cylinder']);
        });
        Schema::dropIfExists('suppliers');
    }
};
