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
}
