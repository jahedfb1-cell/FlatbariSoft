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
        Schema::create('flat_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            // Explicit table name: Laravel's auto-inferred plural of
            // "person" is "people", not "persons".
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->enum('role', ['OWNER', 'TENANT']);
            $table->date('from_date');
            // NULL while the relationship is current; set when it ends
            // (ownership transferred, tenancy vacated) — never deleted.
            $table->date('to_date')->nullable();
            $table->boolean('is_billing_contact')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['flat_id', 'role', 'to_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flat_parties');
    }
};
