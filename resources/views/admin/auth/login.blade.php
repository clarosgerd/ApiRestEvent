@extends('admin.layouts.app')

@section('title', 'Ingresar — Admin Eventos')

@section('content')
<div class="max-w-sm mx-auto mt-16 bg-white rounded-lg shadow p-6">
    <h1 class="text-lg font-bold mb-4">Panel de administración de eventos</h1>
    <form method="POST" action="{{ route('admin.login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-semibold mb-1" for="email">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="password">Contraseña</label>
            <input type="password" name="password" id="password" required
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-md px-3 py-2 text-sm font-semibold">
            Ingresar
        </button>
    </form>
</div>
@endsection
