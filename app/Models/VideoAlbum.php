<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'name', 'is_system'])]
class VideoAlbum extends Model
{
    public function videos()
    {
        return $this->belongsToMany(Video::class, 'video_album', 'video_album_id', 'video_id');
    }
}
