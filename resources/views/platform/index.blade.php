<x-layouts::app title="Plataforma">
    @php
        $labels = ['admin' => 'Administrador', 'trainer' => 'Entrenador', 'receptionist' => 'Recepción', 'staff' => 'Staff', 'client' => 'Cliente'];
        $groups = $permissions->groupBy(fn ($permission) => str($permission->name)->before('.')->toString());
    @endphp
    <div class="tfc-platform-page">
        <x-status-message />
        <p class="tfc-kicker">Administración de plataforma</p>
        <h1 class="tfc-title">Perfiles y permisos</h1>
        <p class="tfc-subtitle">Estos perfiles se aplican a todos los centros. Los administradores de cada centro solo pueden asignarlos.</p>

        <div class="grid gap-6 mt-8">
            @foreach($labels as $name => $label)
                @php($assigned = $roles->get($name)?->permissions->pluck('name')->all() ?? [])
                <form method="POST" action="{{ route('platform.profiles.update', $name) }}" class="tfc-card tfc-form p-6">
                    @csrf @method('PUT')
                    <div class="flex items-start justify-between gap-4"><div><h2 class="tfc-section-title">{{ $label }}</h2><p class="tfc-muted">Perfil global: los cambios afectan a futuras comprobaciones de acceso.</p></div><button class="tfc-button tfc-button--small">Guardar perfil</button></div>
                    <div class="grid gap-5 mt-6 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($groups as $module => $modulePermissions)
                            <fieldset class="tfc-delivery-options"><legend>{{ ucfirst($module) }}</legend>
                                @foreach($modulePermissions as $permission)
                                    <label><input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $assigned, true))> {{ str($permission->name)->after('.')->replace('_', ' ')->headline() }}</label>
                                @endforeach
                            </fieldset>
                        @endforeach
                    </div>
                </form>
            @endforeach
        </div>
    </div>
</x-layouts::app>
