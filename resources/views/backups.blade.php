@extends('layouts.app')

@push('head')
    <meta name="turbo-cache-control" content="no-cache">
    @vite(['resources/css/backups.css', 'resources/js/backups.js'])
@endpush

@section('content')
@php
    use App\Http\Controllers\BackupsController as Bk;

    $tz = config('backups.timezone');
    $when = fn ($date) => $date->copy()->setTimezone($tz)->format('j M Y, H:i');
    $identity = config('backups.restore_identity');
    $plugged = 'storage/app/private/restore-identity.txt';
    $newestId = $lastSuccess?->id;
    $media = $media + ['folders' => []];
@endphp

<div class="nd-page bk" id="bk-root" data-bk-status-url="{{ route('backups.status') }}" @if ($pending) data-bk-pending @endif>
    <header class="nd-page__head">
        <div>
            <h1 class="nd-page__title">Backups</h1>
            <p class="nd-page__sub">
                Encrypted, signed copies of the database, kept in one folder on this machine.
                Restoring is done from a terminal, never from here.
            </p>
        </div>
        <div class="nd-page__actions">
            <form method="POST" action="{{ route('backups.run') }}" data-bk-run-form>
                @csrf
                <button type="submit" class="nd-btn nd-btn--primary" data-bk-run @if ($pending) disabled @endif>
                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                    Back up now
                </button>
            </form>
        </div>
    </header>

    <div class="nd-page__banners">
        @if (session('success') || ($outcome['ok'] ?? false))
            <div class="nd-banner nd-banner--success" role="status">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <p class="nd-banner__text">{{ session('success') ?? $outcome['message'] }}</p>
            </div>
        @endif

        @if (session('bk_failed') || ($outcome && ! $outcome['ok']))
            <div class="nd-banner nd-banner--error" role="alert">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <p class="nd-banner__text">{{ session('bk_failed') ?? $outcome['message'] }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="nd-banner nd-banner--error" role="alert">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <div class="nd-banner__text">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if ($pending)
            <p class="nd-note nd-note--info" role="status" data-bk-progress>
                <i class="fa-solid fa-spinner fa-spin-pulse" aria-hidden="true"></i>
                <span data-bk-progress-text>Backing up. This page updates when it has finished; you can leave it meanwhile.</span>
            </p>
        @elseif ($otherRun)
            <p class="nd-note nd-note--info" role="status">
                <i class="fa-solid fa-spinner fa-spin-pulse" aria-hidden="true"></i>
                <span>A backup started by the scheduler or a terminal is running. Reload to see it finish.</span>
            </p>
        @endif
    </div>

    {{-- ---------------------------------------------------------------- summary --}}
    <section class="bk-summary" aria-label="Status">
        <div class="nd-card bk-stat {{ $attention ? 'bk-stat--warn' : 'bk-stat--ok' }}">
            <p class="bk-stat__label">
                <i class="fa-solid {{ $attention ? 'fa-triangle-exclamation' : 'fa-circle-check' }}" aria-hidden="true"></i>
                Status
            </p>
            <p class="bk-stat__value">{{ $attention ? 'Needs attention' : 'All is well' }}</p>
            <p class="bk-stat__detail">{{ $attention ?? 'The last run succeeded, and the last success is less than '.config('backups.attention_after_hours').' hours old.' }}</p>
        </div>

        <div class="nd-card bk-stat">
            <p class="bk-stat__label"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Last scheduled run</p>
            @if ($lastScheduled)
                <p class="bk-stat__value"><time datetime="{{ $lastScheduled->created_at->toIso8601String() }}">{{ $when($lastScheduled->created_at) }}</time></p>
                <p class="bk-stat__detail">
                    {{ $lastScheduled->status === 'succeeded' ? 'Succeeded' : 'Failed' }} in {{ Bk::duration($lastScheduled->duration_ms) }}
                    · {{ $lastScheduled->created_at->diffForHumans() }}
                </p>
            @else
                <p class="bk-stat__value">None yet</p>
            @endif
            <p class="bk-stat__detail">Daily at {{ config('backups.daily_at') }} ({{ $tz }}), caught up within the hour after a missed one.</p>
        </div>

        <div class="nd-card bk-stat">
            <p class="bk-stat__label"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Last success</p>
            @if ($lastSuccess)
                <p class="bk-stat__value"><time datetime="{{ $lastSuccess->created_at->toIso8601String() }}">{{ $when($lastSuccess->created_at) }}</time></p>
                <p class="bk-stat__detail">{{ ucfirst($lastSuccess->trigger) }} · {{ Bk::bytes((int) $lastSuccess->size) }} · {{ $lastSuccess->created_at->diffForHumans() }}</p>
            @else
                <p class="bk-stat__value">None yet</p>
            @endif
        </div>
    </section>

    {{-- ---------------------------------------------------------------- the list --}}
    <section class="bk-section" aria-labelledby="bk-list-title">
        <div class="bk-section__head">
            <h2 class="bk-section__title" id="bk-list-title">Every backup</h2>
            <p class="nd-count">{{ $backups->count() }} {{ Str::plural('entry', $backups->count()) }}</p>
        </div>

        @if ($onDisk === null)
            <p class="nd-note nd-note--warn">
                <i class="fa-solid fa-folder-minus" aria-hidden="true"></i>
                <span>The backups folder ({{ config('backups.host_path') ?? config('backups.path') }}) can't be read right now, so downloads will fail.</span>
            </p>
        @endif

        @if ($backups->isEmpty())
            <div class="nd-empty nd-empty--block">
                <p class="nd-empty__line">No backups yet.</p>
                <p>Back up now, or wait for the scheduled run at {{ config('backups.daily_at') }}.</p>
            </div>
        @else
            <ol class="bk-list" role="list">
                @foreach ($backups as $backup)
                    @php
                        $check = $verified[$backup->id];
                        $succeeded = $backup->status === 'succeeded';
                        $missing = $succeeded && $onDisk !== null && ! isset($onDisk[$backup->filename]);
                        $keptBecause = match (true) {
                            $backup->trigger === 'prerestore' => 'Pre-restore backups are always kept.',
                            $backup->id === $newestId => 'The newest successful backup is always kept.',
                            $backup->status === 'running' => null,
                            default => false,
                        };
                    @endphp
                    <li class="bk-item" id="bk-{{ $backup->id }}" aria-labelledby="bk-{{ $backup->id }}-title">
                        <div class="bk-item__head">
                            <h3 class="bk-item__title" id="bk-{{ $backup->id }}-title">
                                <time datetime="{{ $backup->created_at->toIso8601String() }}">{{ $when($backup->created_at) }}</time>
                                <span class="nd-sr-only">, backup #{{ $backup->id }}</span>
                            </h3>
                            <span class="bk-tag bk-tag--{{ $backup->trigger }}">{{ $backup->trigger }}</span>
                            <span class="bk-status bk-status--{{ $backup->status }}">
                                <i class="fa-solid {{ ['succeeded' => 'fa-check', 'failed' => 'fa-xmark', 'running' => 'fa-spinner fa-spin-pulse'][$backup->status] }}" aria-hidden="true"></i>
                                {{ $backup->status }}
                            </span>
                            <span class="bk-item__ago">#{{ $backup->id }} · {{ $backup->created_at->diffForHumans() }}</span>
                        </div>

                        <dl class="bk-meta">
                            <div><dt>Size</dt><dd>{{ $backup->size ? Bk::bytes((int) $backup->size) : '—' }}</dd></div>
                            <div><dt>Duration</dt><dd>{{ Bk::duration($backup->duration_ms) }}</dd></div>
                            @if ($succeeded)
                                <div class="bk-meta__sig">
                                    <dt>Signature</dt>
                                    <dd>
                                        <span aria-live="polite" class="bk-check {{ $check === null ? '' : ($check['ok'] ? 'bk-check--ok' : 'bk-check--bad') }}" data-bk-check><span data-bk-check-label>{{ Bk::checkLabel($check) }}</span>@if ($check)<span class="bk-check__when" data-bk-check-when>, {{ Bk::checkWhen($check) }}</span>@endif</span>
                                        <form method="POST" action="{{ route('backups.verify', $backup) }}" class="bk-verify" data-bk-verify-form data-turbo="false">
                                            @csrf
                                            <button type="submit" class="nd-btn nd-btn--sm nd-btn--ghost" data-bk-verify>
                                                <i class="fa-solid fa-signature" aria-hidden="true"></i>
                                                <span data-bk-verify-label>{{ $check ? 'Check again' : 'Check' }}</span><span class="nd-sr-only"> backup #{{ $backup->id }}</span>
                                            </button>
                                        </form>
                                    </dd>
                                </div>
                            @endif
                        </dl>

                        @if ($check && ! $check['ok'])
                            <p class="bk-error" data-bk-check-message>{{ $check['message'] }}</p>
                        @else
                            <p class="bk-error" data-bk-check-message hidden></p>
                        @endif

                        @if ($backup->status === 'failed' && $backup->error)
                            <p class="bk-error"><span class="bk-error__label">Error:</span> {{ $backup->error }}</p>
                        @endif

                        @if ($missing)
                            <p class="bk-error">{{ $backup->filename }} is not in the backups folder any more.</p>
                        @endif

                        <div class="bk-item__actions">
                            @if ($succeeded && ! $missing)
                                <div class="bk-downloads" role="group" aria-label="Download backup #{{ $backup->id }}">
                                    <a class="nd-btn nd-btn--sm" href="{{ route('backups.download', [$backup, 'age']) }}" download data-turbo="false">
                                        <i class="fa-solid fa-download" aria-hidden="true"></i> Data <span class="bk-ext">.age</span>
                                    </a>
                                    <a class="nd-btn nd-btn--sm" href="{{ route('backups.download', [$backup, 'manifest']) }}" download data-turbo="false">
                                        <i class="fa-solid fa-file-lines" aria-hidden="true"></i> Manifest
                                    </a>
                                    <a class="nd-btn nd-btn--sm" href="{{ route('backups.download', [$backup, 'sig']) }}" download data-turbo="false">
                                        <i class="fa-solid fa-signature" aria-hidden="true"></i> Signature
                                    </a>
                                </div>
                            @endif

                            @if ($keptBecause === false)
                                <button type="button" class="nd-btn nd-btn--sm nd-btn--ghost bk-delete" data-bk-delete="{{ $backup->id }}"
                                        data-bk-delete-url="{{ route('backups.destroy', $backup) }}" data-bk-code="{{ $backup->code() }}"
                                        data-bk-label="{{ $when($backup->created_at) }} ({{ $backup->trigger }}, {{ $backup->status }})">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    Delete<span class="nd-sr-only"> backup #{{ $backup->id }}</span>
                                </button>
                            @elseif ($keptBecause)
                                <p class="bk-kept"><i class="fa-solid fa-lock" aria-hidden="true"></i> {{ $keptBecause }}</p>
                            @endif
                        </div>

                        @if ($succeeded)
                            <details class="bk-restore">
                                <summary>Restore commands<span class="nd-sr-only"> for backup #{{ $backup->id }}</span></summary>
                                <p class="bk-restore__intro">From <code>~/projects/baradox-stuff</code> in the WSL terminal:</p>
<pre class="bk-code" id="bk-cmd-{{ $backup->id }}"><code># 1. Check the signature and the hash (nothing is decrypted)
./vendor/bin/sail artisan backup:verify {{ $backup->id }}

# 2. Plug the age key in where the container can read it
cp {{ $identity }} {{ $plugged }}

# 3. Restore: asks you to type RESTORE laravel, takes a pre-restore backup first
./vendor/bin/sail artisan backup:restore {{ $backup->filename }} --identity={{ $plugged }}

# 4. Take the key out again
shred -u {{ $plugged }}</code></pre>
                                <div class="bk-restore__foot">
                                    <button type="button" class="nd-btn nd-btn--sm" data-bk-copy="bk-cmd-{{ $backup->id }}">
                                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                        <span data-bk-copy-label>Copy commands</span>
                                    </button>
                                    <p class="bk-restore__hint">To try a restore without touching the live database, run <code>bash scripts/restore-check.sh</code> instead.</p>
                                </div>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- ---------------------------------------------------------------- restore --}}
    <section class="bk-section bk-info" aria-labelledby="bk-restore-title">
        <h2 class="bk-section__title" id="bk-restore-title">Restoring</h2>
        <div class="bk-prose">
            <p>
                There is no restore button, on purpose. A restore needs the private age key, and that key
                never lives on this machine: putting it behind a web page would mean keeping it on the server.
                <code>backup:restore</code> also wants a terminal. It checks the signature before
                decrypting anything, asks you to type <code>RESTORE laravel</code>, and takes a pre-restore backup
                of the current database first. It replaces every table in the backup, and if it fails midway
                that pre-restore backup is the way back.
            </p>
            <p>
                Each backup above has its commands. <code>{{ $identity }}</code> in them is only where the key is
                plugged in for the length of a restore. It is copied into the project's private storage, which
                the container can read, then shredded.
            </p>
            <p>
                <code>bash scripts/restore-check.sh</code> is the safe test. It restores the newest backup into a
                throwaway MySQL container, compares row counts with the live database, and removes the container.
                The live database is never touched.
            </p>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- coverage --}}
    <section class="bk-section bk-info" aria-labelledby="bk-coverage-title">
        <h2 class="bk-section__title" id="bk-coverage-title">What a backup does not hold</h2>
        <div class="bk-prose">
            <p>
                <strong>The SSD's media is not in these backups.</strong> They are the database only.
                @if ($media['readable'])
                    Photos, videos, video posters and thumbnails live on the SSD:
                    <strong>{{ number_format($media['files']) }} {{ Str::plural('file', $media['files']) }}, {{ Bk::bytes($media['bytes']) }}</strong>
                    (counted {{ \Illuminate\Support\Carbon::createFromTimestamp($media['counted_at'])->diffForHumans() }}).
                @else
                    Photos, videos, video posters and thumbnails live on the SSD, which can't be read right now, so they can't be counted.
                @endif
                The SSD itself is not backed up anywhere.
            </p>
            @if ($media['readable'])
                <ul class="bk-folders" role="list">
                    @foreach ($media['folders'] as $folder => $count)
                        <li><code>{{ $folder }}/</code> <span>{{ number_format($count['files']) }} · {{ Bk::bytes($count['bytes']) }}</span></li>
                    @endforeach
                </ul>
            @endif
            <p>
                <strong>Also keep a copy of the age key and of <code>signing.pub.pem</code> somewhere other than the SSD.</strong>
                The age key is the only way to decrypt a backup. The public signing key is how a new machine
                trusts backups made on this one.
                @if ($fingerprint)
                    Its fingerprint is <code class="bk-fp">{{ $fingerprint }}</code>.
                @endif
                If the SSD dies, it must not take either of them with it.
            </p>
            <p>
                Backups are kept in <code>{{ config('backups.host_path') ?? config('backups.path') }}</code>, off the SSD and off
                Docker's volume. Nothing is copied off this machine automatically.
            </p>
        </div>
    </section>
</div>

{{-- One delete dialog for every row; backups.js points it at the backup. --}}
<dialog class="nd-dialog nd-dialog--sm" id="bk-delete-dialog" aria-labelledby="bk-delete-title">
    <form method="POST" action="" data-bk-delete-form>
        @csrf
        @method('DELETE')
        <div class="nd-dialog__head">
            <div>
                <h2 class="nd-dialog__title" id="bk-delete-title">Delete this backup?</h2>
                <p class="nd-dialog__sub" data-bk-delete-label></p>
            </div>
            <button type="button" class="nd-icon-btn" data-bk-dialog-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="nd-dialog__body bk-delete-body">
            <p>Its three files (data, manifest, signature) are deleted from the backups folder. This can't be undone.</p>
            <div class="nd-field">
                <label class="nd-label" for="bk-delete-confirm">Type the backup's code to confirm</label>
                <input type="text" class="nd-input nd-input--mono" id="bk-delete-confirm" name="confirm" autocomplete="off"
                       spellcheck="false" autocapitalize="off" required data-bk-delete-input aria-describedby="bk-delete-code">
            </div>
            <p class="nd-hint" id="bk-delete-code">The code is <code data-bk-delete-code></code>.</p>
        </div>
        <div class="nd-dialog__foot">
            <button type="button" class="nd-btn nd-btn--ghost" data-bk-dialog-close>Cancel</button>
            <button type="submit" class="nd-btn nd-btn--danger" data-bk-delete-submit disabled>
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                Delete
            </button>
        </div>
    </form>
</dialog>
@endsection
