<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per track per day, bumped by the player after real listening. Bucketed by
        // day on purpose: "most played today" is the only question asked of it.
        Schema::create('track_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->date('play_date'); // local date in Asia/Baghdad, not UTC
            $table->unsignedInteger('count')->default(0);

            $table->unique(['user_id', 'track_id', 'play_date']);
            $table->index(['user_id', 'play_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_plays');
    }
};
