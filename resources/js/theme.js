// The light in the room. partials/theme-boot set the phase before first paint and owns the
// boundaries (window.ndTheme); this keeps it current while a page stays open, cycles the
// override, and keeps the top bar's button honest about what it is doing.

const ORDER = ['auto', 'light', 'dark'];
const ICONS = { dawn: 'fa-cloud-sun', day: 'fa-sun', dusk: 'fa-cloud-moon', night: 'fa-moon' };
const PREF_LABELS = { auto: 'following the clock', light: 'kept light', dark: 'kept dark' };
const SHIFT_MS = 2600;

const theme = () => window.ndTheme;
const root = document.documentElement;

let shiftTimer = null;

const paintControls = () => {
    const { phase, themePref: pref } = root.dataset;
    for (const button of document.querySelectorAll('[data-theme-cycle]')) {
        const icon = button.querySelector('[data-theme-icon]');
        if (icon) icon.className = `fa-solid ${ICONS[phase] ?? 'fa-moon'}`;
        const pinned = button.querySelector('[data-theme-pinned]');
        if (pinned) pinned.hidden = pref === 'auto';
        const label = `Light: ${PREF_LABELS[pref] ?? PREF_LABELS.auto} (${phase}). Change it`;
        button.setAttribute('aria-label', label);
        button.title = label;
    }

    // Mobile browser chrome follows the room too.
    let meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) {
        meta = document.createElement('meta');
        meta.name = 'theme-color';
        document.head.append(meta);
    }
    meta.content = getComputedStyle(root).getPropertyValue('--nd-bg').trim() || '#0b1a20';
};

// Re-resolve the phase. A change of the clock's phase cross-fades the registered colours
// (tokens.css) unless reduced motion is asked for. A change someone asked for switches at
// once: every frame of the fade restyles the whole page, and cut short to a few hundred ms it
// only flickers through its half-way state, where the words briefly vanish.
export const refresh = ({ animate = true } = {}) => {
    if (!theme()) return;
    const before = root.dataset.phase;
    const clock = theme().clockPhase();
    const pref = theme().readPref();
    const phase = theme().resolve(pref, clock);

    if (phase !== before && animate && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        root.classList.add('nd-phase-shift');
        clearTimeout(shiftTimer);
        shiftTimer = setTimeout(() => root.classList.remove('nd-phase-shift'), SHIFT_MS);
    } else if (!animate) {
        // Stop a fade already running, or this change would fade along with it.
        clearTimeout(shiftTimer);
        root.classList.remove('nd-phase-shift');
    }

    root.dataset.clock = clock;
    root.dataset.themePref = pref;
    root.dataset.phase = phase;
    // The registered properties transition, so read the colour once it has settled.
    requestAnimationFrame(paintControls);
    if (phase !== before) document.dispatchEvent(new CustomEvent('nd:phase', { detail: { phase, clock, pref } }));
};

export const cycle = () => {
    if (!theme()) return;
    const next = ORDER[(ORDER.indexOf(theme().readPref()) + 1) % ORDER.length];
    try {
        if (next === 'auto') localStorage.removeItem(theme().KEY);
        else localStorage.setItem(theme().KEY, next);
    } catch {
        // storage blocked: the change holds for this page only
        root.dataset.themePref = next;
    }
    refresh({ animate: false });
};

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-theme-cycle]')) cycle();
});

// Another tab changed it.
window.addEventListener('storage', (event) => {
    if (event.key === theme()?.KEY) refresh({ animate: false });
});

// The clock moves on while a page stays open all day.
setInterval(() => refresh(), 60_000);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') refresh();
});

document.addEventListener('turbo:load', paintControls);
paintControls();
