@php
    // Owner only, and not on the Backups page, which says the same thing in its summary.
    $backupAttention = \App\Models\Backup::isOwner(Auth::user()) && ! request()->routeIs('backups')
        ? \App\Models\Backup::attention() : null;
@endphp

@if ($backupAttention)
    {{-- A card fixed under the top bar rather than a strip in the flow: Extra Notes is a
         full-height column, and a banner above it would push its composer off the screen.
         Dismissing is per browser session and per reason (sessionStorage, shell.js); the
         inline script hides it before it paints when this reason was already dismissed. --}}
    <aside class="app-alert" role="status" aria-label="Backups" data-backup-alert="{{ substr(hash('sha256', $backupAttention), 0, 16) }}">
        <i class="fa-solid fa-triangle-exclamation app-alert__icon" aria-hidden="true"></i>
        <div class="app-alert__text">
            <p>{{ $backupAttention }}</p>
            <a class="nd-link" href="{{ route('backups') }}">Open Backups</a>
        </div>
        <button type="button" class="nd-icon-btn nd-icon-btn--sm app-alert__close" data-backup-alert-dismiss aria-label="Dismiss the backups warning for this session">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </aside>
    <script>
        (function (alert) {
            try { if (sessionStorage.getItem('nd-backup-alert') === alert.dataset.backupAlert) alert.hidden = true; } catch (e) {}
        })(document.currentScript.previousElementSibling);
    </script>
@endif
