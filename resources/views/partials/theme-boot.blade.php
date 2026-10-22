{{-- Runs before first paint so the room never flashes the wrong light. Inline, not a module:
     modules are deferred. theme.js reuses window.ndTheme, so the phase boundaries live here
     and nowhere else. --}}
<script>
    (() => {
        const KEY = 'nd-theme';
        const LIGHT = ['dawn', 'day'];
        const clockPhase = (date = new Date()) => {
            const hour = date.getHours();
            if (hour >= 5 && hour < 8) return 'dawn';
            if (hour >= 8 && hour < 17) return 'day';
            if (hour >= 17 && hour < 20) return 'dusk';
            return 'night';
        };
        // An override keeps the nearest phase of the asked-for kind: "light" at dawn stays
        // dawn, "dark" at dusk stays dusk.
        const resolve = (pref, clock) => {
            if (pref === 'light') return LIGHT.includes(clock) ? clock : 'day';
            if (pref === 'dark') return LIGHT.includes(clock) ? 'night' : clock;
            return clock;
        };
        const readPref = () => {
            try {
                const value = localStorage.getItem(KEY);
                return value === 'light' || value === 'dark' ? value : 'auto';
            } catch {
                return 'auto';
            }
        };
        const apply = () => {
            const root = document.documentElement;
            const clock = clockPhase();
            const pref = readPref();
            root.dataset.clock = clock;
            root.dataset.themePref = pref;
            root.dataset.phase = resolve(pref, clock);
        };
        window.ndTheme = { KEY, clockPhase, resolve, readPref, apply };
        // Lets CSS hold back what a script is about to animate in, only when one will.
        document.documentElement.dataset.js = '';
        apply();
    })();
</script>
