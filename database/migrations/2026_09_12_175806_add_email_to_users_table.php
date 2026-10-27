<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner-requested second login method, alongside mobile+OTP: an
     * email+password login for admin-side accounts (committee members
     * don't always want an OTP text for every login). Nullable and
     * unique — most accounts (flat owners, tenants, staff logins created
     * via FlatOccupancyService) never get an email and keep using OTP.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('mobile');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
