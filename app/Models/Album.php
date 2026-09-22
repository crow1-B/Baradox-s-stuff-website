<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'name', 'is_system'])]
class Album extends Model
{
    public function photos()
    {
        return $this->belongsToMany(Photo::class, 'album_photo');
    }
}
