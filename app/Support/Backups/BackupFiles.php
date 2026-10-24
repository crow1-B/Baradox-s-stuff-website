<?php

namespace App\Support\Backups;

use RuntimeException;

// Where a backup's three files live and the cross-container lock around backups and restores.
// <name>.sql.zst.age is the data, <name>.sql.zst.age.manifest the signed description of it,
// <name>.sql.zst.age.sig the raw 64-byte Ed25519 signature over the manifest's exact bytes.
class BackupFiles
{
    public const NAME_PATTERN = '/^baradox-db-\d{8}T\d{6}Z-(manual|scheduled|prerestore)-[0-9a-f]{8}\.sql\.zst\.age$/';

    public static function dir(): string
    {
        return rtrim((string) config('backups.path'), '/');
    }

    public static function newName(string $trigger): string
    {
        return 'baradox-db-'.now('UTC')->format('Ymd\THis\Z')."-$trigger-".bin2hex(random_bytes(4)).'.sql.zst.age';
    }

    public static function path(string $name): string
    {
        return self::dir().'/'.$name;
    }

    public static function manifestPath(string $dataPath): string
    {
        return $dataPath.'.manifest';
    }

    public static function signaturePath(string $dataPath): string
    {
        return $dataPath.'.sig';
    }

    //Data, manifest, signature. Returns the ones that could not be deleted (missing ones count as gone).
    /** @return list<string> */
    public static function delete(string $name): array
    {
        $path = self::path($name);
        $kept = [];
        foreach ([$path, self::manifestPath($path), self::signaturePath($path)] as $file) {
            if (file_exists($file) && ! unlink($file)) {
                $kept[] = $file;
            }
        }

        return $kept;
    }

    public static function ensureDir(): void
    {
        $dir = self::dir();
        if (! is_dir($dir) || ! is_writable($dir)) {
            $host = config('backups.host_path');
            throw new RuntimeException("The backups folder $dir is missing or not writable".($host ? " (host side: $host, BACKUP_HOST_PATH in .env)" : '').'.');
        }
    }

    //flock, not a cache lock: it spans the app and scheduler containers (same bind mount),
    //disappears with a killed process, and survives a restore rewriting the cache table
    /** @return resource|null */
    public static function lock(bool $wait = false)
    {
        $handle = fopen(storage_path('framework/backups.lock'), 'c');
        if ($handle === false) {
            throw new RuntimeException('Could not open the backups lock file.');
        }
        if (! flock($handle, $wait ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /** @param resource $handle */
    public static function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
