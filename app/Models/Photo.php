<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'path', 'protection', 'key_hash'])]
class Photo extends Model
{
    public function albums()
    {
        return $this->belongsToMany(\App\Models\Album::class, 'album_photo');
    }

    public function isProtected(): bool
    {
        return $this->protection !== 'none';
    }

    // The URL changes whenever the protection does, so a browser can never answer a locked
    // photo's thumbnail with the plain one it cached before the lock.
    public function thumbnailUrl(): string
    {
        return route('photo.thumbnail', [
            'photo' => $this->id,
            'v' => $this->protection[0] . ($this->updated_at?->getTimestamp() ?? 0),
        ]);
    }
}
