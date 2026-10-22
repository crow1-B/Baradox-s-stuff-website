import { initFlow, resetFlowForCache } from './diary-flow.js';
import { seal, unseal } from './diary-seal.js';

// One entry's page: reading it, walking to its neighbours, and the dialogs that change it.

let flow = null;

const copyText = async (value) => {
    try {
        await navigator.clipboard.writeText(value);
        return true;
    } catch {
        // falls through
    }
    try {
        const area = document.createElement('textarea');
        area.value = value;
        area.setAttribute('readonly', '');
        area.className = 'nd-sr-only';
        document.body.append(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        return ok;
    } catch {
        return false;
    }
};

const restoreRevealFrame = (root) => {
    const frame = root.querySelector('[data-dy-reveal-frame]');
    const template = root.querySelector('[data-dy-reveal-template]');
    if (frame && template) frame.replaceChildren(template.content.cloneNode(true));
};

const typing = (target) => target instanceof Element && target.closest('input, textarea, select, [contenteditable]') !== null;

const initEntry = () => {
    const root = document.getElementById('dy-entry-root');
    if (!root || root.dataset.dyReady === 'true') return;
    root.dataset.dyReady = 'true';

    const data = JSON.parse(document.getElementById('dy-entry-data').textContent);
    const sheet = root.querySelector('[data-dy-sheet]');
    flow = initFlow();

    // ------------------------------------------------------------ dialogs

    const dialogs = Object.fromEntries([...root.querySelectorAll('[data-dy-dialog]')].map((dialog) => [dialog.dataset.dyDialog, dialog]));
    const prefixes = { lock: 'lf', unlock: 'uf', delete: 'df' };
    let returnFocus = null;

    const showErrors = (dialog, prefix, errors) => {
        let first = null;
        for (const [field, messages] of Object.entries(errors ?? {})) {
            const node = dialog.querySelector(`#${prefix}-${field}-error`);
            if (!node) continue;
            node.textContent = messages[0];
            node.hidden = false;
            const input = dialog.querySelector(`#${prefix}-${field}`);
            input?.setAttribute('aria-invalid', 'true');
            first ??= input;
        }
        return first;
    };

    const openDialog = (kind, { errors = null, opener = null } = {}) => {
        const dialog = dialogs[kind];
        if (!dialog) return;
        const form = dialog.querySelector('form');
        form.reset();
        for (const node of dialog.querySelectorAll('[data-dy-error]')) node.hidden = true;
        for (const node of dialog.querySelectorAll('[aria-invalid]')) node.removeAttribute('aria-invalid');
        if (kind === 'lock') applyGenerate();
        returnFocus = opener;
        dialog.showModal();
        const first = showErrors(dialog, prefixes[kind], errors);
        (first ?? dialog.querySelector('select, input:not([type="hidden"])') ?? dialog.querySelector('[data-dy-close]'))?.focus();
    };

    for (const dialog of Object.values(dialogs)) {
        let pressedBackdrop = false;
        dialog.addEventListener('mousedown', (event) => (pressedBackdrop = event.target === dialog));
        dialog.addEventListener('click', (event) => {
            if (pressedBackdrop && event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => {
            const submit = dialog.querySelector('[data-dy-submit]');
            if (submit) submit.disabled = false;
            if (returnFocus?.isConnected) returnFocus.focus();
            returnFocus = null;
        });
    }

    // ---- seal: the animation plays first, then the real form submits

    const lockForm = dialogs.lock?.querySelector('form');
    const lockKey = lockForm?.querySelector('[data-dy-lock-key]');
    const lockGenerate = lockForm?.querySelector('[data-dy-lock-generate]');

    function applyGenerate() {
        if (!lockForm) return;
        // required_without:generate_key on the server; mirrored so the field stops asking.
        lockKey.disabled = lockGenerate.checked;
        lockForm.querySelector('[data-dy-lock-keyfield]').hidden = lockGenerate.checked;
    }

    lockGenerate?.addEventListener('change', applyGenerate);

    lockForm?.addEventListener('submit', async (event) => {
        if (lockForm.dataset.sealed === 'true') return;
        event.preventDefault();

        // Checked here as well as on the server, so a typo doesn't play the whole seal only
        // to come back with an error.
        const errors = {};
        if (!lockForm.elements.algorithm.value) errors.algorithm = ['Choose the algorithm to seal it with.'];
        if (!lockGenerate.checked && lockKey.value.length < 8) errors.key = ['The key needs at least 8 characters.'];
        for (const node of lockForm.querySelectorAll('[data-dy-error]')) node.hidden = true;
        if (Object.keys(errors).length) {
            showErrors(dialogs.lock, 'lf', errors)?.focus();
            return;
        }

        lockForm.querySelector('[data-dy-submit]').disabled = true;
        returnFocus = null;
        dialogs.lock.close();
        root.classList.add('is-busy');
        await seal(sheet.querySelector('[data-dy-text]'), sheet);
        lockForm.dataset.sealed = 'true';
        lockForm.requestSubmit();
    });

    for (const dialog of Object.values(dialogs)) {
        dialog.querySelector('form')?.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;
            const submit = dialog.querySelector('[data-dy-submit]');
            if (submit) submit.disabled = true;
        });
    }

    // ---- the key, shown exactly once

    const keyDialog = root.querySelector('[data-dy-key-dialog]');
    if (keyDialog) {
        const gate = keyDialog.querySelector('[data-dy-key-gate]');
        const confirm = keyDialog.querySelector('[data-dy-key-confirm]');
        const copied = keyDialog.querySelector('[data-dy-copied]');
        keyDialog.addEventListener('cancel', (event) => event.preventDefault());
        // Chrome only honours preventDefault on cancel while the page has user activation;
        // anything that still closes it opens it again until the box is ticked.
        keyDialog.addEventListener('close', () => {
            if (keyDialog.dataset.acknowledged !== 'true') keyDialog.showModal();
        });
        gate.addEventListener('change', () => (confirm.disabled = !gate.checked));
        confirm.addEventListener('click', () => {
            keyDialog.dataset.acknowledged = 'true';
            keyDialog.close();
        });
        keyDialog.querySelector('[data-dy-copy-key]').addEventListener('click', async () => {
            const value = keyDialog.querySelector('[data-dy-key-value]').textContent.trim();
            copied.textContent = (await copyText(value)) ? 'Copied.' : 'Couldn’t copy. Select the key above and copy it by hand.';
        });
        keyDialog.showModal();
        gate.focus();
    }

    // ------------------------------------------------------------ the photo lightbox

    const lightbox = root.querySelector('[data-dy-lightbox]');
    const lightboxImg = lightbox.querySelector('[data-dy-lightbox-img]');
    lightbox.addEventListener('close', () => lightboxImg.removeAttribute('src'));
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox || event.target === lightboxImg) lightbox.close();
    });

    // ------------------------------------------------------------ clicks

    root.addEventListener('click', async (event) => {
        const opener = event.target.closest('[data-dy-open]');
        if (opener) {
            openDialog(opener.dataset.dyOpen, { opener });
            return;
        }
        if (event.target.closest('[data-dy-edit]')) {
            flow?.open({ step: 'words' });
            return;
        }
        const photo = event.target.closest('[data-dy-photo]');
        if (photo) {
            lightboxImg.src = photo.dataset.dyPhoto;
            lightbox.showModal();
            return;
        }
        const closer = event.target.closest('[data-dy-close]');
        if (closer) {
            closer.closest('dialog')?.close();
            return;
        }
        const reseal = event.target.closest('[data-dy-reseal]');
        if (reseal) {
            reseal.disabled = true;
            const frame = root.querySelector('[data-dy-reveal-frame]');
            await seal(frame.querySelector('[data-dy-text]'), frame);
            restoreRevealFrame(root);
            frame.querySelector('select')?.focus();
        }
    });

    // ------------------------------------------------------------ reopen after a bounce

    const [kind] = (data.context ?? '').split(':');
    if (dialogs[kind]) openDialog(kind, { errors: data.errors });

    // ------------------------------------------------------------ arriving unsealed

    // An entry unsealed for good comes back with its words hidden ([data-dy-unseal]) and
    // decrypts on screen.
    const arriving = sheet.querySelector(':scope > [data-dy-unseal]');
    if (arriving) unseal(arriving);
};

// A reveal answers inside the frame; its words arrive hidden and decrypt in place.
document.addEventListener('turbo:frame-load', (event) => {
    if (!event.target.matches('[data-dy-reveal-frame]')) return;
    const text = event.target.querySelector('[data-dy-unseal]');
    if (text) unseal(text);
    else event.target.querySelector('select')?.focus();
});

// ←/→ walk to the older and newer entry, Esc goes back to the list.
document.addEventListener('keydown', (event) => {
    const root = document.getElementById('dy-entry-root');
    if (!root || event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
    if (typing(event.target) || document.querySelector('dialog[open]')) return;

    const link =
        event.key === 'ArrowLeft' ? root.querySelector('[data-dy-older]') :
        event.key === 'ArrowRight' ? root.querySelector('[data-dy-newer]') : null;
    if (link) {
        event.preventDefault();
        link.click();
    } else if (event.key === 'Escape') {
        window.Turbo.visit(JSON.parse(document.getElementById('dy-entry-data').textContent).diaryUrl);
    }
});

document.addEventListener('turbo:before-cache', () => {
    const root = document.getElementById('dy-entry-root');
    if (!root) return;
    const keyDialog = root.querySelector('[data-dy-key-dialog]');
    if (keyDialog) keyDialog.dataset.acknowledged = 'true';
    for (const dialog of root.querySelectorAll('dialog[open]')) dialog.close();
    restoreRevealFrame(root);
    resetFlowForCache();
    flow = null;
    delete root.dataset.dyReady;
});

initEntry();
document.addEventListener('turbo:load', initEntry);
