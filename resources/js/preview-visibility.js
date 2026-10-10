export function initiallyVisible() {
    return typeof IntersectionObserver === 'undefined' && !globalThis.document?.hidden;
}

export function observePreviewVisibility(element, changed) {
    const doc = globalThis.document;
    let inViewport = typeof IntersectionObserver === 'undefined';
    let stopped = false;
    const notify = () => {
        if (!stopped) changed(inViewport && !doc?.hidden);
    };
    const observer = typeof IntersectionObserver === 'undefined' ? null : new IntersectionObserver(entries => {
        inViewport = entries.some(entry => entry.isIntersecting);
        notify();
    });
    observer?.observe(element);
    doc?.addEventListener('visibilitychange', notify);
    notify();
    return () => {
        stopped = true;
        observer?.disconnect();
        doc?.removeEventListener('visibilitychange', notify);
    };
}
