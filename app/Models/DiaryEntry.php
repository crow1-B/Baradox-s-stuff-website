<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'title', 'content', 'event_date', 'mood', 'track_id', 'key_hash', 'algorithm_hash'])]
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

    // The day the entry is about: its event date, or the day it was written when it has none.
    public function day(): \Illuminate\Support\Carbon
    {
        return $this->event_date ?? $this->created_at;
    }

    // Null for a key that has since been removed from the config: it simply stops rendering.
    public function moodDetails(): ?array
    {
        return $this->mood === null ? null : config('diary.moods.' . $this->mood);
    }

    public function photos()
    {
        return $this->belongsToMany(\App\Models\Photo::class, 'diary_entry_photo', 'diary_entry_id', 'photo_id')
            ->orderBy('photos.id');
    }

    public function track()
    {
        return $this->belongsTo(\App\Models\Track::class);
    }

    public function people()
    {
        return $this->belongsToMany(\App\Models\Person::class, 'diary_entry_person', 'diary_entry_id', 'person_id');
    }
}
