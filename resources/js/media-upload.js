export function createMediaUpload(dependencies = {}) {
    const makeRequest = dependencies.makeRequest ?? (() => new XMLHttpRequest());
    const makeFormData = dependencies.makeFormData ?? ((form) => new FormData(form));
    const navigate = dependencies.navigate ?? ((url) => window.location.assign(url));

    return {
        file: null,
        fileName: '',
        progress: 0,
        isUploading: false,
        processing: false,
        dragOver: false,
        error: '',
        handleFile(file) {
            this.file = file ?? null;
            this.fileName = file?.name ?? '';
            this.error = '';
        },
        handleDrop(files) {
            this.dragOver = false;
            if (this.isUploading || !files?.length) return;
            this.$refs.fileInput.files = files;
            this.handleFile(files[0]);
        },
        clearFile() {
            this.$refs.fileInput.value = '';
            this.handleFile(null);
        },
        submit(event) {
            if (this.isUploading) return;
            if (!this.file) {
                this.error = 'Selecciona un archivo para subir.';
                return;
            }
            this.error = '';
            this.progress = 0;
            this.processing = false;
            this.isUploading = true;
            const request = makeRequest();
            request.open('POST', event.currentTarget.action);
            request.setRequestHeader('Accept', 'application/json');
            request.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) this.progress = Math.min(100, Math.round(e.loaded / e.total * 100));
            });
            request.upload.addEventListener('load', () => {
                this.progress = 100;
                this.processing = true;
            });
            request.addEventListener('load', () => {
                let body = {};
                try { body = JSON.parse(request.responseText); } catch { /* A server error may not be JSON. */ }
                if (request.status >= 200 && request.status < 300 && body.redirect) {
                    navigate(body.redirect);
                    return;
                }
                const validation = Object.values(body.errors ?? {}).flat().join(' ');
                this.error = validation || body.message || 'No se pudo subir el archivo. Intenta nuevamente.';
                this.isUploading = false;
                this.processing = false;
            });
            const failed = () => {
                this.error = 'Se perdió la conexión durante la carga. Comprueba la biblioteca antes de volver a intentarlo.';
                this.isUploading = false;
                this.processing = false;
            };
            request.addEventListener('error', failed);
            request.addEventListener('abort', failed);
            request.send(makeFormData(event.currentTarget));
        },
    };
}
