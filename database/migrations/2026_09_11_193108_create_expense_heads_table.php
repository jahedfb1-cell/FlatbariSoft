<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_heads', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_bn');
            $table->string('name_en')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('expense_heads')->nullOnDelete();
            // Which chart_of_accounts EXPENSE line this head posts to when
            // an expense against it is approved (e.g. "Staff Salary"
            // expense head -> account 5100).
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_heads');
    }
};
