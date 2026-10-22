<?php

namespace App\Support\Backups;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

// mysqldump → zstd → age, straight into <name>.partial: plaintext only ever exists in pipes.
// The manifest and signature are written before the data file is renamed into place, so a
// final .age file always has its sidecars next to it.
class BackupRunner
{
    /** Returns null when another backup or a restore holds the lock. */
    public static function run(string $trigger): ?Backup
    {
        $lock = BackupFiles::lock();
        if ($lock === null) {
            return null;
        }

        try {
            return self::runLocked($trigger);
        } finally {
            BackupFiles::unlock($lock);
        }
    }

    //The caller must hold BackupFiles::lock()
    public static function runLocked(string $trigger): Backup
    {
        $started = hrtime(true);
        self::recoverInterrupted();

        $name = BackupFiles::newName($trigger);
        $backup = Backup::create(['filename' => $name, 'trigger' => $trigger, 'status' => 'running']);
        $final = BackupFiles::path($name);
        $partials = [$final.'.partial', BackupFiles::manifestPath($final).'.partial', BackupFiles::signaturePath($final).'.partial'];

        try {
            BackupFiles::ensureDir();
            $recipients = self::recipients();
            $signingKey = (string) config('backups.signing_key');
            $verifyKey = (string) config('backups.verify_key');
            if (! BackupSigner::verify('pair-check', BackupSigner::sign('pair-check', $signingKey), $verifyKey)) {
                throw new RuntimeException("The signing key $signingKey does not match the verification key $verifyKey.");
            }

            $db = config('database.connections.'.config('database.default'));
            $dump = [
                'mysqldump',
                '--host='.$db['host'],
                '--port='.$db['port'],
                '--user='.config('backups.db_username'),
                '--single-transaction', '--routines', '--triggers', '--events', '--hex-blob',
            ];
            foreach (config('backups.ignore_tables') as $table) {
                $dump[] = '--ignore-table='.$db['database'].'.'.$table;
            }
            array_push($dump, '--databases', $db['database']);

            $age = ['age', '--encrypt'];
            foreach ($recipients as $recipient) {
                array_push($age, '--recipient', $recipient);
            }
            array_push($age, '--output', $partials[0]);

            Pipeline::run([
                ['argv' => $dump, 'env' => ['MYSQL_PWD' => (string) config('backups.db_password')]],
                ['argv' => ['zstd', '--quiet', '--stdout']],
                ['argv' => $age],
            ]);

            clearstatcache();
            $size = filesize($partials[0]);
            $sha256 = hash_file('sha256', $partials[0]);
            if ($size === false || $size === 0 || $sha256 === false) {
                throw new RuntimeException('age reported success but wrote no file.');
            }

            $manifest = json_encode([
                'format' => 1,
                'file' => $name,
                'size' => $size,
                'sha256' => $sha256,
                'created_at' => $backup->created_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'trigger' => $trigger,
                'database' => $db['database'],
                'migration' => DB::table('migrations')->orderByDesc('id')->value('migration'),
                'compression' => 'zstd',
                'recipients' => $recipients,
                'signing_key_sha256' => BackupSigner::fingerprint($verifyKey),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

            self::write($partials[1], $manifest);
            self::write($partials[2], BackupSigner::sign($manifest, $signingKey));
            self::rename($partials[1], BackupFiles::manifestPath($final));
            self::rename($partials[2], BackupFiles::signaturePath($final));
            self::rename($partials[0], $final);

            $backup->update([
                'status' => 'succeeded',
                'size' => $size,
                'sha256' => $sha256,
                'duration_ms' => self::elapsed($started),
            ]);
        } catch (Throwable $e) {
            foreach ($partials as $partial) {
                @unlink($partial);
            }
            $backup->update(['status' => 'failed', 'error' => $e->getMessage(), 'duration_ms' => self::elapsed($started)]);
            Log::channel('backups')->error("backup $name ($trigger) failed: ".$e->getMessage());

            return $backup;
        }

        Log::channel('backups')->info("backup $name ($trigger) succeeded: $size bytes, sha256 $sha256, {$backup->duration_ms} ms");

        try {
            BackupRetention::apply();
        } catch (Throwable $e) {
            Log::channel('backups')->error('retention failed: '.$e->getMessage());
        }

        return $backup;
    }

    /** @return list<string> */
    public static function recipients(): array
    {
        $recipients = config('backups.recipients');
        if ($recipients === []) {
            throw new RuntimeException('No age recipient is configured (BACKUP_AGE_RECIPIENTS in .env); refusing to back up.');
        }
        foreach ($recipients as $recipient) {
            if (! preg_match('/^age1[02-9ac-hj-np-z]{58}$/', $recipient)) {
                throw new RuntimeException("\"$recipient\" is not an age X25519 recipient (age1…).");
            }
        }

        return $recipients;
    }

    //A process killed mid-backup leaves a running row and .partial files behind
    private static function recoverInterrupted(): void
    {
        $count = Backup::where('status', 'running')
            ->update(['status' => 'failed', 'error' => 'Interrupted: the process ended before the backup finished.']);
        if ($count > 0) {
            Log::channel('backups')->warning("marked $count interrupted backup(s) as failed");
        }

        if (is_dir(BackupFiles::dir())) {
            foreach (glob(BackupFiles::dir().'/*.partial') ?: [] as $partial) {
                @unlink($partial);
            }
        }
    }

    private static function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) !== strlen($contents)) {
            throw new RuntimeException("Could not write $path.");
        }
    }

    private static function rename(string $from, string $to): void
    {
        if (! rename($from, $to)) {
            throw new RuntimeException("Could not rename $from to $to.");
        }
    }

    private static function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1e6);
    }
}
