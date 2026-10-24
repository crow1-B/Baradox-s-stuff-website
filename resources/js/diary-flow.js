import { normaliseSearchText as normalise } from './search-text.js';

// "Close the day": five panels over one form (partials/diary-flow). Used by the Diary index
// to write and by an entry's page to edit.
//
// Drafts are kept in this browser's localStorage, per account and per entry, as you type.
// They hold plain text on this device and nowhere else; the server sees nothing until the
// form is submitted, and a draft is only dropped once the server has confirmed the save
// (dy_saved), never on a submit that might still come back with errors.

const STEPS = ['when', 'mood', 'photos', 'song', 'words'];
const OPTIONAL = ['mood', 'photos', 'song'];
const ERROR_STEP = { event_date: 'when', mood: 'mood', photo_ids: 'photos', photos: 'photos', track_id: 'song' };
const PENDING_KEY = 'nd-diary-pending';
const SAVE_DELAY = 700;
const CARET_LINE = 0.42; // where the line being written sits, as a share of the view height

const pad = (n) => String(n).padStart(2, '0');
const isoDate = (date = new Date()) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const fromIso = (iso) => new Date(`${iso}T12:00:00`);

const dayLabel = (iso, { year = false } = {}) => {
    if (!iso) return '';
    const date = fromIso(iso);
    const options = { weekday: 'long', day: 'numeric', month: 'long' };
    if (year || date.getFullYear() !== new Date().getFullYear()) options.year = 'numeric';
    return new Intl.DateTimeFormat('en-GB', options).format(date);
};

const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

const storage = {
    get(key) {
        try {
            return JSON.parse(localStorage.getItem(key));
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch {
            return false;
        }
    },
    remove(key) {
        try {
            localStorage.removeItem(key);
        } catch {
            // nothing to do
        }
    },
};

// The server confirmed a save on this page load: the draft it came from can go.
const settlePending = (saved) => {
    try {
        const pending = sessionStorage.getItem(PENDING_KEY);
        if (!pending) return;
        if (saved) storage.remove(pending);
        sessionStorage.removeItem(PENDING_KEY);
    } catch {
        // sessionStorage blocked: the draft stays, and the next open offers it back
    }
};

export const initFlow = () => {
    const dialog = document.querySelector('[data-dy-flow]');
    const dataNode = document.getElementById('dy-flow-data');
    if (!dialog || !dataNode || dialog.dataset.ready === 'true') return null;
    dialog.dataset.ready = 'true';

    const data = JSON.parse(dataNode.textContent);
    const form = dialog.querySelector('[data-dy-flow-form]');
    const draftKey = `nd-diary-draft:${data.user}:${data.mode === 'edit' ? `edit-${data.entryId}` : 'new'}`;

    settlePending(Boolean(data.saved));

    const $ = (selector) => dialog.querySelector(selector);
    const dateInput = form.elements.event_date;
    const dateLabelEl = $('[data-dy-date-label]');
    const photoTiles = [...dialog.querySelectorAll('.dy-ptile')];
    const photosNote = $('[data-dy-photos-note]');
    const photosAll = $('[data-dy-photos-all]');
    const upload = $('[data-dy-upload]');
    const uploadList = $('[data-dy-upload-list]');
    const suggest = $('[data-dy-suggest]');
    const trackSearch = $('[data-dy-track-search]');
    const trackItems = [...dialog.querySelectorAll('.dy-tracks__item[data-track-id]')];
    const scroller = $('[data-dy-scroller]');
    const title = $('[data-dy-title]');
    const text = $('[data-dy-text-input]');
    const count = $('[data-dy-wordcount]');
    const saveNote = $('[data-dy-autosave]');
    const back = $('[data-dy-back]');
    const skip = $('[data-dy-skip]');
    const next = $('[data-dy-next]');
    const submit = $('[data-dy-submit]');

    let step = 'when';
    let showAllPhotos = false;
    let saveTimer = null;
    let returnFocus = null;

    // ------------------------------------------------------------ values

    const blank = () => ({ event_date: isoDate(), mood: '', photo_ids: [], track_id: '', title: '', content: '', people: '' });

    const fill = (values) => {
        const v = { ...blank(), ...values };
        dateInput.value = v.event_date || isoDate();
        for (const radio of form.querySelectorAll('input[name="mood"]')) radio.checked = radio.value === (v.mood ?? '');
        const ids = new Set((v.photo_ids ?? []).map(String));
        for (const box of form.querySelectorAll('input[name="photo_ids[]"]')) box.checked = ids.has(box.value);
        for (const radio of form.querySelectorAll('input[name="track_id"]')) radio.checked = radio.value === String(v.track_id ?? '');
        title.value = v.title ?? '';
        text.value = v.content ?? '';
        form.elements.people.value = v.people ?? '';
        upload.value = '';
        paintUploads();
    };

    const collect = () => ({
        event_date: dateInput.value,
        mood: form.querySelector('input[name="mood"]:checked')?.value ?? '',
        photo_ids: [...form.querySelectorAll('input[name="photo_ids[]"]:checked')].map((box) => box.value),
        track_id: form.querySelector('input[name="track_id"]:checked')?.value ?? '',
        title: title.value,
        content: text.value,
        people: form.elements.people.value,
    });

    // ------------------------------------------------------------ autosave

    const timeLabel = (stamp) => new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' }).format(new Date(stamp));

    const saveDraft = () => {
        clearTimeout(saveTimer);
        const values = collect();
        if (!values.content.trim() && !values.title.trim()) return;
        const savedAt = Date.now();
        saveNote.textContent = storage.set(draftKey, { ...values, savedAt })
            ? `Draft kept on this device at ${timeLabel(savedAt)}`
            : 'This browser won’t keep a draft. Finish before you close it.';
        saveNote.dataset.state = 'saved';
    };

    const scheduleSave = () => {
        clearTimeout(saveTimer);
        saveNote.textContent = 'Keeping a draft…';
        saveNote.dataset.state = 'pending';
        saveTimer = setTimeout(saveDraft, SAVE_DELAY);
    };

    // ------------------------------------------------------------ errors

    const clearErrors = () => {
        for (const node of dialog.querySelectorAll('[data-dy-error]')) {
            node.hidden = true;
            node.textContent = '';
        }
        for (const node of dialog.querySelectorAll('[aria-invalid]')) node.removeAttribute('aria-invalid');
    };

    const showErrors = (errors) => {
        let first = null;
        for (const [field, messages] of Object.entries(errors ?? {})) {
            const base = field.split('.')[0];
            const node = dialog.querySelector(`#fl-${base}-error`);
            if (!node) continue;
            node.textContent = messages[0];
            node.hidden = false;
            dialog.querySelector(`#fl-${base}`)?.setAttribute('aria-invalid', 'true');
            first ??= ERROR_STEP[base] ?? 'words';
        }
        return first;
    };

    // ------------------------------------------------------------ the day

    const paintDate = () => {
        dateLabelEl.textContent = dayLabel(dateInput.value, { year: true });
        title.placeholder = dayLabel(dateInput.value);
        for (const chip of dialog.querySelectorAll('[data-dy-date-shift]')) {
            const shifted = new Date();
            shifted.setDate(shifted.getDate() + Number(chip.dataset.dyDateShift));
            chip.setAttribute('aria-pressed', String(isoDate(shifted) === dateInput.value));
        }
    };

    // ------------------------------------------------------------ photos

    const paintPhotos = () => {
        const day = dateInput.value;
        let ofTheDay = 0;
        for (const tile of photoTiles) {
            const sameDay = tile.dataset.day === day;
            const chosen = tile.querySelector('input').checked;
            if (sameDay) ofTheDay += 1;
            tile.hidden = !(showAllPhotos || sameDay || chosen);
        }
        const label = dayLabel(day);
        photosNote.textContent = ofTheDay
            ? `${ofTheDay} ${ofTheDay === 1 ? 'photo was' : 'photos were'} added on ${label}.`
            : `Nothing was added on ${label}.${photoTiles.length ? ' Show other days, or add one from this device.' : ' Add one from this device.'}`;
        if (photosAll) {
            photosAll.setAttribute('aria-pressed', String(showAllPhotos));
            $('[data-dy-photos-all-label]').textContent = showAllPhotos ? 'Only this day' : 'Show other days too';
        }
    };

    function paintUploads() {
        const files = [...(upload.files ?? [])];
        uploadList.hidden = files.length === 0;
        uploadList.replaceChildren(
            ...files.map((file) => {
                const item = document.createElement('li');
                item.innerHTML = '<i class="fa-regular fa-image" aria-hidden="true"></i>';
                const name = document.createElement('span');
                name.textContent = file.name;
                item.append(name);
                return item;
            }),
        );
    }

    // ------------------------------------------------------------ song

    const paintSuggest = () => {
        const id = data.topTracks?.[dateInput.value];
        const track = id ? data.tracks?.[id] : null;
        suggest.hidden = !track;
        if (!track) return;
        const today = dateInput.value === isoDate();
        $('[data-dy-suggest-why]').textContent = today ? 'Played most today' : `Played most on ${dayLabel(dateInput.value)}`;
        $('[data-dy-suggest-title]').textContent = track.title;
        $('[data-dy-suggest-artist]').textContent = track.artist ?? '';
        suggest.dataset.trackId = String(id);
        const chosen = form.querySelector('input[name="track_id"]:checked')?.value === String(id);
        $('[data-dy-suggest-take]').disabled = chosen;
        $('[data-dy-suggest-take]').textContent = chosen ? 'It’s the song' : 'Make it the song';
    };

    const filterTracks = () => {
        const query = normalise(trackSearch?.value.trim() ?? '');
        for (const item of trackItems) {
            item.hidden = Boolean(query) && !normalise(item.dataset.search).includes(query) && !item.querySelector('input').checked;
        }
    };

    // ------------------------------------------------------------ writing

    const mirror = document.createElement('div');
    mirror.className = 'dy-write__mirror';
    mirror.setAttribute('aria-hidden', 'true');

    // The mirror is a hidden copy of the textarea's box and type. It sizes the textarea
    // (height:auto would collapse it for a moment, shrink the scroller and clamp scrollTop,
    // cancelling the smooth scroll the last keystroke started) and finds the caret's line.
    const syncMirror = (upto) => {
        if (!mirror.isConnected) text.after(mirror);
        const style = getComputedStyle(text);
        // Longhands only: Chrome reports the `font` shorthand as "" once font-feature-settings
        // are in play, and a mirror in the default font measures the wrong line.
        for (const prop of ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'fontFeatureSettings', 'fontVariationSettings', 'letterSpacing', 'lineHeight', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderWidth', 'boxSizing', 'direction', 'textAlign', 'wordSpacing']) {
            mirror.style[prop] = style[prop];
        }
        mirror.style.width = `${text.offsetWidth}px`;
        mirror.textContent = text.value.slice(0, upto);
        const marker = document.createElement('span');
        marker.textContent = '\u200b';
        mirror.append(marker);
        return marker;
    };

    const autosize = () => {
        syncMirror(text.value.length);
        text.style.height = `${mirror.offsetHeight}px`;
    };

    // Typewriter scrolling: the line being written stays at the same height on screen and
    // the page moves under it. Coalesced to one scroll per frame, so fast typing doesn't
    // restart the smooth scroll on every key.
    let caretFrame = 0;
    const placeCaret = () => {
        if (step !== 'words' || document.activeElement !== text) return;
        const caret = text.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop + syncMirror(text.selectionEnd).offsetTop;
        const target = Math.max(0, caret - scroller.clientHeight * CARET_LINE);
        if (Math.abs(target - scroller.scrollTop) > 4) {
            scroller.scrollTo({ top: target, behavior: reduced() ? 'auto' : 'smooth' });
        }
    };
    const keepCaretInPlace = () => {
        cancelAnimationFrame(caretFrame);
        caretFrame = requestAnimationFrame(placeCaret);
    };

    const paintCount = () => {
        const words = text.value.trim() ? text.value.trim().split(/\s+/u).length : 0;
        count.textContent = words ? `${words.toLocaleString()} ${words === 1 ? 'word' : 'words'}, about ${Math.max(1, Math.ceil(words / 200))} min to read` : 'No words yet';
    };

    // Chrome fades while words are going down, and comes back on any pointer movement.
    let focusTimer = null;
    const settleFocus = () => {
        clearTimeout(focusTimer);
        focusTimer = setTimeout(() => dialog.classList.add('is-writing'), 900);
    };
    const wakeChrome = () => {
        clearTimeout(focusTimer);
        dialog.classList.remove('is-writing');
    };

    // ------------------------------------------------------------ steps

    const showStep = (name, { focus = true } = {}) => {
        step = name;
        dialog.dataset.step = name;
        const index = STEPS.indexOf(name);

        for (const panel of dialog.querySelectorAll('[data-dy-step]')) panel.hidden = panel.dataset.dyStep !== name;
        for (const button of dialog.querySelectorAll('[data-dy-step-to]')) {
            const at = STEPS.indexOf(button.dataset.dyStepTo);
            if (at === index) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
            button.dataset.done = String(at < index);
        }

        back.hidden = index === 0;
        skip.hidden = !OPTIONAL.includes(name);
        next.hidden = name === 'words';
        submit.hidden = name !== 'words';
        count.hidden = name !== 'words';
        wakeChrome();

        if (name === 'when') paintDate();
        if (name === 'photos') paintPhotos();
        if (name === 'song') {
            paintSuggest();
            filterTracks();
        }
        if (name === 'words') {
            paintCount();
            requestAnimationFrame(() => {
                autosize();
                if (focus) {
                    (title.value.trim() ? text : title).focus();
                    if (document.activeElement === text) text.setSelectionRange(text.value.length, text.value.length);
                }
                keepCaretInPlace();
            });
            return;
        }

        if (focus) {
            const panel = dialog.querySelector(`[data-dy-step="${name}"]`);
            const target =
                panel.querySelector('input:checked') ??
                panel.querySelector('input:not([type="hidden"]):not([type="file"]), button');
            target?.focus({ preventScroll: true });
        }
    };

    const go = (delta) => showStep(STEPS[Math.min(Math.max(STEPS.indexOf(step) + delta, 0), STEPS.length - 1)]);

    // ------------------------------------------------------------ open / close

    const initialValues = () => (data.mode === 'edit' ? data.values : blank());

    const open = ({ step: wanted } = {}) => {
        if (dialog.open) return;
        returnFocus = document.activeElement;
        clearErrors();

        const draft = storage.get(draftKey);
        if (draft) {
            fill(draft);
            saveNote.textContent = `Picked up your draft from ${timeLabel(draft.savedAt)}`;
            saveNote.dataset.state = 'restored';
        } else {
            fill(initialValues());
            saveNote.textContent = '';
            delete saveNote.dataset.state;
        }
        showAllPhotos = false;

        dialog.showModal();
        document.documentElement.classList.add('dy-flow-open');
        showStep(wanted ?? (data.mode === 'edit' ? 'words' : 'when'));
    };

    const reopenWithErrors = () => {
        returnFocus = null;
        fill({ ...initialValues(), ...data.old });
        dialog.showModal();
        document.documentElement.classList.add('dy-flow-open');
        const first = showErrors(data.errors);
        showStep(first ?? 'words');
    };

    dialog.addEventListener('close', () => {
        saveDraft();
        wakeChrome();
        document.documentElement.classList.remove('dy-flow-open');
        if (returnFocus?.isConnected) returnFocus.focus();
        returnFocus = null;
    });

    // ------------------------------------------------------------ events

    dialog.addEventListener('click', (event) => {
        const to = event.target.closest('[data-dy-step-to]');
        // A mood is a one-tap answer, so a pointer click on one moves on. Only a real click:
        // arrowing through the radio group must not jump to the next step.
        if (event.target.closest('.dy-moods__item') && event.detail > 0) {
            setTimeout(() => step === 'mood' && go(1), 240);
            return;
        }
        if (to) showStep(to.dataset.dyStepTo);
        else if (event.target.closest('[data-dy-next]')) go(1);
        else if (event.target.closest('[data-dy-back]')) go(-1);
        else if (event.target.closest('[data-dy-skip]')) {
            const group = dialog.querySelector(`[data-dy-step="${step}"]`);
            if (step === 'mood') group.querySelector('input[name="mood"][value=""]').checked = true;
            if (step === 'song') form.querySelector('input[name="track_id"][value=""]').checked = true;
            scheduleSave();
            go(1);
        } else if (event.target.closest('[data-dy-flow-close]')) dialog.close();
        else if (event.target.closest('[data-dy-date-shift]')) {
            const shifted = new Date();
            shifted.setDate(shifted.getDate() + Number(event.target.closest('[data-dy-date-shift]').dataset.dyDateShift));
            dateInput.value = isoDate(shifted);
            paintDate();
            scheduleSave();
        } else if (event.target.closest('[data-dy-photos-all]')) {
            showAllPhotos = !showAllPhotos;
            paintPhotos();
        } else if (event.target.closest('[data-dy-suggest-take]')) {
            const radio = form.querySelector(`input[name="track_id"][value="${suggest.dataset.trackId}"]`);
            if (radio) {
                radio.checked = true;
                radio.closest('label').scrollIntoView({ block: 'nearest' });
                paintSuggest();
                scheduleSave();
            }
        }
    });

    form.addEventListener('input', (event) => {
        if (event.target === text) {
            autosize();
            paintCount();
            keepCaretInPlace();
            settleFocus();
        }
        if (event.target === trackSearch) {
            filterTracks();
            return;
        }
        if (event.target === dateInput) paintDate();
        if (event.target !== upload) scheduleSave();
    });

    form.addEventListener('change', (event) => {
        if (event.target === upload) paintUploads();
        if (event.target.name === 'track_id') paintSuggest();
        if (event.target !== upload && event.target !== trackSearch) scheduleSave();
    });

    for (const type of ['keyup', 'click']) {
        text.addEventListener(type, (event) => {
            if (type === 'keyup' && !event.key.startsWith('Arrow') && !['Home', 'End', 'PageUp', 'PageDown'].includes(event.key)) return;
            keepCaretInPlace();
        });
    }
    text.addEventListener('focus', keepCaretInPlace);
    text.addEventListener('blur', wakeChrome);
    dialog.addEventListener('pointermove', wakeChrome);
    window.addEventListener('resize', () => {
        if (dialog.open && step === 'words') {
            autosize();
            keepCaretInPlace();
        }
    });

    // Enter moves on rather than submitting from the middle of the flow.
    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.isComposing || event.target === text) return;
        if (event.target.matches('button, [type="file"]')) return;
        event.preventDefault();
        if (event.target === title) text.focus();
        else if (event.target === form.elements.people) submit.focus();
        else if (event.target !== trackSearch) go(1);
    });

    form.addEventListener('submit', (event) => {
        clearErrors();
        if (!text.value.trim()) {
            event.preventDefault();
            showErrors({ content: ['Write at least a line before closing the day.'] });
            showStep('words');
            return;
        }
        if (!title.value.trim()) title.value = dayLabel(dateInput.value, { year: true });
        saveDraft();
        try {
            sessionStorage.setItem(PENDING_KEY, draftKey);
        } catch {
            // the draft simply outlives the save
        }
        submit.disabled = true;
    });

    form.addEventListener('turbo:submit-end', () => (submit.disabled = false));

    // A submit that came back with errors reopens where it failed, with what was typed.
    const ownContext = data.mode === 'edit' ? `edit:${data.entryId}` : 'create';
    if (data.context === ownContext) reopenWithErrors();

    return { open, close: () => dialog.open && dialog.close() };
};

export const resetFlowForCache = () => {
    const dialog = document.querySelector('[data-dy-flow]');
    if (!dialog) return;
    if (dialog.open) dialog.close();
    delete dialog.dataset.ready;
    document.documentElement.classList.remove('dy-flow-open');
};
