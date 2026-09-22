import { normaliseSearchText as normalise } from './search-text.js';

// ---------------------------------------------------------------- now playing

let activeId = null;

const paintNowPlaying = (state) => {
    const root = document.getElementById('mu-root');
    activeId = state?.trackId ?? null;
    if (!root) return;

    const playing = Boolean(state?.playing);

    for (const row of root.querySelectorAll('[data-mu-row]')) {
        const isCurrent = Number(row.dataset.trackId) === activeId;

        if (!isCurrent) delete row.dataset.state;
        else row.dataset.state = playing ? 'playing' : 'current';

        const title = row.dataset.title || 'this song';
        row.querySelector('[data-mu-toggle]')
            ?.setAttribute('aria-label', isCurrent && playing ? `Pause ${title}` : `Play ${title}`);
    }
};

// ---------------------------------------------------------------- setup

const initMusic = () => {
    const root = document.getElementById('mu-root');
    if (!root || root.dataset.muReady === 'true') return;
    root.dataset.muReady = 'true';

    const data = JSON.parse(document.getElementById('mu-data').textContent);
    const tracks = data.tracks ?? {};

    // ------------------------------------------------------------ dialogs

    const addDialog = document.getElementById('mu-add-dialog');
    const editDialog = document.getElementById('mu-edit-dialog');
    const deleteDialog = document.getElementById('mu-delete-dialog');
    const addForm = addDialog.querySelector('form');
    const editForm = editDialog.querySelector('form');
    const deleteForm = deleteDialog.querySelector('form');

    let returnFocusTo = null;

    for (const dialog of [addDialog, editDialog, deleteDialog]) {
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

        dialog.querySelector('form').addEventListener('submit', () => {
            dialog.querySelector('[type="submit"]').disabled = true;
        });
    }

    const control = (form, prefix, name) => form.querySelector(`#${prefix}-${name}`);

    const clearErrors = (form) => {
        for (const node of form.querySelectorAll('[data-mu-error]')) {
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

    const FORM_FIELDS = ['title', 'artist_name', 'duration', 'song_date'];

    const openAddDialog = ({ values = null, errors = null, opener = null } = {}) => {
        addForm.reset();
        clearErrors(addForm);

        for (const name of FORM_FIELDS) control(addForm, 'af', name).value = values?.[name] ?? '';
        control(addForm, 'af', 'favorite').checked = Boolean(values?.favorite);

        const firstInvalid = showErrors(addForm, 'af', errors);
        returnFocusTo = opener;
        addDialog.showModal();
        (firstInvalid ?? control(addForm, 'af', 'title')).focus();
    };

    const openEditDialog = (track, { values = null, errors = null, opener = null } = {}) => {
        editForm.reset();
        clearErrors(editForm);

        editForm.action = track.urls.update;
        editForm.elements._modal.value = `edit:${track.id}`;

        const source = values ?? track;
        for (const name of FORM_FIELDS) control(editForm, 'ef', name).value = source[name] ?? '';

        const firstInvalid = showErrors(editForm, 'ef', errors);
        returnFocusTo = opener;
        editDialog.showModal();
        (firstInvalid ?? control(editForm, 'ef', 'title')).focus();
    };

    const openDeleteDialog = (track, opener) => {
        deleteForm.action = track.urls.destroy;
        deleteForm.querySelector('[data-mu-delete-title]').textContent = track.title;
        returnFocusTo = opener;
        deleteDialog.showModal();
    };

    // ------------------------------------------------------------ filter and sort

    const list = root.querySelector('[data-mu-list]');
    const head = root.querySelector('[data-mu-head]');
    const rows = list ? [...list.querySelectorAll('[data-mu-row]')] : [];
    const serverOrder = [...rows]; // also the player's queue order — see the note below
    const search = root.querySelector('[data-mu-search]');
    const favFilter = root.querySelector('[data-mu-fav-filter]');
    const countEl = root.querySelector('[data-mu-count]');
    const noMatches = root.querySelector('[data-mu-no-matches]');
    const noMatchesNote = root.querySelector('[data-mu-no-matches-note]');
    const clearToolbar = root.querySelector('[data-mu-toolbar] [data-mu-clear]');
    const sortButtons = [...root.querySelectorAll('[data-mu-sort]')];

    const haystack = new Map(rows.map((row) => [row, normalise(`${row.dataset.title} ${row.dataset.artist}`)]));

    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

    const SORT_KEYS = {
        title: (row) => row.dataset.title ?? '',
        artist: (row) => row.dataset.artist ?? '',
        duration: (row) => Number(row.dataset.duration || 0),
        added: (row) => Number(row.dataset.added || 0),
    };
    const FIRST_DIR = { title: 'asc', artist: 'asc', duration: 'asc', added: 'desc' };
    const LABELS = { title: 'title', artist: 'artist', duration: 'length', added: 'date added' };

    let sortKey = null; // null = the order the server sent, which is newest first
    let sortDir = 'desc';

    const applySort = () => {
        for (const button of sortButtons) {
            const active = button.dataset.muSort === sortKey;
            if (active) button.dataset.dir = sortDir;
            else delete button.dataset.dir;

            const name = LABELS[button.dataset.muSort];
            button.setAttribute(
                'aria-label',
                active
                    ? `Sorted by ${name}, ${sortDir === 'asc' ? 'ascending' : 'descending'}. Activate to reverse.`
                    : `Sort by ${name}`,
            );
        }

        if (!list) return;

        let ordered = serverOrder;
        if (sortKey) {
            const read = SORT_KEYS[sortKey];
            const sign = sortDir === 'asc' ? 1 : -1;
            ordered = [...serverOrder].sort((a, b) => {
                const left = read(a);
                const right = read(b);
                const diff = typeof left === 'number' ? left - right : collator.compare(left, right);
                // Equal values keep the server's order, so repeated sorts don't shuffle.
                return diff ? diff * sign : serverOrder.indexOf(a) - serverOrder.indexOf(b);
            });
        }

        list.append(...ordered); // append() of existing nodes moves them, no re-creation
    };

    const applyFilter = () => {
        const query = normalise(search?.value ?? '');
        const favOnly = favFilter?.getAttribute('aria-pressed') === 'true';
        const filtering = Boolean(query) || favOnly;

        let visible = 0;
        let last = null;

        for (const row of rows) {
            const match = (!query || haystack.get(row).includes(query)) && (!favOnly || row.dataset.favorite === '1');
            row.hidden = !match;
            delete row.dataset.last;
            if (match) {
                visible += 1;
                last = row;
            }
        }
        if (last) last.dataset.last = '';

        const total = rows.length;
        if (countEl) {
            countEl.textContent = filtering
                ? `${visible} of ${total} ${total === 1 ? 'song' : 'songs'}`
                : `${total} ${total === 1 ? 'song' : 'songs'}`;
        }

        const nothing = visible === 0;
        if (head) head.hidden = nothing;
        if (list) list.hidden = nothing;
        if (noMatches) noMatches.hidden = !nothing;
        if (noMatchesNote) {
            noMatchesNote.textContent =
                query && favOnly
                    ? `No favourite matches “${search.value.trim()}”.`
                    : query
                      ? `No song matches “${search.value.trim()}”.`
                      : 'Nothing here is marked as a favourite yet.';
        }

        if (clearToolbar) clearToolbar.hidden = !filtering;
    };

    // Filter and sort state lives in the query string: it survives Back/Forward, and the
    // favorite toggle's redirect (back(), i.e. the Referer) lands on the same view.
    const writeUrl = () => {
        const url = new URL(window.location.href);
        const set = (key, value) => (value ? url.searchParams.set(key, value) : url.searchParams.delete(key));

        set('q', search?.value.trim() ?? '');
        set('fav', favFilter?.getAttribute('aria-pressed') === 'true' ? '1' : '');
        set('sort', sortKey ?? '');
        set('dir', sortKey ? sortDir : '');

        history.replaceState(history.state, '', url);
    };

    const readUrl = () => {
        const params = new URL(window.location.href).searchParams;

        if (search) search.value = params.get('q') ?? '';
        if (favFilter) favFilter.setAttribute('aria-pressed', params.get('fav') === '1' ? 'true' : 'false');

        const key = params.get('sort');
        if (key && key in SORT_KEYS) {
            sortKey = key;
            sortDir = params.get('dir') === 'asc' ? 'asc' : 'desc';
        }
    };

    let urlTimer;
    const scheduleUrl = () => {
        clearTimeout(urlTimer);
        urlTimer = setTimeout(writeUrl, 250);
    };

    if (search) {
        search.addEventListener('input', () => {
            applyFilter();
            scheduleUrl();
        });
        // Esc in a type=search field clears it without firing 'input' in some browsers.
        search.addEventListener('search', () => {
            applyFilter();
            scheduleUrl();
        });
    }

    if (favFilter) {
        favFilter.addEventListener('click', () => {
            favFilter.setAttribute('aria-pressed', favFilter.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
            applyFilter();
            writeUrl();
        });
    }

    for (const button of sortButtons) {
        button.addEventListener('click', () => {
            const key = button.dataset.muSort;
            if (sortKey === key) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            else {
                sortKey = key;
                sortDir = FIRST_DIR[key];
            }
            applySort();
            applyFilter(); // the last visible row changed
            writeUrl();
        });
    }

    const clearFilters = () => {
        if (search) search.value = '';
        favFilter?.setAttribute('aria-pressed', 'false');
        applyFilter();
        writeUrl();
        search?.focus();
    };

    // ------------------------------------------------------------ delegated clicks

    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-mu-control]')) event.stopPropagation();

        const toggle = event.target.closest('[data-mu-toggle]');
        if (toggle) {
            const row = toggle.closest('[data-mu-row]');
            if (row && Number(row.dataset.trackId) === activeId) {
                event.stopPropagation();
                window.bsPlayer?.toggle();
            }
            return;
        }

        const opener = event.target.closest('[data-mu-open]');
        if (opener) {
            const kind = opener.dataset.muOpen;
            const track = tracks[opener.dataset.track];

            if (kind === 'add') openAddDialog({ opener });
            else if (kind === 'edit' && track) openEditDialog(track, { opener });
            else if (kind === 'delete' && track) openDeleteDialog(track, opener);
            return;
        }

        const closer = event.target.closest('[data-mu-close]');
        if (closer) {
            closer.closest('dialog').close();
            return;
        }

        if (event.target.closest('[data-mu-clear]')) {
            clearFilters();
            return;
        }

        const dismiss = event.target.closest('[data-mu-dismiss]');
        if (dismiss) dismiss.closest('[data-mu-dismissable]').remove();
    });

    // ------------------------------------------------------------ boot

    readUrl();
    applySort();
    applyFilter();
    paintNowPlaying(window.bsPlayer?.state());

    // Reopen the dialog a failed submit came from, with what was typed and why it failed.
    const [kind, id] = (data.context ?? '').split(':');
    if (kind === 'add') {
        openAddDialog({ values: data.old ?? {}, errors: data.errors });
    } else if (kind === 'edit' && tracks[id]) {
        openEditDialog(tracks[id], { values: data.old ?? {}, errors: data.errors });
    }
};

const resetForCache = () => {
    const root = document.getElementById('mu-root');
    if (!root) return;

    for (const dialog of root.querySelectorAll('dialog[open]')) dialog.close();
    delete root.dataset.muReady;
};

initMusic();
document.addEventListener('turbo:load', initMusic);
document.addEventListener('turbo:before-cache', resetForCache);
document.addEventListener('bs-player:change', (event) => paintNowPlaying(event.detail));
