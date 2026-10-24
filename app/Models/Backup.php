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

    //What --if-stale goes by
    public static function isStale(): bool
    {
        return self::noSuccessWithin((int) config('backups.stale_after_hours'));
    }

    public static function noSuccessWithin(int $hours): bool
    {
        $last = self::lastSuccess();

        return $last === null || $last->created_at->lt(now()->subHours($hours));
    }

    //The flag the UI reads: null when all is well, otherwise why it is not
    public static function attention(): ?string
    {
        $latest = self::where('status', '!=', 'running')->latest('created_at')->latest('id')->first();

        if ($latest !== null && $latest->status === 'failed') {
            return 'The last backup failed: '.$latest->error;
        }
        if (self::noSuccessWithin((int) config('backups.attention_after_hours'))) {
            $last = self::lastSuccess();

            return $last === null
                ? 'No backup has succeeded yet.'
                : 'The last successful backup is from '.$last->created_at->diffForHumans().'.';
        }

        return null;
    }

    //Backups are site-wide (no user_id): one named account sees them, Guest never does
    public static function isOwner(?User $user): bool
    {
        $owner = (string) config('backups.owner');

        return $user !== null && $owner !== '' && ! $user->isPreview() && $user->username === $owner;
    }

    //The 8 hex characters before the extension: what the delete dialog asks to be typed
    public function code(): string
    {
        return preg_match('/-([0-9a-f]{8})\.sql\.zst\.age$/', $this->filename, $m) ? $m[1] : (string) $this->id;
    }
}
