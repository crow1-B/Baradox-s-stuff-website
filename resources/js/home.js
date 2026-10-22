// Home: a clock that keeps time while the page stays open, and the visit history dialog
// that /visits renders already open.

let clockTimer = null;

const greet = (now) => {
    const node = document.getElementById('home-greetings');
    if (!node) return;
    const hour = now.getHours();
    const [, greeting, line] = JSON.parse(node.textContent).filter(([from]) => from <= hour).pop();
    const title = document.querySelector('[data-home-greeting]');
    const sub = document.querySelector('[data-home-line]');
    if (title.textContent !== greeting) title.textContent = greeting;
    if (sub.textContent !== line) sub.textContent = line;
};

const tick = () => {
    const clock = document.querySelector('[data-home-clock]');
    if (!clock) {
        clearInterval(clockTimer);
        clockTimer = null;
        return;
    }
    const now = new Date();
    greet(now);
    clock.textContent = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    clock.dateTime = now.toISOString();
};

const initHome = () => {
    if (document.querySelector('[data-home-clock]') && !clockTimer) {
        tick();
        clockTimer = setInterval(tick, 15_000);
    }

    const history = document.querySelector('[data-home-history]');
    if (history && !history.open) {
        // The URL is /visits, so closing it any way is a visit back to Home.
        history.addEventListener('cancel', (event) => {
            event.preventDefault();
            window.Turbo.visit(history.dataset.closeUrl);
        });
        history.addEventListener('click', (event) => {
            if (event.target === history) window.Turbo.visit(history.dataset.closeUrl);
        });
        history.showModal();
    }
};

initHome();
document.addEventListener('turbo:load', initHome);
document.addEventListener('turbo:before-cache', () => document.querySelector('[data-home-history][open]')?.close());
