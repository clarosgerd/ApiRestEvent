@extends('ops.layout')

@section('title', 'Ingresar')

@section('content')
<div class="max-w-sm mx-auto mt-16 bg-white rounded-lg shadow p-6">
    <h1 class="text-lg font-bold mb-4 text-center">Panel de operaciones</h1>
    <form method="POST" action="{{ route('ops.login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-semibold mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1">Contraseña</label>
            <input type="password" name="password" required
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1">
            Recordarme
        </label>
        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-md px-4 py-2 text-sm font-semibold">
            Ingresar
        </button>
    </form>
</div>
@endsection
