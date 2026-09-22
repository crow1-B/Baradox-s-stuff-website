const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)');

function init() {
    const root = document.querySelector('[data-lg]');
    if (!root || root.dataset.lgReady) return;
    root.dataset.lgReady = '1';
    let flock = null;
    try {
        flock = createFlock(root.querySelector('[data-lg-sky]'), root.querySelector('[data-lg-wire]'));
    } catch (err) {
        console.warn('login: flock disabled', err);
    }

    setupForms(root, () => flock);
}

/* ================================================================ forms */

function setupForms(root, getFlock) {
    const signin = root.querySelector('[data-lg-form="signin"]');
    const errorEl = root.querySelector('[data-lg-error]');
    const inputs = signin.querySelectorAll('.lg-input');
    let engaged = false;
    const engage = () => {
        engaged = true;
        if (signin.contains(document.activeElement)) getFlock()?.settle(true);
    };
    signin.addEventListener('pointerdown', engage);
    signin.addEventListener('keydown', engage);
    signin.addEventListener('input', engage);
    signin.addEventListener('focusin', () => {
        if (engaged) getFlock()?.settle(true);
    });
    signin.addEventListener('focusout', (e) => {
        if (!signin.contains(e.relatedTarget)) getFlock()?.settle(false);
    });

    inputs.forEach((input) => input.addEventListener('input', () => input.removeAttribute('aria-invalid')));

    const showError = (message) => {
        errorEl.textContent = message;
        errorEl.hidden = false;
        inputs.forEach((input) => {
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('aria-describedby', errorEl.id);
        });
    };

    root.querySelectorAll('[data-lg-form]').forEach((form) => {
        const button = form.querySelector('button[type="submit"]');
        const idleLabel = button.textContent;

        const setBusy = (busy) => {
            form.toggleAttribute('data-lg-pending', busy);
            button.disabled = busy;
            button.textContent = busy ? button.dataset.lgBusy : idleLabel;
        };
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (form.hasAttribute('data-lg-pending')) return;
            setBusy(true);

            let response;
            try {
                response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
            } catch {
                return nativeSubmit(form);
            }

            if (response.status === 422) {
                const data = await response.json().catch(() => ({}));
                const first = Object.values(data.errors ?? {})[0]?.[0];
                showError(first ?? data.message ?? 'Wrong username or password.');
                setBusy(false);
                if (form === signin) {
                    const password = signin.querySelector('#password');
                    password.value = '';
                    (signin.querySelector('#username').value ? password : signin.querySelector('#username')).focus();
                }
                return;
            }

            const data = response.ok ? await response.json().catch(() => null) : null;
            if (!data?.redirect) return nativeSubmit(form);

            root.classList.add('is-leaving');
            await getFlock()?.scatter();
            window.location.assign(data.redirect);
        });
    });
}

// Bypasses the submit listener, so this is the ordinary no-JS post.
function nativeSubmit(form) {
    HTMLFormElement.prototype.submit.call(form);
}

/* ================================================================ flock */

// Birds state
const FLYING = 0;
const LANDING = 1;
const PERCHED = 2;
const SCATTER = 3;

const CAPACITY = 420;

// The wire's first path is `M0 10 Q500 190 1000 10` in a 1000×130 viewBox stretched over
// the SVG's box. Its y at a fraction t across the width, in viewBox units.
const WIRE_VB_H = 130;
const wireCurve = (t) => (1 - t) * (1 - t) * 10 + 2 * t * (1 - t) * 190 + t * t * 10;

function createFlock(sky, wire) {
    const canvas = document.createElement('canvas');
    canvas.className = 'lg-flock';
    canvas.setAttribute('aria-hidden', 'true');
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;
    sky.append(canvas);

    const ink = getComputedStyle(document.body).getPropertyValue('--lg-ink').trim() || '#05090b';

    // Structure-of-arrays, allocated once. `n` is how many are live for this screen size.
    const px = new Float32Array(CAPACITY);
    const py = new Float32Array(CAPACITY);
    const vx = new Float32Array(CAPACITY);
    const vy = new Float32Array(CAPACITY);
    const size = new Float32Array(CAPACITY);
    const phase = new Float32Array(CAPACITY);
    const flapRate = new Float32Array(CAPACITY);
    const state = new Uint8Array(CAPACITY);
    const slotOf = new Int16Array(CAPACITY).fill(-1);
    const facing = new Int8Array(CAPACITY);
    let n = 0;

    // Uniform grid for neighbour lookups: a linked list per cell, rebuilt every frame.
    let cols = 0;
    let rows = 0;
    let cellHead = new Int32Array(0);
    const cellNext = new Int32Array(CAPACITY);

    // Geometry, all in sky-local CSS pixels.
    let W = 0;
    let H = 0;
    let dpr = 1;
    let scale = 1;
    let slots = []; // { x, y, taken }
    let wireMidY = 0;

    let mode = 'free'; // free | settled | scatter
    let rng = mulberry32(0x0c70); // seeded, so the still composition is the same every visit
    let lastAssign = 0;
    let pointer = null;
    let falcon = null;
    let nextFalcon = 0;
    let raf = 0;
    let last = 0;
    let onScreen = true;
    let scatterDone = null;

    const tuning = () => {
        const view = 46 * scale;
        return {
            view,
            view2: view * view,
            sep: 20 * scale,
            maxSpeed: (mode === 'settled' ? 2.2 : 3.3) * scale,
            minSpeed: (mode === 'settled' ? 1.2 : 1.7) * scale,
            margin: 70 * scale,
        };
    };

    /* ---------------------------------------------------------- layout */

    function measure() {
        const rect = sky.getBoundingClientRect();
        W = Math.max(1, rect.width);
        H = Math.max(1, rect.height);
        scale = clamp(Math.min(W, 1100) / 1100, 0.7, 1);

        dpr = Math.min(window.devicePixelRatio || 1, W < 700 ? 1.5 : 2);
        canvas.width = Math.round(W * dpr);
        canvas.height = Math.round(H * dpr);

        const cell = 46 * scale;
        cols = Math.ceil(W / cell) + 1;
        rows = Math.ceil(H / cell) + 1;
        cellHead = new Int32Array(cols * rows);

        // Slots along the wire, a crow's width apart with a little jitter.
        const wr = wire.getBoundingClientRect();
        const left = wr.left - rect.left;
        const top = wr.top - rect.top;
        const wireY = (x) => top + (wireCurve(clamp((x - left) / wr.width, 0, 1)) / WIRE_VB_H) * wr.height;
        wireMidY = wireY(W / 2);

        const gap = 13 * scale;
        slots = [];
        for (let x = W * 0.05; x < W * 0.95; x += gap + rng() * gap * 0.6) {
            slots.push({ x, y: wireY(x), taken: false });
        }
        // Birds already on the wire keep their slot if it still exists, otherwise fly off.
        for (let i = 0; i < n; i++) {
            if (slotOf[i] < 0) continue;
            const slot = slots[slotOf[i]];
            if (slot) slot.taken = true;
            else takeOff(i);
        }

        // Population follows sky area: a few hundred on a desktop, far fewer on a phone.
        const target = Math.round(clamp((W * H) / 2300, 70, CAPACITY));
        if (target > n) spawn(target - n);
        for (let i = target; i < n; i++) if (slotOf[i] >= 0 && slots[slotOf[i]]) slots[slotOf[i]].taken = false;
        n = Math.min(n, target);
    }

    function spawn(count) {
        const cx = W * (0.35 + rng() * 0.3);
        const cy = H * (0.3 + rng() * 0.15);
        const heading = rng() * Math.PI * 2;
        for (let k = 0; k < count && n < CAPACITY; k++, n++) {
            const i = n;
            const a = rng() * Math.PI * 2;
            const r = Math.sqrt(rng());
            px[i] = cx + Math.cos(a) * r * W * 0.26;
            py[i] = cy + Math.sin(a) * r * H * 0.16;
            vx[i] = Math.cos(heading) * 2 + (rng() - 0.5);
            vy[i] = Math.sin(heading) * 2 + (rng() - 0.5);
            size[i] = 0.72 + rng() * 0.5;
            phase[i] = rng() * Math.PI * 2;
            flapRate[i] = 0.24 + rng() * 0.12;
            state[i] = FLYING;
            slotOf[i] = -1;
            facing[i] = rng() < 0.5 ? -1 : 1;
        }
    }

    /* ---------------------------------------------------------- behaviour */

    function takeOff(i) {
        if (slotOf[i] >= 0 && slots[slotOf[i]]) slots[slotOf[i]].taken = false;
        slotOf[i] = -1;
        state[i] = FLYING;
        vx[i] = (rng() - 0.5) * 2.4;
        vy[i] = -(1.8 + rng() * 1.6);
    }

    function settle(on) {
        if (mode === 'scatter') return;
        const next = on ? 'settled' : 'free';
        if (next === mode) return;
        mode = next;
        if (!on) {
            for (let i = 0; i < n; i++) if (state[i] === LANDING || state[i] === PERCHED) takeOff(i);
        }
        if (REDUCED.matches) compose();
    }

    // Trickle birds down to the wire one at a time: a roost fills, it doesn't teleport.
    function assignLanding(now) {
        if (mode !== 'settled' || now - lastAssign < 55) return;
        const want = Math.min(Math.round(n * 0.3), slots.length);
        let onWire = 0;
        for (let i = 0; i < n; i++) if (state[i] === LANDING || state[i] === PERCHED) onWire++;
        if (onWire >= want) return;

        const free = slots.map((s, k) => (s.taken ? -1 : k)).filter((k) => k >= 0);
        if (!free.length) return;
        for (let tries = 0; tries < 8; tries++) {
            const i = Math.floor(rng() * n);
            if (state[i] !== FLYING) continue;
            const k = free[Math.floor(rng() * free.length)];
            slots[k].taken = true;
            slotOf[i] = k;
            state[i] = LANDING;
            break;
        }
        lastAssign = now;
    }

    function step(dt, now) {
        const t = tuning();

        // Where the flock drifts: a slow Lissajous wander in open sky, pulled low and tight
        // over the wire while settling.
        let ax0;
        let ay0;
        let pull;
        if (mode === 'settled') {
            ax0 = W * (0.5 + 0.16 * Math.sin(now * 0.00013));
            ay0 = clamp(wireMidY - H * 0.3, H * 0.2, H * 0.6);
            pull = 0.0011;
        } else {
            ax0 = W * (0.5 + 0.3 * Math.sin(now * 0.000105) + 0.08 * Math.sin(now * 0.00041 + 2));
            ay0 = H * (0.4 + 0.13 * Math.sin(now * 0.00017 + 1));
            pull = 0.00045;
        }

        updateFalcon(now, dt);
        assignLanding(now);

        // Grid build.
        cellHead.fill(-1);
        const cell = t.view;
        for (let i = 0; i < n; i++) {
            if (state[i] !== FLYING) continue;
            const c = cellIndex(px[i], py[i], cell);
            cellNext[i] = cellHead[c];
            cellHead[c] = i;
        }

        const floor = wireMidY + 10 * scale;

        for (let i = 0; i < n; i++) {
            const s = state[i];
            if (s === PERCHED) continue;

            let ax = 0;
            let ay = 0;
            let maxSpeed = t.maxSpeed;
            let minSpeed = t.minSpeed;

            if (s === SCATTER) {
                vx[i] *= 1 + 0.02 * dt;
                vy[i] = vy[i] * (1 + 0.02 * dt) - 0.05 * dt;
                px[i] += vx[i] * dt;
                py[i] += vy[i] * dt;
                phase[i] += 0.55 * dt;
                continue;
            }

            if (s === LANDING) {
                const slot = slots[slotOf[i]];
                if (!slot) { takeOff(i); continue; }
                const dx = slot.x - px[i];
                const dy = slot.y - py[i];
                const d = Math.hypot(dx, dy) || 1;
                if (d < 1.5 * scale) {
                    px[i] = slot.x; py[i] = slot.y; vx[i] = 0; vy[i] = 0;
                    state[i] = PERCHED;
                    continue;
                }
                const want = Math.min(t.maxSpeed * 1.1, d * 0.07 + 0.35);
                ax = (dx / d) * want - vx[i];
                ay = (dy / d) * want - vy[i];
                const a = Math.hypot(ax, ay);
                const cap = 0.3;
                if (a > cap) { ax *= cap / a; ay *= cap / a; }
                vx[i] += ax * dt;
                vy[i] += ay * dt;
                px[i] += vx[i] * dt;
                py[i] += vy[i] * dt;
                phase[i] += (flapRate[i] + (d < 40 ? 0.25 : 0)) * dt;
                continue;
            }

            // Flocking over a capped set of neighbours: starlings track about seven, and the
            // cap also bounds the work in a dense knot.
            let count = 0;
            let avx = 0, avy = 0, cx = 0, cy = 0, sx = 0, sy = 0;
            const gx = clamp(Math.floor(px[i] / cell), 0, cols - 1);
            const gy = clamp(Math.floor(py[i] / cell), 0, rows - 1);
            outer: for (let oy = -1; oy <= 1; oy++) {
                const ry = gy + oy;
                if (ry < 0 || ry >= rows) continue;
                for (let ox = -1; ox <= 1; ox++) {
                    const rx = gx + ox;
                    if (rx < 0 || rx >= cols) continue;
                    for (let j = cellHead[ry * cols + rx]; j !== -1; j = cellNext[j]) {
                        if (j === i) continue;
                        const dx = px[j] - px[i];
                        const dy = py[j] - py[i];
                        const d2 = dx * dx + dy * dy;
                        if (d2 > t.view2) continue;
                        avx += vx[j]; avy += vy[j];
                        cx += px[j]; cy += py[j];
                        if (d2 < t.sep * t.sep && d2 > 0.0001) {
                            const d = Math.sqrt(d2);
                            const push = (1 - d / t.sep) / d;
                            sx -= dx * push; sy -= dy * push;
                        }
                        if (++count >= 7) break outer;
                    }
                }
            }
            if (count) {
                ax += (avx / count - vx[i]) * 0.065;
                ay += (avy / count - vy[i]) * 0.065;
                ax += (cx / count - px[i]) * 0.0022;
                ay += (cy / count - py[i]) * 0.0022;
                ax += sx * 1.1;
                ay += sy * 1.1;
            }

            ax += clamp((ax0 - px[i]) * pull, -0.08, 0.08);
            ay += clamp((ay0 - py[i]) * pull, -0.08, 0.08);

            // Keep to the sky: turn back before the edges, and stay above the wire.
            const m = t.margin;
            if (px[i] < m) ax += 0.14 * (1 - px[i] / m);
            else if (px[i] > W - m) ax -= 0.14 * (1 - (W - px[i]) / m);
            if (py[i] < m * 0.6) ay += 0.14 * (1 - py[i] / (m * 0.6));
            else if (py[i] > floor - m) ay -= 0.16 * clamp(1 - (floor - py[i]) / m, 0, 2);

            // Threats: the pointer, and the unseen falcon that makes the waves.
            if (pointer) {
                const r = flee(i, pointer.x, pointer.y, 150 * scale, 0.9);
                if (r) { ax += r[0]; ay += r[1]; maxSpeed *= 1.35; }
            }
            if (falcon) {
                const r = flee(i, falcon.x, falcon.y, 115 * scale, 1.1);
                if (r) { ax += r[0]; ay += r[1]; maxSpeed *= 1.45; }
            }

            vx[i] += ax * dt;
            vy[i] += ay * dt;
            const sp = Math.hypot(vx[i], vy[i]) || 1;
            const target = clamp(sp, minSpeed, maxSpeed);
            vx[i] *= target / sp;
            vy[i] *= target / sp;
            px[i] += vx[i] * dt;
            py[i] += vy[i] * dt;
            phase[i] += flapRate[i] * dt * (0.7 + sp / (t.maxSpeed * 1.4));
        }

        if (mode === 'scatter' && scatterDone) {
            let gone = true;
            for (let i = 0; i < n && gone; i++) {
                if (px[i] > -20 && px[i] < W + 20 && py[i] > -20 && py[i] < H + 20) gone = false;
            }
            if (gone) finishScatter();
        }
    }

    function flee(i, x, y, radius, strength) {
        const dx = px[i] - x;
        const dy = py[i] - y;
        const d2 = dx * dx + dy * dy;
        if (d2 > radius * radius) return null;
        const d = Math.sqrt(d2) || 1;
        const f = (1 - d / radius) * strength;
        return [(dx / d) * f, (dy / d) * f];
    }

    // Every so often something unseen cuts through the flock. Real murmurations ripple
    // like this because of predators; without it the flock drifts but never breathes.
    function updateFalcon(now, dt) {
        if (mode !== 'free') { falcon = null; return; }
        if (!falcon) {
            if (!nextFalcon) nextFalcon = now + 5000 + rng() * 4000;
            if (now < nextFalcon || n === 0) return;
            let cx = 0, cy = 0;
            for (let i = 0; i < n; i++) { cx += px[i]; cy += py[i]; }
            cx /= n; cy /= n;
            const from = rng() < 0.5 ? -1 : 1;
            const sx = cx + from * W * 0.45;
            const sy = cy - H * 0.25 + rng() * H * 0.3;
            const d = Math.hypot(cx - sx, cy - sy) || 1;
            const speed = 6 * scale;
            falcon = { x: sx, y: sy, vx: ((cx - sx) / d) * speed, vy: ((cy - sy) / d) * speed, until: now + 3200 };
            return;
        }
        falcon.x += falcon.vx * dt;
        falcon.y += falcon.vy * dt;
        if (now > falcon.until) {
            falcon = null;
            nextFalcon = now + 8000 + rng() * 7000;
        }
    }

    function cellIndex(x, y, cell) {
        return clamp(Math.floor(y / cell), 0, rows - 1) * cols + clamp(Math.floor(x / cell), 0, cols - 1);
    }

    /* ---------------------------------------------------------- drawing */

    function draw() {
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = ink;

        // Two depths: smaller birds read as further away and a touch lighter. One path per
        // depth keeps this to a handful of fills however many birds there are.
        for (const far of [true, false]) {
            ctx.globalAlpha = far ? 0.72 : 0.95;
            ctx.beginPath();
            for (let i = 0; i < n; i++) {
                if (state[i] === PERCHED || (size[i] < 0.92) !== far) continue;
                wing(i);
            }
            ctx.fill();
        }

        ctx.globalAlpha = 1;
        ctx.beginPath();
        for (let i = 0; i < n; i++) if (state[i] === PERCHED) perched(i);
        ctx.fill();
    }

    // A bird in flight, seen from below: a nose, two wingtips that beat, a notch for the tail.
    function wing(i) {
        const sp = Math.hypot(vx[i], vy[i]) || 1;
        const ux = vx[i] / sp;
        const uy = vy[i] / sp;
        const s = size[i] * scale;
        const beat = Math.sin(phase[i]);
        const span = s * (3.1 + 2.1 * beat);
        const sweep = s * (1.2 - 0.9 * beat);
        const nx = -uy;
        const ny = ux;
        const x = px[i];
        const y = py[i];
        ctx.moveTo(x + ux * 3.6 * s, y + uy * 3.6 * s);
        ctx.lineTo(x - ux * sweep + nx * span, y - uy * sweep + ny * span);
        ctx.lineTo(x - ux * 1.9 * s, y - uy * 1.9 * s);
        ctx.lineTo(x - ux * sweep - nx * span, y - uy * sweep - ny * span);
        ctx.closePath();
    }

    // A crow on the wire: body, head, beak, tail, feet on the line.
    function perched(i) {
        const s = size[i] * scale * 1.1;
        const f = facing[i];
        const x = px[i];
        const y = py[i];
        const bx = x;
        const by = y - 4.1 * s;
        const rx = 2.2 * s;
        const ry = 3.4 * s;
        const rot = f * 0.38;
        ctx.moveTo(bx + rx * Math.cos(rot), by + rx * Math.sin(rot));
        ctx.ellipse(bx, by, rx, ry, rot, 0, Math.PI * 2);
        const hx = x + f * 1.5 * s;
        const hy = y - 7.7 * s;
        ctx.moveTo(hx + 1.6 * s, hy);
        ctx.arc(hx, hy, 1.6 * s, 0, Math.PI * 2);
        ctx.moveTo(hx + f * 0.8 * s, hy - 0.7 * s);
        ctx.lineTo(hx + f * 3.8 * s, hy + 0.2 * s);
        ctx.lineTo(hx + f * 0.8 * s, hy + 0.9 * s);
        ctx.moveTo(x - f * 0.6 * s, y - 2.6 * s);
        ctx.lineTo(x - f * 3.6 * s, y + 2.4 * s);
        ctx.lineTo(x - f * 2.2 * s, y + 2.9 * s);
        ctx.lineTo(x + f * 0.8 * s, y - 1.2 * s);
        ctx.closePath();
    }

    /* ---------------------------------------------------------- still composition */

    // Reduced motion: no loop at all. The same simulation runs forward off-screen from a
    // seeded start and the result is drawn once — a flock caught mid-turn with a row of
    // crows on the wire. It is the scene from the animated page, stopped, not a slower one.
    function compose() {
        rng = mulberry32(0x0c70);
        n = 0;
        const keep = mode;
        mode = 'free';
        falcon = null;
        nextFalcon = 0;
        measure();
        for (let k = 0; k < 260; k++) step(1, 20000 + k * 16.7);
        mode = keep;

        const roost = Math.min(Math.round(n * (keep === 'settled' ? 0.3 : 0.14)), slots.length);
        const order = slots.map((_, k) => k).sort(() => rng() - 0.5);
        for (let k = 0, i = 0; k < roost && i < n; i++) {
            if (state[i] !== FLYING) continue;
            const slot = slots[order[k++]];
            slot.taken = true;
            slotOf[i] = slots.indexOf(slot);
            state[i] = PERCHED;
            px[i] = slot.x;
            py[i] = slot.y;
        }
        draw();
    }

    /* ---------------------------------------------------------- loop */

    function frame(now) {
        raf = 0;
        if (!canvas.isConnected) return destroy(); // removed in devtools: stop cleanly
        const dt = last ? Math.min((now - last) / (1000 / 60), 3) : 1;
        last = now;
        step(dt, now);
        draw();
        schedule();
    }

    function schedule() {
        if (raf || REDUCED.matches || document.hidden || !onScreen) return;
        raf = requestAnimationFrame(frame);
    }

    // `last` resets here so the first frame after a pause is not one enormous step.
    function pause() {
        if (raf) cancelAnimationFrame(raf);
        raf = 0;
        last = 0;
    }

    // A login page burning a GPU in a background tab is indefensible; a hidden tab gets
    // no frames at all, not throttled ones.
    const onVisibility = () => (document.hidden ? pause() : schedule());

    const onPointer = (e) => {
        const r = sky.getBoundingClientRect();
        pointer = { x: e.clientX - r.left, y: e.clientY - r.top };
    };
    const onPointerGone = () => { pointer = null; };
    const onPointerOut = (e) => { if (!e.relatedTarget) pointer = null; };
    const onTouchEnd = (e) => { if (e.pointerType !== 'mouse') pointer = null; };

    const onMotionPref = () => {
        pause();
        if (REDUCED.matches) compose();
        else schedule();
    };

    let resizeTimer = 0;
    const resizer = new ResizeObserver(() => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => (REDUCED.matches ? compose() : measure()), 120);
    });

    const visibility = new IntersectionObserver(([entry]) => {
        onScreen = entry.isIntersecting;
        onScreen ? schedule() : pause();
    });

    function destroy() {
        pause();
        resizer.disconnect();
        visibility.disconnect();
        document.removeEventListener('visibilitychange', onVisibility);
        window.removeEventListener('pointermove', onPointer);
        window.removeEventListener('pointerdown', onPointer);
        window.removeEventListener('pointerup', onTouchEnd);
        window.removeEventListener('blur', onPointerGone);
        document.removeEventListener('pointerout', onPointerOut);
        REDUCED.removeEventListener('change', onMotionPref);
        finishScatter();
    }

    /* ---------------------------------------------------------- scatter */

    // The hand-off. Every bird, roosting ones included, bursts up and out from below the
    // form. Resolves once they are all off the sky, or after a ceiling either way.
    function scatter() {
        if (REDUCED.matches || !raf) return Promise.resolve();
        mode = 'scatter';
        pointer = null;
        falcon = null;
        const ox = W / 2;
        const oy = H * 1.25;
        for (let i = 0; i < n; i++) {
            if (slotOf[i] >= 0 && slots[slotOf[i]]) slots[slotOf[i]].taken = false;
            slotOf[i] = -1;
            const dx = px[i] - ox;
            const dy = py[i] - oy;
            const d = Math.hypot(dx, dy) || 1;
            const speed = (6.5 + rng() * 4) * scale;
            vx[i] = (dx / d) * speed + vx[i] * 0.3;
            vy[i] = (dy / d) * speed + vy[i] * 0.3;
            state[i] = SCATTER;
        }
        return new Promise((resolve) => {
            scatterDone = resolve;
            setTimeout(finishScatter, 700);
        });
    }

    function finishScatter() {
        const done = scatterDone;
        scatterDone = null;
        done?.();
    }

    /* ---------------------------------------------------------- start */

    measure();
    resizer.observe(sky);
    visibility.observe(sky);
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pointermove', onPointer, { passive: true });
    window.addEventListener('pointerdown', onPointer, { passive: true });
    window.addEventListener('pointerup', onTouchEnd, { passive: true });
    window.addEventListener('blur', onPointerGone);
    document.addEventListener('pointerout', onPointerOut);
    REDUCED.addEventListener('change', onMotionPref);

    if (REDUCED.matches) compose();
    else schedule();

    return { settle, scatter };
}

/* ================================================================ helpers */

function clamp(v, lo, hi) {
    return v < lo ? lo : v > hi ? hi : v;
}

// Small seeded PRNG (mulberry32).
function mulberry32(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/* ================================================================ start */

// Last in the file on purpose: a module runs while readyState is already 'interactive', so
// the else-branch calls init() synchronously, and every const above must exist by then.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}
