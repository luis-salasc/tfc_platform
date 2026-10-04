<x-layouts::app :title="'Configuración'">
    <div class="tfc-platform-page tfc-narrow-page"><p class="tfc-kicker">Cuenta y seguridad</p><h1 class="tfc-title">Configuración</h1><p class="tfc-subtitle">Actualiza los datos de acceso del panel.</p>
        <form method="POST" action="{{ route('club-settings.update') }}" class="tfc-card tfc-form mt-8">@csrf @method('PUT')
            <div class="tfc-form-grid"><label>Nombre<input name="name" value="{{ old('name', auth()->user()->name) }}" required></label><label>Email<input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required></label></div>
            <hr><h2 class="tfc-section-title">Cambiar contraseña</h2><p class="tfc-muted">Déjalo vacío si no quieres modificarla.</p><div class="tfc-form-grid"><label>Contraseña actual<input type="password" name="current_password"></label><label>Nueva contraseña<input type="password" name="password"></label><label>Repite la nueva contraseña<input type="password" name="password_confirmation"></label></div><button class="tfc-button">Guardar cambios →</button>
        </form>
    </div>
</x-layouts::app>
