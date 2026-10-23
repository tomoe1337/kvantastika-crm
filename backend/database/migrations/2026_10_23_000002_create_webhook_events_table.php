<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
            $table->timestampTz('event_time');
            $table->string('external_id');
            $table->json('payload');
            $table->string('status', 24)->default('received');
            $table->string('outcome', 32)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'updated_at']);
            $table->index(['external_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
