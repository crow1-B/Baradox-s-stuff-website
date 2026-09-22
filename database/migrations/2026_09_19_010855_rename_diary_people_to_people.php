<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_entry_person', function (Blueprint $table) {
            $table->dropForeign('diary_entry_person_diary_person_id_foreign');
            $table->dropIndex('diary_entry_person_diary_person_id_foreign');
        });

        Schema::rename('diary_people', 'people');

        Schema::table('diary_entry_person', function (Blueprint $table) {
            $table->renameColumn('diary_person_id', 'person_id');
        });

        Schema::table('diary_entry_person', function (Blueprint $table) {
            $table->foreign('person_id')->references('id')->on('people')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('diary_entry_person', function (Blueprint $table) {
            $table->dropForeign('diary_entry_person_person_id_foreign');
            $table->dropIndex('diary_entry_person_person_id_foreign');
        });

        Schema::rename('people', 'diary_people');

        Schema::table('diary_entry_person', function (Blueprint $table) {
            $table->renameColumn('person_id', 'diary_person_id');
        });

        Schema::table('diary_entry_person', function (Blueprint $table) {
            $table->foreign('diary_person_id')->references('id')->on('diary_people')->cascadeOnDelete();
        });
    }
};
