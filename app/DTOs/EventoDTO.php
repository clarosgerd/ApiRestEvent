<?php

namespace App\DTOs;

class EventoDTO
{
    public function __construct(
        public string $nombre,
        public string $descripcion,
        public ?string $longDescription,
        public string $fechaInicio,
        public string $localTime,
        public string $direccion,
        public string $status,
        public bool $publicado,
        public bool $hasDonation,
        public ?int $organizadorId,
        public ?int $tipoEventoId,
        public ?int $subtipoEventoId,
        public ?string $videoUrl,
        public ?string $imagenPortadaUrl,
        public ?string $colorHex,
        // Id del evento en ChronoTrack, si el organizador ya registró la
        // carrera ahí — solo lectura de nuestro lado, ver
        // App\Services\ChronoTrackClient.
        public ?string $chronotrackEventId,
        public ?string $deslinde,
        public ?string $deslindePdfUrl,
        /** @var CoordinateDTO[] */
        public array $coordinates,
        /** @var RouteDTO[] */
        public array $routes,
        /** @var CategoryDTO[] */
        public array $categories,
        /** @var FormTypeDTO[] */
        public array $formTypes,
        /** @var PromoCodeDTO[] */
        public array $promoCodes,
        /** @var AuspiciadorDTO[] */
        public array $auspiciadores,
        /** @var AgendaItemDTO[] */
        public array $agenda,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nombre: $data['name'] ?? $data['nombre'] ?? '',
            descripcion: $data['description'] ?? $data['descripcion'] ?? '',
            longDescription: $data['longDescription'] ?? null,
            fechaInicio: $data['date'] ?? $data['fecha_inicio'] ?? now()->toDateTimeString(),
            localTime: $data['localTime'] ?? '',
            direccion: $data['location'] ?? $data['direccion'] ?? '',
            status: $data['status'] ?? 'open',
            // Todo evento nace como borrador — es el contrato entre el
            // organizador y nosotros; recién es elegible para publicarse
            // (ver EventoController::publicar()) una vez firmado. Un
            // caller explícito puede forzar `publicado: true` si de
            // verdad lo necesita, pero el default ya no es "publicado".
            publicado: (bool) ($data['publicado'] ?? false),
            hasDonation: (bool) ($data['hasDonation'] ?? false),
            organizadorId: isset($data['organizador_id']) ? (int) $data['organizador_id'] : null,
            // Default 1 ("Carrera de Ruta") si no viene: mismo comportamiento
            // histórico para no romper callers que todavía no lo mandan (la
            // SPA de inscripción nunca crea eventos). Ver
            // brain/PLAN-ENDPOINT-CONSUMO-05082026.md.
            tipoEventoId: isset($data['tipo_evento_id']) ? (int) $data['tipo_evento_id'] : null,
            subtipoEventoId: isset($data['subtipo_evento_id']) ? (int) $data['subtipo_evento_id'] : null,
            videoUrl: $data['video'] ?? $data['video_url'] ?? null,
            imagenPortadaUrl: $data['image'] ?? $data['imagen_portada_url'] ?? null,
            colorHex: $data['colorHex'] ?? $data['color_hex'] ?? null,
            chronotrackEventId: $data['chronotrackEventId'] ?? $data['chronotrack_event_id'] ?? null,
            deslinde: $data['deslinde'] ?? null,
            deslindePdfUrl: $data['deslinde_pdf_url'] ?? null,
            coordinates: array_map(
                fn(array $c) => CoordinateDTO::fromArray($c),
                $data['coordinates'] ?? []
            ),
            routes: array_map(
                fn(array $r) => RouteDTO::fromArray($r),
                $data['route'] ?? $data['routes'] ?? []
            ),
            categories: array_map(
                fn(array $c) => CategoryDTO::fromArray($c),
                $data['categories'] ?? []
            ),
            formTypes: array_map(
                fn(array $f) => FormTypeDTO::fromArray($f),
                $data['formTypes'] ?? []
            ),
            promoCodes: array_map(
                fn(array $p) => PromoCodeDTO::fromArray($p),
                $data['promoCodes'] ?? []
            ),
            auspiciadores: array_map(
                fn(array $a) => AuspiciadorDTO::fromArray($a),
                $data['auspiciadores'] ?? []
            ),
            agenda: array_map(
                fn(array $a) => AgendaItemDTO::fromArray($a),
                $data['agenda'] ?? []
            ),
        );
    }
}
