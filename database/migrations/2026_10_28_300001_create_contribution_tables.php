<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The kinds of money that make up the building's construction cost (subscription, gas line, ...).
        Schema::create('contribution_heads', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_bn');
            $table->string('name_en');
            // Whether money collected under this head counts toward each flat's construction-cost target.
            $table->boolean('counts_toward_target')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One row: the target every flat owes unless it has its own (editable, never hard-coded).
        Schema::create('contribution_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('default_target', 15, 4)->default(0);
            $table->timestamps();
        });

        // A flat whose target differs from the default (e.g. a flat held for sale).
        Schema::create('flat_contribution_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->unique()->constrained('flats')->restrictOnDelete();
            $table->decimal('target_amount', 15, 4);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Append-only sub-ledger of every receipt. A mistake is undone with a reversal row (negative amount),
        // never by editing or deleting. "Opening" rows carry money collected before this system: they only
        // move the flat's balance and never touch cash, bank or the journal.
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained('flats')->restrictOnDelete();
            $table->foreignId('contribution_head_id')->constrained('contribution_heads')->restrictOnDelete();
            $table->decimal('amount', 15, 4);   // negative only on a reversal row
            $table->date('received_on');
            $table->enum('method', ['CASH', 'BANK', 'BKASH', 'NAGAD', 'OPENING']);
            $table->string('reference_no')->nullable();
            $table->string('receipt_no')->nullable()->unique();
            $table->foreignId('reverses_id')->nullable()->constrained('contributions')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['flat_id', 'contribution_head_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
        Schema::dropIfExists('flat_contribution_targets');
        Schema::dropIfExists('contribution_settings');
        Schema::dropIfExists('contribution_heads');
    }
};
