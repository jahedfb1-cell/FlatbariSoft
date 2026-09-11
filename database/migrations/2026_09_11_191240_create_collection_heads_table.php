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
        Schema::create('collection_heads', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_bn');
            $table->string('name_en')->nullable();
            $table->enum('payer_type', ['OWNER', 'OCCUPANT']);
            $table->enum('charge_type', ['RECURRING', 'SPECIAL_LEVY', 'MISC']);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_heads');
    }
};
