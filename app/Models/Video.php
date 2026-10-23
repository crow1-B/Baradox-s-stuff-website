<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'title', 'description', 'filename', 'duration', 'slug', 'protection', 'key_hash'])]
class Video extends Model
{
    protected function casts(): array
    {
        return ['duration' => 'integer'];
    }

    public function albums()
    {
        return $this->belongsToMany(\App\Models\VideoAlbum::class, 'video_album', 'video_id', 'video_album_id');
    }

    public function isProtected(): bool
    {
        return $this->protection !== 'none';
    }

    // Same reason as Photo::thumbnailUrl(): a gated video's poster must not share a URL with
    // the plain poster a browser may still hold.
    public function posterUrl(): string
    {
        return route('video.poster', [
            'video' => $this->id,
            'v' => $this->protection[0] . ($this->updated_at?->getTimestamp() ?? 0),
        ]);
    }
}
