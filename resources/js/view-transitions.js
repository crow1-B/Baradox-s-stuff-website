// Shared-element morphs across a Turbo visit. Turbo wraps the render in a View Transition
// (the view-transition meta); this names exactly one element on each side, so only that
// element morphs and the rest of the page swaps without decoration (tokens.css).
//
//   <a data-morph="dy-entry-7">   the element you clicked, on the old page
//   [data-morph-target="dy-entry-7"]   what it becomes, on the new page
//
// Names are applied at the last moment and removed afterwards: two elements sharing a name
// in one snapshot abort the transition.

const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

let pending = null;

document.addEventListener('click', (event) => {
    const source = event.target.closest('[data-morph]');
    if (!source || reduced() || !document.startViewTransition) return;
    for (const named of document.querySelectorAll('[data-morph-named]')) {
        named.style.viewTransitionName = '';
        delete named.dataset.morphNamed;
    }
    pending = source.dataset.morph;
    source.style.viewTransitionName = pending;
    source.dataset.morphNamed = '';
}, true);

document.addEventListener('turbo:before-render', (event) => {
    if (!pending) return;
    const target = event.detail.newBody.querySelector(`[data-morph-target="${CSS.escape(pending)}"]`);
    if (target) {
        target.style.viewTransitionName = pending;
        target.dataset.morphNamed = '';
    }
    pending = null;
});

// Once the morph has played, names come off so a later same-page transition can reuse them.
document.addEventListener('turbo:load', () => {
    requestAnimationFrame(() => {
        setTimeout(() => {
            for (const named of document.querySelectorAll('[data-morph-named]')) {
                named.style.viewTransitionName = '';
                delete named.dataset.morphNamed;
            }
        }, 600);
    });
});
