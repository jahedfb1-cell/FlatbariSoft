<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('basic', 15, 4);
            $table->decimal('overtime', 15, 4)->default(0);
            $table->decimal('bonus', 15, 4)->default(0);
            $table->decimal('advance_deduction', 15, 4)->default(0);
            $table->decimal('net_amount', 15, 4);
            $table->date('paid_date')->nullable();
            $table->enum('treasury_medium', ['BANK', 'CASH'])->nullable();
            $table->enum('status', ['PENDING', 'APPROVED'])->default('PENDING');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['staff_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
