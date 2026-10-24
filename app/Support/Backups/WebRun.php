<?php

namespace App\Support\Backups;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

// "Back up now" on the Backups page. The request never runs the pipeline itself: it starts
// backup:run --ticket=<t> as a detached process, so no php -S worker waits on mysqldump, and
// the page polls the ticket. The flock in BackupFiles is still what keeps two runs apart; the
// cache lock only covers the gap between the click and the process taking the flock.
class WebRun
{
    public const LOCK = 'backups:web-run';

    // Long enough for any real run; the process releases it as soon as it ends.
    public const LOCK_SECONDS = 900;

    // A ticket still "starting" after this never reached backup:run.
    public const LOST_AFTER_SECONDS = 30;

    public static function newTicket(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function isTicket(mixed $ticket): bool
    {
        return is_string($ticket) && preg_match('/^[0-9a-f]{32}$/', $ticket) === 1;
    }

    /** Takes the start lock for this ticket; false when a run from the page is still going. */
    public static function claim(string $ticket): bool
    {
        if (! Cache::lock(self::LOCK, self::LOCK_SECONDS, $ticket)->get()) {
            return false;
        }
        self::mark($ticket, 'starting');

        return true;
    }

    public static function release(string $ticket): void
    {
        Cache::restoreLock(self::LOCK, $ticket)->release();
    }

    /** @param  array<string, mixed>  $extra */
    public static function mark(string $ticket, string $state, array $extra = []): void
    {
        Cache::put("backups:ticket:$ticket", ['state' => $state, 'at' => now()->getTimestamp()] + $extra, 3600);
    }

    /** @return array{state: string, at: int, backup_id?: int, error?: string}|null */
    public static function state(string $ticket): ?array
    {
        $state = Cache::get("backups:ticket:$ticket");
        if (is_array($state) && $state['state'] === 'starting' && $state['at'] < now()->getTimestamp() - self::LOST_AFTER_SECONDS) {
            self::release($ticket);
            self::mark($ticket, 'lost');

            return Cache::get("backups:ticket:$ticket");
        }

        return is_array($state) ? $state : null;
    }

    public static function isFinished(?array $state): bool
    {
        return $state === null || ! in_array($state['state'], ['starting', 'running'], true);
    }

    //setsid -f forks into a new session and returns at once, so the child outlives the request
    //and the worker that served it. Its outcome goes to the ticket and backups.log, not stdout.
    public function launch(string $ticket): void
    {
        $process = proc_open(
            ['setsid', '-f', PHP_BINARY, base_path('artisan'), 'backup:run', '--ticket='.$ticket],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            base_path(),
        );
        if ($process === false) {
            throw new RuntimeException('Could not start the backup process.');
        }
        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException("Could not start the backup process (setsid exited $code).");
        }
    }
}
