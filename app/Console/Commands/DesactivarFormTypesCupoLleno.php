<?php

namespace App\Console\Commands;

use App\Services\RegistrationService;
use Illuminate\Console\Command;

class DesactivarFormTypesCupoLleno extends Command
{
    protected $signature = 'form_types:desactivar-cupo-lleno';

    protected $description = 'Red de seguridad: desactiva form_types que llenaron cupo y no se detectó en caliente al registrar.';

    public function handle(RegistrationService $service): int
    {
        $desactivados = $service->sweepFormTypesCupoLleno();

        $this->info("Form types desactivados: {$desactivados}");

        return self::SUCCESS;
    }
}
