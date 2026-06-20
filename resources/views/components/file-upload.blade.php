@props([
    'name',
    'accept' => null,
    'multiple' => false,
    'maxSize' => 10,
])

<div
    x-data="{
        dragging: false,
        files: [],
        handleDrop(e) {
            e.preventDefault();
            this.dragging = false;
            const droppedFiles = e.dataTransfer.files;
            this.addFiles(droppedFiles);
        },
        handleFiles(e) {
            this.addFiles(e.target.files);
        },
        addFiles(fileList) {
            for (let i = 0; i < fileList.length; i++) {
                if (fileList[i].size > {{ $maxSize }} * 1024 * 1024) {
                    alert('El archivo ' + fileList[i].name + ' excede el tamaño máximo de {{ $maxSize }}MB');
                    continue;
                }
                this.files.push(fileList[i]);
            }
        },
        removeFile(index) {
            this.files.splice(index, 1);
        }
    }"
    @dragover.prevent="dragging = true"
    @dragleave.prevent="dragging = false"
    @drop.prevent="handleDrop($event)"
    class="w-full"
>
    <div
        :class="dragging ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-border bg-surface hover:border-text-muted'"
        class="relative rounded-xl border-2 border-dashed p-8 text-center transition-all duration-200"
    >
        <input
            type="file"
            name="{{ $name }}"
            id="{{ $name }}"
            @if ($accept) accept="{{ $accept }}" @endif
            @if ($multiple) multiple @endif
            @change="handleFiles($event)"
            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
        />

        <div class="flex flex-col items-center gap-2 pointer-events-none">
            <div class="w-12 h-12 rounded-full bg-white border border-border flex items-center justify-center">
                <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
            </div>
            <p class="text-sm font-medium text-text">
                <span class="text-primary">Arrastra archivos aquí</span> o haz clic para seleccionar
            </p>
            <p class="text-xs text-text-muted">
                Tamaño máximo: {{ $maxSize }}MB
                @if ($accept)
                    · Formatos: {{ $accept }}
                @endif
            </p>
        </div>
    </div>

    {{-- Selected files list --}}
    <template x-if="files.length > 0">
        <div class="mt-4 space-y-2">
            <template x-for="(file, index) in files" :key="index">
                <div class="flex items-center gap-3 p-3 rounded-lg border border-border bg-white">
                    <div class="w-8 h-8 rounded-lg bg-surface flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-text truncate" x-text="file.name"></p>
                        <p class="text-xs text-text-muted" x-text="(file.size / 1024 / 1024).toFixed(2) + ' MB'"></p>
                    </div>
                    <button type="button" @click="removeFile(index)" class="p-1 text-text-muted hover:text-danger transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </template>
        </div>
    </template>
</div>
