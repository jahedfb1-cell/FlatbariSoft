<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('narration');
            // July-June (BR-02) — computed at post time, never re-derived
            // from entry_date later, so a mid-year setting change can't
            // silently reclassify old entries into a different year.
            $table->string('fiscal_year', 9);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['POSTED', 'VOID'])->default('POSTED');
            $table->timestamps();

            $table->index('fiscal_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
