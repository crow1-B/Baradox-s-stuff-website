<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            // A key from config('diary.moods'), never a free-text vocabulary.
            $table->string('mood', 16)->nullable()->after('event_date');
            // Song of the day. Deleting the track keeps the entry.
            $table->foreignId('track_id')->nullable()->after('mood')->constrained('tracks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('track_id');
            $table->dropColumn('mood');
        });
    }
};
