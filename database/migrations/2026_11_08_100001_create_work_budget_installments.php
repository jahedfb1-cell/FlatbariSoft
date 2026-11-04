<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The collection schedule of a work: dates, each with the percentage of every flat's share that falls due then.
 * What a flat owes on each date, and whether it is late, is always derived from its share and what it has paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_budget_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_budget_id')->constrained('work_budgets')->cascadeOnDelete();
            $table->unsignedTinyInteger('seq');
            $table->date('due_date');
            $table->decimal('percent', 7, 4);
            $table->timestamps();
            $table->unique(['work_budget_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_budget_installments');
    }
};
