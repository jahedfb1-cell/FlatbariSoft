<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Confirmed rule #9: a Sinking Fund expense of ৳25,000+ needs two
     * separate admins to approve it — the expenses table only has one
     * approved_by column (the finalizing approver), so each individual
     * approval is logged here to enforce "two distinct people", not just
     * "approved twice by the same person".
     */
    public function up(): void
    {
        Schema::create('expense_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->unique(['expense_id', 'approved_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_approvals');
    }
};
