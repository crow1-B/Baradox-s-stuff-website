let page = null;

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
        area.className = 'xn-offscreen';
        document.body.append(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        return ok;
    } catch {
        return false;
    }
};

const isTypingTarget = (node) =>
    node instanceof HTMLElement &&
    (node.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(node.tagName));

const initExtraNotes = () => {
    const root = document.getElementById('xn-root');
    if (!root || root.dataset.xnReady === 'true') return;
    root.dataset.xnReady = 'true';

    const data = JSON.parse(document.getElementById('xn-data').textContent);

    const scroll = root.querySelector('[data-xn-scroll]');
    const list = document.getElementById('xn-stream-list');
    const topSentinel = root.querySelector('[data-xn-top]');
    const topLoading = root.querySelector('[data-xn-top-loading]');
    const topDone = root.querySelector('[data-xn-top-done]');
    const flashSlot = document.getElementById('xn-flash');

    const search = root.querySelector('[data-xn-search]');
    const clearButtons = [...root.querySelectorAll('[data-xn-clear]')];
    const noMatchesNote = root.querySelector('[data-xn-no-matches-note]');
    const emptyNone = root.querySelector('[data-xn-empty="none"]');
    const emptyNoMatches = root.querySelector('[data-xn-empty="no-matches"]');

    const selectToggle = root.querySelector('[data-xn-select-toggle]');
    const selbar = root.querySelector('[data-xn-selbar]');
    const selcount = root.querySelector('[data-xn-selcount]');

    const composer = root.querySelector('[data-xn-composer]');
    const composerInput = root.querySelector('[data-xn-input]');
    const composerSend = root.querySelector('[data-xn-send]');
    const composerTags = [...root.querySelectorAll('[data-xn-composer-tag]')];

    const pinForm = root.querySelector('[data-xn-pin-form]');
    const editDialog = document.getElementById('xn-edit-dialog');
    const tagsDialog = document.getElementById('xn-tags-dialog');
    const deleteDialog = document.getElementById('xn-delete-dialog');
    const bulkDeleteDialog = document.getElementById('xn-bulk-delete-dialog');
    const bulkTagsDialog = document.getElementById('xn-bulk-tags-dialog');
    const dialogs = [editDialog, tagsDialog, deleteDialog, bulkDeleteDialog, bulkTagsDialog];

    // ------------------------------------------------------------ state

    let query = data.q ?? '';
    const activeTags = new Set(data.tags ?? []);
    const selected = new Set();
    let anchorId = null; // last checkbox touched, for shift-click ranges
    let manualMode = false; // the "Select" button, held on with nothing selected
    let oldestId = data.oldestId ?? null;
    let hasMore = Boolean(data.hasMore);
    let loadingOlder = false;
    let returnFocusTo = null;

    const cards = () => [...list.querySelectorAll('[data-xn-msg]')];
    const cardById = (id) => document.getElementById(`xn-msg-${id}`);
    const isFiltering = () => query !== '' || activeTags.size > 0;

    const scrollToBottom = () => {
        scroll.scrollTop = scroll.scrollHeight;
    };

    let atBottom = true;
    scroll.addEventListener(
        'scroll',
        () => (atBottom = scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 80),
        { passive: true },
    );

    let followUntil = 0;
    const followBottom = () => {
        followUntil = performance.now() + 1500;
        scrollToBottom();
    };

    let prepending = false;

    // ------------------------------------------------------------ flash
    const flash = (message, kind = 'success') => {
        const banner = document.createElement('div');
        banner.className = `xn-banner xn-banner--${kind}`;
        banner.dataset.xnBanner = '';

        const icon = document.createElement('i');
        icon.className = `fa-solid ${kind === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'}`;
        icon.setAttribute('aria-hidden', 'true');

        const text = document.createElement('p');
        text.textContent = message;

        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'xn-act';
        dismiss.dataset.xnDismiss = '';
        dismiss.setAttribute('aria-label', 'Dismiss');
        dismiss.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

        banner.append(icon, text, dismiss);
        flashSlot.replaceChildren(banner);
    };

    // ------------------------------------------------------------ empty states

    const describeFilter = () => {
        const bits = [];
        if (query) bits.push(`“${query}”`);
        if (activeTags.size) {
            bits.push(`tagged ${[...activeTags].map((key) => data.labels[key] ?? key).join(' or ')}`);
        }
        return bits.join(', ');
    };

    const refreshEmpty = () => {
        const empty = list.children.length === 0;

        emptyNone.hidden = !(empty && !isFiltering());
        emptyNoMatches.hidden = !(empty && isFiltering());

        if (noMatchesNote && !emptyNoMatches.hidden) {
            noMatchesNote.textContent = `No message matches ${describeFilter()}.`;
        }

        for (const button of clearButtons) button.hidden = !isFiltering();
    };

    const refreshTop = () => {
        topDone.hidden = hasMore || list.children.length === 0;
    };

    // ------------------------------------------------------------ selection

    const paintSelection = () => {
        for (const card of cards()) {
            const on = selected.has(Number(card.dataset.id));
            card.classList.toggle('xn-msg--picked', on);
            const box = card.querySelector('[data-xn-pick]');
            if (box) box.checked = on;
        }

        const selecting = manualMode || selected.size > 0;
        if (selecting) root.dataset.selecting = 'true';
        else delete root.dataset.selecting;

        selectToggle.setAttribute('aria-pressed', selecting ? 'true' : 'false');
        selbar.hidden = selected.size === 0;
        selcount.textContent = `${selected.size} selected`;
    };

    const clearSelection = ({ keepMode = false } = {}) => {
        selected.clear();
        anchorId = null;
        if (!keepMode) manualMode = false;
        paintSelection();
    };

    const selectRange = (fromId, toId, value) => {
        const ids = cards().map((card) => Number(card.dataset.id));
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

    const afterMutation = () => {
        const present = new Set(cards().map((card) => Number(card.dataset.id)));
        for (const id of [...selected]) {
            if (!present.has(id)) selected.delete(id);
        }

        paintSelection();
        refreshEmpty();
        refreshTop();

        if (!prepending && (atBottom || performance.now() < followUntil)) scrollToBottom();
    };

    const mutations = new MutationObserver(() => afterMutation());
    mutations.observe(list, { childList: true });

    // ------------------------------------------------------------ filtering

    const writeUrl = () => {
        const url = new URL(window.location.href);
        const set = (key, value) => (value ? url.searchParams.set(key, value) : url.searchParams.delete(key));
        set('q', query);
        set('tags', [...activeTags].join(','));
        history.replaceState(history.state, '', url);
    };

    const filterParams = (extra = {}) => {
        const params = new URLSearchParams();
        if (query) params.set('q', query);
        if (activeTags.size) params.set('tags', [...activeTags].join(','));
        for (const [key, value] of Object.entries(extra)) params.set(key, value);
        return params;
    };

    const fetchMessages = async (params) => {
        const response = await fetch(`${data.messagesUrl}?${params}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) throw new Error(`Messages request failed (${response.status})`);

        return response.json();
    };

    let searchTimer;

    let filterTicket = 0;

    const applyFilter = async () => {
        clearTimeout(searchTimer);

        const ticket = ++filterTicket;
        clearSelection({ keepMode: true });
        writeUrl();

        try {
            const body = await fetchMessages(filterParams());
            if (ticket !== filterTicket) return;

            list.innerHTML = body.html;
            oldestId = body.oldest_id;
            hasMore = body.has_more;
            afterMutation();
            scrollToBottom();
        } catch {
            if (ticket === filterTicket) {
                flash('Could not reach the server — the list below may be out of date.', 'error');
            }
        }
    };

    if (search) {
        for (const type of ['input', 'search']) {
            search.addEventListener(type, () => {
                query = search.value.trim();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyFilter, 250);
            });
        }
    }

    // ------------------------------------------------------------ backward pagination

    const loadOlder = async () => {
        if (loadingOlder || !hasMore || oldestId === null) return;

        loadingOlder = true;
        topLoading.hidden = false;

        try {
            const body = await fetchMessages(filterParams({ before: oldestId }));
            topLoading.hidden = true;
            const heightBefore = scroll.scrollHeight;
            const topBefore = scroll.scrollTop;

            prepending = true;
            list.insertAdjacentHTML('afterbegin', body.html);
            scroll.scrollTop = topBefore + (scroll.scrollHeight - heightBefore);

            hasMore = body.has_more;
            if (body.oldest_id) oldestId = body.oldest_id;
            afterMutation();
            queueMicrotask(() => (prepending = false));
        } catch {
            flash('Could not load older messages.', 'error');
        } finally {
            loadingOlder = false;
            topLoading.hidden = true;
        }
    };

    const sentinel = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) loadOlder();
        },
        { root: scroll, rootMargin: '300px 0px 0px 0px' },
    );
    sentinel.observe(topSentinel);

    // ------------------------------------------------------------ dialogs

    const openDialog = (dialog, opener) => {
        returnFocusTo = opener ?? null;
        if (!dialog.open) dialog.showModal();
    };

    for (const dialog of dialogs) {
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
    }

    // ------------------------------------------------------------ tag pickers

    const capPicker = (inputs, max) => {
        const chosen = inputs.filter((input) => input.checked).length;
        for (const input of inputs) input.disabled = !input.checked && chosen >= max;
    };

    const tagsDialogInputs = [...tagsDialog.querySelectorAll('[data-xn-tagpick]')];

    for (const [inputs, container] of [
        [composerTags, composer],
        [tagsDialogInputs, tagsDialog],
    ]) {
        container.addEventListener('change', (event) => {
            if (inputs.includes(event.target)) capPicker(inputs, data.maxTags);
        });
    }

    // ------------------------------------------------------------ composer

    const growComposer = () => {
        composerInput.style.height = 'auto';
        composerInput.style.height = `${composerInput.scrollHeight}px`;
    };

    const refreshSend = () => {
        composerSend.disabled = composerInput.value.trim() === '';
    };

    const resetComposer = () => {
        composerInput.value = '';
        for (const input of composerTags) {
            input.checked = false;
            input.disabled = false;
        }
        growComposer();
        refreshSend();
    };

    composerInput.addEventListener('input', () => {
        growComposer();
        refreshSend();
    });

    composerInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;

        event.preventDefault();
        if (composerInput.value.trim() !== '') composer.requestSubmit();
    });

    composer.addEventListener('submit', (event) => {
        if (composerInput.value.trim() === '') event.preventDefault();
    });

    composer.addEventListener('turbo:submit-end', (event) => {
        if (!event.detail.success) return;

        resetComposer();
        composerInput.focus();
        if (isFiltering()) {
            query = '';
            if (search) search.value = '';
            activeTags.clear();
            for (const button of root.querySelectorAll('[data-xn-tagfilter]')) {
                button.classList.remove('xn-tagbtn--on');
                button.setAttribute('aria-pressed', 'false');
            }
            applyFilter();
            return;
        }

        followBottom();
    });

    // ------------------------------------------------------------ the shared forms

    const editForm = editDialog.querySelector('form');
    const tagsForm = tagsDialog.querySelector('form');
    const deleteForm = deleteDialog.querySelector('form');
    const bulkDeleteForm = bulkDeleteDialog.querySelector('form');
    const bulkTagsForm = bulkTagsDialog.querySelector('form');

    for (const [form, dialog] of [
        [editForm, editDialog],
        [tagsForm, tagsDialog],
        [deleteForm, deleteDialog],
        [bulkDeleteForm, bulkDeleteDialog],
        [bulkTagsForm, bulkTagsDialog],
    ]) {
        form.addEventListener('turbo:submit-end', (event) => {
            if (!event.detail.success) return;
            dialog.close();
            if (form === bulkDeleteForm || form === bulkTagsForm) clearSelection();
        });
    }

    const fillIds = (form) => {
        const holder = form.querySelector('[data-xn-ids]');
        holder.replaceChildren(
            ...[...selected].map((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = String(id);
                return input;
            }),
        );
    };

    // ------------------------------------------------------------ opening each action

    const openEdit = (card, opener) => {
        editForm.action = `${data.base}/${card.dataset.id}`;
        const area = editForm.querySelector('#xn-edit-content');
        area.value = card.dataset.content;
        openDialog(editDialog, opener);
        area.focus();
        area.setSelectionRange(area.value.length, area.value.length);
    };

    const openTags = (card, opener) => {
        tagsForm.action = `${data.base}/${card.dataset.id}/tags`;
        const current = (card.dataset.tags || '').split(',').filter(Boolean);
        for (const input of tagsDialogInputs) input.checked = current.includes(input.value);
        capPicker(tagsDialogInputs, data.maxTags);
        openDialog(tagsDialog, opener);
    };

    const openDelete = (card, opener) => {
        deleteForm.action = `${data.base}/${card.dataset.id}`;
        deleteDialog.querySelector('[data-xn-delete-preview]').textContent = card.dataset.content;
        openDialog(deleteDialog, opener);
        deleteDialog.querySelector('[type="submit"]').focus();
    };

    const openBulkDelete = (opener) => {
        if (selected.size === 0) return;
        if (selected.size > data.maxBulk) {
            flash(`That is more than ${data.maxBulk} messages at once.`, 'error');
            return;
        }

        fillIds(bulkDeleteForm);
        // "Delete 23 messages" is a different action from deleting one, so the dialog says so.
        const count = selected.size;
        bulkDeleteDialog.querySelector('[data-xn-bulk-delete-title]').textContent =
            `Delete ${count} message${count === 1 ? '' : 's'}?`;
        openDialog(bulkDeleteDialog, opener);
        bulkDeleteDialog.querySelector('[data-xn-bulk-delete-submit]').focus();
    };

    const openBulkTags = (opener) => {
        if (selected.size === 0) return;

        fillIds(bulkTagsForm);
        const count = selected.size;
        bulkTagsDialog.querySelector('[data-xn-bulk-tags-title]').textContent =
            `Tag ${count} message${count === 1 ? '' : 's'}`;
        openDialog(bulkTagsDialog, opener);
    };

    const togglePin = (id) => {
        pinForm.action = `${data.base}/${id}/pin`;
        pinForm.requestSubmit();
    };

    const jumpTo = (id) => {
        const card = cardById(id);

        if (!card) {
            flash('That message is not in view. Clear the filter, or scroll up to load it.', 'error');
            return;
        }

        card.scrollIntoView({ block: 'center', behavior: 'smooth' });
        card.classList.add('xn-msg--flash');
        setTimeout(() => card.classList.remove('xn-msg--flash'), 1200);
    };

    // ------------------------------------------------------------ delegated clicks

    root.addEventListener('click', async (event) => {
        const box = event.target.closest('[data-xn-pick]');
        if (box) {
            togglePick(Number(box.closest('[data-xn-msg]').dataset.id), { shift: event.shiftKey });
            return;
        }

        if (event.target.closest('[data-xn-dismiss]')) {
            flashSlot.replaceChildren();
            return;
        }

        const closer = event.target.closest('[data-xn-close]');
        if (closer) {
            closer.closest('dialog').close();
            return;
        }

        const clear = event.target.closest('[data-xn-clear]');
        if (clear) {
            query = '';
            if (search) search.value = '';
            activeTags.clear();
            for (const button of root.querySelectorAll('[data-xn-tagfilter]')) {
                button.classList.remove('xn-tagbtn--on');
                button.setAttribute('aria-pressed', 'false');
            }
            applyFilter();
            return;
        }

        const tagFilter = event.target.closest('[data-xn-tagfilter]');
        if (tagFilter) {
            const key = tagFilter.dataset.xnTagfilter;
            const on = !activeTags.has(key);
            if (on) activeTags.add(key);
            else activeTags.delete(key);
            tagFilter.classList.toggle('xn-tagbtn--on', on);
            tagFilter.setAttribute('aria-pressed', on ? 'true' : 'false');
            applyFilter();
            return;
        }

        if (event.target.closest('[data-xn-select-toggle]')) {
            manualMode = !manualMode;
            if (!manualMode) selected.clear();
            paintSelection();
            return;
        }

        if (event.target.closest('[data-xn-selcancel]')) {
            clearSelection();
            return;
        }

        const bulk = event.target.closest('[data-xn-bulk]');
        if (bulk) {
            if (bulk.dataset.xnBulk === 'delete') openBulkDelete(bulk);
            else openBulkTags(bulk);
            return;
        }

        const pinnedToggle = event.target.closest('[data-xn-pinned-toggle]');
        if (pinnedToggle) {
            const open = pinnedToggle.getAttribute('aria-expanded') === 'true';
            pinnedToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            document.getElementById('xn-pinned-list').hidden = open;
            return;
        }

        const jump = event.target.closest('[data-xn-jump]');
        if (jump) {
            jumpTo(Number(jump.dataset.xnJump));
            return;
        }

        const act = event.target.closest('[data-xn-act]');
        if (!act) return;

        const id = Number(act.dataset.id);
        const kind = act.dataset.xnAct;

        if (kind === 'pin') {
            togglePin(id);
            return;
        }

        const card = cardById(id);
        if (!card) return;

        if (kind === 'copy') {
            const ok = await copyText(card.dataset.content);
            flash(ok ? 'Copied.' : 'Couldn’t copy — select the text and copy it by hand.', ok ? 'success' : 'error');
        } else if (kind === 'edit') openEdit(card, act);
        else if (kind === 'tags') openTags(card, act);
        else if (kind === 'delete') openDelete(card, act);
    });

    // ------------------------------------------------------------ long press (touch)

    let pressTimer = null;
    const cancelPress = () => {
        clearTimeout(pressTimer);
        pressTimer = null;
    };

    list.addEventListener(
        'touchstart',
        (event) => {
            const card = event.target.closest('[data-xn-msg]');
            if (!card) return;
            cancelPress();
            pressTimer = setTimeout(() => {
                pressTimer = null;
                togglePick(Number(card.dataset.id));
            }, 500);
        },
        { passive: true },
    );

    for (const type of ['touchmove', 'touchend', 'touchcancel']) {
        list.addEventListener(type, cancelPress, { passive: true });
    }

    // ------------------------------------------------------------ boot

    page = {
        root,
        scroll,
        search,
        composerInput,
        exitSelection: () => {
            if (manualMode || selected.size > 0) {
                clearSelection();
                return true;
            }
            return false;
        },
        teardown: () => {
            mutations.disconnect();
            sentinel.disconnect();
        },
    };

    refreshSend();
    growComposer();
    refreshEmpty();
    refreshTop();
    paintSelection();
    scrollToBottom();
    requestAnimationFrame(scrollToBottom);
};

const resetForCache = () => {
    const root = document.getElementById('xn-root');
    if (!root) return;

    for (const dialog of root.querySelectorAll('dialog[open]')) dialog.close();

    for (const box of root.querySelectorAll('[data-xn-pick]')) box.checked = false;
    for (const card of root.querySelectorAll('[data-xn-msg]')) card.classList.remove('xn-msg--picked');
    root.querySelector('[data-xn-selbar]').hidden = true;
    root.querySelector('[data-xn-select-toggle]').setAttribute('aria-pressed', 'false');
    delete root.dataset.selecting;

    page?.teardown();
    page = null;
    delete root.dataset.xnReady;
};

document.addEventListener('keydown', (event) => {
    if (!page) return;

    if (event.key === 'Escape') {
        if (document.querySelector('.xn-dialog[open]')) return;
        if (page.exitSelection()) event.preventDefault();
        return;
    }

    if (event.ctrlKey || event.metaKey || event.altKey || isTypingTarget(event.target)) return;
    if (event.key === 'c' || event.key === 'C') {
        event.preventDefault();
        page.composerInput.focus();
    } else if (event.key === '/') {
        event.preventDefault();
        page.search?.focus();
    }
});

initExtraNotes();
document.addEventListener('turbo:load', initExtraNotes);
document.addEventListener('turbo:before-cache', resetForCache);
