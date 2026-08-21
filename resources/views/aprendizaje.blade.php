<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $nombreClub }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-zinc-950 text-white">
    <main class="max-w-xl px-6 text-center">
        <p class="mb-3 text-sm font-semibold uppercase tracking-[0.3em] text-lime-400">
            Nueva plataforma
        </p>

        <h1 class="text-4xl font-bold sm:text-6xl">
            {{ $nombreClub }}
        </h1>

        <p class="mt-5 text-lg text-zinc-400">
            Nuestra primera vista creada con Laravel y Blade.
        </p>
    </main>
</body>
</html>