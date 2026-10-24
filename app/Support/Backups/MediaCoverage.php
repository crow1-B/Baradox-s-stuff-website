<?php

namespace App\Support\Backups;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

// What a database backup leaves out: the files on the SSD. Counted with one walk of each
// folder (over drvfs every stat is a round trip into Windows), cached for ten minutes.
class MediaCoverage
{
    /** @return array{readable: bool, files: int, bytes: int, folders: array<string, array{files: int, bytes: int}>, counted_at: int} */
    public static function summary(): array
    {
        return Cache::remember('backups:media-coverage', 600, fn () => self::count());
    }

    private static function count(): array
    {
        $root = rtrim((string) config('filesystems.disks.ssd_photos.root'), '/');
        $summary = ['readable' => is_dir($root) && is_file("$root/.baradox-stuff-media"), 'files' => 0, 'bytes' => 0, 'folders' => [], 'counted_at' => now()->getTimestamp()];
        if (! $summary['readable']) {
            return $summary;
        }

        foreach (config('backups.media_folders') as $folder) {
            $files = 0;
            $bytes = 0;
            if (is_dir("$root/$folder")) {
                try {
                    $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$folder", FilesystemIterator::SKIP_DOTS));
                    foreach ($walk as $file) {
                        if ($file->isFile()) {
                            $files++;
                            $bytes += $file->getSize();
                        }
                    }
                } catch (Throwable) {
                    $summary['readable'] = false;
                }
            }
            $summary['folders'][$folder] = ['files' => $files, 'bytes' => $bytes];
            $summary['files'] += $files;
            $summary['bytes'] += $bytes;
        }

        return $summary;
    }
}
