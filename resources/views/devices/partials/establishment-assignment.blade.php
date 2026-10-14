@if(auth()->user()->assignsDevices())
    <section class="rounded-xl border border-border bg-white p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-text">Asignar dispositivo a establecimiento</h2>
        <p class="mt-1 text-sm text-text-light">Vincula este equipo existente a un establecimiento.</p>
        <form action="{{ $assignmentUrl }}" method="POST" class="mt-5 space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label for="assignment_establishment_id" class="mb-1.5 block text-sm font-medium text-text">Establecimiento</label>
                <select id="assignment_establishment_id" name="establishment_id" required class="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <option value="">Seleccionar establecimiento</option>
                    @foreach($establishments as $establishment)
                        <option value="{{ $establishment->id }}" @selected((string) old('establishment_id', $assignedEstablishmentId) === (string) $establishment->id)>{{ $establishment->name }} — {{ $establishment->address }}</option>
                    @endforeach
                </select>
                @error('establishment_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary/90">Asignar establecimiento</button>
        </form>
    </section>
@endif
