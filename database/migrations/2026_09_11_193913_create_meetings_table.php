<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('meeting_date');
            $table->string('venue')->nullable();
            $table->text('agenda')->nullable();
            $table->text('minutes')->nullable();
            $table->enum('status', ['SCHEDULED', 'HELD', 'CANCELLED'])->default('SCHEDULED');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
