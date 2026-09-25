<?php

namespace App\Actions;

use App\Mail\ExpositorCredencialesMail;
use App\Models\Answer;
use App\Models\Category;
use App\Models\EmpresaExpositora;
use App\Models\FormularioCampos;
use App\Models\Participante;
use App\Models\Registration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * SmartStand (25/09/2026) — al confirmarse el pago de una inscripción cuyo
 * form_type es `es_expositor`, crea la cuenta de la empresa expositora y le
 * manda por correo su acceso (usuario, contraseña y link de la app de
 * escaneo). Llamada desde NotificacionService::notificarPagoConfirmado(), el
 * punto único de "pago confirmado".
 *
 * Idempotencia (a diferencia de registration_notifications, cuya reserva se
 * hace ANTES de enviar y por eso un SMTP caído nunca se reintenta): el UNIQUE
 * de `empresas_expositoras.registration_id` garantiza que un solo proceso
 * crea la cuenta y es el único que manda el primer correo. Los reintentos de
 * webhook/polling NO reenvían nada (si la cuenta ya existe, es no-op) — así
 * dos disparos concurrentes jamás mandan dos contraseñas distintas. Si el
 * envío falla, `credenciales_enviadas_at` queda null y se recupera con
 * reenviarCredenciales() (botón del panel / comando diario).
 */
class ProvisionarCuentaExpositorAction
{
    /** Razón social: respuesta a la pregunta custom cuyo `nombre_campo` es este. */
    public const CAMPO_RAZON_SOCIAL = 'razon_social';

    public function handle(Registration $registration): ?EmpresaExpositora
    {
        $registration->loadMissing(['formType', 'participants', 'evento']);

        if (! $registration->formType?->es_expositor || $registration->pago_status !== 'paid') {
            return null;
        }

        if (EmpresaExpositora::where('registration_id', $registration->id)->exists()) {
            return null;
        }

        $representante = $registration->participants->first();
        if (! $representante || blank($representante->correo)) {
            Log::warning('SmartStand: inscripción de expositor pagada sin correo del representante, no se creó la cuenta', [
                'registration_id' => $registration->id,
            ]);

            return null;
        }

        $passwordPlano = $this->generarPassword();

        try {
            $cuenta = EmpresaExpositora::create([
                'evento_id'       => $registration->evento_id,
                'registration_id' => $registration->id,
                'nombre'          => $this->razonSocial($registration, $representante),
                'email'           => strtolower(trim($representante->correo)),
                'password'        => Hash::make($passwordPlano),
                'categoria_id'    => $this->categoriaId($registration, $representante),
                'activo'          => true,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Otro proceso ganó la carrera (mismo registration_id, o el mismo
            // correo ya tiene cuenta en este evento) — no es un error.
            Log::info('SmartStand: cuenta de expositor ya existente, se omite el alta', [
                'registration_id' => $registration->id,
            ]);

            return null;
        }

        $this->enviarCredenciales($cuenta, $passwordPlano);

        return $cuenta;
    }

    /**
     * Regenera la contraseña y reenvía las credenciales. Usada por el botón
     * "Reenviar credenciales" del panel y por el comando de reconciliación.
     * La contraseña vieja deja de valer (no hay forma de recuperarla: solo
     * se guarda el hash).
     */
    public function reenviarCredenciales(EmpresaExpositora $cuenta): bool
    {
        $passwordPlano = $this->generarPassword();
        $cuenta->update(['password' => Hash::make($passwordPlano)]);

        return $this->enviarCredenciales($cuenta, $passwordPlano);
    }

    private function enviarCredenciales(EmpresaExpositora $cuenta, string $passwordPlano): bool
    {
        try {
            Mail::to($cuenta->email)->send(new ExpositorCredencialesMail($cuenta, $passwordPlano));
        } catch (\Throwable $e) {
            Log::error('SmartStand: no se pudo enviar el correo de credenciales del expositor', [
                'empresa_expositora_id' => $cuenta->id,
                'error'                 => $e->getMessage(),
            ]);

            return false;
        }

        $cuenta->update(['credenciales_enviadas_at' => now()]);

        return true;
    }

    private function generarPassword(): string
    {
        // Sin símbolos ni espacios: viaja por correo y se teclea en un celular.
        return Str::password(12, true, true, false, false);
    }

    private function razonSocial(Registration $registration, Participante $representante): string
    {
        $preguntaIds = FormularioCampos::where('form_types_id', $registration->form_types_id)
            ->where('nombre_campo', self::CAMPO_RAZON_SOCIAL)
            ->pluck('id');

        $valor = $preguntaIds->isEmpty()
            ? null
            : Answer::where('participante_id', $representante->id)
                ->whereIn('question_id', $preguntaIds)
                ->value('value');

        return filled($valor)
            ? trim($valor)
            : trim($representante->nombre . ' ' . $representante->apellido);
    }

    /**
     * `participantes.categoria` guarda el id de la categoría como string (o,
     * si el form_type no requiere categoría, el nombre del form_type) — solo
     * se toma si es un id que existe y pertenece a ESTE evento.
     */
    private function categoriaId(Registration $registration, Participante $representante): ?int
    {
        if (! ctype_digit((string) $representante->categoria)) {
            return null;
        }

        return Category::where('id', (int) $representante->categoria)
            ->where('event_id', $registration->evento_id)
            ->value('id');
    }
}
