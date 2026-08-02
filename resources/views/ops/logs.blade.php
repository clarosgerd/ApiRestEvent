@extends('ops.layout')

@section('title', 'Logs')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-lg font-bold">Logs</h1>
    <form method="GET" action="{{ route('ops.logs') }}" class="flex gap-2">
        <select name="file" onchange="this.form.submit()" class="border border-slate-300 rounded-md px-3 py-2 text-sm">
            @foreach ($files as $f)
                <option value="{{ $f }}" @selected($f === $file)>{{ $f }}</option>
            @endforeach
        </select>
    </form>
</div>

<pre class="bg-slate-900 text-slate-100 text-xs rounded-lg p-4 overflow-x-auto whitespace-pre-wrap max-h-[70vh] overflow-y-auto">{{ $lines }}</pre>
@endsection
