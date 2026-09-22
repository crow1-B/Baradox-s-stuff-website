const initProjects = () => {
    const root = document.getElementById('pj-root');
    if (!root || root.dataset.pjReady === 'true') return;
    root.dataset.pjReady = 'true';

    const data = JSON.parse(document.getElementById('pj-data').textContent);
    const projects = data.projects ?? {};

    const FORM_FIELDS = ['title', 'description', 'languages', 'people', 'start_date', 'finish_date', 'root_path', 'lines_of_code', 'github_link'];
    const TRANSITION_FIELDS = ['start_date', 'finish_date', 'root_path', 'lines_of_code'];

    const VISIBLE = {
        future: ['title', 'status', 'description', 'languages', 'people', 'start_date', 'github_link'],
        in_work: ['title', 'status', 'description', 'languages', 'people', 'start_date', 'finish_date', 'root_path', 'github_link'],
        done: ['title', 'status', 'description', 'languages', 'people', 'start_date', 'finish_date', 'root_path', 'lines_of_code', 'github_link'],
    };

    // start_date/finish_date mean "expected" or "actual" depending on status.
    const DATE_LABELS = {
        future: { start_date: 'Planned start', finish_date: 'Target finish' },
        in_work: { start_date: 'Started', finish_date: 'Expected finish' },
        done: { start_date: 'Started', finish_date: 'Finished' },
    };

    // ---------------------------------------------------------------- helpers

    const pad = (n) => String(n).padStart(2, '0');
    // Local calendar date, not UTC — near midnight toISOString() would give the wrong day.
    const today = () => {
        const d = new Date();
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    };

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const icon = (name) => document.getElementById(`pj-icon-${name}`).content.firstElementChild.cloneNode(true);

    let uidCounter = 0;
    const uid = (prefix) => `${prefix}-${++uidCounter}`;

    const toastEl = root.querySelector('[data-pj-toast]');
    let toastTimer;
    const toast = (message) => {
        toastEl.textContent = message;
        toastEl.dataset.visible = 'true';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toastEl.dataset.visible = 'false'), 1800);
    };

    const copyText = async (text) => {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const area = el('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.append(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }
        toast('Path copied');
    };

    // ------------------------------------------------------------------- tabs

    const tabs = [...root.querySelectorAll('[data-pj-tab]')];
    const createButton = root.querySelector('header [data-pj-open="create"]');

    const activateTab = (tab, { focus = false } = {}) => {
        for (const t of tabs) {
            const selected = t === tab;
            t.setAttribute('aria-selected', String(selected));
            t.tabIndex = selected ? 0 : -1;
            document.getElementById(t.getAttribute('aria-controls')).hidden = !selected;
        }

        if (focus) tab.focus();

        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab.dataset.pjTab);
        history.replaceState(history.state, '', url);

        createButton.dataset.status = tab.dataset.pjTab;
    };

    for (const tab of tabs) {
        tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;

        tab.addEventListener('click', (event) => {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
            event.preventDefault();
            activateTab(tab);
        });

        tab.addEventListener('keydown', (event) => {
            const index = tabs.indexOf(tab);
            const target = {
                ArrowRight: tabs[(index + 1) % tabs.length],
                ArrowLeft: tabs[(index - 1 + tabs.length) % tabs.length],
                Home: tabs[0],
                End: tabs[tabs.length - 1],
            }[event.key];

            if (target) {
                event.preventDefault();
                activateTab(target, { focus: true });
            } else if (event.key === ' ') {
                event.preventDefault();
                activateTab(tab);
            }
        });
    }

    // ---------------------------------------------------------------- dialogs

    const projectDialog = document.getElementById('pj-project-dialog');
    const projectForm = projectDialog.querySelector('form');
    const statusDialog = document.getElementById('pj-status-dialog');
    const statusForm = statusDialog.querySelector('form');
    const deleteDialog = document.getElementById('pj-delete-dialog');
    const deleteForm = deleteDialog.querySelector('form');

    for (const dialog of [projectDialog, statusDialog, deleteDialog]) {
        let pressedBackdrop = false;
        dialog.addEventListener('mousedown', (event) => (pressedBackdrop = event.target === dialog));
        dialog.addEventListener('click', (event) => {
            if (pressedBackdrop && event.target === dialog) dialog.close();
        });

        dialog.addEventListener('close', () => {
            const submit = dialog.querySelector('[type="submit"]');
            if (submit) submit.disabled = false;
        });

        dialog.querySelector('form').addEventListener('submit', () => {
            const submit = dialog.querySelector('[type="submit"]');
            submit.disabled = true;
        });
    }

    const control = (form, prefix, name) => form.querySelector(`#${prefix}-${name}`);
    const wrapper = (form, name) => form.querySelector(`[data-pj-field="${name}"]`);

    const clearErrors = (form) => {
        for (const node of form.querySelectorAll('[data-pj-error]')) {
            node.hidden = true;
            node.textContent = '';
        }
        for (const node of form.querySelectorAll('[aria-invalid]')) node.removeAttribute('aria-invalid');
        const other = form.querySelector('[data-pj-other-errors]');
        if (other) {
            other.hidden = true;
            other.replaceChildren();
        }
    };

    const showErrors = (form, prefix, errors) => {
        let first = null;
        const leftovers = [];

        for (const [field, messages] of Object.entries(errors ?? {})) {
            const errorNode = form.querySelector(`#${prefix}-${field}-error`);
            const fieldWrapper = wrapper(form, field);

            if (!errorNode || fieldWrapper?.hidden) {
                leftovers.push(messages[0]);
                continue;
            }

            errorNode.textContent = messages[0];
            errorNode.hidden = false;

            const input = control(form, prefix, field) ?? form.querySelector(`[name="${field}"]:checked`);
            if (input) {
                if (input.type !== 'radio') input.setAttribute('aria-invalid', 'true');
                first ??= input;
            }
        }

        const other = form.querySelector('[data-pj-other-errors]');
        if (other && leftovers.length) {
            other.replaceChildren(...leftovers.map((message) => el('li', '', message)));
            other.hidden = false;
        }

        return first;
    };

    const setRequired = (form, prefix, name, required) => {
        const fieldWrapper = wrapper(form, name);
        fieldWrapper.querySelector('[data-pj-req]')?.toggleAttribute('hidden', !required);
        const input = control(form, prefix, name);
        if (input) input.setAttribute('aria-required', String(required));
    };

    const setDateLabels = (form, status) => {
        for (const name of ['start_date', 'finish_date']) {
            const label = wrapper(form, name)?.querySelector('[data-pj-label]');
            if (label) label.textContent = DATE_LABELS[status][name];
        }
    };

    // ---- create / edit

    let projectMode = 'create';

    const applyProjectStatus = (status) => {
        for (const name of FORM_FIELDS) {
            const visible = VISIBLE[status].includes(name);
            const fieldWrapper = wrapper(projectForm, name);
            fieldWrapper.hidden = !visible;
            control(projectForm, 'pf', name).disabled = projectMode === 'create' && !visible;

            setRequired(projectForm, 'pf', name, data.required[status].includes(name));
        }
        setDateLabels(projectForm, status);
    };

    const checkedStatus = () => projectForm.querySelector('input[name="status"]:checked')?.value ?? 'future';

    for (const radio of projectForm.querySelectorAll('input[name="status"]')) {
        radio.addEventListener('change', () => {
            applyProjectStatus(radio.value);
            const start = control(projectForm, 'pf', 'start_date');
            if (radio.value === 'in_work' && !start.value) start.value = today();
        });
    }

    const openProjectDialog = (mode, { project = null, status = 'future', values = null, errors = null } = {}) => {
        projectMode = mode;
        projectForm.reset();
        clearErrors(projectForm);

        const methodInput = projectForm.querySelector('input[name="_method"]');
        const isEdit = mode === 'edit';

        projectForm.action = isEdit ? project.urls.update : data.storeUrl;
        methodInput.disabled = !isEdit;
        methodInput.value = isEdit ? 'PATCH' : 'POST';
        projectForm.elements._modal.value = isEdit ? `edit:${project.id}` : 'create';

        document.getElementById('pj-project-dialog-title').textContent = isEdit ? 'Edit project' : 'New project';
        projectForm.querySelector('[data-pj-submit]').textContent = isEdit ? 'Save changes' : 'Create project';
        projectForm.querySelector('[data-pj-create-only]').hidden = isEdit;

        const source = values ?? project ?? {};
        for (const name of FORM_FIELDS) {
            control(projectForm, 'pf', name).value = source[name] ?? '';
        }

        const effectiveStatus = isEdit ? project.status : (values?.status ?? status);
        if (!isEdit) {
            const radio = projectForm.querySelector(`input[name="status"][value="${effectiveStatus}"]`);
            if (radio) radio.checked = true;
            if (effectiveStatus === 'in_work' && !values && !control(projectForm, 'pf', 'start_date').value) {
                control(projectForm, 'pf', 'start_date').value = today();
            }
        }

        applyProjectStatus(isEdit ? project.status : checkedStatus());
        const firstInvalid = showErrors(projectForm, 'pf', errors);

        projectDialog.showModal();
        (firstInvalid ?? control(projectForm, 'pf', 'title')).focus();
    };

    // ---- status transition

    const transitionFields = (from, to) => {
        const fields = new Set(data.required[to].filter((name) => !data.required[from].includes(name)));
        if (to === 'in_work' && from === 'future') fields.add('start_date');
        if (to === 'done' && from === 'future') fields.add('start_date');
        if (to === 'done') fields.add('finish_date');
        return TRANSITION_FIELDS.filter((name) => fields.has(name));
    };

    const prefillsToday = (from, to, name) =>
        (name === 'start_date' && from === 'future' && to === 'in_work') || (name === 'finish_date' && to === 'done');

    const transitionCopy = (from, to) => {
        if (from === 'future' && to === 'in_work') {
            return ['Start work', 'The start date becomes the real one, and an expected finish date is now required. Today is filled in — change it if you started earlier.'];
        }
        if (to === 'done' && from === 'in_work') {
            return ['Mark done', 'The finish date becomes the real one. A done project also needs its folder and a final line count.'];
        }
        if (to === 'done') {
            return ['Mark done', 'Skipping straight to done — confirm the real dates, the folder and the final line count.'];
        }
        if (to === 'in_work') {
            return ['Reopen', 'Nothing is cleared. The finish date goes back to meaning “expected”, and the line count is kept but hidden until the project is done again.'];
        }
        return ['Move to Future', 'Nothing is cleared. The start date goes back to meaning “planned”.'];
    };

    const openStatusDialog = (project, to, { values = null, errors = null } = {}) => {
        const from = project.status;
        const fields = transitionFields(from, to);
        const [action, note] = transitionCopy(from, to);

        statusForm.reset();
        clearErrors(statusForm);
        statusForm.action = project.urls.status;
        statusForm.elements._modal.value = `status:${project.id}:${to}`;
        statusForm.elements.status.value = to;

        document.getElementById('pj-status-dialog-title').textContent = `${action}: ${project.title}`;
        document.getElementById('pj-status-note').textContent = note;
        statusForm.querySelector('[data-pj-submit]').textContent = action;

        for (const name of TRANSITION_FIELDS) {
            const shown = fields.includes(name);
            const input = control(statusForm, 'sf', name);
            wrapper(statusForm, name).hidden = !shown;
            input.disabled = !shown;
            setRequired(statusForm, 'sf', name, shown && data.required[to].includes(name));

            if (values) {
                input.value = values[name] ?? '';
            } else {
                input.value = prefillsToday(from, to, name) ? today() : (project[name] ?? '');
            }
        }
        setDateLabels(statusForm, to);

        const firstInvalid = showErrors(statusForm, 'sf', errors);

        statusDialog.showModal();
        (firstInvalid ?? statusForm.querySelector('.pj-field:not([hidden]) .pj-input') ?? statusForm.querySelector('[data-pj-submit]')).focus();
    };

    // ---- delete

    const openDeleteDialog = (project) => {
        deleteForm.action = project.urls.destroy;
        deleteForm.querySelector('[data-pj-delete-title]').textContent = project.title;
        deleteDialog.showModal();
    };

    // ------------------------------------------------------------ file tree

    const note = (text, extraClass = '') => el('li', `pj-tree__note ${extraClass}`.trim(), text);

    const fileNode = (entry) => {
        const item = el('li');
        const row = el('div', 'pj-tree__row');

        const label = el('span', 'pj-tree__file');
        label.title = entry.wsl;
        label.append(icon('file'), el('span', 'pj-tree__name', entry.name));

        const actions = el('div', 'pj-tree__actions');

        const open = el('a', 'pj-btn pj-btn--ghost pj-btn--sm');
        open.href = entry.vscode;
        open.title = 'Open in VS Code';
        open.setAttribute('aria-label', `Open ${entry.name} in VS Code`);
        open.append(icon('code'), el('span', 'pj-tree__label-long', 'VS Code'));

        const copy = el('button', 'pj-btn pj-btn--ghost pj-btn--sm pj-btn--icon');
        copy.type = 'button';
        copy.title = 'Copy path';
        copy.dataset.pjCopy = entry.wsl;
        copy.setAttribute('aria-label', `Copy path of ${entry.name}`);
        copy.append(icon('copy'));

        actions.append(open, copy);
        row.append(label, actions);
        item.append(row);
        return item;
    };

    const folderNode = (entry, url) => {
        const item = el('li');
        const row = el('div', 'pj-tree__row');
        const children = el('ul');
        children.id = uid('pj-tree');
        children.hidden = true;

        const toggle = el('button', 'pj-tree__toggle');
        toggle.type = 'button';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-controls', children.id);
        toggle.append(icon('chevron'), icon('folder'), el('span', 'pj-tree__name', entry.name));
        toggle.addEventListener('click', () => toggleFolder(toggle, children, url, entry.path));

        row.append(toggle);
        item.append(row, children);
        return item;
    };

    const loadFolder = async (list, url, path) => {
        list.dataset.loaded = 'true';
        list.setAttribute('aria-busy', 'true');
        list.replaceChildren(note('Loading…'));

        let body;
        try {
            const response = await fetch(`${url}?path=${encodeURIComponent(path)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            // An expired session redirects to the login page, which comes back as HTML.
            if (!(response.headers.get('content-type') ?? '').includes('application/json')) {
                throw new Error('Couldn’t load files — your session may have expired. Reload the page.');
            }
            body = await response.json();
        } catch (error) {
            list.removeAttribute('aria-busy');
            const retry = el('button', 'pj-linkbtn ml-1', 'Retry');
            retry.type = 'button';
            retry.addEventListener('click', () => loadFolder(list, url, path));
            const item = note(error instanceof TypeError ? 'Couldn’t reach the server.' : error.message, 'pj-tree__note--error');
            item.append(retry);
            list.replaceChildren(item);
            return;
        }

        list.removeAttribute('aria-busy');

        if (body.state !== 'ok' && body.state !== 'empty') {
            list.replaceChildren(note(body.message ?? 'This folder couldn’t be read.', 'pj-tree__note--error'));
            return;
        }

        if (body.state === 'empty') {
            list.replaceChildren(note('This folder is empty.'));
            return;
        }

        const nodes = body.entries.map((entry) => (entry.type === 'dir' ? folderNode(entry, url) : fileNode(entry)));
        if (body.truncated) nodes.push(note(`Showing the first ${body.entries.length} items.`));
        list.replaceChildren(...nodes);
    };

    const toggleFolder = (button, list, url, path) => {
        const open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(open));
        list.hidden = !open;
        if (open && list.dataset.loaded !== 'true') loadFolder(list, url, path);
    };

    for (const tree of root.querySelectorAll('[data-pj-tree]')) {
        const button = tree.querySelector('[data-pj-tree-root]');
        const region = document.getElementById(button.getAttribute('aria-controls'));
        const list = el('ul');
        region.append(list);

        button.addEventListener('click', () => {
            const open = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(open));
            region.hidden = !open;
            if (open && list.dataset.loaded !== 'true') loadFolder(list, tree.dataset.url, '');
        });
    }

    // ------------------------------------------------------- delegated clicks

    root.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-pj-open]');
        if (opener) {
            const project = projects[opener.dataset.project];
            const kind = opener.dataset.pjOpen;

            if (kind === 'create') openProjectDialog('create', { status: opener.dataset.status ?? 'future' });
            else if (kind === 'edit' && project) openProjectDialog('edit', { project });
            else if (kind === 'status' && project) openStatusDialog(project, opener.dataset.target);
            else if (kind === 'delete' && project) openDeleteDialog(project);
            return;
        }

        const closer = event.target.closest('[data-pj-close]');
        if (closer) {
            closer.closest('dialog').close();
            return;
        }

        const copier = event.target.closest('[data-pj-copy]');
        if (copier) {
            copyText(copier.dataset.pjCopy);
            return;
        }

        const dismiss = event.target.closest('[data-pj-dismiss]');
        if (dismiss) dismiss.closest('[data-pj-dismissable]').remove();
    });

    // ---------------------------------------------- arriving from a Future Updates link


    const highlightFromUrl = () => {
        const url = new URL(window.location.href);
        const id = url.searchParams.get('highlight');
        if (!id) return;

        url.searchParams.delete('highlight');
        history.replaceState(history.state, '', url);

        const card = document.getElementById(`project-${id}`);
        if (!card) return;

        const panel = card.closest('[role="tabpanel"]');
        if (panel?.hidden) {
            const tab = tabs.find((t) => t.getAttribute('aria-controls') === panel.id);
            if (tab) activateTab(tab);
        }

        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.classList.add('pj-flash');
        card.addEventListener('animationend', () => card.classList.remove('pj-flash'), { once: true });
    };

    highlightFromUrl();

    // ------------------------------------- reopen the dialog a failed submit came from

    const context = data.context;
    if (context) {
        const old = data.old ?? {};
        const errors = data.errors ?? {};
        const [kind, id, target] = context.split(':');

        if (kind === 'create') {
            openProjectDialog('create', { values: old, errors });
        } else if (kind === 'edit' && projects[id]) {
            openProjectDialog('edit', { project: projects[id], values: old, errors });
        } else if (kind === 'status' && projects[id]) {
            openStatusDialog(projects[id], target, { values: old, errors });
        }
    }
};

const resetForCache = () => {
    const root = document.getElementById('pj-root');
    if (!root) return;

    for (const dialog of root.querySelectorAll('dialog[open]')) dialog.close();
    for (const card of root.querySelectorAll('.pj-flash')) card.classList.remove('pj-flash');
    for (const button of root.querySelectorAll('[data-pj-tree-root]')) {
        button.setAttribute('aria-expanded', 'false');
        const region = document.getElementById(button.getAttribute('aria-controls'));
        region.hidden = true;
        region.replaceChildren();
    }
    delete root.querySelector('[data-pj-toast]')?.dataset.visible;
    delete root.dataset.pjReady;
};

initProjects();
document.addEventListener('turbo:load', initProjects);
document.addEventListener('turbo:before-cache', resetForCache);
