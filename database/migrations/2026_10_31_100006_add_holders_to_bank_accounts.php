<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who operates the account: up to three account holders (name and phone each), and the one number the
     * bank sends its SMS alerts to. (The branch column already exists.)
     */
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('holder_1_name')->nullable()->after('account_title');
            $table->string('holder_1_phone', 20)->nullable()->after('holder_1_name');
            $table->string('holder_2_name')->nullable()->after('holder_1_phone');
            $table->string('holder_2_phone', 20)->nullable()->after('holder_2_name');
            $table->string('holder_3_name')->nullable()->after('holder_2_phone');
            $table->string('holder_3_phone', 20)->nullable()->after('holder_3_name');
            $table->string('sms_phone', 20)->nullable()->after('holder_3_phone');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['holder_1_name', 'holder_1_phone', 'holder_2_name', 'holder_2_phone', 'holder_3_name', 'holder_3_phone', 'sms_phone']);
        });
    }
};
