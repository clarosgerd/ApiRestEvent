<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Eventos')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: { 600: '#022858', 700: '#011a3d' } } } },
        };
    </script>
</head>
<body class="bg-slate-100 text-slate-900 font-sans min-h-screen">
{{--
    Consolidación monolito (21/08/2026) — este layout es la migración 1:1
    de elascenso/admin-eventos/resources/views/layouts/app.blade.php, con
    los nombres de ruta prefijados `admin.*` y cada link de nav envuelto en
    Route::has() porque, mientras dure la Fase 1 (sub-fases 1a-1e), la
    mayoría de esas pantallas todavía viven solo en admin-eventos y no acá
    todavía. A medida que cada sub-fase se completa, su link deja de estar
    condicionado (ya no hace falta el @if) — no se saca el patrón hasta que
    la Fase 1 esté 100% migrada. Ver
    ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
--}}
@if (session('admin_token'))
    @php($admin = session('admin_user'))
    <header class="bg-brand-600 text-white px-5 py-3 flex justify-between items-center flex-wrap gap-2">
        <nav class="flex flex-wrap gap-4 text-sm font-semibold">
            @if (Route::has('admin.dashboard'))
                <a class="hover:underline" href="{{ route('admin.dashboard') }}">
                    {{ ($admin['rol'] ?? null) === 'super_admin' ? 'Eventos' : 'Mi evento' }}
                </a>
            @endif
            @if (($admin['rol'] ?? null) === 'super_admin')
                <a class="hover:underline" href="{{ route('admin.usuarios.index') }}">Usuarios</a>
                <a class="hover:underline" href="{{ route('admin.auditoria.index') }}">Auditoría</a>
                @if (Route::has('admin.socios.index'))
                    <a class="hover:underline" href="{{ route('admin.socios.index') }}">Socios</a>
                @endif
                @if (Route::has('admin.organizadores.index'))
                    <a class="hover:underline" href="{{ route('admin.organizadores.index') }}">Organizadores</a>
                @endif
                @if (Route::has('admin.presupuesto-categorias.index'))
                    <a class="hover:underline" href="{{ route('admin.presupuesto-categorias.index') }}">Categorías de presupuesto</a>
                @endif
                @if (Route::has('admin.catalogos.index'))
                    <a class="hover:underline" href="{{ route('admin.catalogos.index') }}">Catálogos</a>
                @endif
            @endif
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="text-sm bg-brand-700 hover:bg-brand-700/80 px-3 py-1.5 rounded-md">
                Salir ({{ $admin['email'] ?? '' }} — {{ $admin['rol'] ?? '' }})
            </button>
        </form>
    </header>
@endif
<main class="max-w-5xl mx-auto p-5">
    @if (session('status'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-5 text-sm">
            {{ $errors->first() }}
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
