<x-layouts::auth :title="__('Log in')">
    <div>
        <p class="tfc-login-kicker">Acceso seguro</p>
        <h1>Iniciar sesión</h1>
        <p>Introduce tus credenciales para continuar.</p>
        <x-auth-session-status :status="session('status')" />
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label>Correo electrónico<input name="email" value="{{ old('email') }}" type="email" required autofocus autocomplete="email"></label>
            <label>Contraseña<div class="relative"><input class="w-full" id="login-password" name="password" type="password" required autocomplete="current-password"><button type="button" class="absolute end-3 top-1/2 -translate-y-1/2 text-sm text-zinc-400" data-password-toggle>Ver</button></div></label>
            <label class="!flex !grid-cols-[auto_1fr] !items-center"><input class="!min-h-0 !w-auto" name="remember" type="checkbox" @checked(old('remember'))><span>Recordarme</span></label>
            @if (Route::has('password.request'))<a class="text-sm text-blue-400 underline" href="{{ route('password.request') }}" wire:navigate>¿Has olvidado tu contraseña?</a>@endif
            <button class="tfc-login-submit" type="submit" data-test="login-button">Entrar al panel <span>→</span></button>
        </form>
    </div>
    <script>document.addEventListener('click', event => { if (!event.target.matches('[data-password-toggle]')) return; const input = document.getElementById('login-password'); input.type = input.type === 'password' ? 'text' : 'password'; event.target.textContent = input.type === 'password' ? 'Ver' : 'Ocultar'; });</script>
</x-layouts::auth>
