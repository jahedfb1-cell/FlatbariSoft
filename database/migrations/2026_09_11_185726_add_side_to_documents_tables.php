<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only meaningful for document_type = NID (front/back of the card) —
        // needed so the NID print page (two images, front+back, side by
        // side on the top half of an A4 sheet) knows which image is which.
        // Nullable for every other document type.
        Schema::table('owner_documents', function (Blueprint $table) {
            $table->enum('side', ['FRONT', 'BACK'])->nullable()->after('document_type');
        });

        Schema::table('tenant_documents', function (Blueprint $table) {
            $table->enum('side', ['FRONT', 'BACK'])->nullable()->after('document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_documents', function (Blueprint $table) {
            $table->dropColumn('side');
        });

        Schema::table('tenant_documents', function (Blueprint $table) {
            $table->dropColumn('side');
        });
    }
};
