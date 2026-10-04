<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>@include('partials.head')</head>
<body class="tfc-app-body">
    @php($membership = request()->attributes->get('organizationMembership'))
    <header class="tfc-mobile-header">
        <a href="{{ route('dashboard') }}" class="tfc-sidebar-brand"><span>THE</span><strong>FITNESS<br>CLUB</strong></a>
        <div class="tfc-mobile-context"><span>&Aacute;rea privada</span><strong>{{ $title ?? 'Panel' }}</strong></div>
        <details class="tfc-mobile-menu"><summary aria-label="Abrir men&uacute;">Men&uacute;</summary><nav aria-label="Navegaci&oacute;n m&oacute;vil">
            <a href="{{ route('dashboard') }}">Dashboard</a><a href="{{ route('miembros.create') }}">Nuevo miembro</a><a href="{{ route('miembros.index') }}">Ver miembros</a><a href="{{ route('attendance.index') }}">Asistencia</a>
            @if (auth()->user()->is_platform_admin)<a href="{{ route('platform.index') }}">Plataforma</a>@endif
            @if ($membership && in_array($membership->role->value, ['owner', 'admin', 'trainer']))<a href="{{ route('payments.index') }}">Pagos</a>@endif
            <a href="{{ route('club-settings.edit') }}">Configuraci&oacute;n</a>@if ($membership && in_array($membership->role->value, ['owner', 'admin']))<a href="{{ route('team.index') }}">Admin. usuarios</a>@endif
        </nav></details>
    </header>
    <div class="tfc-app-shell">
        <aside class="tfc-app-sidebar">
            <a href="{{ route('dashboard') }}" class="tfc-sidebar-brand"><span>THE</span><strong>FITNESS<br>CLUB</strong></a>
            <p class="tfc-sidebar-caption">ÁREA PRIVADA TFC</p>
            <nav class="tfc-app-nav" aria-label="Navegación principal">
                <a href="{{ route('dashboard') }}" @class(['is-active' => request()->routeIs('dashboard')])>Dashboard</a>
                <a href="{{ route('miembros.create') }}" @class(['is-active' => request()->routeIs('miembros.create')])>Nuevo miembro</a>
                <a href="{{ route('miembros.index') }}" @class(['is-active' => request()->routeIs('miembros.index', 'miembros.show', 'miembros.edit')])>Ver miembros</a>
                <a href="{{ route('attendance.index') }}" @class(['is-active' => request()->routeIs('attendance.*')])>Asistencia</a>
                @if (auth()->user()->is_platform_admin)<a href="{{ route('platform.index') }}" @class(['is-active' => request()->routeIs('platform.*')])>Plataforma</a>@endif
                @if ($membership && in_array($membership->role->value, ['owner', 'admin', 'trainer']))<a href="{{ route('payments.index') }}" @class(['is-active' => request()->routeIs('payments.*')])>Pagos</a>@endif
                <a href="{{ route('club-settings.edit') }}" @class(['is-active' => request()->routeIs('club-settings.*')])>Configuración</a>
                @if ($membership && in_array($membership->role->value, ['owner', 'admin']))<a href="{{ route('team.index') }}" @class(['is-active' => request()->routeIs('team.*')])>Admin. usuarios</a>@endif
            </nav>
            <div class="tfc-sidebar-footer">
                @if ($membership)<a href="{{ route('kiosk.show', $membership->organization) }}" target="_blank" class="tfc-kiosk-link">↗ Abrir Kiosco</a>@endif
                <div class="tfc-user-name">{{ auth()->user()->name }}</div><div class="tfc-user-email">{{ auth()->user()->email }}</div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="tfc-logout">Cerrar sesión →</button></form>
            </div>
        </aside>
        <main class="tfc-app-main"><header class="tfc-desktop-context"><span>&Aacute;rea privada TFC</span><strong>{{ $title ?? 'Panel' }}</strong></header>{{ $slot }}</main>
    </div>
    <script>
    document.addEventListener('input', event => { const form = event.target.closest('[data-live-filter]'); if (!form) return; clearTimeout(form._filterTimer); form._filterTimer = setTimeout(() => form.requestSubmit(), 300); });
    document.addEventListener('change', event => { const form = event.target.closest('[data-live-filter]'); if (form) form.requestSubmit(); });

    (() => {
        const forms = [...document.querySelectorAll('form.tfc-form, .tfc-form form, form[data-registration-wizard]')]
            .filter(form => form.method.toLowerCase() !== 'get' && !form.matches('[data-live-filter], [data-no-unsaved-warning]'));
        let allowNavigation = false;
        const hasChanges = () => forms.some(form => form.dataset.dirty === 'true' && form.dataset.submitting !== 'true');
        const markDirty = event => {
            const form = event.target.closest('form');
            if (!forms.includes(form) || event.target.type === 'hidden' || event.target.disabled) return;
            form.dataset.dirty = 'true';
        };
        forms.forEach(form => {
            form.addEventListener('input', markDirty);
            form.addEventListener('change', markDirty);
            form.addEventListener('reset', () => window.setTimeout(() => { form.dataset.dirty = 'false'; }, 0));
        });
        document.addEventListener('submit', event => {
            const form = event.target;
            if (!forms.includes(form) || event.defaultPrevented || !form.checkValidity() || form.dataset.submitting === 'true') return;
            form.dataset.submitting = 'true';
        });
        window.addEventListener('beforeunload', event => {
            if (!allowNavigation && hasChanges()) { event.preventDefault(); event.returnValue = ''; }
        });
        document.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (!link || link.target === '_blank' || link.hasAttribute('download') || !hasChanges()) return;
            const destination = new URL(link.href, window.location.href);
            if (destination.href === window.location.href || destination.hash && destination.pathname === window.location.pathname) return;
            if (!window.confirm('Tienes cambios sin guardar. Si sales, se perderán. ¿Quieres continuar?')) { event.preventDefault(); return; }
            allowNavigation = true;
        });
    })();
    </script>
</body>
</html>
