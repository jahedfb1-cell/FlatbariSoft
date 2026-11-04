<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paying staff: a month's salary row is paid by an ordinary expense (so it needs approval like any payment), and an
 * advance given to a staff member is its own record that later salaries deduct from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->after('approved_by')->constrained('expenses')->nullOnDelete();
        });

        Schema::create('staff_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->decimal('amount', 15, 4);
            $table->decimal('monthly_deduction', 15, 4)->default(0);
            $table->date('given_date');
            $table->string('note')->nullable();
            $table->foreignId('expense_id')->constrained('expenses')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_advances');
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
        });
    }
};
