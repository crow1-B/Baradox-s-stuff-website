// The Backups page: polling a run started from here, checking signatures in place, the shared
// delete dialog and copying restore commands. Everything else is server-rendered.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

let pollTimer = null;

const stopPolling = () => {
    clearTimeout(pollTimer);
    pollTimer = null;
};

// A quick run (most are under a second) shows its result on the first or second poll; a slow
// one keeps the note up without blocking anything.
const poll = (root, attempt = 0) => {
    pollTimer = setTimeout(async () => {
        if (!root.isConnected) return;
        let state = null;
        try {
            const response = await fetch(root.dataset.bkStatusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (response.ok) state = (await response.json()).state;
        } catch {
            // offline for a moment: try again
        }
        if (!root.isConnected) return;

        if (state === 'finished' || state === 'idle') {
            window.Turbo.visit(window.location.pathname, { action: 'replace' });
            return;
        }
        if (state === null && attempt > 20) {
            const text = root.querySelector('[data-bk-progress-text]');
            if (text) text.textContent = 'Lost touch with the server. Reload to see whether the backup finished.';
            return;
        }
        poll(root, attempt + 1);
    }, attempt < 4 ? 600 : 1500);
};

const verify = async (form) => {
    const item = form.closest('.bk-item');
    const button = form.querySelector('[data-bk-verify]');
    const check = item.querySelector('[data-bk-check]');
    const message = item.querySelector('[data-bk-check-message]');
    button.disabled = true;
    check.querySelector('[data-bk-check-label]').textContent = 'Checking…';

    let result = null;
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        });
        if (response.ok) result = await response.json();
    } catch {
        // reported below
    }
    button.disabled = false;

    if (!result) {
        check.className = 'bk-check bk-check--bad';
        check.querySelector('[data-bk-check-label]').textContent = 'Could not check';
        return;
    }

    check.className = `bk-check ${result.ok ? 'bk-check--ok' : 'bk-check--bad'}`;
    check.querySelector('[data-bk-check-label]').textContent = result.label;
    let when = check.querySelector('[data-bk-check-when]');
    if (!when) {
        when = document.createElement('span');
        when.className = 'bk-check__when';
        when.dataset.bkCheckWhen = '';
        check.querySelector('[data-bk-check-label]').after(when);
    }
    when.textContent = `, ${result.when}`;
    message.textContent = result.ok ? '' : result.message;
    message.hidden = result.ok;
    button.querySelector('[data-bk-verify-label]').textContent = 'Check again';
};

const openDelete = (trigger) => {
    const dialog = document.getElementById('bk-delete-dialog');
    if (!dialog) return;
    const form = dialog.querySelector('[data-bk-delete-form]');
    const input = dialog.querySelector('[data-bk-delete-input]');
    form.action = trigger.dataset.bkDeleteUrl;
    form.dataset.code = trigger.dataset.bkCode;
    dialog.querySelector('[data-bk-delete-label]').textContent = trigger.dataset.bkLabel;
    dialog.querySelector('[data-bk-delete-code]').textContent = trigger.dataset.bkCode;
    input.value = '';
    dialog.querySelector('[data-bk-delete-submit]').disabled = true;
    dialog.returnTo = trigger;
    dialog.showModal();
    input.focus();
};

const copy = async (button) => {
    const block = document.getElementById(button.dataset.bkCopy);
    const label = button.querySelector('[data-bk-copy-label]');
    const text = block.textContent;
    let copied = false;
    try {
        await navigator.clipboard.writeText(text);
        copied = true;
    } catch {
        const range = document.createRange();
        range.selectNodeContents(block);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        copied = document.execCommand('copy');
    }
    label.textContent = copied ? 'Copied' : 'Selected: press Ctrl+C';
    setTimeout(() => {
        label.textContent = 'Copy commands';
    }, 2500);
};

const setUp = () => {
    const root = document.getElementById('bk-root');
    if (!root || root.dataset.bkReady) return;
    root.dataset.bkReady = '1';

    stopPolling();
    if (root.hasAttribute('data-bk-pending')) poll(root);

    const dialog = document.getElementById('bk-delete-dialog');
    if (dialog) {
        const input = dialog.querySelector('[data-bk-delete-input]');
        const form = dialog.querySelector('[data-bk-delete-form]');
        input.addEventListener('input', () => {
            dialog.querySelector('[data-bk-delete-submit]').disabled = input.value.trim() !== form.dataset.code;
        });
        dialog.addEventListener('close', () => {
            input.value = '';
            dialog.returnTo?.focus();
        });
    }
};

document.addEventListener('click', (event) => {
    if (!document.getElementById('bk-root')) return;
    const del = event.target.closest('[data-bk-delete]');
    const copyButton = event.target.closest('[data-bk-copy]');
    if (del) openDelete(del);
    else if (copyButton) copy(copyButton);
    else if (event.target.closest('[data-bk-dialog-close]')) event.target.closest('dialog')?.close();
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-bk-verify-form]');
    if (!form) return;
    event.preventDefault();
    verify(form);
});

// Turbo disables the submitter only while the request is in flight; keep "Back up now" off
// until the page that comes back says whether a run is going.
document.addEventListener('turbo:submit-start', (event) => {
    if (event.target.matches?.('[data-bk-run-form]')) event.target.querySelector('[data-bk-run]').disabled = true;
});

document.addEventListener('turbo:before-cache', () => {
    stopPolling();
    document.getElementById('bk-delete-dialog')?.close();
});

document.addEventListener('turbo:load', setUp);
