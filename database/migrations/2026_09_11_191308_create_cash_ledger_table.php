<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_ledger', function (Blueprint $table) {
            $table->id();
            $table->date('txn_date');
            $table->string('particulars');
            $table->decimal('in_amount', 15, 4)->default(0);
            $table->decimal('out_amount', 15, 4)->default(0);
            $table->decimal('balance_after', 15, 4);
            $table->enum('fund_type', ['OPERATING', 'GAS', 'CAPITAL', 'SINKING']);
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_ledger');
    }
};
