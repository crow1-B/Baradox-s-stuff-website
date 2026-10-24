// The seal and the unseal: purely visual. Nothing here touches a key or real ciphertext —
// the glyphs are random, drawn from the base64 alphabet the stored ciphertext uses, and the
// server does all the cryptography exactly as before.
//
// Text is changed in place, text node by text node, so paragraphs and line breaks keep
// their layout. Timing comes from a Web Animations clock rather than Date: it pauses with
// the tab, and follows the document timeline's playback rate.

const GLYPHS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
const MAX_ANIMATED = 1600; // characters; anything past this switches in one go

export const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

const glyph = () => GLYPHS[(Math.random() * GLYPHS.length) | 0];

const clock = (duration) => {
    const animation = new Animation(new KeyframeEffect(null, null, { duration, fill: 'forwards' }), document.timeline);
    animation.play();
    return {
        progress: () => animation.effect.getComputedTiming().progress ?? 1,
        finished: animation.finished,
    };
};

const run = (duration, frame) =>
    new Promise((resolve) => {
        const timer = clock(duration);
        const step = () => {
            const p = timer.progress();
            frame(p);
            if (p < 1) requestAnimationFrame(step);
            else resolve();
        };
        requestAnimationFrame(step);
    });

const textNodes = (root) => {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode: (node) => (node.data.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT),
    });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    return nodes;
};

// Latin glyphs inside an Arabic paragraph would flip its direction mid-animation; pin each
// paragraph to the direction it resolved to before anything changes.
const pinDirection = (root) => {
    for (const el of [root, ...root.querySelectorAll('[dir="auto"]')]) {
        el.dataset.dyDir = el.getAttribute('dir') ?? '';
        el.setAttribute('dir', getComputedStyle(el).direction);
    }
};

const unpinDirection = (root) => {
    for (const el of [root, ...root.querySelectorAll('[data-dy-dir]')]) {
        if (el.dataset.dyDir === undefined) continue;
        if (el.dataset.dyDir) el.setAttribute('dir', el.dataset.dyDir);
        else el.removeAttribute('dir');
        delete el.dataset.dyDir;
    }
};

// One plan per character: when it starts flickering, and the glyph it lands on. A sweep from
// the first character to the last with some scatter, so it reads as a wave, not a wipe.
// Each node also keeps the window its characters move in, so a frame can skip every node the
// wave hasn't reached or has finished with — rewriting a text node relays its whole paragraph.
const plan = (nodes) => {
    const originals = nodes.map((node) => node.data);
    const total = originals.reduce((sum, text) => sum + text.length, 0);
    const span = Math.min(total, MAX_ANIMATED);
    let index = 0;
    const chars = originals.map((text) => {
        const list = [...text].map((char) => {
            const at = index++;
            const keep = char === '\n';
            const start = at < span ? (at / span) * 0.72 + Math.random() * 0.12 : 0.84;
            return { char, keep, start, final: keep ? char : glyph() };
        });
        const starts = list.filter((c) => !c.keep).map((c) => c.start);
        return {
            list,
            first: starts.length ? Math.min(...starts) : 1,
            last: starts.length ? Math.max(...starts) : 0,
            sealed: list.map((c) => c.final).join(''),
            settled: false,
        };
    });
    return { originals, chars };
};

const FLICKER = 0.16;

const paint = (nodes, chars, p, direction) => {
    nodes.forEach((node, n) => {
        const plan = chars[n];
        if (plan.settled) return;
        if (direction === 'seal' && p < plan.first) return;
        if (p >= plan.last + FLICKER) {
            node.data = direction === 'seal' ? plan.sealed : plan.list.map((c) => c.char).join('');
            plan.settled = true;
            return;
        }
        let out = '';
        for (const c of plan.list) {
            if (c.keep) out += c.char;
            else if (direction === 'seal') {
                if (p < c.start) out += c.char;
                else if (p < c.start + FLICKER) out += glyph();
                else out += c.final;
            } else if (p < c.start) out += Math.random() < 0.06 ? glyph() : c.final;
            else if (p < c.start + FLICKER) out += glyph();
            else out += c.char;
        }
        if (node.data !== out) node.data = out;
    });
};

// The words are the point of both animations, so they are brought on screen first: an
// entry with photos above its text would otherwise seal below the fold.
const needsScroll = (text) => {
    const top = text.getBoundingClientRect().top;
    return !(top > 0 && top < window.innerHeight * 0.55);
};

const bringIntoView = async (text) => {
    if (!needsScroll(text)) return;
    text.scrollIntoView({ block: 'start', behavior: 'smooth' });
    await run(450, () => {});
};

// Where the page will be once bringIntoView has run, so the paragraphs that are going to be
// seen can be picked before it starts scrolling.
const landing = (text) => {
    if (!needsScroll(text)) return window.scrollY;
    const margin = parseFloat(getComputedStyle(text).scrollMarginTop) || 0;
    const target = window.scrollY + text.getBoundingClientRect().top - margin;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    return Math.max(0, Math.min(target, max));
};

// Only paragraphs on screen there, or within a quarter of a screen of it, scramble; the rest
// switch in one go where nobody is looking. Every rewritten paragraph is laid out and painted
// again on every frame, and on a phone a long entry's off-screen paragraphs were most of that
// work for nothing anyone could see.
const MARGIN = 0.25;

const onScreen = (nodes, scrollY) => {
    const top = scrollY - window.innerHeight * MARGIN;
    const bottom = scrollY + window.innerHeight * (1 + MARGIN);
    return nodes.map((node) => {
        const box = node.parentElement.getBoundingClientRect();
        const y = box.top + window.scrollY;
        return y + box.height > top && y < bottom;
    });
};

const scrambled = (text) => [...text].map((char) => (char === '\n' ? char : glyph())).join('');

// The seal lands over the middle of whatever part of the text is on screen, not the middle
// of a text that may be several screens long. Placed in px, measured at the moment it lands.
const wax = (text) => {
    const template = document.querySelector('[data-dy-wax-template]');
    const seal = template ? template.content.firstElementChild.cloneNode(true) : null;
    if (!seal) return null;
    const box = text.getBoundingClientRect();
    const visibleTop = Math.max(box.top, 0);
    const visibleBottom = Math.min(box.bottom, window.innerHeight);
    seal.style.top = `${(visibleTop + visibleBottom) / 2 - box.top}px`;
    seal.classList.add('dy-wax--stamp');
    text.append(seal);
    return seal;
};

// Plaintext → ciphertext → a seal pressed over it. Resolves when the seal has landed;
// the caller submits the real form then.
export const seal = async (text, sheet) => {
    if (!text || reducedMotion()) return;

    const nodes = textNodes(text);
    if (!nodes.length) return;
    const shown = onScreen(nodes, landing(text));
    const moving = nodes.filter((_, n) => shown[n]);
    const { chars } = plan(moving);

    await bringIntoView(text);
    pinDirection(text);
    nodes.forEach((node, n) => {
        if (!shown[n]) node.data = scrambled(node.data);
    });
    text.classList.add('is-sealing');
    await run(1500, (p) => paint(moving, chars, p, 'seal'));

    text.classList.add('is-sealed-out');
    sheet?.classList.add('is-sealing');
    const stamp = wax(text);
    await (stamp?.getAnimations?.()[0]?.finished ?? run(650, () => {}));
    await run(250, () => {});
};

// Glyphs resolve into the words, after the seal lifts. The element arrives hidden
// ([data-dy-unseal]) so the plaintext never shows for a frame before this starts.
export const unseal = async (text) => {
    if (!text) return;
    if (reducedMotion()) {
        text.classList.add('is-unsealed');
        return;
    }

    const nodes = textNodes(text);
    const shown = onScreen(nodes, landing(text));
    const moving = nodes.filter((_, n) => shown[n]);
    const { originals, chars } = plan(moving);

    pinDirection(text);
    paint(moving, chars, 0, 'unseal');
    text.classList.add('is-unsealing');
    await bringIntoView(text);

    const lifting = wax(text);
    lifting?.classList.replace('dy-wax--stamp', 'dy-wax--lift');

    await run(1700, (p) => paint(moving, chars, p, 'unseal'));
    moving.forEach((node, n) => (node.data = originals[n]));
    unpinDirection(text);
    text.classList.remove('is-unsealing');
    text.classList.add('is-unsealed');
    lifting?.remove();
};
