{{-- Every mutation on this page redirects (Turbo Drive rejects a 200 page response), so
     both of these arrive as a flash on the next render. --}}
<div class="nd-page__banners">
    @if (session('success'))
        <div class="nd-banner nd-banner--success" role="status" data-ph-dismissable>
            <i class="fa-solid fa-check" aria-hidden="true"></i>
            <p class="nd-banner__text">{{ session('success') }}</p>
            <button type="button" class="nd-icon-btn nd-icon-btn--sm nd-banner__close" data-ph-dismiss aria-label="Dismiss">
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
</div>
