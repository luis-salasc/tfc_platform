<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body>
        <main class="tfc-login-page">
            <div class="tfc-login-shell">
                <aside class="tfc-login-hero" aria-label="The Fitness Club">
                    <a class="tfc-brand" href="{{ route('home') }}"><span>THE</span><strong>FITNESS<br>CLUB</strong></a>
                    <div><p class="tfc-login-kicker">Área privada TFC</p><h1>Todo el centro.<br><em>Un solo lugar.</em></h1><p>Gestiona miembros, asistencia, progreso y pagos con una experiencia conectada a The Fitness Club.</p></div>
                    <a class="tfc-login-back" href="{{ route('home') }}">← Volver a la web</a>
                </aside>
                <section class="tfc-login-card">{{ $slot }}</section>
            </div>
        </main>
        @fluxScripts
    </body>
</html>
