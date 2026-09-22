import { normaliseSearchText as normalise } from './search-text.js';

const restoreRevealFrames = (root) => {
    for (const dialog of root.querySelectorAll('[data-dy-reveal-dialog]')) {
        const frame = dialog.querySelector('turbo-frame');
        const template = dialog.querySelector('[data-dy-reveal-template]');
        if (frame && template) frame.replaceChildren(template.content.cloneNode(true));
    }
};

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
        area.className = 'dy-offscreen';
        document.body.append(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        return ok;
    } catch {
        return false;
    }
};

const initDiary = () => {
    const root = document.getElementById('dy-root');
    if (!root || root.dataset.dyReady === 'true') return;
    root.dataset.dyReady = 'true';

    const data = JSON.parse(document.getElementById('dy-data').textContent);
    const entries = data.entries ?? {};

    // ------------------------------------------------------------ dialogs

    const entryDialog = document.getElementById('dy-entry-dialog');
    const viewDialog = document.getElementById('dy-view-dialog');
    const lockDialog = document.getElementById('dy-lock-dialog');
    const unlockDialog = document.getElementById('dy-unlock-dialog');
    const deleteDialog = document.getElementById('dy-delete-dialog');
    const keyDialog = document.getElementById('dy-key-dialog');

    const entryForm = entryDialog.querySelector('form');
    const lockForm = lockDialog.querySelector('form');
    const unlockForm = unlockDialog.querySelector('form');
    const deleteForm = deleteDialog.querySelector('form');

    const revealDialogs = [...root.querySelectorAll('[data-dy-reveal-dialog]')];

    // Only one dialog is modal at a time, so one slot is enough.
    let returnFocusTo = null;

    const ordinaryDialogs = [entryDialog, viewDialog, lockDialog, unlockDialog, deleteDialog, ...revealDialogs];

    for (const dialog of ordinaryDialogs) {
        let pressedBackdrop = false;
        dialog.addEventListener('mousedown', (event) => (pressedBackdrop = event.target === dialog));
        dialog.addEventListener('click', (event) => {
            if (pressedBackdrop && event.target === dialog) dialog.close();
        });

        dialog.addEventListener('close', () => {
            const submit = dialog.querySelector('[type="submit"]');
            if (submit) submit.disabled = false;
            if (returnFocusTo?.isConnected) returnFocusTo.focus();
            returnFocusTo = null;
        });

        dialog.querySelector('form')?.addEventListener('submit', () => {
            const submit = dialog.querySelector('[type="submit"]');
            if (submit) submit.disabled = true;
        });
    }
    for (const dialog of revealDialogs) {
        dialog.addEventListener('close', () => restoreRevealFrames(root));
    }

    const control = (form, prefix, name) => form.querySelector(`#${prefix}-${name}`);

    const clearErrors = (form) => {
        for (const node of form.querySelectorAll('[data-dy-error]')) {
            node.hidden = true;
            node.textContent = '';
        }
        for (const node of form.querySelectorAll('[aria-invalid]')) node.removeAttribute('aria-invalid');
    };

    const showErrors = (form, prefix, errors) => {
        let first = null;

        for (const [field, messages] of Object.entries(errors ?? {})) {
            const errorNode = form.querySelector(`#${prefix}-${field}-error`);
            if (!errorNode) continue;

            errorNode.textContent = messages[0];
            errorNode.hidden = false;

            const input = control(form, prefix, field);
            if (input) {
                input.setAttribute('aria-invalid', 'true');
                first ??= input;
            }
        }

        return first;
    };

    const openDialog = (dialog, opener) => {
        returnFocusTo = opener ?? null;
        if (!dialog.open) dialog.showModal();
    };

    // ---- write / edit

    const ENTRY_FIELDS = ['title', 'content', 'event_date', 'people'];

    const photoModes = entryForm.querySelector('[data-dy-photo-modes]');
    const photoPanes = [...entryForm.querySelectorAll('[data-dy-photo-pane]')];
    const photoCurrent = entryForm.querySelector('[data-dy-photo-current]');
    const photoCurrentName = entryForm.querySelector('[data-dy-photo-current-name]');
    const photoFile = control(entryForm, 'ef', 'photo');

    const photoMode = () => entryForm.querySelector('input[name="photo_source"]:checked')?.value ?? 'none';

    const applyPhotoMode = () => {
        const mode = photoMode();
        for (const pane of photoPanes) pane.hidden = pane.dataset.dyPhotoPane !== mode;
        photoFile.disabled = mode !== 'upload';
    };

    photoModes.addEventListener('change', applyPhotoMode);

    const openEntryDialog = (mode, { entry = null, values = null, errors = null, opener = null } = {}) => {
        const isEdit = mode === 'edit';

        entryForm.reset();
        clearErrors(entryForm);

        const methodInput = entryForm.querySelector('input[name="_method"]');
        entryForm.action = isEdit ? entry.urls.update : data.storeUrl;
        methodInput.disabled = !isEdit;
        methodInput.value = isEdit ? 'PATCH' : 'POST';
        entryForm.elements._modal.value = isEdit ? `edit:${entry.id}` : 'create';

        document.getElementById('dy-entry-dialog-title').textContent = isEdit ? 'Edit memory' : 'New memory';
        entryForm.querySelector('[data-dy-submit]').textContent = isEdit ? 'Save changes' : 'Save memory';

        const source = values ?? {};
        for (const name of ENTRY_FIELDS) {
            control(entryForm, 'ef', name).value =
                source[name] ?? (isEdit ? entryValue(entry, name) : '');
        }

        // "Keep" only makes sense while editing something that actually has a photo.
        const keepable = isEdit && Boolean(entry.photo);
        for (const node of entryForm.querySelectorAll('[data-dy-edit-only]')) node.hidden = !keepable;
        const keepRadio = control(entryForm, 'ef', 'photo-keep');
        keepRadio.disabled = !keepable;

        const wanted = source.photo_source ?? (keepable ? 'keep' : 'none');
        const radio = entryForm.querySelector(`input[name="photo_source"][value="${wanted}"]`);
        (radio && !radio.disabled ? radio : control(entryForm, 'ef', 'photo-none')).checked = true;

        if (source.photo_id) {
            const picked = entryForm.querySelector(`input[name="photo_id"][value="${source.photo_id}"]`);
            if (picked) picked.checked = true;
        }

        photoCurrent.hidden = !keepable;
        if (keepable) photoCurrentName.textContent = `photo #${entry.photo.id}`;

        applyPhotoMode();

        const firstInvalid = showErrors(entryForm, 'ef', errors);
        openDialog(entryDialog, opener);
        (firstInvalid ?? control(entryForm, 'ef', 'title')).focus();
    };

    const entryValue = (entry, name) => (name === 'people' ? entry.people.join(', ') : entry[name] ?? '');

    entryForm.addEventListener('submit', (event) => {
        const mode = photoMode();
        const complain = (id, message) => {
            const node = entryForm.querySelector(`#${id}`);
            node.textContent = message;
            node.hidden = false;
            event.preventDefault();
            entryForm.querySelector('[data-dy-submit]').disabled = false;
        };

        if (mode === 'existing' && !entryForm.querySelector('input[name="photo_id"]:checked')) {
            complain('ef-photo_id-error', 'Pick a photo, or choose another source.');
        } else if (mode === 'upload' && !photoFile.files.length) {
            complain('ef-photo-error', 'Choose a file, or pick another source.');
        }
    });

    // ---- read an unlocked memory

    const viewPhoto = viewDialog.querySelector('[data-dy-view-photo]');
    const viewPeople = viewDialog.querySelector('[data-dy-view-people]');

    const openViewDialog = (entry, opener) => {
        document.getElementById('dy-view-dialog-title').textContent = entry.title;
        viewDialog.querySelector('[data-dy-view-date]').textContent =
            entry.dated ? entry.dateLabel : `Written ${entry.dateLabel}`;
        viewDialog.querySelector('[data-dy-view-content]').textContent = entry.content ?? '';

        viewPhoto.hidden = !entry.photo;
        if (entry.photo) viewPhoto.src = entry.photo.full;
        else viewPhoto.removeAttribute('src');

        viewPeople.hidden = entry.people.length === 0;
        viewPeople.replaceChildren(
            ...entry.people.map((name) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'dy-chip';
                chip.dataset.dyPerson = name;
                const label = document.createElement('span');
                label.dir = 'auto';
                label.textContent = name;
                chip.append(label);
                return chip;
            }),
        );

        openDialog(viewDialog, opener);
    };

    // ---- lock

    const lockKey = lockForm.querySelector('[data-dy-lock-key]');
    const lockGenerate = lockForm.querySelector('[data-dy-lock-generate]');

    const applyGenerate = () => {
        // required_without:generate_key on the server; mirror it so the field stops asking.
        lockKey.disabled = lockGenerate.checked;
        lockKey.closest('[data-dy-field]').hidden = lockGenerate.checked;
    };

    lockGenerate.addEventListener('change', applyGenerate);

    const openLockDialog = (entry, { errors = null, opener = null } = {}) => {
        lockForm.reset();
        clearErrors(lockForm);
        lockForm.action = entry.urls.lock;
        lockForm.elements._modal.value = `lock:${entry.id}`;
        lockForm.querySelector('[data-dy-lock-title]').textContent = entry.title;
        applyGenerate();

        const firstInvalid = showErrors(lockForm, 'lf', errors);
        openDialog(lockDialog, opener);
        (firstInvalid ?? control(lockForm, 'lf', 'algorithm')).focus();
    };

    // ---- unlock

    const openUnlockDialog = (entry, { errors = null, opener = null } = {}) => {
        unlockForm.reset();
        clearErrors(unlockForm);
        unlockForm.action = entry.urls.unlock;
        unlockForm.elements._modal.value = `unlock:${entry.id}`;
        unlockForm.querySelector('[data-dy-unlock-title]').textContent = entry.title;

        const firstInvalid = showErrors(unlockForm, 'uf', errors);
        openDialog(unlockDialog, opener);
        (firstInvalid ?? control(unlockForm, 'uf', 'algorithm')).focus();
    };

    // ---- delete

    const deleteCredentials = deleteForm.querySelector('[data-dy-delete-credentials]');

    const openDeleteDialog = (entry, { errors = null, opener = null } = {}) => {
        deleteForm.reset();
        clearErrors(deleteForm);
        deleteForm.action = entry.urls.destroy;
        deleteForm.elements._modal.value = `delete:${entry.id}`;
        deleteForm.querySelector('[data-dy-delete-title]').textContent = entry.title;

        // A locked memory needs its key and algorithm to delete, same as to open it.
        deleteCredentials.hidden = !entry.locked;
        for (const field of ['key', 'algorithm']) {
            const input = control(deleteForm, 'df', field);
            input.disabled = !entry.locked;
            input.required = entry.locked;
        }

        const firstInvalid = showErrors(deleteForm, 'df', errors);
        openDialog(deleteDialog, opener);
        (firstInvalid ?? (entry.locked ? control(deleteForm, 'df', 'algorithm') : deleteForm.querySelector('[data-dy-close]'))).focus();
    };

    // ---- the generated key, shown exactly once

    if (keyDialog) {
        const gate = keyDialog.querySelector('[data-dy-key-gate]');
        const confirm = keyDialog.querySelector('[data-dy-key-confirm]');
        const copied = keyDialog.querySelector('[data-dy-copied]');
        keyDialog.addEventListener('cancel', (event) => event.preventDefault());
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
            copied.textContent = (await copyText(value))
                ? 'Copied.'
                : 'Couldn’t copy — select the key above and copy it by hand.';
        });

        keyDialog.showModal();
        gate.focus();
    }

    // ------------------------------------------------------------ filter

    const cards = [...root.querySelectorAll('[data-dy-entry]')];
    const groups = [...root.querySelectorAll('[data-dy-group]')];
    const search = root.querySelector('[data-dy-search]');
    const countEl = root.querySelector('[data-dy-count]');
    const filterBar = root.querySelector('[data-dy-filter]');
    const filterName = root.querySelector('[data-dy-filter-name]');
    const noMatches = root.querySelector('[data-dy-no-matches]');
    const noMatchesNote = root.querySelector('[data-dy-no-matches-note]');
    const clearToolbar = root.querySelector('[data-dy-clear]');

    const haystack = new Map(
        cards.map((card) => {
            const entry = entries[card.dataset.entryId] ?? {};
            return [card, normalise(`${entry.title ?? ''} ${entry.content ?? ''}`)];
        }),
    );

    const peopleOf = new Map(
        cards.map((card) => {
            const entry = entries[card.dataset.entryId] ?? {};
            return [card, (entry.people ?? []).map(normalise)];
        }),
    );

    let person = null; // the person's name as typed, or null

    const applyFilter = () => {
        const query = normalise(search?.value ?? '');
        const wanted = person ? normalise(person) : null;
        const filtering = Boolean(query) || Boolean(wanted);

        let visible = 0;

        for (const card of cards) {
            const match =
                (!query || haystack.get(card).includes(query)) &&
                (!wanted || peopleOf.get(card).includes(wanted));
            card.hidden = !match;
            if (match) visible += 1;
        }

        for (const group of groups) {
            const shown = [...group.querySelectorAll('[data-dy-entry]')].filter((card) => !card.hidden);
            group.hidden = shown.length === 0;
            const count = group.querySelector('[data-dy-group-count]');
            if (count) count.textContent = String(shown.length);
        }

        const total = cards.length;
        const word = (n) => (n === 1 ? 'memory' : 'memories');
        if (countEl) countEl.textContent = filtering ? `${visible} of ${total} ${word(total)}` : `${total} ${word(total)}`;

        if (filterBar) {
            filterBar.hidden = !person;
            if (person) filterName.textContent = person;
        }

        const nothing = visible === 0;
        if (noMatches) noMatches.hidden = !nothing;
        if (noMatchesNote) {
            const bits = [];
            if (query) bits.push(`“${search.value.trim()}”`);
            if (person) bits.push(`memories with ${person}`);
            noMatchesNote.textContent = query
                ? `Nothing matches ${bits.join(', and no ')}. Remember that locked memories can only match on their title.`
                : `Nothing here lists ${person}. Locked memories never do — they show no people at all.`;
        }

        if (clearToolbar) clearToolbar.hidden = !filtering;
    };

    const writeUrl = () => {
        const url = new URL(window.location.href);
        const set = (key, value) => (value ? url.searchParams.set(key, value) : url.searchParams.delete(key));
        set('q', search?.value.trim() ?? '');
        set('person', person ?? '');
        history.replaceState(history.state, '', url);
    };

    const readUrl = () => {
        const params = new URL(window.location.href).searchParams;
        if (search) search.value = params.get('q') ?? '';
        person = params.get('person') || null;
    };

    let urlTimer;
    const scheduleUrl = () => {
        clearTimeout(urlTimer);
        urlTimer = setTimeout(writeUrl, 250);
    };

    if (search) {
        for (const type of ['input', 'search']) {
            search.addEventListener(type, () => {
                applyFilter();
                scheduleUrl();
            });
        }
    }

    const setPerson = (name) => {
        person = name;
        applyFilter();
        writeUrl();
    };

    const clearAll = () => {
        if (search) search.value = '';
        person = null;
        applyFilter();
        writeUrl();
        search?.focus();
    };

    // ------------------------------------------------------------ delegated clicks

    root.addEventListener('click', (event) => {
        const chip = event.target.closest('[data-dy-person]');
        if (chip) {
            setPerson(chip.dataset.dyPerson);
            if (viewDialog.open) viewDialog.close();
            return;
        }

        if (event.target.closest('[data-dy-clear-person]')) {
            setPerson(null);
            return;
        }

        if (event.target.closest('[data-dy-clear]')) {
            clearAll();
            return;
        }

        if (event.target.closest('[data-dy-reveal-hide]')) {
            const dialog = event.target.closest('dialog');
            dialog.close(); // the close handler puts the key prompt back
            return;
        }

        const opener = event.target.closest('[data-dy-open]');
        if (opener) {
            const kind = opener.dataset.dyOpen;
            const entry = entries[opener.dataset.entry];

            if (kind === 'create') openEntryDialog('create', { opener });
            else if (!entry) return;
            else if (kind === 'edit') openEntryDialog('edit', { entry, opener });
            else if (kind === 'view') openViewDialog(entry, opener);
            else if (kind === 'lock') openLockDialog(entry, { opener });
            else if (kind === 'unlock') openUnlockDialog(entry, { opener });
            else if (kind === 'delete') openDeleteDialog(entry, { opener });
            else if (kind === 'reveal') {
                const dialog = document.getElementById(`dy-reveal-${entry.id}`);
                openDialog(dialog, opener);
                dialog.querySelector('input[type="password"]')?.focus();
            }
            return;
        }

        const closer = event.target.closest('[data-dy-close]');
        if (closer) {
            closer.closest('dialog').close();
            return;
        }

        const dismiss = event.target.closest('[data-dy-dismiss]');
        if (dismiss) dismiss.closest('[data-dy-dismissable]').remove();
    });

    // ------------------------------------------------------------ boot

    readUrl();
    applyFilter();
    const [kind, id] = (data.context ?? '').split(':');
    const entry = entries[id];
    const errors = data.errors ?? {};

    if (kind === 'create') openEntryDialog('create', { values: data.old ?? {}, errors });
    else if (kind === 'edit' && entry) openEntryDialog('edit', { entry, values: data.old ?? {}, errors });
    else if (kind === 'lock' && entry) openLockDialog(entry, { errors });
    else if (kind === 'unlock' && entry) openUnlockDialog(entry, { errors });
    else if (kind === 'delete' && entry) openDeleteDialog(entry, { errors });

    if (data.revealedId) {
        const dialog = document.getElementById(`dy-reveal-${data.revealedId}`);
        if (dialog && !dialog.open) dialog.showModal();
    }
};

const resetForCache = () => {
    const root = document.getElementById('dy-root');
    if (!root) return;
    const keyDialog = document.getElementById('dy-key-dialog');
    if (keyDialog) keyDialog.dataset.acknowledged = 'true';

    for (const dialog of root.querySelectorAll('dialog[open]')) dialog.close();
    restoreRevealFrames(root);
    delete root.dataset.dyReady;
};

initDiary();
document.addEventListener('turbo:load', initDiary);
document.addEventListener('turbo:before-cache', resetForCache);
