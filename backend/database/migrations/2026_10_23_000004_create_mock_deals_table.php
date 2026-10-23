<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_deals', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('title');
            $table->string('status', 32);
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('version');
            $table->timestampTz('external_updated_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_deals');
    }
};
