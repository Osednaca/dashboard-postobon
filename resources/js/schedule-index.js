export function createScheduleIndex(initialView = 'list') {
    return {
        view: initialView,
        refreshError: false,
        refreshing: false,
        timer: null,
        controller: null,
        init() {
            this.timer = window.setInterval(() => this.refresh(), 15000);
        },
        destroy() {
            window.clearInterval(this.timer);
            this.controller?.abort();
        },
        setView(view) {
            this.view = view;
            const url = new URL(window.location.href);
            url.searchParams.set('view', view);
            window.history.replaceState(null, '', url);
            this.$refs.results.querySelectorAll('[data-schedule-pagination] a').forEach(link => {
                const target = new URL(link.href);
                target.searchParams.set('view', view);
                link.href = target.href;
            });
        },
        async refresh() {
            // Do not replace a focused action or a pending confirmation dialog.
            if (this.refreshing || document.hidden || this.$refs.results.contains(document.activeElement)) return;
            this.refreshing = true;
            this.controller = new AbortController();
            const timeout = window.setTimeout(() => this.controller.abort(), 10000);
            try {
                const response = await fetch(window.location.href, {
                    headers: { Accept: 'text/html' }, cache: 'no-store', signal: this.controller.signal,
                });
                if (!response.ok || response.redirected) throw new Error('No se pudo actualizar');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const results = page.querySelector('[data-schedule-results]');
                if (!results) throw new Error('Respuesta inesperada');
                // Keep expanded days open when the automatic status refresh replaces the markup.
                const expandedDays = new Set(Array.from(
                    this.$refs.results.querySelectorAll('[data-calendar-more][open]'),
                    details => details.dataset.calendarMore,
                ));
                this.$refs.results.innerHTML = results.innerHTML;
                this.$refs.results.querySelectorAll('[data-calendar-more]').forEach(details => {
                    details.open = expandedDays.has(details.dataset.calendarMore);
                });
                this.refreshError = false;
            } catch {
                this.refreshError = true;
            } finally {
                window.clearTimeout(timeout);
                this.refreshing = false;
            }
        },
    };
}
