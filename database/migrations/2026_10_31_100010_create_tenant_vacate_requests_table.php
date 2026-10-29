<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The owner (or manager) tells the office "my tenant is leaving". The tenancy ends only when an admin accepts.
     */
    public function up(): void
    {
        Schema::create('tenant_vacate_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('end_date');
            $table->text('note')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('PENDING');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_vacate_requests');
    }
};
