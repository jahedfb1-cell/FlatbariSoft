<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Finishing a complaint records what was done and who finished it, so the office (and the resident) can
     * see how it ended instead of just a status word.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->text('resolution_note')->nullable()->after('assigned_to');
            $table->foreignId('resolved_by')->nullable()->after('resolution_note')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn('resolution_note');
        });
    }
};
