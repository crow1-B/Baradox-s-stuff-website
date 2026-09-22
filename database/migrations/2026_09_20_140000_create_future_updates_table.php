<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('future_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('orphaned_project_title')->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_done']);
            $table->index(['user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('future_updates');
    }
};
