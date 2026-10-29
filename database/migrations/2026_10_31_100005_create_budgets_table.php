<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The yearly budget, one amount per expense head per fiscal year (July-June). Actual spending is always derived from the expenses. */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');   // the year it starts in: 2026 = July 2026 - June 2027
            $table->foreignId('expense_head_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['fiscal_year', 'expense_head_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
