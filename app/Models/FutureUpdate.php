<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'content', 'project_id', 'orphaned_project_title', 'is_done'])]
class FutureUpdate extends Model
{
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(\App\Models\Project::class);
    }

    //True when the project this update was written about has been deleted
    public function isOrphaned(): bool
    {
        return $this->project_id === null && $this->orphaned_project_title !== null;
    }
}
