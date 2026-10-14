@extends('admin.layouts.app')

@section('title', 'Buscar inscripción — Caja')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.caja.index', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">&larr; Volver a Caja</a>
</div>
<h1 class="text-lg font-bold mb-5">Buscar inscripción — {{ $evento['name'] ?? '' }}</h1>

<div class="bg-white rounded-lg shadow p-5 mb-5">
    <label class="block text-sm font-semibold mb-1" for="q">Referencia, documento, nombre o apellido</label>
    <input type="text" id="q" autofocus placeholder="Escribí al menos 2 caracteres…"
           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
</div>

<div id="resultados" class="space-y-3"></div>
<p id="msg" class="text-sm text-slate-500"></p>

{{-- Anular un cobro (02/10/2026) — modal único, reusado para cualquier fila;
     se completa con la referencia elegida al abrirlo. Mismo criterio de
     "motivo obligatorio" que el textarea de quitar taller pagado en
     _formulario.blade.php, pero como flujo aparte (no integrado a la
     edición, ver plan). --}}
<div id="anularModal" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4" style="display:none; z-index:50;">
    <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-5">
        <h2 class="text-base font-bold mb-1">Anular cobro</h2>
        <p class="text-xs text-slate-500 mb-3">Referencia: <span id="anularRef" class="font-mono"></span>. Esto cancela toda la
            inscripción y libera su cupo — no se puede deshacer.</p>
        <div id="anularMovimientos" class="space-y-2 mb-3 max-h-60 overflow-y-auto"></div>
        <label class="block text-sm font-semibold mb-1" for="anularMotivo">Motivo (obligatorio)</label>
        <textarea id="anularMotivo" rows="3" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm mb-3"
            placeholder="Ej: chargeback bancario informado por el banco el 02/10."></textarea>
        <p id="anularMsg" class="text-xs text-red-600 mb-2"></p>
        <div class="flex justify-end gap-2">
            <button type="button" id="anularCancelar" class="bg-white border border-slate-300 hover:bg-slate-50 rounded-md px-3 py-1.5 text-xs font-semibold">Cerrar</button>
            <button type="button" id="anularConfirmar" class="bg-red-600 hover:bg-red-700 text-white rounded-md px-3 py-1.5 text-xs font-semibold">Anular cobro</button>
        </div>
    </div>
</div>

<script>
(function () {
    const eventoId = {{ (int) $evento['id'] }};
    const buscarUrl = @json(route('admin.caja.buscar.resultados', $evento['id']));
    const cobrarUrlBase = @json(route('admin.caja.cobrar-pendiente', [$evento['id'], '__REF__']));
    const editarUrlBase = @json(route('admin.caja.editar', [$evento['id'], '__REF__']));
    const eticketUrlBase = @json(route('admin.caja.eticket', [$evento['id'], '__REF__']));
    const movimientosUrlBase = @json(route('admin.caja.movimientos', [$evento['id'], '__REF__']));
    const anularUrlBase = @json(route('admin.caja.anular-cobro', [$evento['id'], '__REF__']));
    const input = document.getElementById('q');
    const cont = document.getElementById('resultados');
    const msg = document.getElementById('msg');
    let debounceTimer = null;

    const anularModal = document.getElementById('anularModal');
    const anularRef = document.getElementById('anularRef');
    const anularMovimientosCont = document.getElementById('anularMovimientos');
    const anularMotivo = document.getElementById('anularMotivo');
    const anularMsg = document.getElementById('anularMsg');
    let anularReferenciaActual = null;

    const tipoLabels = { inscripcion_nueva: 'Inscripción nueva', cobro_pendiente: 'Cobro pendiente', edicion_pagada: 'Edición pagada', anulacion: 'Anulación' };

    async function abrirModalAnular(referencia) {
        anularReferenciaActual = referencia;
        anularRef.textContent = referencia;
        anularMotivo.value = '';
        anularMsg.textContent = '';
        anularMovimientosCont.innerHTML = 'Cargando…';
        anularModal.style.display = 'flex';

        try {
            const resp = await fetch(movimientosUrlBase.replace('__REF__', referencia));
            const data = await resp.json();
            const movimientos = (data.data || []);
            if (movimientos.length === 0) {
                anularMovimientosCont.innerHTML = '<p class="text-xs text-slate-500">Sin movimientos de caja para esta inscripción.</p>';
                return;
            }
            anularMovimientosCont.innerHTML = movimientos.map(m => {
                const fecha = m.createdAt ? new Date(m.createdAt).toLocaleString() : '';
                const tipo = tipoLabels[m.tipo] || m.tipo;
                const disabled = m.anulable ? '' : 'disabled';
                const opacidad = m.anulable ? '' : 'opacity-50';
                // Observaciones (07/10/2026) — nota libre del cajero,
                // texto escapado acá mismo (este archivo no tiene un
                // escHtml() propio, a diferencia de index.php).
                const obsTxt = (m.observaciones || '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));
                const obsHtml = obsTxt ? `<br><span class="text-xs text-slate-400 italic">${obsTxt}</span>` : '';
                return `<label class="flex items-start gap-2 text-sm border border-slate-200 rounded-md p-2 ${opacidad}">
                    <input type="radio" name="movimiento_id" value="${m.id}" ${disabled} class="mt-1">
                    <span>
                        <strong>${tipo}</strong> — ${Number(m.monto).toFixed(2)} (${m.metodoPago})<br>
                        <span class="text-xs text-slate-500">${fecha}${m.anulable ? '' : ' — ya anulado o no anulable'}</span>${obsHtml}
                    </span>
                </label>`;
            }).join('');
        } catch (e) {
            anularMovimientosCont.innerHTML = '<p class="text-xs text-red-600">No se pudo cargar el historial.</p>';
        }
    }

    document.getElementById('anularCancelar').addEventListener('click', function () {
        anularModal.style.display = 'none';
    });

    document.getElementById('anularConfirmar').addEventListener('click', async function () {
        const seleccionado = anularMovimientosCont.querySelector('input[name="movimiento_id"]:checked');
        if (!seleccionado) { anularMsg.textContent = 'Elegí qué movimiento anular.'; return; }
        const motivo = anularMotivo.value.trim();
        if (!motivo) { anularMsg.textContent = 'El motivo es obligatorio.'; return; }
        if (!confirm('Esto cancela toda la inscripción y libera su cupo. ¿Confirmás la anulación?')) return;

        const btn = this;
        btn.disabled = true;
        anularMsg.textContent = '';
        try {
            const resp = await fetch(anularUrlBase.replace('__REF__', anularReferenciaActual), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ movimiento_id: seleccionado.value, motivo: motivo }),
            });
            const data = await resp.json();
            if (data.success) {
                anularModal.style.display = 'none';
                buscar();
            } else {
                anularMsg.textContent = data.error || 'No se pudo anular el cobro.';
            }
        } catch (e) {
            anularMsg.textContent = 'No se pudo conectar con el servidor.';
        } finally {
            btn.disabled = false;
        }
    });

    function render(items) {
        if (!items || items.length === 0) {
            cont.innerHTML = '';
            msg.textContent = 'Sin resultados.';
            return;
        }
        msg.textContent = '';
        cont.innerHTML = items.map(r => {
            const p = (r.participantes && r.participantes[0]) || {};
            const total = r.totales && r.totales.grand_total !== undefined ? Number(r.totales.grand_total).toFixed(2) : '—';
            const estado = r.pago_status === 'paid' ? 'Pagada' : (r.pago_status === 'pending' ? 'Pendiente' : r.pago_status);
            const editarUrl = editarUrlBase.replace('__REF__', r.referencia);
            const eticketUrl = eticketUrlBase.replace('__REF__', r.referencia);
            {{-- Método de pago en Caja (18/09/2026, ampliado 30/09/2026 con
                 Depósito/Organizador/Cortesía) — botones en vez de un
                 diálogo (más rápido para el cajero con cola de gente, un
                 solo click). --}}
            const cobrarBtn = r.pago_status === 'pending'
                ? `<button type="button" class="btn-cobrar bg-brand-600 hover:bg-brand-700 text-white rounded-md px-3 py-1.5 text-xs font-semibold" data-ref="${r.referencia}" data-metodo="EFECTIVO">Efectivo</button>
                   <button type="button" class="btn-cobrar bg-white border border-brand-600 text-brand-600 hover:bg-brand-50 rounded-md px-3 py-1.5 text-xs font-semibold" data-ref="${r.referencia}" data-metodo="QR">QR</button>
                   <button type="button" class="btn-cobrar bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 rounded-md px-3 py-1.5 text-xs font-semibold" data-ref="${r.referencia}" data-metodo="DEPOSITO">Depósito</button>
                   <button type="button" class="btn-cobrar bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 rounded-md px-3 py-1.5 text-xs font-semibold" data-ref="${r.referencia}" data-metodo="ORGANIZADOR">Organizador</button>
                   <button type="button" class="btn-cobrar bg-white border border-amber-400 text-amber-700 hover:bg-amber-50 rounded-md px-3 py-1.5 text-xs font-semibold" data-ref="${r.referencia}" data-metodo="CORTESIA">Cortesía</button>`
                : '';
            // Anular cobro (02/10/2026) — solo tiene sentido sobre una
            // inscripción ya pagada (hay algo real que revertir).
            const anularBtn = r.pago_status === 'paid'
                ? `<button type="button" class="btn-anular bg-white border border-red-300 text-red-700 hover:bg-red-50 rounded-md px-3 py-1.5 text-xs font-semibold" data-ref="${r.referencia}">Anular cobro</button>`
                : '';
            return `<div class="bg-white rounded-lg shadow p-4 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <p class="font-semibold text-sm">${p.nombre || ''} ${p.apellido || ''} — ${p.numeroDocumento || ''}</p>
                    <p class="text-xs text-slate-500">${r.referencia} — ${estado} — Total: ${total}</p>
                </div>
                <div class="flex gap-2">
                    ${cobrarBtn}
                    <a href="${eticketUrl}" target="_blank" class="bg-white border border-slate-300 hover:bg-slate-50 rounded-md px-3 py-1.5 text-xs font-semibold">Comprobante</a>
                    <a href="${editarUrl}" class="bg-white border border-slate-300 hover:bg-slate-50 rounded-md px-3 py-1.5 text-xs font-semibold">Editar</a>
                    ${anularBtn}
                </div>
            </div>`;
        }).join('');

        cont.querySelectorAll('.btn-anular').forEach(btn => {
            btn.addEventListener('click', function () { abrirModalAnular(btn.dataset.ref); });
        });

        cont.querySelectorAll('.btn-cobrar').forEach(btn => {
            const textoOriginal = btn.textContent;
            btn.addEventListener('click', async function () {
                const metodo = btn.dataset.metodo;
                const etiquetas = {
                    EFECTIVO: 'en efectivo', QR: 'por QR', DEPOSITO: 'por depósito',
                    ORGANIZADOR: 'a cargo del organizador', CORTESIA: 'como cortesía (Bs 0.00)',
                };
                const etiqueta = etiquetas[metodo] || 'en efectivo';
                if (!confirm(`¿Confirmás el cobro ${etiqueta} de esta inscripción?`)) return;

                // Observaciones (07/10/2026) — nota libre y opcional, para
                // cualquier método de pago. Un prompt() en vez de un campo
                // en la fila para no tocar el resto de esta pantalla (lista
                // en vivo que se vuelve a dibujar en cada buscar()) — el
                // flujo sigue siendo "un click" para el caso común (cajero
                // con cola de gente), el prompt solo pide una línea y se
                // puede dejar vacío con Cancelar o aceptando en blanco.
                const observaciones = prompt('Observaciones (opcional) — Enter para dejar en blanco:', '') || '';

                // Deshabilitar los botones de la fila (uno por método), no
                // solo el clickeado — evita un doble cobro si el cajero
                // apreta otro mientras la request está en vuelo.
                const fila = btn.closest('div.flex.gap-2') || btn.parentElement;
                const botonesFila = fila ? fila.querySelectorAll('.btn-cobrar') : [btn];
                botonesFila.forEach(b => b.disabled = true);
                btn.textContent = 'Cobrando…';

                try {
                    const resp = await fetch(cobrarUrlBase.replace('__REF__', btn.dataset.ref), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ metodo_pago: metodo, observaciones: observaciones }),
                    });
                    const data = await resp.json();
                    if (data.success) {
                        window.open(eticketUrlBase.replace('__REF__', btn.dataset.ref), '_blank');
                        buscar();
                    } else {
                        alert(data.error || 'No se pudo cobrar.');
                        botonesFila.forEach(b => b.disabled = false);
                        btn.textContent = textoOriginal;
                    }
                } catch (e) {
                    alert('No se pudo conectar con el servidor.');
                    botonesFila.forEach(b => b.disabled = false);
                    btn.textContent = textoOriginal;
                }
            });
        });
    }

    async function buscar() {
        const q = input.value.trim();
        if (q.length < 2) { cont.innerHTML = ''; msg.textContent = 'Escribí al menos 2 caracteres.'; return; }
        msg.textContent = 'Buscando…';
        try {
            const resp = await fetch(buscarUrl + '?q=' + encodeURIComponent(q));
            const data = await resp.json();
            render(data.data || []);
        } catch (e) {
            msg.textContent = 'No se pudo conectar con el servidor.';
        }
    }

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(buscar, 300);
    });
})();
</script>
@endsection
