<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The building's own phone numbers - manager, guard, lift, electrician - shown to every flat owner and tenant. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable(); // "Manager", "Guard", "Lift service" ...
            $table->string('phone', 30);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_contacts');
    }
};
