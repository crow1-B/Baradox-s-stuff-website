<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Support\Backups\BackupRunner;
use App\Support\Backups\WebRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class BackupRun extends Command
{
    protected $signature = 'backup:run
        {--scheduled : Record the run as scheduled rather than manual}
        {--if-stale : Only back up when the last successful backup is older than 24 hours}
        {--ticket= : Set by the Backups page: where to report the outcome}';

    protected $description = 'Dump the database, compress it, encrypt it with age, sign it, and apply retention.';

    public function handle(): int
    {
        $ticket = $this->option('ticket');
        if ($ticket !== null && ! WebRun::isTicket($ticket)) {
            error('--ticket must be 32 hex characters.');

            return self::FAILURE;
        }

        if ($ticket === null) {
            return $this->backUp();
        }

        WebRun::mark($ticket, 'running');
        try {
            return $this->backUp($ticket);
        } finally {
            WebRun::release($ticket);
        }
    }

    private function backUp(?string $ticket = null): int
    {
        $trigger = $this->option('scheduled') ? 'scheduled' : 'manual';
        $ifStale = (bool) $this->option('if-stale');

        try {
            if ($ifStale && ! Backup::isStale()) {
                info('Skipped: the last successful backup is less than '.config('backups.stale_after_hours').' hours old.');

                return self::SUCCESS;
            }

            $backup = BackupRunner::run($trigger);
        } catch (Throwable $e) {
            Log::channel('backups')->error("backup ($trigger) could not run: ".$e->getMessage());
            error('The backup could not run: '.$e->getMessage());
            $ticket && WebRun::mark($ticket, 'error', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        if ($backup === null) {
            if ($ifStale) {
                info('Skipped: another backup or a restore is running.');

                return self::SUCCESS;
            }
            error('Another backup or a restore is running.');
            $ticket && WebRun::mark($ticket, 'busy');

            return self::FAILURE;
        }

        $ticket && WebRun::mark($ticket, 'done', ['backup_id' => $backup->id]);

        if ($backup->status !== 'succeeded') {
            error("Backup #{$backup->id} failed: {$backup->error}");

            return self::FAILURE;
        }

        info("Backup #{$backup->id} ({$backup->trigger}): {$backup->filename}");
        $this->line("  {$backup->size} bytes, sha256 {$backup->sha256}, {$backup->duration_ms} ms");

        return self::SUCCESS;
    }
}
