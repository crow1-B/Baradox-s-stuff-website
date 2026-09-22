<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'name'])]
class Person extends Model
{
    public static function idsFromList(int $userId, ?string $list): array
    {
        return array_map(
            fn ($name) => static::firstOrCreate(['user_id' => $userId, 'name' => $name])->id,
            static::parseList($list)
        );
    }

    public static function parseList(?string $list): array
    {
        $names = [];

        foreach (explode(',', (string) $list) as $name) {
            $name = preg_replace('/\s+/', ' ', trim($name));

            if ($name !== '' && !in_array(mb_strtolower($name), array_map('mb_strtolower', $names), true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    public function diaryEntries()
    {
        return $this->belongsToMany(\App\Models\DiaryEntry::class, 'diary_entry_person', 'person_id', 'diary_entry_id');
    }

    public function projects()
    {
        return $this->belongsToMany(\App\Models\Project::class, 'project_person');
    }
}
