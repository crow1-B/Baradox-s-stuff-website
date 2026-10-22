<?php

namespace App\Console\Commands;

use App\Models\BackupRestore as BackupRestoreRecord;
use App\Support\Backups\BackupFiles;
use App\Support\Backups\BackupRunner;
use App\Support\Backups\BackupVerifier;
use App\Support\Backups\Pipeline;
use App\Support\Backups\VerificationFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

// CLI only: there is no route, and the age identity is read from a path given here, never stored.
// Order: verify (signature + hash, before anything is decrypted) → typed confirmation →
// pre-restore backup → age -d | zstd -d | mysql into the configured database.
class BackupRestore extends Command
{
    protected $signature = 'backup:restore
        {file : A backup filename in the backups folder, or a path to one}
        {--identity= : Path to the age identity (private key) file}';

    protected $description = 'Replace the live database with a backup. Takes a pre-restore backup first.';

    public function handle(): int
    {
        if (! $this->laravel->runningInConsole()) {
            return self::FAILURE;
        }

        $file = (string) $this->argument('file');
        $path = str_contains($file, '/') ? $file : BackupFiles::path($file);
        if (! preg_match(BackupFiles::NAME_PATTERN, basename($path))) {
            error(basename($path).' is not a backup filename.');

            return VerificationFailed::CONFIG;
        }

        $identity = (string) $this->option('identity');
        if ($identity === '' || ! is_file($identity) || ! is_readable($identity)) {
            error('--identity must name a readable age identity file (the private key; it is never stored).');

            return VerificationFailed::CONFIG;
        }

        try {
            $manifest = BackupVerifier::verify($path);
        } catch (VerificationFailed $e) {
            error('Refused: '.$e->getMessage());

            return $e->getCode();
        }

        $db = config('database.connections.'.config('database.default'));
        if ($manifest['database'] !== $db['database']) {
            error("The backup is of database {$manifest['database']}; this app uses {$db['database']}. The dump selects its own database, so restoring would not land where you expect.");

            return VerificationFailed::CONFIG;
        }
        if (! Schema::hasTable('backups') || ! Schema::hasTable('backup_restores')) {
            error('The backups tables are missing. Run php artisan migrate first (on a fresh machine, migrate, then restore).');

            return VerificationFailed::CONFIG;
        }

        info('Signature and hash verified.');
        $this->line("  backup:   {$manifest['file']}");
        $this->line("  created:  {$manifest['created_at']} ({$manifest['trigger']}), migration {$manifest['migration']}");
        $this->line("  target:   database {$db['database']} on {$db['host']}:{$db['port']}");
        warning('Every table in the backup is dropped and recreated. A pre-restore backup is taken first.');

        if (! $this->input->isInteractive() || ! stream_isatty(STDIN)) {
            error('A restore needs an interactive terminal to confirm.');

            return VerificationFailed::CONFIG;
        }
        $expected = "RESTORE {$db['database']}";
        if (text("Type \"$expected\" to continue") !== $expected) {
            error('Cancelled; nothing was changed.');

            return self::FAILURE;
        }

        $lock = BackupFiles::lock();
        if ($lock === null) {
            error('A backup or another restore is running; try again when it has finished.');

            return self::FAILURE;
        }

        $started = hrtime(true);
        $record = BackupRestoreRecord::create(['filename' => $manifest['file'], 'sha256' => $manifest['sha256'], 'status' => 'running']);
        $pre = null;

        try {
            $pre = BackupRunner::runLocked('prerestore');
            if ($pre->status !== 'succeeded') {
                throw new RuntimeException("The pre-restore backup failed, so nothing was restored: {$pre->error}");
            }
            info("Pre-restore backup: {$pre->filename}");

            Pipeline::run([
                ['argv' => ['age', '--decrypt', '--identity', $identity, $path]],
                ['argv' => ['zstd', '--quiet', '--decompress', '--stdout']],
                [
                    'argv' => ['mysql', '--host='.$db['host'], '--port='.$db['port'], '--user='.config('backups.db_username')],
                    'env' => ['MYSQL_PWD' => (string) config('backups.db_password')],
                ],
            ]);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if ($pre !== null && $pre->status === 'succeeded') {
                $message .= " The database may be partly restored; {$pre->filename} holds it as it was just before.";
            }
            $record->update(['status' => 'failed', 'error' => $message, 'duration_ms' => self::elapsed($started)]);
            Log::channel('backups')->error("restore of {$manifest['file']} failed: $message");
            BackupFiles::unlock($lock);
            error($message);

            return self::FAILURE;
        }

        $record->update(['status' => 'succeeded', 'duration_ms' => self::elapsed($started)]);
        BackupFiles::unlock($lock);
        Log::channel('backups')->info("restored {$manifest['file']} (sha256 {$manifest['sha256']}) into {$db['database']}; pre-restore backup {$pre->filename}");
        info("Restored {$manifest['file']} in {$record->duration_ms} ms.");

        $restoredMigration = DB::table('migrations')->orderByDesc('id')->value('migration');
        $latest = pathinfo(collect(glob(database_path('migrations/*.php')))->sort()->last(), PATHINFO_FILENAME);
        if ($restoredMigration !== $latest) {
            warning("The restored database is at migration $restoredMigration; the code's newest is $latest. Run php artisan migrate if the backup is older.");
        }

        return self::SUCCESS;
    }

    private static function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1e6);
    }
}
