export function setupPwa({ window = globalThis.window, navigator = globalThis.navigator, document = globalThis.document } = {}) {
    if (!window?.isSecureContext) return;

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' })
            .catch(() => window.console.warn('No se pudo activar el modo sin conexión.'));
    }

    const button = document.querySelector('[data-pwa-install]');
    if (!button) return;

    let promptEvent = null;
    let installed = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    window.addEventListener('beforeinstallprompt', (event) => {
        if (installed) return;
        event.preventDefault();
        promptEvent = event;
        button.hidden = false;
    });
    window.addEventListener('appinstalled', () => {
        installed = true;
        promptEvent = null;
        button.hidden = true;
    });
    button.addEventListener('click', async () => {
        if (!promptEvent) return;
        const currentPrompt = promptEvent;
        promptEvent = null;
        button.hidden = true;
        button.disabled = true;
        try {
            await currentPrompt.prompt();
            await currentPrompt.userChoice;
        } catch {
            // The browser can withdraw the prompt; its own install menu remains available.
        } finally {
            button.disabled = false;
        }
    });
}
