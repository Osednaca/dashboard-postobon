<x-app-layout>
    <x-slot name="title">Editar Usuario</x-slot>

    @php $user = $user ?? (object)['id' => 1, 'name' => 'Juan Pérez', 'email' => 'juan@3dfan.com', 'role' => 'operator']; @endphp

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('users.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Editar Usuario</h2>
                <p class="mt-1 text-sm text-text-muted">{{ $user->name }}</p>
            </div>
        </div>

        <form action="{{ route('users.update', $user->id) }}" method="POST" class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]" x-data="{ changePassword: false }">
            @csrf
            @method('PUT')

            <div class="p-6 space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-text mb-1.5">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" required value="{{ old('name', $user->name) }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-text mb-1.5">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" required value="{{ old('email', $user->email) }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-text mb-1.5">Rol <span class="text-danger">*</span></label>
                    <select name="role" id="role" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Administrador</option>
                        <option value="operator" {{ old('role', $user->role) === 'operator' ? 'selected' : '' }}>Operador</option>
                    </select>
                </div>

                <div class="pt-4 border-t border-border">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" x-model="changePassword" class="w-4 h-4 rounded border-border text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-text">Cambiar contraseña</span>
                    </label>
                </div>

                <div x-show="changePassword" x-transition class="space-y-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-text mb-1.5">Nueva Contraseña</label>
                        <input type="password" name="password" id="password" :required="changePassword" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors" placeholder="Mínimo 8 caracteres">
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-text mb-1.5">Confirmar Contraseña</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" :required="changePassword" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors" placeholder="Repite la contraseña">
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-surface border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('users.index') }}" class="px-4 py-2.5 rounded-lg text-sm font-medium text-text-light hover:bg-surface-dark transition-colors">Cancelar</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">Guardar Cambios</button>
            </div>
        </form>
    </div>
</x-app-layout>
