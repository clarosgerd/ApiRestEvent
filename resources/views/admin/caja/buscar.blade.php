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

<script>
(function () {
    const eventoId = {{ (int) $evento['id'] }};
    const buscarUrl = @json(route('admin.caja.buscar.resultados', $evento['id']));
    const cobrarUrlBase = @json(route('admin.caja.cobrar-pendiente', [$evento['id'], '__REF__']));
    const editarUrlBase = @json(route('admin.caja.editar', [$evento['id'], '__REF__']));
    const eticketUrlBase = @json(route('admin.caja.eticket', [$evento['id'], '__REF__']));
    const input = document.getElementById('q');
    const cont = document.getElementById('resultados');
    const msg = document.getElementById('msg');
    let debounceTimer = null;

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
            const cobrarBtn = r.pago_status === 'pending'
                ? `<button type="button" class="btn-cobrar bg-brand-600 hover:bg-brand-700 text-white rounded-md px-3 py-1.5 text-xs font-semibold" data-ref="${r.referencia}">Cobrar</button>`
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
                </div>
            </div>`;
        }).join('');

        cont.querySelectorAll('.btn-cobrar').forEach(btn => {
            btn.addEventListener('click', async function () {
                if (!confirm('¿Confirmás el cobro en efectivo de esta inscripción?')) return;
                btn.disabled = true;
                btn.textContent = 'Cobrando…';
                try {
                    const resp = await fetch(cobrarUrlBase.replace('__REF__', btn.dataset.ref), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await resp.json();
                    if (data.success) {
                        window.open(eticketUrlBase.replace('__REF__', btn.dataset.ref), '_blank');
                        buscar();
                    } else {
                        alert(data.error || 'No se pudo cobrar.');
                        btn.disabled = false;
                        btn.textContent = 'Cobrar';
                    }
                } catch (e) {
                    alert('No se pudo conectar con el servidor.');
                    btn.disabled = false;
                    btn.textContent = 'Cobrar';
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
