<?php /* Check-in de staff por sesión de congreso (individual y masivo). Ver AsistenciaSesionController y elascenso/event/brain/ (sesión 11/08/2026). */ ?>
@extends('admin.layouts.app')

@section('title', 'Acreditación — '.$sesion['titulo'])

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Acreditación: {{ $sesion['titulo'] }}</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }} @if($sesion['sala']) · {{ $sesion['sala'] }} @endif</p>
    </div>
    <a href="{{ route('admin.sesiones.index', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a sesiones
    </a>
</div>

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">
        {{ session('status') }}
    </div>
@endif

{{-- Escáner de cámara --}}
<section class="bg-white rounded-lg shadow p-5 mb-6">
    <h2 class="font-bold mb-3 text-sm">Escanear QR</h2>
    <div id="qr-reader" style="max-width: 400px;" class="mx-auto"></div>
    <p id="qrReaderError" class="text-sm text-red-600 mt-2 text-center" style="display:none;">
        No se pudo acceder a la cámara — revisá los permisos del navegador, o usá el campo manual abajo.
    </p>
    <div class="mt-3 text-center">
        <button type="button" id="btnReiniciarScan" class="text-sm text-brand-600 hover:underline" style="display:none;">
            Escanear otro
        </button>
    </div>
</section>

{{-- Búsqueda manual --}}
<section class="bg-white rounded-lg shadow p-5 mb-6">
    <h2 class="font-bold mb-3 text-sm">O escribir la referencia a mano</h2>
    <form id="formManual" class="flex gap-2 flex-wrap" onsubmit="event.preventDefault(); buscarReferencia(document.getElementById('inputReferencia').value);">
        <input type="text" id="inputReferencia" placeholder="ej. LA-3605681A"
               class="flex-1 min-w-[200px] border border-slate-300 rounded-md px-3 py-2 text-sm font-mono uppercase"
               oninput="this.value = this.value.toUpperCase();">
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-md">
            Buscar
        </button>
    </form>
</section>

{{-- Resultado de la búsqueda --}}
<section id="resultadoSection" class="bg-white rounded-lg shadow p-5 mb-6" style="display:none;">
    <h2 class="font-bold mb-1 text-sm">Resultado</h2>
    <p id="resultadoReferencia" class="text-xs text-slate-500 font-mono mb-3"></p>
    <p id="resultadoError" class="text-sm text-red-600" style="display:none;"></p>
    <div id="resultadoParticipantes" class="space-y-2"></div>
</section>

{{-- Check-in masivo --}}
<section class="bg-white rounded-lg shadow p-5">
    <h2 class="font-bold mb-1 text-sm">Check-in masivo</h2>
    <p class="text-xs text-slate-500 mb-3">
        Todos los participantes pagados del evento — marcá varios y acreditalos juntos (útil para grupos que llegan
        a la vez).
    </p>
    <div class="max-h-80 overflow-y-auto border border-slate-200 rounded-md mb-3">
        @forelse ($participantesPagados as $p)
            <label class="flex items-center gap-2 px-3 py-2 text-sm border-b border-slate-100 last:border-b-0">
                <input type="checkbox" class="bulk-checkbox" value="{{ $p['id'] }}">
                {{ $p['nombre'] }} {{ $p['apellido'] }}
                <span class="text-xs text-slate-400">#{{ $p['id'] }}</span>
            </label>
        @empty
            <p class="px-3 py-4 text-sm text-slate-400">No hay participantes pagados en este evento todavía.</p>
        @endforelse
    </div>
    <button type="button" id="btnCheckinBulk" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
        Acreditar seleccionados
    </button>
    <div id="bulkResultado" class="mt-3 text-sm"></div>
</section>

{{-- El layout no tiene una sección/stack de scripts propia — mismo
     criterio que eventos/acreditacion.blade.php. --}}
{{--
    checkinUrlBase se calcula en un bloque PHP aparte, arriba, en vez de
    directo dentro del "echo-json" de más abajo — con 3 elementos y 2
    accesos de array anidados en la misma expresión, el compilador de
    Blade corta la directiva a mitad de camino (bug reproducido: genera
    PHP inválido). Escribir la variable ya calculada evita el problema.
    OJO al editar este comentario: escribir el nombre de una directiva
    de Blade (con el símbolo arroba) ACÁ ADENTRO también la dispara.
--}}
@php
    $checkinUrlBase = route('admin.sesiones.acreditacion.checkin', ['event' => $evento['id'], 'sesion' => $sesion['id'], 'participante' => '__PARTICIPANTE__']);
@endphp
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const LOOKUP_URL = @json(route('admin.sesiones.acreditacion.lookup', [$evento['id'], $sesion['id']]));
const CHECKIN_URL_BASE = @json($checkinUrlBase);
const CHECKIN_BULK_URL = @json(route('admin.sesiones.acreditacion.checkin-bulk', [$evento['id'], $sesion['id']]));
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify(body),
    });
    return { status: res.status, data: await res.json() };
}

async function patchJson(url) {
    const res = await fetch(url, {
        method: 'PATCH',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
    });
    return { status: res.status, data: await res.json() };
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function buscarReferencia(referencia) {
    referencia = (referencia || '').trim();
    if (!referencia) return;

    const section = document.getElementById('resultadoSection');
    const errorEl = document.getElementById('resultadoError');
    const listEl = document.getElementById('resultadoParticipantes');
    section.style.display = '';
    errorEl.style.display = 'none';
    listEl.innerHTML = '<p class="text-sm text-slate-500">Buscando…</p>';
    document.getElementById('resultadoReferencia').textContent = referencia;

    const { status, data } = await postJson(LOOKUP_URL, { referencia });

    if (status !== 200 || !data.success) {
        listEl.innerHTML = '';
        errorEl.textContent = data.error || 'No se encontró ninguna inscripción con esa referencia para este evento.';
        errorEl.style.display = 'block';
        return;
    }

    renderParticipantes(data);
}

function renderParticipantes(data) {
    const listEl = document.getElementById('resultadoParticipantes');
    const pagado = data.pagoStatus === 'paid';
    listEl.innerHTML = '';

    if (!pagado) {
        const aviso = document.createElement('p');
        aviso.className = 'text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 mb-2';
        aviso.textContent = 'Esta inscripción todavía no tiene el pago confirmado — no se puede acreditar.';
        listEl.appendChild(aviso);
    }

    data.participantes.forEach(p => {
        const card = document.createElement('div');
        card.className = 'border border-slate-200 rounded-md p-3 flex items-center justify-between gap-3 flex-wrap';
        card.id = 'participante-' + p.id;

        const info = document.createElement('div');
        info.innerHTML = `<span class="font-semibold text-sm">${escHtml(p.nombre)} ${escHtml(p.apellido)}</span>
            <span class="text-xs text-slate-500"> · Categoría: ${escHtml(p.categoria)}</span>`;
        card.appendChild(info);

        const accion = document.createElement('div');
        if (p.asistioSesion) {
            accion.innerHTML = `<span class="text-sm text-green-700 font-semibold">✓ Ya acreditado en esta sesión</span>`;
        } else if (!pagado) {
            accion.innerHTML = `<span class="text-sm text-slate-400">Pago pendiente</span>`;
        } else {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-1.5 rounded-md';
            btn.textContent = '✓ Acreditar';
            btn.onclick = () => acreditarParticipante(p.id, card);
            accion.appendChild(btn);
        }
        card.appendChild(accion);

        listEl.appendChild(card);
    });
}

async function acreditarParticipante(participanteId, cardEl) {
    const url = CHECKIN_URL_BASE.replace('__PARTICIPANTE__', participanteId);
    const { status, data } = await patchJson(url);

    if (![200, 201].includes(status) || !data.success) {
        alert('⚠ ' + (data.error || 'No se pudo acreditar.'));
        return;
    }

    const accionEl = cardEl.querySelector('div:last-child');
    accionEl.innerHTML = data.alreadyCheckedIn
        ? '<span class="text-sm text-green-700 font-semibold">✓ Ya acreditado en esta sesión</span>'
        : '<span class="text-sm text-green-700 font-semibold">✓ Acreditado</span>';
}

// ── Check-in masivo ─────────────────────────────────────────────────
document.getElementById('btnCheckinBulk').addEventListener('click', async () => {
    const ids = Array.from(document.querySelectorAll('.bulk-checkbox:checked')).map(cb => parseInt(cb.value, 10));
    const resultadoEl = document.getElementById('bulkResultado');

    if (ids.length === 0) {
        resultadoEl.innerHTML = '<p class="text-amber-700">Seleccioná al menos un participante.</p>';
        return;
    }

    resultadoEl.innerHTML = '<p class="text-slate-500">Acreditando…</p>';
    const { status, data } = await postJson(CHECKIN_BULK_URL, { participante_ids: ids });

    if (status !== 200 || !data.success) {
        resultadoEl.innerHTML = `<p class="text-red-600">${escHtml(data.error || 'No se pudo procesar el check-in masivo.')}</p>`;
        return;
    }

    let html = `<p class="text-green-700 font-semibold">✓ ${data.acreditados.length} acreditados ahora.</p>`;
    if (data.yaAcreditados.length > 0) {
        html += `<p class="text-slate-500">${data.yaAcreditados.length} ya estaban acreditados.</p>`;
    }
    if (data.rechazados.length > 0) {
        html += `<p class="text-red-600">${data.rechazados.length} rechazados:</p><ul class="list-disc pl-5 text-red-600">`;
        data.rechazados.forEach(r => {
            html += `<li>#${r.participanteId}: ${escHtml(r.motivo)}</li>`;
        });
        html += '</ul>';
    }
    resultadoEl.innerHTML = html;
});

// ── Cámara ──────────────────────────────────────────────────────────
let html5QrCode = null;

function pararScanner() {
    if (html5QrCode) {
        html5QrCode.stop().catch(() => {});
    }
    document.getElementById('btnReiniciarScan').style.display = '';
}

function iniciarScanner() {
    document.getElementById('btnReiniciarScan').style.display = 'none';
    html5QrCode = new Html5Qrcode('qr-reader');
    html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: 250 },
        (decodedText) => {
            pararScanner();
            buscarReferencia(decodedText);
        },
        () => { /* frame sin QR — normal, no es un error */ }
    ).catch(() => {
        document.getElementById('qrReaderError').style.display = 'block';
    });
}

document.getElementById('btnReiniciarScan').addEventListener('click', iniciarScanner);
document.addEventListener('DOMContentLoaded', iniciarScanner);
</script>
@endsection
