<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_transfers', function (Blueprint $table) {
            $table->id();
            $table->enum('from_fund', ['OPERATING', 'GAS', 'CAPITAL', 'SINKING']);
            $table->enum('to_fund', ['OPERATING', 'GAS', 'CAPITAL', 'SINKING']);
            $table->decimal('amount', 15, 4);
            $table->string('reason');
            // References `resolutions` (Phase 2.3) — FK deferred, see expenses.
            $table->unsignedBigInteger('resolution_id')->nullable();
            $table->date('transfer_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_transfers');
    }
};
