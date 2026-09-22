let page = null;

const SEEK_STEP = 5;
const COPIED_MS = 1600;

const initVideos = () => {
    const root = document.getElementById('vd-root');
    if (!root || root.dataset.vdReady === 'true') return;
    root.dataset.vdReady = 'true';

    const data = JSON.parse(document.getElementById('vd-data').textContent);
    const meta = new Map((data.videos ?? []).map((video) => [video.id, video]));
    const urls = data.urls ?? {};

    const favForm = root.querySelector('[data-vd-fav-form]');
    const lockForm = root.querySelector('[data-vd-lock-form]');
    const unlockForm = root.querySelector('[data-vd-unlock-form]');
    const deleteForm = root.querySelector('[data-vd-delete-form]');

    const playerDialog = document.getElementById('vd-player-dialog');
    const registerDialog = document.getElementById('vd-register-dialog');
    const lockDialog = document.getElementById('vd-lock-dialog');
    const unlockDialog = document.getElementById('vd-unlock-dialog');
    const deleteDialog = document.getElementById('vd-delete-dialog');
    const albumDialog = document.getElementById('vd-album-dialog');
    const albumDeleteDialog = document.getElementById('vd-album-delete-dialog');

    const dialogs = [
        playerDialog, registerDialog, lockDialog, unlockDialog,
        deleteDialog, albumDialog, albumDeleteDialog,
    ].filter(Boolean);

    let returnFocusTo = null;

    // ------------------------------------------------------------ dialogs

    const openDialog = (dialog, focus = null) => {
        if (!dialog) return;
        returnFocusTo = document.activeElement;
        dialog.showModal();
        if (focus) focus.focus();
    };

    for (const dialog of dialogs) {
        dialog.addEventListener('close', () => {
            if (returnFocusTo && document.contains(returnFocusTo)) returnFocusTo.focus();
            returnFocusTo = null;
        });
    }

    // ------------------------------------------------------------ the player

    const video = playerDialog.querySelector('[data-vd-video]');
    const plTitle = playerDialog.querySelector('[data-vd-pl-title]');
    const plBadge = playerDialog.querySelector('[data-vd-pl-badge]');
    const plLen = playerDialog.querySelector('[data-vd-pl-len]');
    const plFile = playerDialog.querySelector('[data-vd-pl-file]');
    const gate = playerDialog.querySelector('[data-vd-pl-gate]');
    const gateForm = playerDialog.querySelector('[data-vd-key-form]');
    const keyInput = playerDialog.querySelector('[data-vd-key]');
    const keyError = playerDialog.querySelector('[data-vd-key-error]');
    const failed = playerDialog.querySelector('[data-vd-pl-failed]');
    const failedNote = playerDialog.querySelector('[data-vd-pl-failed-note]');

    let current = null;

    const tearDown = () => {
        video.pause();
        video.removeAttribute('src');
        video.load();
        video.hidden = true;
    };

    const play = (src) => {
        gate.hidden = true;
        failed.hidden = true;
        video.hidden = false;
        video.src = src;
        video.play().catch(() => {});
    };

    const showFailure = (note) => {
        tearDown();
        gate.hidden = true;
        failedNote.textContent = note;
        failed.hidden = false;
    };

    const openPlayer = (id) => {
        const info = meta.get(id);
        if (!info || info.missing) return;

        current = info;

        plTitle.textContent = info.title;
        plBadge.hidden = info.protection !== 'gated';
        plLen.textContent = info.durationLabel ?? '';
        plFile.textContent = info.filename;

        keyInput.value = '';
        keyError.hidden = true;
        failed.hidden = true;

        if (info.protection === 'gated') {
            tearDown();
            gate.hidden = false;
            openDialog(playerDialog, keyInput);
            return;
        }

        gate.hidden = true;
        openDialog(playerDialog);
        play(info.urls.stream);
    };

    gateForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const key = keyInput.value;
        if (!key || !current) return;

        keyError.hidden = true;
        play(current.urls.stream + '?key=' + encodeURIComponent(key));
    });

    video.addEventListener('error', () => {
        if (!current || !video.getAttribute('src')) return;

        if (current.protection === 'gated') {
            video.hidden = true;
            gate.hidden = false;
            keyError.textContent = 'That key was not accepted, or the file could not be read.';
            keyError.hidden = false;
            keyInput.focus();
            keyInput.select();
            return;
        }

        showFailure('The file could not be read from the drive. It may have been renamed or moved, or the drive may not be mounted.');
    });

    playerDialog.addEventListener('close', () => {
        tearDown();
        keyInput.value = '';
        keyError.hidden = true;
        failed.hidden = true;
        current = null;
    });

    // ------------------------------------------------------------ register dialog

    const registerForm = root.querySelector('[data-vd-register-form]');
    const filenameInput = registerForm?.querySelector('[data-vd-filename]');
    const manualToggle = registerForm?.querySelector('[data-vd-manual]');
    const manualField = registerForm?.querySelector('[data-vd-manual-field]');
    const pickedRow = registerForm?.querySelector('[data-vd-picked]');
    const pickedName = registerForm?.querySelector('[data-vd-picked-name]');
    const pickButtons = registerForm ? [...registerForm.querySelectorAll('[data-vd-pick]')] : [];

    const paintFilename = () => {
        if (!filenameInput) return;

        const manual = manualToggle?.checked ?? true;
        const value = filenameInput.value.trim();

        if (manualField) manualField.hidden = !manual;
        filenameInput.required = manual;

        for (const button of pickButtons) {
            button.setAttribute('aria-pressed', button.dataset.name === value ? 'true' : 'false');
        }

        if (pickedRow) {
            const show = !manual && value !== '';
            pickedRow.hidden = !show;
            if (show && pickedName) pickedName.textContent = value;
        }
    };

    manualToggle?.addEventListener('change', () => {
        paintFilename();
        if (manualToggle.checked) filenameInput?.focus();
    });

    filenameInput?.addEventListener('input', paintFilename);

    // ------------------------------------------------------------ album dialogs

    const albumForm = root.querySelector('[data-vd-album-form]');
    const albumMethod = root.querySelector('[data-vd-album-method]');
    const albumHeading = root.querySelector('[data-vd-album-heading]');
    const albumSubmit = root.querySelector('[data-vd-album-submit]');
    const albumName = document.getElementById('vd-album-name');
    const albumDeleteForm = root.querySelector('[data-vd-album-delete-form]');
    const albumDeleteName = root.querySelector('[data-vd-album-delete-name]');

    const openAlbumDialog = (mode, id = null, name = '') => {
        if (!albumForm) return;

        const renaming = mode === 'rename';
        albumForm.action = renaming ? `${urls.album}/${id}` : albumForm.dataset.storeAction;
        albumMethod.innerHTML = renaming ? '<input type="hidden" name="_method" value="PATCH">' : '';
        albumHeading.textContent = renaming ? 'Rename album' : 'New album';
        albumSubmit.textContent = renaming ? 'Save name' : 'Create album';
        albumName.value = renaming ? name : '';

        openDialog(albumDialog, albumName);
    };

    if (albumForm) albumForm.dataset.storeAction = albumForm.getAttribute('action');

    // ------------------------------------------------------------ the shared forms

    const submitFor = (form, id, suffix = '') => {
        form.action = `${urls.video}/${id}${suffix}`;
        form.requestSubmit();
    };

    // ------------------------------------------------------------ copy the watch link

    const copyLink = async (button) => {
        const url = button.dataset.url;
        let ok = false;

        try {
            await navigator.clipboard.writeText(url);
            ok = true;
        } catch {
            const scratch = document.createElement('textarea');
            scratch.value = url;
            scratch.setAttribute('readonly', '');
            scratch.className = 'vd-sr';
            root.appendChild(scratch);
            scratch.select();
            try {
                ok = document.execCommand('copy');
            } catch {
                ok = false;
            }
            scratch.remove();
        }

        const icon = button.querySelector('i');
        const url_el = button.closest('.vd-link')?.querySelector('.vd-link__url');

        if (!ok) {
            if (url_el) {
                const range = document.createRange();
                range.selectNodeContents(url_el);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
            }
            button.dataset.copyFailed = '';
            button.setAttribute('aria-label', 'Could not copy automatically — the link is selected, press Ctrl+C');
            if (icon) icon.className = 'fa-solid fa-triangle-exclamation';
        } else {
            button.dataset.copied = '';
            if (icon) icon.className = 'fa-solid fa-check';
        }

        const label = button.getAttribute('aria-label');

        window.clearTimeout(button.dataset.timer);
        button.dataset.timer = window.setTimeout(() => {
            delete button.dataset.copied;
            delete button.dataset.copyFailed;
            if (icon) icon.className = 'fa-regular fa-copy';
            if (!ok) button.setAttribute('aria-label', label.replace(/^Could not copy.*$/, 'Copy the watch link'));
        }, COPIED_MS);
    };

    // ------------------------------------------------------------ delegated clicks

    root.addEventListener('click', (event) => {
        const dismiss = event.target.closest('[data-vd-dismiss]');
        if (dismiss) {
            dismiss.closest('[data-vd-dismissable]').remove();
            return;
        }

        const closer = event.target.closest('[data-vd-close]');
        if (closer) {
            closer.closest('dialog').close();
            return;
        }

        const opener = event.target.closest('[data-vd-open-dialog]');
        if (opener) {
            if (opener.dataset.vdOpenDialog === 'register') {
                paintFilename();
                openDialog(registerDialog, document.getElementById('vd-title'));
            } else {
                openAlbumDialog('new');
            }
            return;
        }

        const pick = event.target.closest('[data-vd-pick]');
        if (pick) {
            filenameInput.value = pick.dataset.name;
            paintFilename();
            return;
        }

        const copy = event.target.closest('[data-vd-copy]');
        if (copy) {
            copyLink(copy);
            return;
        }

        const watch = event.target.closest('[data-vd-watch]');
        if (watch) {
            openPlayer(Number(watch.dataset.id));
            return;
        }

        const albumAction = event.target.closest('[data-vd-album-action]');
        if (albumAction) {
            const { id, name } = albumAction.dataset;
            if (albumAction.dataset.vdAlbumAction === 'rename') {
                openAlbumDialog('rename', id, name);
            } else {
                albumDeleteForm.action = `${urls.album}/${id}`;
                albumDeleteName.textContent = name;
                openDialog(albumDeleteDialog);
            }
            return;
        }

        const action = event.target.closest('[data-vd-action]');
        if (!action) return;

        const id = Number(action.dataset.id);

        switch (action.dataset.vdAction) {
            case 'favorite':
                submitFor(favForm, id, '/favorite');
                break;
            case 'lock':
                lockForm.action = `${urls.video}/${id}/lock`;
                openDialog(lockDialog, document.getElementById('vd-lock-key'));
                break;
            case 'unlock':
                unlockForm.action = `${urls.video}/${id}/unlock`;
                openDialog(unlockDialog, document.getElementById('vd-unlock-key'));
                break;
            case 'delete':
                deleteForm.action = `${urls.video}/${id}`;
                openDialog(deleteDialog);
                break;
        }
    });

    root.addEventListener('error', (event) => {
        const img = event.target;
        if (!(img instanceof HTMLImageElement) || !('vdPoster' in img.dataset)) return;

        img.hidden = true;
        const blank = img.parentElement?.querySelector('[data-vd-blank]');
        if (blank) blank.hidden = false;
    }, true);

    // ------------------------------------------------------------ arriving from a slug link

    if (data.highlightId) {
        const card = document.getElementById(`video-${data.highlightId}`);

        if (card) {
            const url = new URL(window.location.href);
            url.searchParams.delete('highlight');
            history.replaceState(history.state, '', url);

            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card.classList.add('vd-flash');
        }
    }

    // ------------------------------------------------------------ boot

    paintFilename();

    page = { root, playerDialog, video, gate, keyInput };
};

const onKeydown = (event) => {
    if (!page || !page.playerDialog.open) return;

    const video = page.video;
    if (video.hidden || !video.getAttribute('src')) return;

    if (document.activeElement === page.keyInput) return;

    if (document.activeElement === video) return;

    if (event.key === ' ' || event.key === 'Spacebar') {
        event.preventDefault();
        if (video.paused) video.play().catch(() => {});
        else video.pause();
        return;
    }

    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        if (!Number.isFinite(video.duration)) return;
        event.preventDefault();
        const step = event.key === 'ArrowLeft' ? -SEEK_STEP : SEEK_STEP;
        video.currentTime = Math.min(Math.max(0, video.currentTime + step), video.duration);
    }
};

const resetForCache = () => {
    const root = document.getElementById('vd-root');
    if (!root) return;

    const video = root.querySelector('[data-vd-video]');
    if (video) {
        video.pause();
        video.removeAttribute('src');
        video.load();
        video.hidden = true;
    }

    for (const dialog of document.querySelectorAll('.vd dialog[open]')) dialog.close();
    for (const flashed of root.querySelectorAll('.vd-flash')) flashed.classList.remove('vd-flash');

    delete root.dataset.vdReady;
    page = null;
};

initVideos();
document.addEventListener('turbo:load', initVideos);
document.addEventListener('turbo:before-cache', resetForCache);
document.addEventListener('keydown', onKeydown);
