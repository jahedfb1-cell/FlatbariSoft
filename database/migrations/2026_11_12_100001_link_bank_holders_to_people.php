<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The people who sign for a bank account are flat owners of the building: each holder can be linked to that person. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            foreach ([1, 2, 3] as $n) {
                $table->foreignId("holder_{$n}_person_id")->nullable()->constrained('persons')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            foreach ([1, 2, 3] as $n) {
                $table->dropConstrainedForeignId("holder_{$n}_person_id");
            }
        });
    }
};
