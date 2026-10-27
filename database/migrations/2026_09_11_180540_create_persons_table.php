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
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->string('name_bn');
            $table->string('name_en')->nullable();
            $table->string('country_code', 5)->default('+880');
            $table->string('mobile', 20);
            $table->string('alt_mobile', 20)->nullable();
            $table->string('nid')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('photo')->nullable();
            $table->enum('preferred_channel', ['SMS', 'WHATSAPP', 'EMAIL', 'APP'])->default('SMS');
            $table->timestamps();
            $table->softDeletes();

            $table->index('mobile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
