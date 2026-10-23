// Show/hide on password and key fields (x-nd-secret). The button switches the input's type and
// nothing else, so autocomplete stays as rendered; a field in mask mode stays type="text" and
// switches its CSS mask instead. A revealed field never outlives what it was revealed for: every
// field goes back to hidden when its form submits or resets, when its dialog closes, when a
// Turbo visit starts, and — emptied as well — before Turbo snapshots the page and when the page
// is hidden, so Back/Forward can't bring a key back on screen.

const fields = (root) => (root instanceof Element || root instanceof Document ? root.querySelectorAll('[data-secret]') : []);

const show = (field, shown) => {
    const input = field.querySelector('[data-secret-input]');
    const button = field.querySelector('[data-secret-toggle]');
    if (!input || !button) return;
    if (field.dataset.secretMode === 'mask') input.toggleAttribute('data-secret-shown', shown);
    else input.type = shown ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(shown));
    button.setAttribute('aria-label', `${shown ? 'Hide' : 'Show'} ${button.dataset.secretWhat || 'key'}`);
    const icon = button.querySelector('i');
    if (icon) icon.className = `fa-solid ${shown ? 'fa-eye-slash' : 'fa-eye'}`;
};

const hideAll = (root, { empty = false } = {}) => {
    for (const field of fields(root)) {
        show(field, false);
        if (empty) {
            const input = field.querySelector('[data-secret-input]');
            if (input) input.value = '';
        }
    }
};

document.addEventListener('click', (event) => {
    const button = event.target.closest?.('[data-secret-toggle]');
    if (!button) return;
    const field = button.closest('[data-secret]');
    if (field) show(field, button.getAttribute('aria-pressed') !== 'true');
});

// Capturing, so the field is hidden before any form handler reads it or the page moves on.
document.addEventListener('submit', (event) => hideAll(event.target), true);
document.addEventListener('reset', (event) => hideAll(event.target), true);
document.addEventListener('close', (event) => hideAll(event.target), true);
document.addEventListener('turbo:visit', () => hideAll(document));
document.addEventListener('turbo:before-cache', () => hideAll(document, { empty: true }));
window.addEventListener('pagehide', () => hideAll(document, { empty: true }));
