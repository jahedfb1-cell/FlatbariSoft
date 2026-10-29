<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When the last "your bill is due" SMS went out for this bill, so the reminders can repeat at the office's interval. */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
