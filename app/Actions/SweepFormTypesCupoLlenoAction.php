<?php

namespace App\Actions;

use App\Models\FormType;
use App\Services\RegistrationService;

class SweepFormTypesCupoLlenoAction
{
    public function __construct(
        private readonly RegistrationService $registrationService
    ) {
    }

    /**
     * Red de seguridad diaria (comando form_types:desactivar-cupo-lleno):
     * recorre todos los form_types activos y desactiva los que ya llenaron
     * cupo. El chequeo "en caliente" ya ocurre en
     * RegistrationService::create()/update()/updatePaidRegistration();
     * esto solo cubre condiciones de carrera o ediciones manuales directas
     * en BD.
     *
     * Reusa RegistrationService::deactivateFormTypeIfCupoLleno() (mismo
     * colaborador que usa el chequeo en caliente) en vez de reimplementar
     * la regla de cupo acá.
     */
    public function handle(): int
    {
        $desactivados = 0;

        foreach (FormType::where('activo', true)->pluck('id') as $formTypeId) {
            $this->registrationService->deactivateFormTypeIfCupoLleno($formTypeId);
            if (!FormType::find($formTypeId)->activo) {
                $desactivados++;
            }
        }

        return $desactivados;
    }
}
