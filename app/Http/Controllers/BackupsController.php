<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Support\Backups\BackupFiles;
use App\Support\Backups\BackupSigner;
use App\Support\Backups\BackupVerifier;
use App\Support\Backups\MediaCoverage;
use App\Support\Backups\VerificationFailed;
use App\Support\Backups\WebRun;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

// A page over the Phase 1 engine (docs/backups.md). Every route is behind backups.owner, so
// nothing here checks who is asking. Restore is deliberately not here: it needs the private age
// identity, which never lives on this machine, and a TTY for its typed confirmation.
class BackupsController extends Controller
{
    // One "Back up now" per this many seconds per account.
    public const RUN_DECAY_SECONDS = 10;

    public const PARTS = ['age' => '', 'manifest' => '.manifest', 'sig' => '.sig'];

    public function showBackupsPage(Request $request)
    {
        $backups = Backup::orderByDesc('created_at')->orderByDesc('id')->get();
        $lastSuccess = Backup::lastSuccess();
        $lastScheduled = Backup::where('trigger', 'scheduled')->where('status', '!=', 'running')
            ->latest('created_at')->latest('id')->first();

        $checks = Cache::many($backups->map(fn ($backup) => self::checkKey($backup))->all());
        $verified = $backups->mapWithKeys(fn ($backup) => [$backup->id => $checks[self::checkKey($backup)] ?? null]);

        // One listing of the folder, not a stat per row. null: the folder itself can't be read.
        $dir = BackupFiles::dir();
        $onDisk = is_dir($dir) && is_readable($dir) ? array_flip(scandir($dir) ?: []) : null;

        $ticket = $request->session()->get('backups.ticket');
        $pending = WebRun::isTicket($ticket) && ! WebRun::isFinished(WebRun::state($ticket));
        $outcome = $pending ? null : $this->settle($request);

        return view('backups', [
            'backups' => $backups,
            'lastSuccess' => $lastSuccess,
            'lastScheduled' => $lastScheduled,
            'attention' => Backup::attention(),
            'otherRun' => Backup::where('status', 'running')->exists(),
            'pending' => $pending,
            'outcome' => $outcome,
            'verified' => $verified,
            'onDisk' => $onDisk,
            'media' => MediaCoverage::summary(),
            'fingerprint' => $this->fingerprint(),
        ]);
    }

    public function runBackup(Request $request)
    {
        $user = Auth::user();
        $limit = 'backups-run:'.$user->id;
        if (RateLimiter::tooManyAttempts($limit, 1)) {
            return back()->withErrors(['run' => 'Wait '.RateLimiter::availableIn($limit).' s before starting another backup.']);
        }
        RateLimiter::hit($limit, self::RUN_DECAY_SECONDS);

        // The same flock backup:run takes. Held means a scheduled run or a restore is going.
        $lock = BackupFiles::lock();
        if ($lock === null) {
            return back()->withErrors(['run' => 'Another backup or a restore is running. Try again when it has finished.']);
        }
        BackupFiles::unlock($lock);

        $ticket = WebRun::newTicket();
        if (! WebRun::claim($ticket)) {
            return back()->withErrors(['run' => 'A backup started from this page is still running.']);
        }

        try {
            app(WebRun::class)->launch($ticket);
        } catch (Throwable $e) {
            WebRun::release($ticket);
            Log::channel('backups')->error("web: {$user->username} could not start a backup: ".$e->getMessage());

            return back()->withErrors(['run' => 'The backup could not start: '.$e->getMessage()]);
        }

        Log::channel('backups')->info("web: backup requested by {$user->username}");
        $request->session()->put('backups.ticket', $ticket);

        return redirect()->route('backups');
    }

    //Polled by backups.js while a run from this page is going; never a visit
    public function runStatus(Request $request)
    {
        $ticket = $request->session()->get('backups.ticket');
        if (! WebRun::isTicket($ticket)) {
            return response()->json(['state' => 'idle']);
        }

        $state = WebRun::state($ticket);
        if (! WebRun::isFinished($state)) {
            return response()->json(['state' => $state['state']]);
        }

        $outcome = $this->settle($request);
        $request->session()->flash($outcome['ok'] ? 'success' : 'bk_failed', $outcome['message']);

        return response()->json(['state' => 'finished']);
    }

    public function verifyBackup(Request $request, Backup $backup)
    {
        try {
            $manifest = BackupVerifier::verifyRow($backup);
            $check = ['ok' => true, 'code' => 0, 'message' => 'Signature valid, file unchanged. Encrypted to '.implode(', ', $manifest['recipients']).'.'];
        } catch (VerificationFailed $e) {
            $check = ['ok' => false, 'code' => $e->getCode(), 'message' => $e->getMessage()];
        }
        $check['at'] = now()->getTimestamp();
        Cache::forever(self::checkKey($backup), $check);

        if ($request->expectsJson()) {
            return response()->json($check + ['label' => self::checkLabel($check), 'when' => self::checkWhen($check)]);
        }

        return $check['ok']
            ? back()->with('success', "Backup #{$backup->id}: ".$check['message'])
            : back()->withErrors(['verify' => "Backup #{$backup->id}: ".$check['message']]);
    }

    public function downloadBackup(Backup $backup, string $part)
    {
        if ($backup->status !== 'succeeded') {
            abort(404);
        }
        $path = BackupFiles::path($backup->filename).self::PARTS[$part];
        if (! is_file($path) || ! is_readable($path)) {
            abort(404);
        }

        Log::channel('backups')->info('web: '.Auth::user()->username.' downloaded '.basename($path));

        // As-is: the encrypted bytes and the signed sidecars exactly as they are on disk.
        return response()->download($path, basename($path), ['Content-Type' => 'application/octet-stream']);
    }

    public function destroyBackup(Request $request, Backup $backup)
    {
        if ($backup->trigger === 'prerestore') {
            return back()->withErrors(['delete' => 'Pre-restore backups are never deleted: each one is the state from just before a restore.']);
        }
        if ($backup->status === 'running') {
            return back()->withErrors(['delete' => "Backup #{$backup->id} is still running."]);
        }
        if (trim((string) $request->input('confirm')) !== $backup->code()) {
            return back()->withErrors(['delete' => "Type {$backup->code()} to delete backup #{$backup->id}."]);
        }

        // Under the same flock as runs and retention, so the newest success can't change underneath.
        $lock = BackupFiles::lock();
        if ($lock === null) {
            return back()->withErrors(['delete' => 'A backup or a restore is running. Try again when it has finished.']);
        }

        try {
            if ($backup->id === Backup::lastSuccess()?->id) {
                return back()->withErrors(['delete' => "Backup #{$backup->id} is the newest successful backup, so it can't be deleted."]);
            }

            $kept = BackupFiles::delete($backup->filename);
            if ($kept !== []) {
                Log::channel('backups')->error('web: '.Auth::user()->username.' could not delete '.implode(', ', $kept).'; its row is kept');

                return back()->withErrors(['delete' => 'Could not delete '.implode(', ', array_map('basename', $kept)).'. The entry is kept.']);
            }

            $backup->delete();
            Cache::forget(self::checkKey($backup));
            Log::channel('backups')->info('web: '.Auth::user()->username." deleted {$backup->filename} ({$backup->trigger}, {$backup->status})");
        } finally {
            BackupFiles::unlock($lock);
        }

        return back()->with('success', "Backup #{$backup->id} deleted.");
    }

    public static function checkKey(Backup $backup): string
    {
        return "backups:verify:{$backup->id}:{$backup->sha256}";
    }

    public static function checkLabel(?array $check): string
    {
        return match (true) {
            $check === null => 'Not checked',
            $check['ok'] => 'Signature valid',
            $check['code'] === VerificationFailed::MISSING => 'Files missing',
            $check['code'] === VerificationFailed::CONFIG => 'No verification key',
            $check['code'] === VerificationFailed::SIGNATURE => 'Bad signature',
            default => 'File changed',
        };
    }

    public static function checkWhen(array $check): string
    {
        return 'checked '.Carbon::createFromTimestamp($check['at'])->diffForHumans();
    }

    //A finished ticket from this session, turned into the sentence the page shows once
    /** @return array{ok: bool, message: string}|null */
    private function settle(Request $request): ?array
    {
        $ticket = $request->session()->pull('backups.ticket');
        if (! WebRun::isTicket($ticket)) {
            return null;
        }
        $state = WebRun::state($ticket);
        $backup = isset($state['backup_id']) ? Backup::find($state['backup_id']) : null;

        return match (true) {
            $backup?->status === 'succeeded' => ['ok' => true, 'message' => "Backup #{$backup->id} finished: ".self::bytes((int) $backup->size).' in '.self::duration($backup->duration_ms).'.'],
            $backup !== null => ['ok' => false, 'message' => "Backup #{$backup->id} failed: {$backup->error}"],
            ($state['state'] ?? null) === 'busy' => ['ok' => false, 'message' => 'The backup did not run: another backup or a restore was already running.'],
            ($state['state'] ?? null) === 'error' => ['ok' => false, 'message' => 'The backup could not run: '.$state['error']],
            default => ['ok' => false, 'message' => 'The backup process never reported back. See storage/logs/backups.log.'],
        };
    }

    private function fingerprint(): ?string
    {
        try {
            return BackupSigner::fingerprint((string) config('backups.verify_key'));
        } catch (Throwable) {
            return null;
        }
    }

    public static function bytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $unit) {
            if ($bytes < 1024 ** ($i + 1) || $unit === 'GB') {
                return $i === 0 ? "$bytes B" : number_format($bytes / 1024 ** $i, $bytes < 10 * 1024 ** $i ? 1 : 0).' '.$unit;
            }
        }
    }

    public static function duration(?int $ms): string
    {
        return match (true) {
            $ms === null => '—',
            $ms < 1000 => "$ms ms",
            $ms < 60000 => number_format($ms / 1000, 1).' s',
            default => intdiv($ms, 60000).' min '.intdiv($ms % 60000, 1000).' s',
        };
    }
}
