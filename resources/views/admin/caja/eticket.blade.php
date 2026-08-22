@extends('admin.layouts.app')

@section('title', 'Comprobante '.$referencia.' — Caja')

@section('content')
@php
    // Comprobante imprimible (20/08/2026) — mismo diseño que el e-ticket
    // del formulario público (index.php::buildETicket()), portado acá
    // server-side porque Caja no comparte JS/CSS con elascenso/event. La
    // categoría llega como ID en `participante.categoria` (mismo patrón
    // ya visto en certificados/gafetes), se resuelve a nombre acá.
    $categoryNames = collect($evento['categories'] ?? [])->pluck('name', 'id');
    $totales = $registro['totales'] ?? [];
    $participantes = $registro['participantes'] ?? [];
    $esPagada = ($registro['pago_status'] ?? null) === 'paid';
@endphp

<div class="mb-4 no-print flex items-center justify-between">
    <a href="{{ route('admin.caja.buscar', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">&larr; Volver a Caja</a>
    <button type="button" onclick="window.print()" class="bg-brand-600 hover:bg-brand-700 text-white rounded-md px-4 py-2 text-sm font-semibold">
        🖨️ Imprimir comprobante
    </button>
</div>

<style>
    /* Portado de elascenso/event index.php (.eticket*) — ver comentario de
       arriba. Variables propias en vez de var(--...) para no depender del
       tema del panel (admin-eventos usa Tailwind, no estas custom
       properties). */
    .eticket {
        --primary: #00bad2; --secondary: #022858; --success: #258f36;
        --white: #ffffff; --light: #f4f8fb; --border: #d0dce8;
        --text: #1a2a3a; --muted: #607080; --radius: 8px;
        background: var(--white); border: 2px solid var(--border);
        border-radius: 12px; max-width: 520px; margin: 0 auto 24px;
        overflow: hidden; text-align: left; font-family: 'Segoe UI', Arial, sans-serif;
    }
    .eticket-header { background: var(--secondary); color: var(--white); padding: 20px 24px; text-align: center; }
    .eticket-header h3 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
    .eticket-header p { font-size: 12px; opacity: .7; margin: 0; }
    .eticket-ref { background: var(--primary); color: var(--white); text-align: center; padding: 14px 20px; }
    .eticket-ref span { font-size: 24px; font-weight: 800; letter-spacing: 4px; }
    .eticket-ref p { font-size: 11px; opacity: .8; margin-top: 2px; }
    .eticket-body { padding: 20px 24px; }
    .eticket-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 8px 0; border-bottom: 1px dashed var(--border); font-size: 13px; }
    .eticket-row:last-child { border-bottom: none; }
    .eticket-label { color: var(--muted); font-weight: 600; min-width: 120px; }
    .eticket-value { color: var(--text); text-align: right; flex: 1; }
    .eticket-divider { border: none; border-top: 2px dashed var(--border); margin: 0; }
    .eticket-participant { background: var(--light); border-radius: var(--radius); padding: 12px 16px; margin-bottom: 10px; }
    .eticket-participant-name { font-weight: 700; color: var(--secondary); font-size: 14px; margin-bottom: 6px; }
    .eticket-participant-detail { font-size: 12px; color: var(--muted); line-height: 1.8; }
    .eticket-total { background: var(--secondary); color: var(--white); padding: 16px 24px; text-align: center; }
    .eticket-total p { font-size: 12px; opacity: .7; margin-bottom: 2px; }
    .eticket-total span { font-size: 28px; font-weight: 800; }
    .eticket-footer { padding: 12px 24px; text-align: center; font-size: 11px; color: var(--muted); background: var(--light); }

    @media print {
        body * { visibility: hidden; }
        .eticket, .eticket * { visibility: visible; }
        .eticket { position: absolute; left: 0; top: 0; width: 100%; border: none; margin: 0; }
        .no-print { display: none !important; }
    }
    @media (max-width: 600px) {
        .eticket-row { flex-direction: column; gap: 2px; }
        .eticket-value { text-align: left; }
    }
</style>

<div class="eticket">
    <div class="eticket-header">
        <h3>{{ $evento['name'] ?? '' }}</h3>
        <p>{{ collect([$evento['date'] ?? null, $evento['location'] ?? null])->filter()->implode(' · ') }}</p>
    </div>
    <div class="eticket-ref">
        <p>REFERENCIA</p>
        <span>{{ $referencia }}</span>
    </div>
    <div class="eticket-body">
        <div class="eticket-row">
            <span class="eticket-label">Fecha</span>
            <span class="eticket-value">{{ \Illuminate\Support\Carbon::parse($registro['fecha'] ?? now())->format('d/m/Y H:i') }}</span>
        </div>
        <div class="eticket-row">
            <span class="eticket-label">Pago</span>
            <span class="eticket-value">{{ $registro['tipo_pago'] ?? 'EFECTIVO' }} (Caja)</span>
        </div>
        <div class="eticket-row">
            <span class="eticket-label">Estado</span>
            <span class="eticket-value" style="color: {{ $esPagada ? '#258f36' : '#c0392b' }}; font-weight:700;">
                {{ $esPagada ? '✓ Pagada' : 'Pendiente de pago' }}
            </span>
        </div>
    </div>
    <hr class="eticket-divider">
    <div class="eticket-body">
        <p style="font-size:12px;font-weight:700;color:#022858;margin-bottom:10px;text-transform:uppercase;">
            Participantes ({{ count($participantes) }})
        </p>
        @foreach ($participantes as $p)
            @php
                $souvenirs = collect($p['souvenirs'] ?? [])->map(fn($s) => ($s['nombre'] ?? '') . ' (' . number_format($s['precio'] ?? 0, 2) . ')')->implode(', ');
                $talleres = collect($p['talleres'] ?? [])->map(fn($t) => $t['titulo'] ?? $t['tallerNombre'] ?? '')->filter()->implode(', ');
                $categoriaNombre = $categoryNames[$p['categoria'] ?? null] ?? ($p['categoria'] ?? '');
            @endphp
            <div class="eticket-participant">
                <div class="eticket-participant-name">
                    {{ trim(($p['alias'] ?? '') . ' ' . ($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? '')) }}
                </div>
                <div class="eticket-participant-detail">
                    @if ($categoriaNombre)
                        Categoría: <strong>{{ $categoriaNombre }}</strong> · {{ number_format($p['precioCategoria'] ?? 0, 2) }}<br>
                    @endif
                    Documento: <strong>{{ $p['tipoDocumento'] ?? '' }} {{ $p['numeroDocumento'] ?? '' }}</strong><br>
                    @if ($souvenirs)
                        Kit: {{ $souvenirs }}<br>
                    @endif
                    @if ($talleres)
                        Talleres: {{ $talleres }}<br>
                    @endif
                    @if (($p['donacion'] ?? 0) > 0)
                        Donación: <strong>{{ number_format($p['donacion'], 2) }}</strong><br>
                    @endif
                    @if (($p['promoDescuento'] ?? 0) > 0)
                        Promo: <strong>{{ $p['promoCodigo'] ?? '' }} (-{{ number_format($p['promoDescuento'], 2) }})</strong>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    <hr class="eticket-divider">
    <div class="eticket-body" style="padding-bottom:0;">
        <div class="eticket-row"><span class="eticket-label">Inscripción</span><span class="eticket-value">{{ number_format($totales['inscripcion'] ?? 0, 2) }}</span></div>
        @if (($totales['talleres'] ?? 0) > 0)
            <div class="eticket-row"><span class="eticket-label">Talleres</span><span class="eticket-value">{{ number_format($totales['talleres'], 2) }}</span></div>
        @endif
        <div class="eticket-row"><span class="eticket-label">Souvenirs</span><span class="eticket-value">{{ number_format($totales['souvenirs'] ?? 0, 2) }}</span></div>
        <div class="eticket-row"><span class="eticket-label">Donación</span><span class="eticket-value">{{ number_format($totales['donacion'] ?? 0, 2) }}</span></div>
        <div class="eticket-row"><span class="eticket-label">Cargo de servicio</span><span class="eticket-value">{{ number_format($totales['fee'] ?? 0, 2) }}</span></div>
        @if (($totales['descuento'] ?? 0) > 0)
            <div class="eticket-row"><span class="eticket-label">Descuento</span><span class="eticket-value" style="color:#258f36;">-{{ number_format($totales['descuento'], 2) }}</span></div>
        @endif
        @if (($totales['descuento_registrante'] ?? 0) > 0)
            <div class="eticket-row"><span class="eticket-label">Descuento grupal</span><span class="eticket-value" style="color:#258f36;">-{{ number_format($totales['descuento_registrante'], 2) }}</span></div>
        @endif
    </div>
    <div class="eticket-total">
        <p>TOTAL COBRADO</p>
        <span>{{ number_format($totales['grand_total'] ?? 0, 2) }}</span>
    </div>
    <div class="eticket-footer">
        Conservá este comprobante · {{ $referencia }}
    </div>
</div>
@endsection
