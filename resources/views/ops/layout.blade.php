<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Ops') — inscrito</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: { 600: '#022858', 700: '#011a3d' } } } },
        };
    </script>
</head>
<body class="bg-slate-100 text-slate-900 font-sans min-h-screen">
    @auth
        <header class="bg-brand-600 text-white px-5 py-3 flex justify-between items-center flex-wrap gap-2">
            <nav class="flex flex-wrap gap-4 text-sm font-semibold">
                <a class="hover:underline" href="{{ route('ops.backups') }}">Backups</a>
                <a class="hover:underline" href="{{ route('ops.jobs') }}">Jobs</a>
                <a class="hover:underline" href="{{ route('ops.logs') }}">Logs</a>
                <a class="hover:underline" href="{{ route('ops.enlaces') }}">Enlaces</a>
            </nav>
            <form method="POST" action="{{ route('ops.logout') }}">
                @csrf
                <button type="submit" class="text-sm bg-brand-700 hover:bg-brand-700/80 px-3 py-1.5 rounded-md">
                    Salir ({{ auth()->user()->email }})
                </button>
            </form>
        </header>
    @endauth

    <main class="max-w-5xl mx-auto p-5">
        @if (session('status'))
            <div class="bg-green-50 text-green-800 border border-green-200 rounded-md px-4 py-2 mb-4 text-sm">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="bg-red-50 text-red-800 border border-red-200 rounded-md px-4 py-2 mb-4 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
