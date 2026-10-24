<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_entry_photo', function (Blueprint $table) {
            $table->foreignId('diary_entry_id')->constrained('diary_entries')->cascadeOnDelete();
            // Cascade on the photo side is the pivot's version of the old nullOnDelete:
            // deleting a photo detaches it and never touches the entry.
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->primary(['diary_entry_id', 'photo_id']);
        });

        DB::table('diary_entry_photo')->insertUsing(
            ['diary_entry_id', 'photo_id'],
            DB::table('diary_entries')->whereNotNull('photo_id')->select('id', 'photo_id'),
        );

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photo_id');
        });
    }

    // Lossy by necessity: the old column holds one photo, so an entry with several keeps
    // the one with the lowest id.
    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->foreignId('photo_id')->nullable()->after('algorithm_hash')->constrained('photos')->nullOnDelete();
        });

        $first = DB::table('diary_entry_photo')
            ->select('diary_entry_id', DB::raw('MIN(photo_id) as photo_id'))
            ->groupBy('diary_entry_id')
            ->get();

        foreach ($first as $row) {
            DB::table('diary_entries')->where('id', $row->diary_entry_id)->update(['photo_id' => $row->photo_id]);
        }

        Schema::dropIfExists('diary_entry_photo');
    }
};
