<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The papers behind an expense - the voucher, the shop's bill, a quotation: photos or PDFs, as many as needed -
     * and the written note each approver leaves when they approve it.
     */
    public function up(): void
    {
        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('expense_id');
        });

        Schema::table('expense_approvals', function (Blueprint $table) {
            $table->text('note')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('expense_approvals', function (Blueprint $table) {
            $table->dropColumn('note');
        });
        Schema::dropIfExists('expense_attachments');
    }
};
