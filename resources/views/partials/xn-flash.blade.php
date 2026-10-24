<div class="nd-banner nd-banner--{{ $kind }}" data-xn-banner>
    <i class="fa-solid {{ $kind === 'success' ? 'fa-check' : 'fa-triangle-exclamation' }}" aria-hidden="true"></i>
    <p class="nd-banner__text">{{ $message }}</p>
    <button type="button" class="nd-icon-btn nd-icon-btn--sm nd-banner__close" data-xn-dismiss aria-label="Dismiss">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
</div>
