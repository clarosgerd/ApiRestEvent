@extends('admin.layouts.app')

@section('title', 'Acreditación — '.$evento['name'])

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Acreditación</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a editar evento
    </a>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6 flex items-center justify-between flex-wrap gap-3">
    <p class="text-sm text-slate-600">
        Escaneá el QR de referencia del e-ticket (o escribilo a mano abajo) para marcar presente a cada participante.
        Solo se puede acreditar si la inscripción ya está pagada.
    </p>
    <p class="text-sm font-semibold text-slate-700 whitespace-nowrap">
        <span id="acreditadosCount">{{ $totalAcreditados }}</span> de <span id="totalCount">{{ $totalPagados }}</span> acreditados
    </p>
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

{{-- Búsqueda por nombre/apellido (07/10/2026) — para cuando el staff no
     tiene el QR/referencia a mano (ej. ponentes sin ticket físico). Puede
     haber más de una coincidencia, a diferencia de la búsqueda por
     referencia — se listan y al elegir una se reusa buscarReferencia(). --}}
<section class="bg-white rounded-lg shadow p-5 mb-6">
    <h2 class="font-bold mb-3 text-sm">O buscar por nombre y apellido</h2>
    <form id="formNombre" class="flex gap-2 flex-wrap" onsubmit="event.preventDefault(); buscarPorNombre(document.getElementById('inputNombre').value);">
        <input type="text" id="inputNombre" placeholder="ej. Alvaro Justiniano"
               class="flex-1 min-w-[200px] border border-slate-300 rounded-md px-3 py-2 text-sm">
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-md">
            Buscar
        </button>
    </form>
    <div id="resultadoNombreLista" class="mt-3 space-y-1"></div>
</section>

{{-- Resultado de la búsqueda --}}
<section id="resultadoSection" class="bg-white rounded-lg shadow p-5" style="display:none;">
    <h2 class="font-bold mb-1 text-sm">Resultado</h2>
    <p id="resultadoReferencia" class="text-xs text-slate-500 font-mono mb-3"></p>
    <p id="resultadoError" class="text-sm text-red-600" style="display:none;"></p>
    {{-- Talleres y pagos en acreditación (26/08/2026) — el staff necesita
         ver, en el momento de acreditar, qué pagó esta persona (inscripción
         base + cualquier cobro adicional por SIP) para poder resolver ahí
         mismo un pago 'pending'/'error' sin tocado, en vez de enterarse
         después. --}}
    <div id="resultadoPagos" class="mb-3"></div>
    <div id="resultadoParticipantes" class="space-y-2"></div>
</section>

{{-- Imprimir gafete directo (08/10/2026) — pedido real del usuario: un
     click que mande el gafete directo al diálogo de impresión del
     navegador, sin pasar por una pestaña nueva. El PDF ya se sirve
     'inline' en las dos capas (ApiRestEvent y el proxy de acá), así que
     un iframe oculto puede cargarlo y disparar su propio print() — mismo
     origen, misma sesión, sin problema de CORS. Un solo iframe reusado
     en cada click, no uno por participante. --}}
<iframe id="gafeteImprimirFrame" style="display:none"></iframe>

{{-- El layout (layouts/app.blade.php) no tiene @yield('scripts')/@stack —
     el script va acá adentro, mismo criterio que usa elascenso-blade para
     páginas que necesitan JS de terceros sin bundler. --}}
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const ACREDITACION_LOOKUP_URL = @json(route('admin.acreditacion.lookup', $evento['id']));
const ACREDITACION_BUSCAR_URL = @json(route('admin.acreditacion.buscar', $evento['id']));
const ACREDITACION_CHECKIN_URL_BASE = @json(route('admin.acreditacion.checkin', [$evento['id'], '__PARTICIPANTE__']));
// Gafete por demanda (23/09/2026) — un participante puntual, ver
// EventoController::gafetePdfParticipante() en este repo.
const GAFETE_PDF_URL_BASE = @json(route('admin.eventos.gafete-pdf-participante', [$evento['id'], '__PARTICIPANTE__']));
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

async function getJson(url) {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
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

    const { status, data } = await postJson(ACREDITACION_LOOKUP_URL, { referencia });

    if (status !== 200 || !data.success) {
        listEl.innerHTML = '';
        errorEl.textContent = data.error || 'No se encontró ninguna inscripción con esa referencia para este evento.';
        errorEl.style.display = 'block';
        return;
    }

    renderParticipantes(data);
}

const ESTADO_PAGO_LISTA = {
    paid: { label: 'Pagado', cls: 'text-green-700' },
    pending: { label: 'Pago pendiente', cls: 'text-amber-700' },
    error: { label: 'Pago con error', cls: 'text-red-700' },
    cancelada: { label: 'Cancelada', cls: 'text-slate-400' },
};

async function buscarPorNombre(q) {
    q = (q || '').trim();
    const listaEl = document.getElementById('resultadoNombreLista');
    if (q.length < 2) {
        listaEl.innerHTML = '<p class="text-sm text-amber-700">Escribí al menos 2 letras.</p>';
        return;
    }
    listaEl.innerHTML = '<p class="text-sm text-slate-500">Buscando…</p>';

    const { status, data } = await getJson(ACREDITACION_BUSCAR_URL + '?q=' + encodeURIComponent(q));

    if (status !== 200 || !data.success) {
        listaEl.innerHTML = `<p class="text-sm text-red-600">${escHtml(data.error || 'No se pudo buscar.')}</p>`;
        return;
    }

    const resultados = data.resultados || [];
    if (!resultados.length) {
        listaEl.innerHTML = '<p class="text-sm text-slate-500">Sin coincidencias.</p>';
        return;
    }

    listaEl.innerHTML = '';
    resultados.forEach(r => {
        const estado = ESTADO_PAGO_LISTA[r.pagoStatus] || { label: r.pagoStatus, cls: 'text-slate-500' };
        const fila = document.createElement('button');
        fila.type = 'button';
        fila.className = 'w-full text-left border border-slate-200 rounded-md px-3 py-2 text-sm hover:bg-slate-50 flex items-center justify-between gap-2 flex-wrap';
        fila.innerHTML = `<span><span class="font-semibold">${escHtml(r.nombre)} ${escHtml(r.apellido)}</span>
            <span class="text-xs text-slate-400 font-mono">· ${escHtml(r.referencia)}</span></span>
            <span class="text-xs ${estado.cls}">${r.checkedInAt ? '✓ Ya acreditado' : estado.label}</span>`;
        fila.onclick = () => {
            buscarReferencia(r.referencia);
            document.getElementById('resultadoSection').scrollIntoView({ behavior: 'smooth' });
        };
        listaEl.appendChild(fila);
    });
}

// Etiqueta + color por estado de un pago adicional — 'pending'/'error' se
// destacan a propósito (es justo lo que el staff necesita ver para actuar),
// 'paid' queda discreto (ya está resuelto), 'expired' informativo nomás.
const PAGO_ADICIONAL_ESTADO = {
    paid:    { label: 'Pagado',    cls: 'bg-green-50 text-green-700 border-green-200' },
    pending: { label: 'Pendiente', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
    error:   { label: '⚠ Sin resolver', cls: 'bg-red-50 text-red-700 border-red-200' },
    expired: { label: 'Vencido',   cls: 'bg-slate-100 text-slate-500 border-slate-200' },
};

function renderResumenPagos(data) {
    const cont = document.getElementById('resultadoPagos');
    cont.innerHTML = '';

    const totalBase = document.createElement('p');
    totalBase.className = 'text-xs text-slate-600 mb-1';
    const moneda = data.monedaPago === 'USD' ? 'US$' : 'Bs';
    const monto = data.monedaPago === 'USD' ? data.totalPagado : (data.totales ? data.totales.grand_total : null);
    totalBase.innerHTML = `<strong>Inscripción:</strong> ${monto != null ? moneda + ' ' + Number(monto).toFixed(2) : '—'}`
        + (data.tipoPago ? ` · ${escHtml(data.tipoPago)}` : '');
    cont.appendChild(totalBase);

    (data.pagosAdicionales || []).forEach(pago => {
        const estado = PAGO_ADICIONAL_ESTADO[pago.pagoStatus] || { label: pago.pagoStatus, cls: 'bg-slate-100 text-slate-600 border-slate-200' };
        const row = document.createElement('p');
        row.className = `text-xs border rounded-md px-2 py-1 mt-1 inline-block mr-2 ${estado.cls}`;
        row.innerHTML = `<strong>Adicional ${escHtml(pago.referencia)}:</strong> Bs ${Number(pago.monto).toFixed(2)} · ${estado.label}`;
        cont.appendChild(row);
    });
}

function renderParticipantes(data) {
    const listEl = document.getElementById('resultadoParticipantes');
    const pagado = data.pagoStatus === 'paid';
    listEl.innerHTML = '';
    renderResumenPagos(data);

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

        const talleresTxt = (p.talleres || []).length
            ? '<br><span class="text-xs text-slate-500">Talleres: ' + p.talleres.map(t => escHtml(t.tallerNombre || '')).join(', ') + '</span>'
            : '';
        const info = document.createElement('div');
        info.innerHTML = `<span class="font-semibold text-sm">${escHtml(p.nombre)} ${escHtml(p.apellido)}</span>
            <span class="text-xs text-slate-500"> · Categoría: ${escHtml(p.categoria)}</span>${talleresTxt}`;
        card.appendChild(info);

        const accion = document.createElement('div');
        accion.className = 'flex items-center gap-2 flex-wrap';
        if (p.checkedInAt) {
            accion.innerHTML = `<span class="text-sm text-green-700 font-semibold">✓ Ya acreditado</span>`;
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

        if (pagado) {
            const linkGafete = document.createElement('a');
            linkGafete.href = GAFETE_PDF_URL_BASE.replace('__PARTICIPANTE__', p.id);
            linkGafete.target = '_blank';
            linkGafete.rel = 'noopener';
            linkGafete.className = 'text-sm text-brand-600 hover:underline whitespace-nowrap';
            linkGafete.textContent = '🖨 Imprimir gafete';
            accion.appendChild(linkGafete);

            const btnImprimirDirecto = document.createElement('button');
            btnImprimirDirecto.type = 'button';
            btnImprimirDirecto.className = 'text-sm text-brand-600 hover:underline whitespace-nowrap';
            btnImprimirDirecto.textContent = '🖨️ Imprimir directo';
            btnImprimirDirecto.onclick = () => imprimirGafeteDirecto(p.id);
            accion.appendChild(btnImprimirDirecto);
        }

        card.appendChild(accion);

        listEl.appendChild(card);
    });
}

/**
 * Imprimir gafete directo (08/10/2026) — carga el mismo PDF que ya usa
 * "Imprimir gafete" en un iframe oculto y, al terminar de cargar, llama a
 * su propio print() — abre el diálogo de impresión del navegador ya
 * posicionado sobre el gafete, sin pestaña nueva ni click extra dentro
 * del visor de PDF.
 */
function imprimirGafeteDirecto(participanteId) {
    const frame = document.getElementById('gafeteImprimirFrame');
    const url = GAFETE_PDF_URL_BASE.replace('__PARTICIPANTE__', participanteId);

    frame.onload = () => {
        // Margen chico — onload del iframe no garantiza que el visor de
        // PDF embebido terminó de renderizar en todos los navegadores.
        setTimeout(() => {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                // Fallback si el navegador bloquea el print del iframe
                // (ej. Safari viejo) — mismo comportamiento que ya existe
                // hoy con "Imprimir gafete".
                window.open(url, '_blank');
            }
        }, 300);
    };
    frame.src = url;
}

async function acreditarParticipante(participanteId, cardEl) {
    const url = ACREDITACION_CHECKIN_URL_BASE.replace('__PARTICIPANTE__', participanteId);
    const { status, data } = await patchJson(url);

    if (status !== 200 || !data.success) {
        alert('⚠ ' + (data.error || 'No se pudo acreditar.'));
        return;
    }

    const accionEl = cardEl.querySelector('div:last-child');
    accionEl.innerHTML = '<span class="text-sm text-green-700 font-semibold">✓ Acreditado</span>';

    // Solo suma al contador si de verdad se acreditó ahora — un
    // reescaneo (alreadyCheckedIn) no debe inflar el contador de nuevo.
    if (!data.alreadyCheckedIn) {
        const counter = document.getElementById('acreditadosCount');
        counter.textContent = String(parseInt(counter.textContent, 10) + 1);
    }
}

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
