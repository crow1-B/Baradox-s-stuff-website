<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'title', 'path', 'artist_name', 'duration', 'playtime', 'song_date', 'favorite'])]
class Track extends Model
{
    public function durationForHumans(): string
    {
        if ($this->duration === null) {
            return '';
        }

        return sprintf('%d:%02d', intdiv((int) $this->duration, 60), (int) $this->duration % 60);
    }

    public function playerPayload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist' => $this->artist_name,
            'duration' => $this->duration,
            'src' => route('music.stream', $this),
        ];
    }
}
