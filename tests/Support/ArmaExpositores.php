<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\EmpresaExpositora;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Support\Facades\Hash;

/**
 * SmartStand (25/09/2026) — helpers compartidos por los tests de expositores.
 */
trait ArmaExpositores
{
    protected function crearEvento(array $overrides = []): Evento
    {
        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        return Evento::factory()->create(array_merge([
            'organizador_id'    => $organizador->id,
            'tipo_evento_id'    => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id'           => $pais->id,
            'ciudad_id'         => $ciudad->id,
        ], $overrides));
    }

    protected function crearFormType(Evento $evento, bool $esExpositor = true): FormType
    {
        return FormType::factory()->create([
            'event_id'           => $evento->id,
            'es_expositor'       => $esExpositor,
            'requiere_categoria' => true,
        ]);
    }

    protected function crearCategoria(Evento $evento, ?FormType $formType = null, string $nombre = 'Stand 3x3'): Category
    {
        return Category::factory()->create(array_filter([
            'event_id'      => $evento->id,
            'formulario_id' => $formType?->id,
            'name'          => $nombre,
        ]));
    }

    /**
     * Inscripción con UN participante (el representante de la empresa, o un
     * médico/asistente según el caso).
     */
    protected function crearInscripcion(
        Evento $evento,
        FormType $formType,
        array $participante = [],
        string $pago = 'paid',
    ): Registration {
        $registration = Registration::create([
            'referencia'      => 'LA-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'fecha'           => now(),
            'evento_id'       => $evento->id,
            'form_types_id'   => $formType->id,
            'evento_nombre'   => $evento->nombre,
            'tipo_pago'       => 'sip',
            'pago_status'     => $pago,
        ]);

        Participante::create(array_merge([
            'registration_id'  => $registration->id,
            'nombre'           => 'Nombre' . rand(1000, 9999),
            'apellido'         => 'Apellido',
            'genero'           => 'Femenino',
            'tipo_documento'   => 'DNI',
            'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1990-01-01',
            'edad'             => 35,
            'correo'           => 'p' . rand(10000, 99999) . '@test.net',
            'direccion'        => 'x',
            'ciudad'           => 'Santa Cruz',
            'telefono'         => '123',
            'categoria'        => 'x',
            'precio_categoria' => 0,
            'subtotal'         => 0,
        ], $participante));

        return $registration->load('participants');
    }

    /** Un asistente (médico) cuyo gafete puede ser escaneado. */
    protected function crearAsistente(Evento $evento, array $participante = [], string $pago = 'paid'): Participante
    {
        $formType = $this->crearFormType($evento, esExpositor: false);

        return $this->crearInscripcion($evento, $formType, $participante, $pago)->participants->first();
    }

    protected function crearCuenta(Evento $evento, array $overrides = [], string $password = 'clave-de-prueba'): EmpresaExpositora
    {
        return EmpresaExpositora::create(array_merge([
            'evento_id' => $evento->id,
            'nombre'    => 'Farma ' . rand(100, 999),
            'email'     => 'expositor' . rand(1000, 9999) . '@empresa.test',
            'password'  => Hash::make($password),
            'activo'    => true,
        ], $overrides));
    }

    /** Autentica las próximas requests como esta empresa (guard `expositores`). */
    protected function comoExpositor(EmpresaExpositora $cuenta): string
    {
        // El guard de Sanctum cachea el usuario entre requests de un mismo
        // test: sin esto, cambiar de token no cambia de usuario.
        $this->app['auth']->forgetGuards();
        $token = $cuenta->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $token;
    }
}
