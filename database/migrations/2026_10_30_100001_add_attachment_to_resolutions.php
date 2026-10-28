<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A resolution can be typed text, a PDF, a photo of the signed minutes - or text with a file. So the
     * description becomes optional (the request still insists on one of the two) and the file is recorded here.
     */
    public function up(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->string('attachment_path')->nullable()->after('decision');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime', 100)->nullable()->after('attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime']);
        });
    }
};
