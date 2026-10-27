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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();

            // Identity (name/mobile/NID) lives on `persons` — a tenant login
            // needs users.person_id to resolve to something, so tenants are
            // persons too, not a separate parallel identity. This table only
            // holds fields specific to the tenancy contract itself.
            // Explicit table name: Laravel's auto-inferred plural of
            // "person" is "people", not "persons".
            $table->foreignId('person_id')->constrained('persons')->restrictOnDelete();

            $table->string('occupation')->nullable();
            $table->unsignedTinyInteger('family_members_count')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('monthly_rent', 15, 4)->nullable();
            $table->decimal('security_deposit', 15, 4)->nullable();
            $table->enum('status', ['ACTIVE', 'VACATED'])->default('ACTIVE');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['flat_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
