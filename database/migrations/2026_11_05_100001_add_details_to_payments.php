<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment can carry the bank name and a free note, and the method becomes plain text so mobile banking can name
 * its provider (bKash, Nagad, Rocket, Upay) instead of being limited to the first two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 20)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable()->after('reference_no');
            $table->string('remarks', 500)->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'remarks']);
        });
    }
};
