let page = null;

const LONG_PRESS_MS = 450;

const initPhotos = () => {
    const root = document.getElementById('ph-root');
    if (!root || root.dataset.phReady === 'true') return;
    root.dataset.phReady = 'true';

    const data = JSON.parse(document.getElementById('ph-data').textContent);
    const meta = new Map((data.photos ?? []).map((photo) => [photo.id, photo]));

    const tiles = [...root.querySelectorAll('[data-ph-tile]')];
    const grid = root.querySelector('[data-ph-grid]');
    const countEl = root.querySelector('[data-ph-count]');
    const noMatches = root.querySelector('[data-ph-no-matches]');
    const noMatchesNote = root.querySelector('[data-ph-no-matches-note]');
    const filterChips = [...root.querySelectorAll('[data-ph-toolbar] [data-ph-filter]')];
    const selectToggle = root.querySelector('[data-ph-select-toggle]');
    const selbar = root.querySelector('[data-ph-selbar]');
    const selcount = root.querySelector('[data-ph-selcount]');

    const favForm = root.querySelector('[data-ph-fav-form]');
    const lockDialog = document.getElementById('ph-lock-dialog');
    const unlockDialog = document.getElementById('ph-unlock-dialog');
    const deleteDialog = document.getElementById('ph-delete-dialog');
    const bulkDeleteDialog = document.getElementById('ph-bulk-delete-dialog');
    const bulkAlbumDialog = document.getElementById('ph-bulk-album-dialog');
    const uploadDialog = document.getElementById('ph-upload-dialog');
    const albumDialog = document.getElementById('ph-album-dialog');
    const albumDeleteDialog = document.getElementById('ph-album-delete-dialog');
    const lightbox = document.getElementById('ph-lightbox');

    const dialogs = [
        lockDialog, unlockDialog, deleteDialog, bulkDeleteDialog,
        bulkAlbumDialog, uploadDialog, albumDialog, albumDeleteDialog, lightbox,
    ].filter(Boolean);

    // ------------------------------------------------------------ state

    let filter = 'all';
    const selected = new Set();
    let anchorId = null; // last tile picked, for shift-ranges
    let manualMode = false; // the Select button, held on with nothing picked
    let returnFocusTo = null;

    const idOf = (tile) => Number(tile.dataset.id);
    const visibleTiles = () => tiles.filter((tile) => !tile.hidden);

    // ------------------------------------------------------------ filter

    const MATCHES = {
        all: () => true,
        favorite: (tile) => tile.dataset.favorite === '1',
        protected: (tile) => tile.dataset.protection !== 'none',
    };
    const FILTER_NOTES = {
        favorite: 'Nothing here is marked as a favourite yet.',
        protected: 'Nothing here is locked.',
    };

    const applyFilter = () => {
        const match = MATCHES[filter] ?? MATCHES.all;
        let visible = 0;

        for (const tile of tiles) {
            const ok = match(tile);
            tile.hidden = !ok;
            if (ok) visible += 1;
        }

        for (const chip of filterChips) {
            chip.setAttribute('aria-pressed', chip.dataset.phFilter === filter ? 'true' : 'false');
        }

        const total = tiles.length;
        if (countEl) {
            countEl.textContent = filter === 'all'
                ? `${total} ${total === 1 ? 'photo' : 'photos'}`
                : `${visible} of ${total} photos`;
        }

        const nothing = visible === 0 && total > 0;
        if (grid) grid.hidden = nothing;
        if (noMatches) noMatches.hidden = !nothing;
        if (noMatchesNote && nothing) {
            noMatchesNote.textContent = FILTER_NOTES[filter] ?? 'No photo here matches the current filter.';
        }
    };
    const writeUrl = () => {
        const url = new URL(window.location.href);
        if (filter === 'all') url.searchParams.delete('filter');
        else url.searchParams.set('filter', filter);
        history.replaceState(history.state, '', url);
    };

    const readUrl = () => {
        const wanted = new URL(window.location.href).searchParams.get('filter');
        if (wanted && wanted in MATCHES) filter = wanted;
    };

    const setFilter = (next) => {
        if (next === filter) return;
        filter = next;
        clearSelection();
        applyFilter();
        writeUrl();
    };

    // ------------------------------------------------------------ selection

    const selecting = () => manualMode || selected.size > 0;

    const paintSelection = () => {
        for (const tile of tiles) {
            const on = selected.has(idOf(tile));
            tile.classList.toggle('ph-tile--picked', on);
            const box = tile.querySelector('[data-ph-pick]');
            if (box) box.checked = on;
        }

        if (selecting()) root.dataset.selecting = 'true';
        else delete root.dataset.selecting;

        selectToggle?.setAttribute('aria-pressed', selecting() ? 'true' : 'false');
        if (selbar) selbar.hidden = selected.size === 0;
        if (selcount) selcount.textContent = `${selected.size} selected`;
    };

    const clearSelection = ({ keepMode = false } = {}) => {
        selected.clear();
        anchorId = null;
        if (!keepMode) manualMode = false;
        paintSelection();
    };

    const selectRange = (fromId, toId, value) => {
        const ids = visibleTiles().map(idOf);
        let from = ids.indexOf(fromId);
        let to = ids.indexOf(toId);
        if (from < 0 || to < 0) return;
        if (from > to) [from, to] = [to, from];

        for (const id of ids.slice(from, to + 1)) {
            if (value) selected.add(id);
            else selected.delete(id);
        }
    };

    const togglePick = (id, { shift = false } = {}) => {
        const wanted = !selected.has(id);

        if (shift && anchorId !== null && anchorId !== id) selectRange(anchorId, id, wanted);
        else if (wanted) selected.add(id);
        else selected.delete(id);

        anchorId = id;
        paintSelection();
    };

    // ------------------------------------------------------------ dialogs

    const openDialog = (dialog, opener = null) => {
        if (!dialog) return;
        returnFocusTo = opener;
        dialog.showModal();
    };

    for (const dialog of dialogs) {
        let pressedBackdrop = false;
        dialog.addEventListener('mousedown', (event) => (pressedBackdrop = event.target === dialog));
        dialog.addEventListener('click', (event) => {
            if (pressedBackdrop && event.target === dialog) dialog.close();
        });

        dialog.addEventListener('close', () => {
            for (const submit of dialog.querySelectorAll('[type="submit"]')) submit.disabled = false;
            if (returnFocusTo?.isConnected) returnFocusTo.focus();
            returnFocusTo = null;
        });

        for (const form of dialog.querySelectorAll('form')) {
            if (form.hasAttribute('data-ph-upload-form') || form.hasAttribute('data-ph-lb-key-form')) continue;
            form.addEventListener('submit', () => {
                const submit = form.querySelector('[type="submit"]');
                if (submit) submit.disabled = true;
            });
        }
    }
    for (const dialog of [lockDialog, unlockDialog].filter(Boolean)) {
        dialog.addEventListener('close', () => dialog.querySelector('form')?.reset());
    }

    const photoUrl = (id, suffix = '') => `${data.urls.photo}/${id}${suffix}`;

    // ------------------------------------------------------------ per-photo actions

    const actOnPhoto = (action, id, opener) => {
        if (!meta.has(id)) return;

        if (action === 'favorite') {
            favForm.action = photoUrl(id, '/favorite');
            favForm.requestSubmit();
            return;
        }
        if (action === 'lock') {
            lockDialog.querySelector('form').action = photoUrl(id, '/lock');
            openDialog(lockDialog, opener);
            lockDialog.querySelector('#ph-lock-key').focus();
            return;
        }
        if (action === 'unlock') {
            unlockDialog.querySelector('form').action = photoUrl(id, '/unlock');
            openDialog(unlockDialog, opener);
            unlockDialog.querySelector('#ph-unlock-key').focus();
            return;
        }
        if (action === 'delete') {
            deleteDialog.querySelector('form').action = photoUrl(id);
            openDialog(deleteDialog, opener);
        }
    };

    // ------------------------------------------------------------ bulk actions

    const fillIds = (form) => {
        const slot = form.querySelector('[data-ph-ids]');
        slot.replaceChildren(...[...selected].map((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = String(id);
            return input;
        }));
    };

    const openBulk = (kind, opener) => {
        if (selected.size === 0) return;

        if (kind === 'delete') {
            fillIds(bulkDeleteDialog.querySelector('form'));
            bulkDeleteDialog.querySelector('[data-ph-bulk-count]').textContent =
                `${selected.size} ${selected.size === 1 ? 'photo' : 'photos'}`;
            openDialog(bulkDeleteDialog, opener);
            return;
        }

        const form = bulkAlbumDialog.querySelector('form');
        fillIds(form);
        form.querySelector('[data-ph-bulk-mode]').value = kind;
        bulkAlbumDialog.querySelector('[data-ph-bulk-album-title]').textContent =
            kind === 'add' ? 'Add to album' : 'Remove from album';
        const hint = bulkAlbumDialog.querySelector('[data-ph-bulk-album-hint]');
        if (hint) {
            hint.textContent = kind === 'add'
                ? `${selected.size} ${selected.size === 1 ? 'photo' : 'photos'} will be added. Ones already in it are left alone.`
                : `${selected.size} ${selected.size === 1 ? 'photo' : 'photos'} will be taken out of it. The photos themselves stay.`;
        }
        openDialog(bulkAlbumDialog, opener);
    };

    // ------------------------------------------------------------ album management

    const openAlbumDialog = (mode, { id = null, name = '' } = {}, opener = null) => {
        if (!albumDialog) return;
        const form = albumDialog.querySelector('form');
        const method = form.querySelector('[data-ph-album-method]');
        const input = form.querySelector('#ph-album-name');

        if (mode === 'rename') {
            form.action = `${data.urls.album}/${id}`;
            method.innerHTML = '<input type="hidden" name="_method" value="PATCH">';
            albumDialog.querySelector('[data-ph-album-heading]').textContent = 'Rename album';
            form.querySelector('[data-ph-album-submit]').textContent = 'Save name';
        } else {
            form.action = data.urls.album;
            method.replaceChildren();
            albumDialog.querySelector('[data-ph-album-heading]').textContent = 'New album';
            form.querySelector('[data-ph-album-submit]').textContent = 'Create album';
        }

        input.value = name;
        openDialog(albumDialog, opener);
        input.focus();
        input.select();
    };

    // ------------------------------------------------------------ lightbox

    let lbList = [];
    let lbIndex = -1;
    let lbKey = null;

    const lbImg = lightbox.querySelector('[data-ph-lb-img]');
    const lbSpinner = lightbox.querySelector('[data-ph-lb-spinner]');
    const lbGate = lightbox.querySelector('[data-ph-lb-gate]');
    const lbGateTitle = lightbox.querySelector('[data-ph-lb-gate-title]');
    const lbGateNote = lightbox.querySelector('[data-ph-lb-gate-note]');
    const lbKeyForm = lightbox.querySelector('[data-ph-lb-key-form]');
    const lbKeyInput = lightbox.querySelector('[data-ph-lb-key]');
    const lbKeyError = lightbox.querySelector('[data-ph-lb-key-error]');
    const lbPos = lightbox.querySelector('[data-ph-lb-pos]');
    const lbBadge = lightbox.querySelector('[data-ph-lb-badge]');
    const lbAdded = lightbox.querySelector('[data-ph-lb-added]');
    const lbPrev = lightbox.querySelector('[data-ph-lb-prev]');
    const lbNext = lightbox.querySelector('[data-ph-lb-next]');
    const lbFav = lightbox.querySelector('[data-ph-lb-fav]');
    const lbFavLabel = lightbox.querySelector('[data-ph-lb-fav-label]');
    const lbLock = lightbox.querySelector('[data-ph-lb-lock]');
    const lbUnlock = lightbox.querySelector('[data-ph-lb-unlock]');

    const currentId = () => (lbIndex >= 0 && lbList[lbIndex] ? idOf(lbList[lbIndex]) : null);

    const clearStage = () => {
        lbImg.hidden = true;
        lbImg.removeAttribute('src');
        lbSpinner.hidden = true;
        lbGate.hidden = true;
        lbKeyError.hidden = true;
        lbKeyError.textContent = '';
        lbKeyInput.value = '';
    };

    const loadStage = (url, onError) => {
        lbSpinner.hidden = false;
        lbImg.hidden = true;
        lbImg.onload = () => {
            lbSpinner.hidden = true;
            lbImg.hidden = false;
        };
        lbImg.onerror = () => {
            lbSpinner.hidden = true;
            lbImg.hidden = true;
            lbImg.removeAttribute('src');
            onError?.();
        };
        lbImg.src = url;
    };

    const showGate = (entry, message = null) => {
        lbGate.hidden = false;
        lbGateTitle.textContent = entry.protection === 'encrypted' ? 'This photo is encrypted' : 'This photo is gated';
        lbGateNote.textContent = entry.protection === 'encrypted'
            ? 'The file on the drive is scrambled. Its key decrypts it for this one view.'
            : 'The file is readable on the drive; this site asks for the key before serving it.';
        if (message) {
            lbKeyError.textContent = message;
            lbKeyError.hidden = false;
        }
        lbKeyInput.focus();
    };

    const showAt = (index) => {
        if (index < 0 || index >= lbList.length) return;
        lbIndex = index;
        lbKey = null;
        clearStage();

        const entry = meta.get(currentId());
        lbPos.textContent = `${index + 1} of ${lbList.length}`;
        lbAdded.textContent = entry?.added ?? '';

        const locked = entry && entry.protection !== 'none';
        lbBadge.hidden = !locked;
        if (locked) {
            lbBadge.textContent = entry.protection === 'encrypted' ? 'Encrypted' : 'Gated';
            lbBadge.classList.toggle('ph-badge--encrypted', entry.protection === 'encrypted');
        }

        lbLock.hidden = Boolean(locked);
        lbUnlock.hidden = !locked;

        const favorite = lbList[index].dataset.favorite === '1';
        lbFav.setAttribute('aria-pressed', favorite ? 'true' : 'false');
        lbFav.querySelector('i').className = `${favorite ? 'fa-solid' : 'fa-regular'} fa-star`;
        lbFavLabel.textContent = favorite ? 'Favourited' : 'Favourite';

        lbPrev.disabled = index === 0;
        lbNext.disabled = index === lbList.length - 1;

        if (!entry) return;
        if (entry.protection === 'none') loadStage(entry.urls.view, () => showGate(entry, 'That photo could not be loaded.'));
        else showGate(entry);
    };

    const move = (step) => {
        const next = lbIndex + step;
        if (next < 0 || next >= lbList.length) return;
        showAt(next);
    };

    const openLightbox = (tile, opener) => {
        lbList = visibleTiles();
        const index = lbList.indexOf(tile);
        if (index < 0) return;
        returnFocusTo = opener;
        lightbox.showModal();
        showAt(index);
    };

    lbKeyForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const entry = meta.get(currentId());
        if (!entry) return;

        lbKey = lbKeyInput.value;
        if (!lbKey) return;

        lbKeyError.hidden = true;
        lbGate.hidden = true;
        loadStage(`${entry.urls.view}?key=${encodeURIComponent(lbKey)}`, () => {
            lbKey = null;
            showGate(entry, 'Incorrect key.');
        });
    });

    lbPrev.addEventListener('click', () => move(-1));
    lbNext.addEventListener('click', () => move(1));

    lightbox.addEventListener('close', () => {
        clearStage();
        lbIndex = -1;
        lbKey = null;
    });

    // ------------------------------------------------------------ upload

    const uploadForm = uploadDialog.querySelector('form');
    const fileInput = uploadDialog.querySelector('[data-ph-file-input]');
    const dropLabel = uploadDialog.querySelector('[data-ph-drop]');
    const queueEl = uploadDialog.querySelector('[data-ph-queue]');
    const uploadSubmit = uploadDialog.querySelector('[data-ph-upload-submit]');
    const albumSelect = uploadDialog.querySelector('[data-ph-album-select]');
    const albumModeInput = uploadDialog.querySelector('[data-ph-album-mode]');
    const newAlbumField = uploadDialog.querySelector('[data-ph-new-album]');
    const newAlbumInput = uploadDialog.querySelector('#ph-upload-new-album');
    const dropzone = root.querySelector('[data-ph-dropzone]');

    let queue = [];
    let uploading = false;
    let uploadedAny = false;

    const humanSize = (bytes) => (bytes < 1048576
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / 1048576).toFixed(1)} MB`);

    const renderQueue = () => {
        queueEl.replaceChildren(...queue.map((entry) => {
            const li = document.createElement('li');
            li.className = 'ph-file';
            li.dataset.state = entry.state;

            const icon = document.createElement('i');
            icon.className = `ph-file__icon fa-solid ${
                entry.state === 'done' ? 'fa-circle-check'
                    : entry.state === 'failed' ? 'fa-circle-exclamation'
                        : 'fa-image'
            }`;
            icon.setAttribute('aria-hidden', 'true');

            const name = document.createElement('span');
            name.className = 'ph-file__name';
            name.textContent = entry.file.name;

            const size = document.createElement('span');
            size.className = 'ph-file__size';
            size.textContent = humanSize(entry.file.size);

            li.append(icon, name, size);

            if (entry.state === 'failed') {
                const why = document.createElement('p');
                why.className = 'ph-file__why';
                why.textContent = entry.error ?? 'Rejected.';
                li.append(why);
            } else if (entry.state !== 'done') {
                const bar = document.createElement('span');
                bar.className = 'ph-file__bar';
                const fill = document.createElement('i');
                fill.style.width = `${Math.round((entry.progress ?? 0) * 100)}%`;
                entry.fill = fill;
                bar.append(fill);
                li.append(bar);
            }

            return li;
        }));

        uploadSubmit.disabled = uploading || queue.every((entry) => entry.state !== 'waiting');
    };

    const addFiles = (files) => {
        for (const file of files) {
            if (!file) continue;
            queue.push({ file, state: 'waiting', progress: 0, error: null });
        }
        renderQueue();
    };

    const uploadOne = (entry) => new Promise((resolve) => {
        const body = new FormData();
        body.append('_token', uploadForm.querySelector('[name="_token"]').value);
        body.append('photos[]', entry.file);

        const mode = albumModeInput.value || 'none';
        body.append('album_mode', mode);
        if (mode === 'new') body.append('new_album_name', newAlbumInput.value.trim());
        else if (mode === 'existing') body.append('album_name', albumSelect.value);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', data.urls.upload);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) return;
            entry.progress = event.loaded / event.total;
            if (entry.fill) entry.fill.style.width = `${Math.round(entry.progress * 100)}%`;
        });

        const finish = (ok, error) => {
            entry.state = ok ? 'done' : 'failed';
            entry.error = error;
            if (ok) uploadedAny = true;
            renderQueue();
            resolve();
        };

        xhr.addEventListener('load', () => {
            let payload = null;
            try {
                payload = JSON.parse(xhr.responseText);
            } catch {
                // falls through to the status check
            }

            if (xhr.status >= 200 && xhr.status < 300 && payload?.ok) finish(true, null);
            else finish(false, payload?.error ?? payload?.rejected?.[0]?.reason ?? `Rejected by the server (${xhr.status}).`);
        });

        xhr.addEventListener('error', () => finish(false, 'The upload did not reach the server.'));
        xhr.addEventListener('abort', () => finish(false, 'Upload cancelled.'));

        entry.state = 'uploading';
        renderQueue();
        xhr.send(body);
    });

    const runUpload = async () => {
        if (uploading) return;

        if (albumModeInput.value === 'new' && newAlbumInput.value.trim() === '') {
            newAlbumInput.setAttribute('aria-invalid', 'true');
            newAlbumInput.focus();
            return;
        }
        newAlbumInput.removeAttribute('aria-invalid');

        uploading = true;
        renderQueue();
        for (const entry of queue) {
            if (entry.state === 'waiting') await uploadOne(entry);
        }

        uploading = false;
        renderQueue();
        if (queue.every((entry) => entry.state === 'done')) uploadDialog.close();
    };

    uploadForm.addEventListener('submit', (event) => {
        event.preventDefault();
        runUpload();
    });

    fileInput.addEventListener('change', () => {
        addFiles(fileInput.files);
        fileInput.value = '';
    });

    albumSelect.addEventListener('change', () => {
        const isNew = albumSelect.value === '__new__';
        newAlbumField.hidden = !isNew;
        albumModeInput.value = isNew ? 'new' : albumSelect.value ? 'existing' : 'none';
        if (isNew) newAlbumInput.focus();
    });
    albumModeInput.value = albumSelect.value ? 'existing' : 'none';

    uploadDialog.addEventListener('close', () => {
        queue = [];
        renderQueue();
        if (uploadedAny) {
            uploadedAny = false;
            window.Turbo?.visit(window.location.href, { action: 'replace' });
        }
    });

    let dragDepth = 0;
    const hasFiles = (event) => [...(event.dataTransfer?.types ?? [])].includes('Files');

    root.addEventListener('dragenter', (event) => {
        if (!hasFiles(event)) return;
        event.preventDefault();
        dragDepth += 1;
        dropzone.hidden = false;
    });
    root.addEventListener('dragover', (event) => {
        if (!hasFiles(event)) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
    });
    root.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);
        if (dragDepth === 0) dropzone.hidden = true;
    });
    root.addEventListener('drop', (event) => {
        if (!hasFiles(event)) return;
        event.preventDefault();
        dragDepth = 0;
        dropzone.hidden = true;

        const files = [...(event.dataTransfer?.files ?? [])];
        if (files.length === 0) return;

        addFiles(files);
        if (!uploadDialog.open) openDialog(uploadDialog);
    });

    dropLabel.addEventListener('dragover', (event) => {
        if (!hasFiles(event)) return;
        dropLabel.dataset.over = '';
    });
    dropLabel.addEventListener('dragleave', () => delete dropLabel.dataset.over);
    dropLabel.addEventListener('drop', () => delete dropLabel.dataset.over);

    // ------------------------------------------------------------ delegated clicks

    root.addEventListener('click', (event) => {
        const dismiss = event.target.closest('[data-ph-dismiss]');
        if (dismiss) {
            dismiss.closest('[data-ph-dismissable]').remove();
            return;
        }

        const closer = event.target.closest('[data-ph-close]');
        if (closer) {
            closer.closest('dialog').close();
            return;
        }

        const chip = event.target.closest('[data-ph-filter]');
        if (chip) {
            setFilter(chip.dataset.phFilter);
            return;
        }

        if (event.target.closest('[data-ph-select-toggle]')) {
            if (selecting()) clearSelection();
            else {
                manualMode = true;
                paintSelection();
            }
            return;
        }

        if (event.target.closest('[data-ph-selclear]')) {
            clearSelection();
            return;
        }

        const bulk = event.target.closest('[data-ph-bulk]');
        if (bulk) {
            openBulk(bulk.dataset.phBulk, bulk);
            return;
        }

        const opener = event.target.closest('[data-ph-open-dialog]');
        if (opener) {
            const kind = opener.dataset.phOpenDialog;
            if (kind === 'upload') openDialog(uploadDialog, opener);
            else if (kind === 'album') openAlbumDialog('create', {}, opener);
            return;
        }

        const albumAction = event.target.closest('[data-ph-album-action]');
        if (albumAction) {
            const { id, name } = albumAction.dataset;
            if (albumAction.dataset.phAlbumAction === 'rename') {
                openAlbumDialog('rename', { id, name }, albumAction);
            } else {
                const form = albumDeleteDialog.querySelector('form');
                form.action = `${data.urls.album}/${id}`;
                albumDeleteDialog.querySelector('[data-ph-album-delete-name]').textContent = name;
                openDialog(albumDeleteDialog, albumAction);
            }
            return;
        }

        const action = event.target.closest('[data-ph-action]');
        if (action) {
            const id = action.dataset.id ? Number(action.dataset.id) : currentId();
            if (id === null) return;

            if (lightbox.open && action.dataset.id === undefined) lightbox.close();

            actOnPhoto(action.dataset.phAction, id, action);
            return;
        }

        if (event.target.closest('[data-ph-pick]')) return;

        const open = event.target.closest('[data-ph-open]');
        if (!open) return;

        const tile = open.closest('[data-ph-tile]');
        if (!tile) return;

        if (selecting()) {
            togglePick(idOf(tile), { shift: event.shiftKey });
            return;
        }

        openLightbox(tile, open);
    });

    for (const box of root.querySelectorAll('[data-ph-pick]')) {
        box.addEventListener('click', (event) => {
            event.stopPropagation();
            const tile = box.closest('[data-ph-tile]');
            togglePick(idOf(tile), { shift: event.shiftKey });
        });
    }

    // Long press on touch is the selection trigger, matching Extra Notes.
    let pressTimer = null;
    const cancelPress = () => {
        clearTimeout(pressTimer);
        pressTimer = null;
    };

    grid?.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'touch') return;
        const tile = event.target.closest('[data-ph-tile]');
        if (!tile) return;

        pressTimer = setTimeout(() => {
            manualMode = true;
            togglePick(idOf(tile));
            // The tap that follows the press must not also open the photo.
            tile.dataset.longPressed = '';
            setTimeout(() => delete tile.dataset.longPressed, 400);
        }, LONG_PRESS_MS);
    });
    for (const type of ['pointerup', 'pointercancel', 'pointermove', 'scroll']) {
        grid?.addEventListener(type, cancelPress, { passive: true });
    }
    grid?.addEventListener('click', (event) => {
        const tile = event.target.closest('[data-ph-tile]');
        if (tile && 'longPressed' in tile.dataset) {
            event.stopPropagation();
            event.preventDefault();
        }
    }, true);

    // ------------------------------------------------------------ boot

    readUrl();
    applyFilter();
    paintSelection();
    renderQueue();

    page = { root, clearSelection, selecting, lightbox, move, lbKeyInput };
};

const onKeydown = (event) => {
    if (!page) return;

    if (page.lightbox.open && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
        if (document.activeElement === page.lbKeyInput && page.lbKeyInput.value !== '') return;
        event.preventDefault();
        page.move(event.key === 'ArrowLeft' ? -1 : 1);
        return;
    }

    if (event.key !== 'Escape') return;
    if (document.querySelector('.ph dialog[open]')) return;
    if (page.selecting()) page.clearSelection();
};

const resetForCache = () => {
    const root = document.getElementById('ph-root');
    if (!root) return;

    for (const dialog of document.querySelectorAll('.ph dialog[open]')) dialog.close();
    for (const tile of root.querySelectorAll('.ph-tile--picked')) tile.classList.remove('ph-tile--picked');
    for (const box of root.querySelectorAll('[data-ph-pick]')) box.checked = false;
    delete root.dataset.selecting;
    delete root.dataset.phReady;
    page = null;
};

initPhotos();
document.addEventListener('turbo:load', initPhotos);
document.addEventListener('turbo:before-cache', resetForCache);
document.addEventListener('keydown', onKeydown);
