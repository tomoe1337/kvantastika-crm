<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('title');
            $table->string('status', 32)->index();
            $table->decimal('amount', 12, 2);
            $table->timestampTz('external_updated_at')->nullable();
            $table->unsignedBigInteger('external_version')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
