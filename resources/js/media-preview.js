export function formatDuration(seconds) {
    if (!Number.isFinite(Number(seconds)) || Number(seconds) <= 0) return '—';
    const total = Math.floor(Number(seconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60).toString().padStart(2, '0');
    const remainder = (total % 60).toString().padStart(2, '0');
    return hours ? `${hours}:${minutes}:${remainder}` : `${minutes}:${remainder}`;
}

export function createMediaDurationStore() {
    return {
        values: {},
        discover(id, seconds) {
            if (!this.values[id] && Number.isFinite(Number(seconds)) && Number(seconds) > 0) {
                this.values[id] = Number(seconds);
            }
        },
        label(id, known = 0) {
            return formatDuration(Number(known) > 0 ? known : this.values[id]);
        },
    };
}

export function createMediaPreview(id, url, kind, knownDuration = 0) {
    let observer;
    return {
        source: null,
        status: kind === 'unsupported' ? 'unsupported' : 'loading',
        seeking: false,
        destroyed: false,
        init() {
            if (kind === 'unsupported') return;
            if (typeof IntersectionObserver === 'undefined') {
                this.start();
                return;
            }
            observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) this.start();
            }, { rootMargin: '150px' });
            observer.observe(this.$el);
        },
        start() {
            if (this.destroyed || this.source !== null) return;
            observer?.disconnect();
            this.source = url;
        },
        metadata(video) {
            if (this.destroyed || this.status === 'error') return;
            if (!(Number(knownDuration) > 0)) this.$store.mediaDurations.discover(id, video.duration);
            const target = Number.isFinite(video.duration) && video.duration > 0
                ? Math.min(0.1, video.duration / 2) : 0;
            this.seeking = target > 0;
            try {
                if (this.seeking) video.currentTime = target;
            } catch {
                this.seeking = false;
            }
            if (!this.seeking && video.readyState >= 2) this.ready();
        },
        loaded() {
            if (!this.seeking) this.ready();
        },
        ready() {
            if (!this.destroyed && this.status !== 'error') {
                this.seeking = false;
                this.status = 'ready';
            }
        },
        fail() {
            if (this.destroyed) return;
            this.$refs.video?.pause();
            this.status = 'error';
        },
        retry() {
            this.status = 'loading';
            this.seeking = false;
            this.source = null;
            this.$nextTick(() => {
                if (!this.destroyed) {
                    this.source = url;
                    this.$nextTick(() => {
                        if (!this.destroyed) this.$refs.video?.load();
                    });
                }
            });
        },
        destroy() {
            this.destroyed = true;
            observer?.disconnect();
            const video = this.$refs.video;
            if (video) {
                video.pause();
                video.removeAttribute('src');
                video.load();
            }
        },
    };
}
