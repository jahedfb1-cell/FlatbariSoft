<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Collections can be taken through Rocket and Upay as well: the method becomes plain text, as on payments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->string('method', 20)->change();
        });
    }

    public function down(): void
    {
        // plain text is a superset of the old values; nothing to undo
    }
};
