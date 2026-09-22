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
}
