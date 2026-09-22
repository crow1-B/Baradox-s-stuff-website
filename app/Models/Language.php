<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name'])]
class Language extends Model
{
    public static function idsFromList(?string $list): array
    {
        return array_map(
            fn ($name) => static::firstOrCreate(['name' => $name])->id,
            Person::parseList($list)
        );
    }

    public function projects()
    {
        return $this->belongsToMany(\App\Models\Project::class, 'project_language');
    }
}
