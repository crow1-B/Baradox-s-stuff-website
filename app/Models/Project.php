<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'title', 'description', 'status', 'github_link', 'root_path', 'start_date', 'finish_date', 'lines_of_code'])]
class Project extends Model
{
    public const STATUSES = ['future', 'in_work', 'done'];

    protected static function booted(): void
    {
        static::deleting(function (Project $project) {
            $project->futureUpdates()->update(['orphaned_project_title' => $project->title]);
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'finish_date' => 'date',
            'lines_of_code' => 'integer',
        ];
    }

    public function people()
    {
        return $this->belongsToMany(\App\Models\Person::class, 'project_person');
    }

    public function languages()
    {
        return $this->belongsToMany(\App\Models\Language::class, 'project_language');
    }

    public function futureUpdates()
    {
        return $this->hasMany(\App\Models\FutureUpdate::class);
    }

    public function wslPath(string $relative = ''): string
    {
        return rtrim(config('projects.wsl_root'), '/') . '/' . $this->joinRelative($relative);
    }

    public function vscodeUrl(string $relative = ''): string
    {
        $segments = array_map('rawurlencode', explode('/', ltrim($this->wslPath($relative), '/')));

        return 'vscode://vscode-remote/wsl+' . rawurlencode(config('projects.wsl_distro')) . '/' . implode('/', $segments);
    }

    private function joinRelative(string $relative): string
    {
        return trim($this->root_path . ($relative === '' ? '' : '/' . $relative), '/');
    }
}
