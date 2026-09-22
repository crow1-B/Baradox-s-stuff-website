{{-- Every mutation on this page redirects (Turbo Drive rejects a 200 page response), so
     both of these arrive as a flash on the next render. --}}
@if (session('success'))
    <div class="ph-banner ph-banner--success mt-6" role="status" data-ph-dismissable>
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <p class="flex-1">{{ session('success') }}</p>
        <button type="button" class="ph-btn ph-btn--ghost ph-btn--sm ph-btn--icon -my-1" data-ph-dismiss aria-label="Dismiss">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
@endif

@if ($errors->any())
    <div class="ph-banner ph-banner--error mt-6" role="alert">
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
