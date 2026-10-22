<div class="xn-banner xn-banner--{{ $kind }}" data-xn-banner>
    <i class="fa-solid {{ $kind === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' }}" aria-hidden="true"></i>
    <p>{{ $message }}</p>
    <button type="button" class="xn-act" data-xn-dismiss aria-label="Dismiss">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
</div>
