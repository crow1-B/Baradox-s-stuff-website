<div class="nd-page__banners">
    @if (session('success'))
        <div class="nd-banner nd-banner--success" role="status" data-vd-dismissable>
            <i class="fa-solid fa-check" aria-hidden="true"></i>
            <p class="nd-banner__text">{{ session('success') }}</p>
            <button type="button" class="nd-icon-btn nd-icon-btn--sm nd-banner__close" data-vd-dismiss aria-label="Dismiss">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
    @endif

    @if ($errors->any())
        <div class="nd-banner nd-banner--error" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <div class="nd-banner__text">
                <p>That didn't go through.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @unless ($driveReady)
        <div class="nd-banner nd-banner--warn" role="alert">
            <i class="fa-solid fa-hard-drive" aria-hidden="true"></i>
            <div class="nd-banner__text">
                <p><strong>The drive isn't mounted.</strong></p>
                <p>
                    {{ $folderLabel }} can't be read, so nothing below can be played and no file can be
                    registered. The external SSD doesn't always survive a reboot or a USB reconnect —
                    run <code>wsl --shutdown</code> from PowerShell, reopen the Ubuntu terminal and
                    <code>sail up -d</code> again.
                </p>
            </div>
        </div>
    @endunless
</div>
