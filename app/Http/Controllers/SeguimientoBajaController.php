<?php

namespace App\Http\Controllers;

use App\Models\LeadCapturado;
use App\Models\SeguimientoBaja;

/**
 * SmartStand fase 4 — baja del correo de seguimiento. La URL llega firmada
 * (middleware `signed`, ver routes/api.php) en el pie de cada correo: sin sesión,
 * solo quien lo recibió tiene un link válido. Registra el CORREO del asistente,
 * así que vale para todas las empresas y eventos (a diferencia de un link por lead).
 */
class SeguimientoBajaController extends Controller
{
    public function baja(LeadCapturado $lead)
    {
        $correo = mb_strtolower(trim((string) $lead->participante?->correo));

        if ($correo !== '') {
            SeguimientoBaja::firstOrCreate(['email' => $correo]);
        }

        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>Baja registrada</title></head>'
            . '<body style="font-family:Segoe UI,Arial,sans-serif;background:#f4f6f8;margin:0;padding:48px 16px;color:#1a2a3a;">'
            . '<div style="max-width:440px;margin:0 auto;background:#fff;border-radius:10px;padding:32px;text-align:center;">'
            . '<h1 style="font-size:20px;margin:0 0 12px;">Listo, te diste de baja</h1>'
            . '<p style="font-size:15px;line-height:1.5;margin:0;">No recibirás más correos de seguimiento de empresas expositoras en este correo.</p>'
            . '</div></body></html>';

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
