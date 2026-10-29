<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The gate book: who came to which flat, when they went in and when they left. */
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->restrictOnDelete();
            $table->string('visitor_name');
            $table->string('mobile', 20)->nullable();
            $table->enum('purpose', ['GUEST', 'DELIVERY', 'SERVICE', 'OTHER'])->default('GUEST');
            $table->string('vehicle_no', 30)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('in_at');
            $table->timestamp('out_at')->nullable();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['flat_id', 'in_at']);
            $table->index('out_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
