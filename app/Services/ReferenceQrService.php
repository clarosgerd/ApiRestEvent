<?php

namespace App\Services;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR interno de referencia para los correos de inscripción (pendiente y
 * pagada) — solo codifica el número de referencia (p.ej. LA-3605681A) para
 * check-in, sin relación con el QR de cobro bancario de QrService.
 */
class ReferenceQrService
{
    public static function toBase64Png(string $referencia, int $size = 200): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64'    => false,
            'scale'           => max(1, (int) round($size / 25)),
        ]);

        $png = (new QRCode($options))->render($referencia);

        return base64_encode($png);
    }
}
