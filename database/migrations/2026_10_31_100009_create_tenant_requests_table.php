<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A flat owner (or the building manager) tells the office "this person is renting my flat". Nothing happens
     * to the flat until an admin accepts: the request becomes the real tenancy and the tenant's login at that moment.
     */
    public function up(): void
    {
        Schema::create('tenant_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name_bn');
            $table->string('name_en')->nullable();
            $table->string('mobile', 20);
            $table->string('nid', 30)->nullable();
            $table->date('start_date');
            $table->decimal('monthly_rent', 15, 4)->nullable();
            $table->decimal('security_deposit', 15, 4)->nullable();
            $table->string('occupation')->nullable();
            $table->unsignedTinyInteger('family_members_count')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('PENDING');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'flat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_requests');
    }
};
