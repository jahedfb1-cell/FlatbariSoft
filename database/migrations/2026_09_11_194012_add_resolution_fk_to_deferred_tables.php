<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `resolutions` didn't exist when construction_dues (2.1), expenses
     * and fund_transfers (1.7) were created — same deferred-FK pattern as
     * users.person_id in Phase 0.5.
     */
    public function up(): void
    {
        Schema::table('construction_dues', function (Blueprint $table) {
            $table->foreign('resolution_id')->references('id')->on('resolutions')->nullOnDelete();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('resolution_id')->references('id')->on('resolutions')->nullOnDelete();
        });
        Schema::table('fund_transfers', function (Blueprint $table) {
            $table->foreign('resolution_id')->references('id')->on('resolutions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('construction_dues', fn (Blueprint $table) => $table->dropForeign(['resolution_id']));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropForeign(['resolution_id']));
        Schema::table('fund_transfers', fn (Blueprint $table) => $table->dropForeign(['resolution_id']));
    }
};
