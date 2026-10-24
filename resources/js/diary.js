import { normaliseSearchText as normalise } from './search-text.js';
import { initFlow, resetFlowForCache } from './diary-flow.js';

// The Diary index: the month-by-month list, its search and person filter, and the way in
// to "Close the day". Reading happens on each entry's own page (diary-entry.js).

let flow = null;

const initDiary = () => {
    const root = document.getElementById('dy-root');
    if (!root || root.dataset.dyReady === 'true') return;
    root.dataset.dyReady = 'true';

    const entries = JSON.parse(document.getElementById('dy-data').textContent).entries ?? {};
    flow = initFlow();

    // ------------------------------------------------------------ writing

    const url = new URL(window.location.href);
    if (url.searchParams.has('write')) {
        // Arrived from W, Home or the palette: open, then drop the parameter so a reload or
        // Back doesn't open it again.
        url.searchParams.delete('write');
        history.replaceState(history.state, '', url);
        flow?.open();
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

    const haystack = new Map(cards.map((card) => {
        const entry = entries[card.dataset.entryId] ?? {};
        return [card, normalise(`${entry.title ?? ''} ${entry.content ?? ''}`)];
    }));

    const peopleOf = new Map(cards.map((card) => {
        const entry = entries[card.dataset.entryId] ?? {};
        return [card, (entry.people ?? []).map(normalise)];
    }));

    let person = null;

    const applyFilter = () => {
        const query = normalise(search?.value ?? '');
        const wanted = person ? normalise(person) : null;
        const filtering = Boolean(query) || Boolean(wanted);
        let visible = 0;

        for (const card of cards) {
            const match = (!query || haystack.get(card).includes(query)) && (!wanted || peopleOf.get(card).includes(wanted));
            card.hidden = !match;
            if (match) visible += 1;
        }

        for (const group of groups) {
            const shown = [...group.querySelectorAll('[data-dy-entry]')].filter((card) => !card.hidden).length;
            group.hidden = shown === 0;
            const count = group.querySelector('[data-dy-group-count]');
            if (count) count.textContent = String(shown);
        }

        const word = (n) => (n === 1 ? 'memory' : 'memories');
        if (countEl) countEl.textContent = filtering ? `${visible} of ${cards.length} ${word(cards.length)}` : `${cards.length} ${word(cards.length)}`;

        if (filterBar) {
            filterBar.hidden = !person;
            if (person) filterName.textContent = person;
        }

        if (noMatches) {
            noMatches.hidden = visible !== 0;
            noMatchesNote.textContent = query
                ? `Nothing matches “${search.value.trim()}”${person ? ` among memories with ${person}` : ''}. Sealed memories can only match on their title.`
                : `Nothing here lists ${person}. Sealed memories never do: they show no people at all.`;
        }
    };

    // ?q= is written on Enter and before a form submits, never live: the search box can be
    // claimed by another script (CLAUDE.md §4), so a keystroke-synced URL would put whatever
    // was typed into history.
    let committedQuery = '';

    const queryForUrl = () => {
        const value = search?.value.trim() ?? '';
        if (value === committedQuery) return value;
        const sync = new CustomEvent('diary:search-sync', { bubbles: true, cancelable: true, detail: { query: value } });
        return search.dispatchEvent(sync) ? value : committedQuery;
    };

    const writeUrl = () => {
        const next = new URL(window.location.href);
        const set = (key, value) => (value ? next.searchParams.set(key, value) : next.searchParams.delete(key));
        if (search) set('q', queryForUrl());
        set('person', person ?? '');
        history.replaceState(history.state, '', next);
    };

    const readUrl = () => {
        const params = new URL(window.location.href).searchParams;
        committedQuery = params.get('q') ?? '';
        if (search) search.value = committedQuery;
        person = params.get('person') || null;
    };

    if (search) {
        for (const type of ['input', 'search']) search.addEventListener(type, applyFilter);

        let committing = false;
        search.addEventListener('keydown', async (event) => {
            if (event.key !== 'Enter' || event.isComposing || committing) return;
            event.preventDefault();

            const value = search.value;
            const claims = [];
            const commit = new CustomEvent('diary:search-commit', {
                bubbles: true,
                cancelable: true,
                detail: { query: value, claim: (promise) => claims.push(Promise.resolve(promise).catch(() => false)) },
            });
            search.dispatchEvent(commit);

            committing = true;
            const claimed = commit.defaultPrevented || (await Promise.all(claims)).some(Boolean);
            committing = false;

            if (claimed) {
                search.value = '';
                person = null;
                applyFilter();
                return;
            }

            committedQuery = value.trim();
            writeUrl();
        });
    }

    root.addEventListener('submit', writeUrl, true);

    const setPerson = (name) => {
        person = name;
        applyFilter();
        writeUrl();
    };

    root.addEventListener('click', (event) => {
        const chip = event.target.closest('[data-dy-person]');
        if (chip) {
            setPerson(chip.dataset.dyPerson);
            return;
        }
        if (event.target.closest('[data-dy-clear-person]')) {
            setPerson(null);
            return;
        }
        if (event.target.closest('[data-dy-clear]')) {
            if (search) search.value = '';
            person = null;
            applyFilter();
            writeUrl();
            search?.focus();
            return;
        }
        if (event.target.closest('[data-dy-write]')) flow?.open();
    });

    readUrl();
    applyFilter();
};

// W and the palette's "Close the day": claimed here, so they open in place.
document.addEventListener('nd:write', (event) => {
    if (!document.getElementById('dy-root') || !flow) return;
    event.preventDefault();
    flow.open();
});

document.addEventListener('turbo:before-cache', () => {
    const root = document.getElementById('dy-root');
    if (!root) return;
    resetFlowForCache();
    flow = null;
    delete root.dataset.dyReady;
});

initDiary();
document.addEventListener('turbo:load', initDiary);
