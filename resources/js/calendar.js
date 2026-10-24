// The Calendar page: layer filters, day selection, keyboard movement through the month grid
// and the year heatmap, and the month <-> year switch. Both views are rendered by the server;
// only moving to another month or year is a visit.

const LAYERS_KEY = 'nd-calendar-layers';
const LAYERS_COOKIE = 'nd_cal_layers';
const FOCUS_KEY = 'nd-calendar-focus';

const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---------------------------------------------------------------- dates (UTC, no clock skew)

const parseDay = (key) => {
    const [y, m, d] = key.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d));
};
const dayKey = (date) => date.toISOString().slice(0, 10);
const addDays = (key, n) => {
    const date = parseDay(key);
    date.setUTCDate(date.getUTCDate() + n);
    return dayKey(date);
};
// Same day number in another month, clamped to its length.
const addMonths = (key, n) => {
    const date = parseDay(key);
    const target = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + n, 1));
    const last = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();
    target.setUTCDate(Math.min(date.getUTCDate(), last));
    return dayKey(target);
};

// ---------------------------------------------------------------- remembered layers

// Per browser and per account. Storage can be missing or throw (a private window, blocked
// site data); the page then simply uses the default set.
const storeKey = (root) => `${LAYERS_KEY}:${root.dataset.user}`;

const readLayers = (root) => {
    const known = root.dataset.layers.split(' ');
    try {
        const raw = localStorage.getItem(storeKey(root));
        if (raw === null) return null;
        const list = JSON.parse(raw);
        return Array.isArray(list) ? list.filter((layer) => known.includes(layer)) : null;
    } catch {
        return null;
    }
};

// Mirrored into a cookie the server reads (CalendarController::LAYER_COOKIE), so the next
// full load is rendered with the choice instead of flashing the defaults. Sent to /calendar only.
const writeCookie = (root, layers) => {
    try {
        document.cookie = `${LAYERS_COOKIE}=${root.dataset.user}:${layers.join('-')}; path=/calendar; max-age=31536000; samesite=lax`;
    } catch {
        // A blocked cookie only brings the first-load flash back.
    }
};

const writeLayers = (root, layers) => {
    try {
        localStorage.setItem(storeKey(root), JSON.stringify(layers));
    } catch {
        // Not remembered here; the cookie below may still carry it.
    }
    writeCookie(root, layers);
};

const activeLayers = (root) => root.dataset.layers.split(' ').filter((layer) => root.classList.contains(`cal--${layer}`));

// Works on a detached body too, so turbo:before-render can paint the right layers first.
const applyLayers = (root, layers) => {
    for (const layer of root.dataset.layers.split(' ')) {
        root.classList.toggle(`cal--${layer}`, layers.includes(layer));
    }
    for (const chip of root.querySelectorAll('[data-cal-layer]')) {
        chip.setAttribute('aria-pressed', layers.includes(chip.dataset.calLayer) ? 'true' : 'false');
    }

    let anyInMonth = false;
    for (const day of root.querySelectorAll('[data-cal-panel]')) {
        const present = [...day.querySelectorAll(':scope > .cal-items > li[data-layer]')].map((li) => li.dataset.layer);
        const blank = !present.some((layer) => layers.includes(layer));
        day.classList.toggle('is-blank', blank);
        if (!blank && !day.classList.contains('is-out')) anyInMonth = true;
    }
    root.querySelector('[data-cal-panel-list]')?.classList.toggle('is-quiet', !anyInMonth);
};

// Storage first; without it, whatever the server rendered (the cookie's choice, else the
// defaults) — never the bare defaults over a choice the cookie carried.
const storedOrRendered = (root) => readLayers(root) ?? activeLayers(root);

const sameLayers = (a, b) => a.length === b.length && a.every((layer) => b.includes(layer));

document.addEventListener('turbo:before-render', (event) => {
    const root = event.detail.newBody.querySelector('#cal-root');
    if (root) applyLayers(root, storedOrRendered(root));
});

// ---------------------------------------------------------------- page

const setUrl = (changes) => {
    const url = new URL(window.location.href);
    for (const [name, value] of Object.entries(changes)) {
        if (value === null) url.searchParams.delete(name);
        else url.searchParams.set(name, value);
    }
    history.replaceState(history.state, '', url);
};

const initCalendar = () => {
    const root = document.getElementById('cal-root');
    if (!root || root.dataset.calReady === 'true') return;
    root.dataset.calReady = 'true';

    const grid = root.querySelector('[data-cal-grid]');
    const heatmap = root.querySelector('[data-cal-heatmap]');
    const board = root.querySelector('[data-cal-views]');
    const monthOf = (key) => key.slice(0, 7);

    // The server rendered the cookie's layers. If storage says otherwise (a cookie that
    // expired or was cleared), storage wins and the cookie is brought back in step.
    const rendered = activeLayers(root);
    const chosen = storedOrRendered(root);
    applyLayers(root, chosen);
    if (!sameLayers(rendered, chosen)) writeCookie(root, chosen);

    for (const chip of root.querySelectorAll('[data-cal-layer]')) {
        chip.addEventListener('click', () => {
            const layer = chip.dataset.calLayer;
            const now = activeLayers(root);
            const next = now.includes(layer) ? now.filter((l) => l !== layer) : [...now, layer];
            applyLayers(root, next);
            writeLayers(root, next);
        });
    }

    // ------------------------------------------------------------ view switch

    // Resolves once the new view is in the DOM (a transition runs its update asynchronously).
    const setView = (view) => {
        if (root.dataset.view === view) return Promise.resolve();
        const apply = () => {
            root.dataset.view = view;
            for (const radio of root.querySelectorAll('[data-cal-view-choice]')) radio.checked = radio.value === view;
            setUrl({ view: view === 'year' ? 'year' : null });
        };

        if (reduced() || !document.startViewTransition) {
            apply();
            return Promise.resolve();
        }
        board.style.viewTransitionName = 'cal-board';
        const transition = document.startViewTransition(apply);
        transition.finished.finally(() => {
            board.style.viewTransitionName = '';
        });
        return transition.updateCallbackDone.catch(() => {});
    };

    for (const radio of root.querySelectorAll('[data-cal-view-choice]')) {
        radio.addEventListener('change', () => radio.checked && setView(radio.value));
    }

    // ------------------------------------------------------------ days

    const dayButton = (key) => grid?.querySelector(`[data-cal-day="${key}"]`);

    const selectDay = (key, { focus = false } = {}) => {
        const button = dayButton(key);
        if (!button) return false;

        for (const el of root.querySelectorAll('[data-cal-day][aria-pressed="true"]')) {
            el.setAttribute('aria-pressed', 'false');
            el.tabIndex = -1;
        }
        for (const el of root.querySelectorAll('.cal-cell.is-selected, .cal-num.is-selected, [data-cal-panel].is-selected')) {
            el.classList.remove('is-selected');
        }

        button.setAttribute('aria-pressed', 'true');
        button.tabIndex = 0;
        button.classList.add('is-selected');
        grid.querySelector(`[data-cal-cell="${key}"]`)?.classList.add('is-selected');
        root.querySelector(`[data-cal-panel="${key}"]`)?.classList.add('is-selected');

        // The heatmap's tab stop follows, when the day is in it.
        const heat = heatmap?.querySelector(`[data-cal-heat="${key}"]`);
        if (heat) {
            for (const el of heatmap.querySelectorAll('[data-cal-heat][tabindex="0"]')) el.tabIndex = -1;
            heat.tabIndex = 0;
        }

        setUrl({ month: root.dataset.month, day: key });
        if (focus) button.focus();
        return true;
    };

    // Off the grid's edge: the same move, on the other month's page.
    const visitDay = (key, view = 'month') => {
        try {
            sessionStorage.setItem(FOCUS_KEY, view);
        } catch {
            // Focus just won't follow.
        }
        const url = new URL(window.location.href);
        url.searchParams.set('month', monthOf(key));
        url.searchParams.set('day', key);
        if (view === 'year') url.searchParams.set('view', 'year');
        else url.searchParams.delete('view');
        window.Turbo.visit(url.toString());
    };

    if (grid) {
        grid.addEventListener('click', (event) => {
            if (event.target.closest('a')) return;
            const day = event.target.closest('[data-cal-day], [data-cal-cell]');
            if (day) selectDay(day.dataset.calDay ?? day.dataset.calCell);
        });

        grid.addEventListener('keydown', (event) => {
            const button = event.target.closest('[data-cal-day]');
            if (!button || event.altKey || event.ctrlKey || event.metaKey) return;
            const key = button.dataset.calDay;
            const column = (parseDay(key).getUTCDay() - Number(root.dataset.weekStart) + 7) % 7;

            const target = {
                ArrowLeft: () => addDays(key, -1),
                ArrowRight: () => addDays(key, 1),
                ArrowUp: () => addDays(key, -7),
                ArrowDown: () => addDays(key, 7),
                Home: () => addDays(key, -column),
                End: () => addDays(key, 6 - column),
                PageUp: () => addMonths(key, -1),
                PageDown: () => addMonths(key, 1),
            }[event.key]?.();
            if (!target) return;

            event.preventDefault();
            // Page Up/Down always change month; an arrow stays on the page while the grid has the day.
            if (!event.key.startsWith('Page') && selectDay(target, { focus: true })) return;
            visitDay(target);
        });
    }

    // ------------------------------------------------------------ heatmap

    if (heatmap) {
        heatmap.addEventListener('keydown', (event) => {
            const cell = event.target.closest('[data-cal-heat]');
            if (!cell || event.altKey || event.ctrlKey || event.metaKey) return;
            const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[event.key];
            if (!step) return;
            event.preventDefault();
            const next = heatmap.querySelector(`[data-cal-heat="${addDays(cell.dataset.calHeat, step)}"]`);
            if (!next) return;
            cell.tabIndex = -1;
            next.tabIndex = 0;
            next.focus();
        });

        // A day in the month already on the page opens without a visit.
        heatmap.addEventListener('click', (event) => {
            const cell = event.target.closest('[data-cal-heat]');
            if (!cell || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
            if (monthOf(cell.dataset.calHeat) !== root.dataset.month) return;
            event.preventDefault();
            selectDay(cell.dataset.calHeat);
            setView('month').then(() => dayButton(cell.dataset.calHeat)?.focus({ preventScroll: true }));
        });
    }

    // ------------------------------------------------------------ the two markers of an entry

    const pair = (event, on) => {
        const el = event.target.closest?.('[data-cal-entry]');
        if (!el) return;
        for (const other of root.querySelectorAll(`[data-cal-entry="${el.dataset.calEntry}"]`)) {
            other.classList.toggle('is-paired', on);
        }
    };
    root.addEventListener('pointerover', (event) => pair(event, true));
    root.addEventListener('pointerout', (event) => pair(event, false));
    root.addEventListener('focusin', (event) => pair(event, true));
    root.addEventListener('focusout', (event) => pair(event, false));

    // ------------------------------------------------------------ focus after a keyboard visit

    let focusView = null;
    try {
        focusView = sessionStorage.getItem(FOCUS_KEY);
        sessionStorage.removeItem(FOCUS_KEY);
    } catch {
        focusView = null;
    }
    if (focusView === 'month') {
        root.querySelector('[data-cal-day][aria-pressed="true"]')?.focus();
    }
};

const resetForCache = () => {
    const root = document.getElementById('cal-root');
    if (!root) return;
    for (const el of root.querySelectorAll('.is-paired')) el.classList.remove('is-paired');
    root.querySelector('[data-cal-views]')?.style.removeProperty('view-transition-name');
};

document.addEventListener('turbo:load', initCalendar);
document.addEventListener('turbo:before-cache', resetForCache);
