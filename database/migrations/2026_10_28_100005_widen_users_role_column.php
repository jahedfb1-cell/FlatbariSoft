<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Roles are data now (committee roles, custom roles), so the fixed five-value ENUM no longer fits.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->change();
        });
    }

    public function down(): void
    {
        // Intentionally left as a string: narrowing back to the old ENUM would fail on any new role.
    }
};
