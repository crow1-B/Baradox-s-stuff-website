<?php

namespace App\Support\Backups;

use App\Models\Backup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

// Keeps the newest backup of each of the last keep_daily days that have one, and the newest
// of each of the last keep_weekly ISO weeks that have one. Days and weeks without a backup
// don't count, so a machine left off for a fortnight doesn't come back to an empty folder.
// Manual and scheduled backups are pruned alike; prerestore backups never are.
class BackupRetention
{
    public static function apply(): void
    {
        $daily = (int) config('backups.keep_daily');
        $weekly = (int) config('backups.keep_weekly');
        if ($daily < 1 || $weekly < 0) {
            throw new RuntimeException('BACKUP_KEEP_DAILY must be at least 1 and BACKUP_KEEP_WEEKLY at least 0.');
        }

        $candidates = Backup::where('status', 'succeeded')
            ->whereIn('trigger', ['manual', 'scheduled'])
            ->get();

        foreach (self::select($candidates, $daily, $weekly, (string) config('backups.timezone')) as $backup) {
            $path = BackupFiles::path($backup->filename);
            $kept = [];
            foreach ([$path, BackupFiles::manifestPath($path), BackupFiles::signaturePath($path)] as $file) {
                if (file_exists($file) && ! unlink($file)) {
                    $kept[] = $file;
                }
            }

            if ($kept !== []) {
                Log::channel('backups')->error("retention could not delete ".implode(', ', $kept)."; its row is kept");

                continue;
            }

            $backup->delete();
            Log::channel('backups')->info("retention removed {$backup->filename}");
        }
    }

    /**
     * @param  Collection<int, Backup>  $backups
     * @return Collection<int, Backup> the ones to delete
     */
    public static function select(Collection $backups, int $daily, int $weekly, string $timezone): Collection
    {
        $newestFirst = $backups->sort(
            fn (Backup $a, Backup $b) => [$b->created_at->getTimestamp(), $b->id] <=> [$a->created_at->getTimestamp(), $a->id]
        );

        $days = [];
        $weeks = [];
        foreach ($newestFirst as $backup) {
            $local = $backup->created_at->copy()->setTimezone($timezone);
            $day = $local->format('Y-m-d');
            $week = $local->format('o-W');

            if (! isset($days[$day]) && count($days) < $daily) {
                $days[$day] = $backup->id;
            }
            if (! isset($weeks[$week]) && count($weeks) < $weekly) {
                $weeks[$week] = $backup->id;
            }
        }

        $keep = array_flip([...array_values($days), ...array_values($weeks)]);

        return $newestFirst->reject(fn (Backup $backup) => isset($keep[$backup->id]))->values();
    }
}
