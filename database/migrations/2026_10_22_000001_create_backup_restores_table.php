<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_restores', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->char('sha256', 64)->nullable();
            $table->enum('status', ['running', 'succeeded', 'failed']);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restores');
    }
};
