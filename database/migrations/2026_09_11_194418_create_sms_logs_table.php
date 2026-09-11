<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 20);
            $table->text('message');
            $table->string('event_key')->nullable();
            $table->string('gateway')->nullable();
            $table->enum('status', ['QUEUED_PENDING_GATEWAY', 'SENT', 'FAILED'])->default('QUEUED_PENDING_GATEWAY');
            $table->decimal('cost', 10, 4)->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['mobile', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
