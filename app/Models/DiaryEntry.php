<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'title', 'content', 'event_date', 'key_hash', 'algorithm_hash', 'photo_id'])]
class DiaryEntry extends Model
{
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public function isLocked(): bool
    {
        return $this->key_hash !== null;
    }

    public function photo()
    {
        return $this->belongsTo(\App\Models\Photo::class);
    }

    public function people()
    {
        return $this->belongsToMany(\App\Models\Person::class, 'diary_entry_person', 'diary_entry_id', 'person_id');
    }
}
