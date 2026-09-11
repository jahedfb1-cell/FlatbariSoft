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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Nullable, plain columns for now — no FK constraint yet because
            // `persons` (Phase 0.5) and `staff` (Phase 0.7) don't exist yet.
            // The FK constraint itself is added by a migration in each of
            // those phases once the referenced table is created.
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();

            $table->string('country_code', 5)->default('+880');
            $table->string('mobile', 20)->unique();
            $table->string('password');

            // Denormalized "primary role" for fast display/filtering.
            // Access control itself is enforced via spatie/laravel-permission
            // (model_has_roles / model_has_permissions) — this column never
            // gates a permission check, it only labels the account.
            $table->enum('role', ['super_admin', 'admin', 'manager', 'flat_owner', 'tenant']);

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamp('last_login')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('person_id');
            $table->index('staff_id');
        });

        // No password_reset_tokens table: auth is mobile + OTP (Phase 0.4),
        // there is no email-based "forgot password" flow to support.

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
