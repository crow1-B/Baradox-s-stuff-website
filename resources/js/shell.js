const drawerQuery = window.matchMedia('(max-width: 1023.98px)');

const shell = () => document.querySelector('[data-app-shell]');
const isOpen = () => Boolean(shell()?.hasAttribute('data-nav-open'));

const behindDrawer = () =>
    [
        document.querySelector('.app-skip'),
        document.querySelector('.app-topbar'),
        document.getElementById('main'),
        document.getElementById('bs-player'),
    ].filter(Boolean);

const drawerFocusables = () => [...shell().querySelectorAll('.app-sidebar a[href], .app-sidebar button:not([disabled])')];

const openNav = () => {
    const root = shell();
    if (!root || !drawerQuery.matches || isOpen()) return;

    root.setAttribute('data-nav-open', '');
    root.querySelector('[data-app-menu-open]').setAttribute('aria-expanded', 'true');
    root.querySelector('.app-backdrop').hidden = false;
    for (const el of behindDrawer()) el.inert = true;

    (root.querySelector('.app-sidebar [aria-current="page"]') ?? root.querySelector('.app-sidebar a')).focus();
};

const closeNav = ({ returnFocus = false } = {}) => {
    const root = shell();
    if (!root || !isOpen()) return;

    root.removeAttribute('data-nav-open');
    const opener = root.querySelector('[data-app-menu-open]');
    opener.setAttribute('aria-expanded', 'false');
    root.querySelector('.app-backdrop').hidden = true;
    for (const el of behindDrawer()) el.inert = false;

    if (returnFocus) opener.focus();
};

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-app-menu-open]')) openNav();
    else if (event.target.closest('[data-app-menu-close]')) closeNav({ returnFocus: true });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Tab' && isOpen()) {
        const items = drawerFocusables();
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
        return;
    }

    if (event.key !== 'Escape' || event.defaultPrevented) return;

    if (isOpen()) {
        closeNav({ returnFocus: true });
        return;
    }

    document.querySelector('[data-visits-close]')?.click();
});
drawerQuery.addEventListener('change', () => closeNav());

document.addEventListener('turbo:visit', () => closeNav());
document.addEventListener('turbo:before-cache', () => closeNav());
