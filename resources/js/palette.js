import { normaliseSearchText as normalise } from './search-text.js';
import { cycle as cycleTheme } from './theme.js';

// Ctrl+K and the single-key shortcuts. Everything is delegated on document and every
// element is looked up when used: the palette lives in the body Turbo swaps on each visit.

const GROUPS = { action: 'Actions', page: 'Pages', entry: 'Memories', track: 'Songs', project: 'Projects', album: 'Albums', video: 'Videos' };
const PER_GROUP = 6;
const GO_TIMEOUT = 1200;

const isMac = /Mac|iPhone|iPad/.test(navigator.platform);

const shellData = () => {
    try {
        return JSON.parse(document.getElementById('nd-shell-data')?.textContent ?? 'null');
    } catch {
        return null;
    }
};

const palette = () => document.querySelector('[data-palette]');
const shortcuts = () => document.querySelector('[data-shortcuts]');

const visit = (url) => (window.Turbo ? window.Turbo.visit(url) : window.location.assign(url));

// ------------------------------------------------------------------ actions

const writeToday = () => {
    const data = shellData();
    if (!data?.writeUrl) return;
    // On the Diary page the flow opens in place; anywhere else it is a visit.
    const claimed = !document.dispatchEvent(new CustomEvent('nd:write', { cancelable: true }));
    if (!claimed) visit(data.writeUrl);
};

const actions = () => {
    const data = shellData();
    const list = [];
    if (data?.writeUrl) list.push({ kind: 'action', label: 'Close the day', hint: 'Write today’s entry', icon: 'fa-feather', run: writeToday });
    list.push({ kind: 'action', label: 'Play or pause the music', icon: 'fa-play', run: () => window.bsPlayer?.toggle() });
    list.push({ kind: 'action', label: 'Change the light', hint: 'Clock, light, dark', icon: 'fa-circle-half-stroke', run: cycleTheme });
    list.push({ kind: 'action', label: 'Keyboard shortcuts', icon: 'fa-keyboard', run: () => openShortcuts() });
    return list;
};

// ------------------------------------------------------------------ palette

let items = [];
let fetched = null;
let ticket = 0;
let options = [];
let active = 0;
let returnFocus = null;

const loadItems = async () => {
    const data = shellData();
    if (!data?.itemsUrl) return;
    const mine = ++ticket;
    try {
        const response = await fetch(data.itemsUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const json = await response.json();
        if (mine !== ticket) return;
        items = json.items ?? [];
        fetched = Date.now();
        if (palette()?.open) render();
    } catch {
        // offline: pages and actions still work
    }
};

const score = (haystack, query) => {
    if (!query) return 1;
    const index = haystack.indexOf(query);
    if (index === -1) return 0;
    if (index === 0) return 3;
    return /\s/.test(haystack[index - 1]) ? 2 : 1;
};

const candidates = (query) => {
    const data = shellData();
    const pages = (data?.pages ?? []).map((page) => ({ kind: 'page', label: page.label, icon: page.icon, url: page.url, keys: ['G', page.key.toUpperCase()] }));
    const pool = [...actions(), ...pages, ...items];

    if (!query) {
        // Nothing typed: what you can do, where you can go, and the latest memories.
        return [...actions(), ...pages, ...items.filter((item) => item.kind === 'entry').slice(0, 4)];
    }

    const counts = {};
    return pool
        .map((item) => ({ item, rank: score(normalise(`${item.label} ${item.hint ?? ''}`), query) }))
        .filter(({ rank }) => rank > 0)
        .sort((a, b) => b.rank - a.rank)
        .map(({ item }) => item)
        .filter((item) => (counts[item.kind] = (counts[item.kind] ?? 0) + 1) <= PER_GROUP);
};

const render = () => {
    const dialog = palette();
    if (!dialog) return;
    const list = dialog.querySelector('[data-palette-list]');
    const input = dialog.querySelector('[data-palette-input]');
    const query = normalise(input.value.trim());

    const found = candidates(query);
    // Group in a fixed order so the list doesn't reshuffle as you type.
    const order = Object.keys(GROUPS);
    found.sort((a, b) => order.indexOf(a.kind) - order.indexOf(b.kind));
    options = found;
    active = Math.min(active, Math.max(found.length - 1, 0));

    list.replaceChildren();
    if (!found.length) {
        const empty = document.createElement('li');
        empty.className = 'nd-palette__empty';
        empty.setAttribute('role', 'presentation');
        empty.textContent = fetched ? `Nothing called “${input.value.trim()}”.` : 'Looking…';
        list.append(empty);
        input.removeAttribute('aria-activedescendant');
        return;
    }

    let group = null;
    found.forEach((item, index) => {
        if (item.kind !== group) {
            group = item.kind;
            const heading = document.createElement('li');
            heading.className = 'nd-palette__group';
            heading.setAttribute('role', 'presentation');
            heading.textContent = GROUPS[group];
            list.append(heading);
        }

        const option = document.createElement('li');
        option.className = 'nd-palette__option';
        option.id = `nd-palette-option-${index}`;
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', String(index === active));
        option.dataset.index = String(index);

        const icon = document.createElement('span');
        icon.className = 'nd-palette__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = `<i class="fa-solid ${item.icon ?? 'fa-circle'}"></i>`;

        const label = document.createElement('span');
        label.className = 'nd-palette__label' + (item.kind === 'entry' ? ' nd-palette__label--written' : '');
        label.dir = 'auto';
        label.textContent = item.label;

        option.append(icon, label);

        if (item.hint) {
            const hint = document.createElement('span');
            hint.className = 'nd-palette__hint';
            hint.dir = 'auto';
            hint.textContent = item.hint;
            option.append(hint);
        }
        if (item.keys) {
            const keys = document.createElement('span');
            keys.className = 'nd-palette__hint';
            keys.setAttribute('aria-hidden', 'true');
            keys.innerHTML = item.keys.map((key) => `<kbd class="nd-kbd">${key}</kbd>`).join(' ');
            option.append(keys);
        }

        list.append(option);
    });

    input.setAttribute('aria-activedescendant', `nd-palette-option-${active}`);
};

const highlight = (index) => {
    const dialog = palette();
    if (!dialog || !options.length) return;
    active = (index + options.length) % options.length;
    for (const option of dialog.querySelectorAll('[role="option"]')) {
        option.setAttribute('aria-selected', String(Number(option.dataset.index) === active));
    }
    const current = dialog.querySelector(`#nd-palette-option-${active}`);
    current?.scrollIntoView({ block: 'nearest' });
    dialog.querySelector('[data-palette-input]').setAttribute('aria-activedescendant', current?.id ?? '');
};

const choose = (index) => {
    const item = options[index];
    if (!item) return;
    closePalette({ restoreFocus: false });
    if (item.run) item.run();
    else if (item.play) window.bsPlayer?.pick(item.play);
    else if (item.url) visit(item.url);
};

export const openPalette = () => {
    const dialog = palette();
    if (!dialog || dialog.open) return;
    returnFocus = document.activeElement;
    const input = dialog.querySelector('[data-palette-input]');
    input.value = '';
    active = 0;
    render();
    dialog.showModal();
    input.focus();
    // Refetched on every open: the lists change as you add things.
    loadItems();
};

function closePalette({ restoreFocus = true } = {}) {
    const dialog = palette();
    if (!dialog?.open) return;
    dialog.close();
    if (restoreFocus && returnFocus?.isConnected) returnFocus.focus();
}

document.addEventListener('input', (event) => {
    if (!event.target.matches('[data-palette-input]')) return;
    active = 0;
    render();
});

document.addEventListener('keydown', (event) => {
    if (!event.target.matches?.('[data-palette-input]')) return;
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlight(active + 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlight(active - 1);
    } else if (event.key === 'Home' && event.ctrlKey) {
        highlight(0);
    } else if (event.key === 'Enter' && !event.isComposing) {
        event.preventDefault();
        choose(active);
    }
});

document.addEventListener('mousemove', (event) => {
    const option = event.target.closest?.('.nd-palette__option');
    if (option && Number(option.dataset.index) !== active) highlight(Number(option.dataset.index));
});

document.addEventListener('click', (event) => {
    const option = event.target.closest('.nd-palette__option');
    if (option) {
        choose(Number(option.dataset.index));
        return;
    }
    if (event.target.closest('[data-palette-open]')) {
        openPalette();
        return;
    }
    if (event.target.closest('[data-shortcuts-open]')) {
        openShortcuts();
        return;
    }
    const closer = event.target.closest('[data-dialog-close]');
    if (closer && closer.closest('[data-shortcuts]')) {
        closer.closest('dialog').close();
        return;
    }
    // A click on the backdrop lands on the dialog element itself.
    if (event.target === palette()) closePalette();
    else if (event.target === shortcuts()) shortcuts().close();
});

// ------------------------------------------------------------------ shortcuts overlay

function openShortcuts() {
    const dialog = shortcuts();
    if (!dialog || dialog.open) return;
    closePalette({ restoreFocus: false });
    dialog.showModal();
}

// ------------------------------------------------------------------ single keys

const typing = (target) =>
    target instanceof Element && (target.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"]') !== null);

const anyDialogOpen = () => document.querySelector('dialog[open]') !== null;

let goPending = false;
let goTimer = null;

document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        if (palette()?.open) closePalette();
        else openPalette();
        return;
    }

    if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey || event.isComposing) return;
    if (typing(event.target) || anyDialogOpen()) return;

    const key = event.key.toLowerCase();

    if (goPending) {
        goPending = false;
        clearTimeout(goTimer);
        const page = (shellData()?.pages ?? []).find((item) => item.key === key);
        if (page) {
            event.preventDefault();
            visit(page.url);
        }
        return;
    }

    if (key === 'g') {
        goPending = true;
        goTimer = setTimeout(() => (goPending = false), GO_TIMEOUT);
    } else if (event.key === '?') {
        event.preventDefault();
        openShortcuts();
    } else if (key === 't') {
        cycleTheme();
    } else if (key === 'p') {
        window.bsPlayer?.toggle();
    } else if (key === 'w') {
        event.preventDefault();
        writeToday();
    } else if (event.key === '/') {
        const search = document.querySelector('[data-page-search]');
        event.preventDefault();
        if (search) search.focus();
        else openPalette();
    }
});

// ------------------------------------------------------------------ housekeeping

const paintModKeys = () => {
    if (!isMac) return;
    for (const key of document.querySelectorAll('[data-mod-key]')) key.textContent = '⌘';
};

document.addEventListener('turbo:load', paintModKeys);
document.addEventListener('turbo:before-cache', () => {
    closePalette({ restoreFocus: false });
    shortcuts()?.open && shortcuts().close();
});
paintModKeys();
