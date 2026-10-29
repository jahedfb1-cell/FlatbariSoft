<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tenant's KYC form: where they work, who to call in an emergency, a reference, where they lived
     * before, anything else the committee wants on record, and every family member who will live in the flat.
     * Name, mobile, NID, e-mail and the permanent address stay on `persons` (a tenant is a person, with a login).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('workplace')->nullable()->after('occupation');
            $table->string('emergency_name')->nullable()->after('family_members_count');
            $table->string('emergency_phone', 20)->nullable()->after('emergency_name');
            $table->text('previous_address')->nullable()->after('emergency_phone');
            $table->string('reference_name')->nullable()->after('previous_address');
            $table->string('reference_phone', 20)->nullable()->after('reference_name');
            $table->text('other_info')->nullable()->after('reference_phone');
            $table->timestamp('kyc_submitted_at')->nullable()->after('other_info');
        });

        Schema::create('tenant_family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('relation', ['SPOUSE', 'SON', 'DAUGHTER', 'FATHER', 'MOTHER', 'BROTHER', 'SISTER', 'RELATIVE', 'OTHER'])->default('OTHER');
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('nid', 30)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('occupation')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_family_members');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['workplace', 'emergency_name', 'emergency_phone', 'previous_address', 'reference_name', 'reference_phone', 'other_info', 'kyc_submitted_at']);
        });
    }
};
