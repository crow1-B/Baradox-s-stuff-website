<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['filename', 'size', 'sha256', 'trigger', 'status', 'duration_ms', 'error'])]
class Backup extends Model
{
    public static function lastSuccess(): ?self
    {
        return self::where('status', 'succeeded')->latest('created_at')->latest('id')->first();
    }

    public static function isStale(): bool
    {
        $last = self::lastSuccess();

        return $last === null
            || $last->created_at->lt(now()->subHours((int) config('backups.stale_after_hours')));
    }

    //The flag the UI reads: null when all is well, otherwise why it is not
    public static function attention(): ?string
    {
        $latest = self::where('status', '!=', 'running')->latest('created_at')->latest('id')->first();

        if ($latest !== null && $latest->status === 'failed') {
            return 'The last backup failed: '.$latest->error;
        }
        if (self::isStale()) {
            $last = self::lastSuccess();

            return $last === null
                ? 'No backup has succeeded yet.'
                : 'The last successful backup is from '.$last->created_at->diffForHumans().'.';
        }

        return null;
    }
}
