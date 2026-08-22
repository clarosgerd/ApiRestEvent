<?php /* Mapa de ubicación de delivery de un evento — de solo lectura.
     Ver elascenso/event/brain/DEPLOY-CHECKLIST-MAPA-DELIVERY-12082026.md. */ ?>
@extends('admin.layouts.app')

@section('title', 'Delivery — ' . $evento['name'] . ' — Admin Eventos')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<div class="mb-4">
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">← Volver al evento</a>
    <h1 class="text-lg font-bold mt-1">Delivery — {{ $evento['name'] }}</h1>
    <p class="text-sm text-slate-500">
        Participantes que pidieron recibir su kit a domicilio. El pin en el mapa es opcional —
        lo marca el participante al inscribirse, no todos van a tener uno; cuando falta, solo
        queda la dirección de texto.
    </p>
    <div class="flex gap-2 flex-wrap mt-2 text-xs">
        @foreach (($resumen ?? []) as $estado => $cantidad)
            @if ($estado !== 'total')
                <span class="px-2 py-1 rounded bg-slate-100 text-slate-600">{{ ucfirst($estado) }}: <strong>{{ $cantidad }}</strong></span>
            @endif
        @endforeach
        <span class="px-2 py-1 rounded bg-slate-100 text-slate-600">Total: <strong>{{ $resumen['total'] ?? 0 }}</strong></span>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-4 p-2">
    <div id="delivery-map" style="height:400px;border-radius:8px;"></div>
    <p id="delivery-map-empty" class="text-sm text-slate-400 text-center py-6 hidden">
        Ninguno de estos participantes marcó un pin todavía — solo hay direcciones de texto.
    </p>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Dirección</th>
                <th class="px-4 py-2">Teléfono</th>
                <th class="px-4 py-2">Tipo de formulario</th>
                <th class="px-4 py-2">Estado</th>
                <th class="px-4 py-2">Mapa</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($participantes as $p)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">{{ $p['nombre'] }} {{ $p['apellido'] }}</td>
                    <td class="px-4 py-2">{{ $p['direccion'] }}{{ $p['ciudad'] ? ', '.$p['ciudad'] : '' }}</td>
                    <td class="px-4 py-2">{{ $p['telefono'] ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $p['tipoFormulario'] ?? '—' }}</td>
                    <td class="px-4 py-2">
                        @php $colores = ['pendiente' => 'bg-amber-100 text-amber-700', 'confirmado' => 'bg-blue-100 text-blue-700', 'entregado' => 'bg-green-100 text-green-700', 'cancelado' => 'bg-slate-100 text-slate-500']; @endphp
                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $colores[$p['estadoDelivery']] ?? '' }}">{{ $p['estadoDelivery'] }}</span>
                    </td>
                    <td class="px-4 py-2">
                        @if ($p['lat'] !== null && $p['lng'] !== null)
                            <a href="https://www.google.com/maps?q={{ $p['lat'] }},{{ $p['lng'] }}" target="_blank" rel="noopener" class="text-blue-600 underline">Abrir en Maps →</a>
                        @else
                            <span class="text-slate-400">Sin pin</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Nadie pidió delivery en este evento todavía.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const participantes = @json($participantes);
    const conPin = participantes.filter(p => p.lat !== null && p.lng !== null);

    if (conPin.length === 0) {
        document.getElementById('delivery-map').style.display = 'none';
        document.getElementById('delivery-map-empty').classList.remove('hidden');
        return;
    }

    const map = L.map('delivery-map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(map);

    const markers = conPin.map(p => {
        const marker = L.marker([p.lat, p.lng]).addTo(map);
        marker.bindPopup(
            '<strong>' + (p.nombre || '') + ' ' + (p.apellido || '') + '</strong><br>' +
            (p.direccion || '') + (p.ciudad ? ', ' + p.ciudad : '') + '<br>' +
            (p.telefono || '') + '<br>' +
            '<em>' + (p.estadoDelivery || '') + '</em>'
        );
        return marker;
    });

    if (markers.length === 1) {
        map.setView([conPin[0].lat, conPin[0].lng], 15);
    } else {
        map.fitBounds(L.featureGroup(markers).getBounds(), { padding: [30, 30] });
    }
})();
</script>
@endsection
