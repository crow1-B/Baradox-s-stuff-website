<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->unique();
            $table->unsignedBigInteger('size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->enum('trigger', ['manual', 'scheduled', 'prerestore']);
            $table->enum('status', ['running', 'succeeded', 'failed']);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
