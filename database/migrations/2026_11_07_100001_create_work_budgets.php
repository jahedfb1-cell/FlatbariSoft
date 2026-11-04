<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A one-off piece of work (roof repair, painting, a new lift) with its own estimate. The money for it is asked of
 * flats in their own contribution book (each flat's share is its target there, set by hand per flat); expenses are
 * linked to the work so what was collected and what was spent can be read side by side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribution_book_id')->unique()->constrained('contribution_books')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('estimated_cost', 15, 4);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 12)->default('PLANNED'); // PLANNED, ONGOING, DONE, CANCELLED
            $table->string('split_method', 16)->default('EQUAL'); // how the shares were first worked out
            $table->foreignId('resolution_id')->nullable()->constrained('resolutions')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('work_budget_id')->nullable()->after('resolution_id')->constrained('work_budgets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_budget_id');
        });
        Schema::dropIfExists('work_budgets');
    }
};
