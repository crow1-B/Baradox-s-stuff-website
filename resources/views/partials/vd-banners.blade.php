@if (session('success'))
    <div class="vd-banner vd-banner--success mt-6" role="status" data-vd-dismissable>
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <p class="flex-1">{{ session('success') }}</p>
        <button type="button" class="vd-btn vd-btn--ghost vd-btn--sm vd-btn--icon -my-1" data-vd-dismiss aria-label="Dismiss">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
@endif

@if ($errors->any())
    <div class="vd-banner vd-banner--error mt-6" role="alert">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div>
            <p class="font-medium">That didn't go through.</p>
            <ul class="mt-1 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@unless ($driveReady)
    <div class="vd-banner vd-banner--warn mt-6" role="alert">
        <i class="fa-solid fa-hard-drive" aria-hidden="true"></i>
        <div>
            <p class="font-medium">The drive isn't mounted.</p>
            <p class="mt-1 text-sm">
                {{ $folderLabel }} can't be read, so nothing below can be played and no file can be
                registered. The external SSD doesn't always survive a reboot or a USB reconnect —
                run <code>wsl --shutdown</code> from PowerShell, reopen the Ubuntu terminal and
                <code>sail up -d</code> again.
            </p>
        </div>
    </div>
@endunless
