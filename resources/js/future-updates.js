const initFutureUpdates = () => {
    const root = document.getElementById('fu-root');
    if (!root || root.dataset.fuReady === 'true') return;
    root.dataset.fuReady = 'true';

    const data = JSON.parse(document.getElementById('fu-data').textContent);
    const updates = data.updates ?? {};

    // ---------------------------------------------------------------- dialogs

    const editDialog = document.getElementById('fu-edit-dialog');
    const deleteDialog = document.getElementById('fu-delete-dialog');
    const editForm = editDialog.querySelector('form');
    const deleteForm = deleteDialog.querySelector('form');
    let returnFocusTo = null;

    for (const dialog of [editDialog, deleteDialog]) {
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

    const editContent = editForm.querySelector('#fu-edit-content');
    const editProject = editForm.querySelector('#fu-edit-project');
    const editErrors = editForm.querySelector('[data-fu-errors]');
    const orphanNote = editForm.querySelector('[data-fu-orphan-note]');
    const plainNote = editForm.querySelector('[data-fu-plain-note]');

    const openEditDialog = (update, { values = null, errors = null, opener = null } = {}) => {
        editForm.action = update.urls.update;
        editForm.elements._modal.value = `edit:${update.id}`;

        const source = values ?? update;
        editContent.value = source.content ?? '';
        editProject.value = source.project_id == null ? '' : String(source.project_id);
        const orphanedTitle = update.orphaned_project_title;
        orphanNote.textContent = orphanedTitle
            ? `This was planned for “${orphanedTitle}”, which has been deleted. Saving files it under whatever you pick here.`
            : '';
        orphanNote.hidden = !orphanedTitle;
        plainNote.hidden = Boolean(orphanedTitle);

        editErrors.replaceChildren();
        editErrors.hidden = !(errors && errors.length);
        if (errors) {
            for (const message of errors) {
                const item = document.createElement('li');
                item.textContent = message;
                editErrors.append(item);
            }
        }

        returnFocusTo = opener;
        editDialog.showModal();
        editContent.focus();
    };

    const openDeleteDialog = (update, opener) => {
        deleteForm.action = update.urls.destroy;
        deleteForm.querySelector('[data-fu-delete-preview]').textContent = update.content;
        returnFocusTo = opener;
        deleteDialog.showModal();
    };

    // ---------------------------------------------------------------- view state

    const viewButtons = [...root.querySelectorAll('[data-fu-view]')];
    const doneToggle = root.querySelector('[data-fu-done-toggle]');
    const emptyView = root.querySelector('[data-fu-empty-view]');
    const groups = [...root.querySelectorAll('[data-fu-group]')];

    const openTotal = root.querySelectorAll('[data-fu-panel="flat"] .fu-row[data-done="0"]').length;
    const total = root.querySelectorAll('[data-fu-panel="flat"] .fu-row').length;

    const writeUrl = () => {
        const url = new URL(window.location.href);
        const set = (key, value) => (value ? url.searchParams.set(key, value) : url.searchParams.delete(key));

        set('view', root.dataset.view === 'flat' ? 'flat' : '');
        set('done', root.dataset.done === 'shown' ? '1' : '');

        history.replaceState(history.state, '', url);
    };

    const paintEmptyStates = () => {
        const showingDone = root.dataset.done === 'shown';

        for (const group of groups) {
            const empty = group.querySelector('[data-fu-group-empty]');
            if (empty) empty.hidden = showingDone || Number(group.dataset.open || 0) > 0;
        }

        if (emptyView) emptyView.hidden = showingDone || total === 0 || openTotal > 0;
    };

    for (const button of viewButtons) {
        button.addEventListener('click', () => {
            root.dataset.view = button.dataset.fuView;
            for (const other of viewButtons) {
                other.setAttribute('aria-pressed', String(other === button));
            }
            writeUrl();
        });
    }

    if (doneToggle) {
        doneToggle.addEventListener('click', () => {
            const showing = doneToggle.getAttribute('aria-pressed') === 'true';
            doneToggle.setAttribute('aria-pressed', String(!showing));
            root.dataset.done = showing ? 'hidden' : 'shown';
            paintEmptyStates();
            writeUrl();
        });
    }

    // ---------------------------------------------------------------- delegated events

    root.addEventListener('change', (event) => {
        const check = event.target.closest('[data-fu-done-check]');
        if (check) check.closest('form').requestSubmit();
    });

    root.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-fu-open]');
        if (opener) {
            const update = updates[opener.dataset.update];
            if (!update) return;

            if (opener.dataset.fuOpen === 'edit') openEditDialog(update, { opener });
            else openDeleteDialog(update, opener);
            return;
        }

        const closer = event.target.closest('[data-fu-close]');
        if (closer) {
            closer.closest('dialog').close();
            return;
        }

        const dismiss = event.target.closest('[data-fu-dismiss]');
        if (dismiss) dismiss.closest('[data-fu-dismissable]').remove();
    });

    // ---------------------------------------------------------------- boot

    paintEmptyStates();
    const [kind, id] = (data.context ?? '').split(':');
    if (kind === 'edit' && updates[id]) {
        openEditDialog(updates[id], { values: data.old ?? {}, errors: data.errors });
    }
};

const resetForCache = () => {
    const root = document.getElementById('fu-root');
    if (!root) return;

    for (const dialog of root.querySelectorAll('dialog[open]')) dialog.close();
    delete root.dataset.fuReady;
};

initFutureUpdates();
document.addEventListener('turbo:load', initFutureUpdates);
document.addEventListener('turbo:before-cache', resetForCache);
