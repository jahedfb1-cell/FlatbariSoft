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
        // Deferred from Phase 0.3 — `staff` didn't exist yet.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('staff_id')->references('id')->on('staff')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
        });
    }
};
