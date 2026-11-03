<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A staff member's full KYC (personal, address, emergency contact, family, work history) and their papers (CV, NID, photo ...). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('spouse_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('religion', 30)->nullable();
            $table->string('alt_mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->string('emergency_relation', 50)->nullable();
            $table->string('education')->nullable();
            $table->text('experience')->nullable();
            $table->string('reference_name')->nullable();
            $table->string('reference_phone', 20)->nullable();
            $table->text('other_info')->nullable();
            $table->json('family_members')->nullable();
            $table->timestamp('kyc_updated_at')->nullable();
        });

        Schema::create('staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('type', 20); // CV, NID, PHOTO, CERTIFICATE, OTHER
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['staff_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_documents');
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn([
                'father_name', 'mother_name', 'spouse_name', 'date_of_birth', 'gender', 'marital_status', 'blood_group', 'religion',
                'alt_mobile', 'email', 'present_address', 'permanent_address', 'emergency_name', 'emergency_phone', 'emergency_relation',
                'education', 'experience', 'reference_name', 'reference_phone', 'other_info', 'family_members', 'kyc_updated_at',
            ]);
        });
    }
};
