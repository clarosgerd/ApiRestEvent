<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * tipo_pago era un enum fijo ('QR','TRANSFERENCIA','TIGO','EFECTIVO') que
     * no tenía relación con formas_pagos y obligaba a "pedir prestado" un
     * valor del enum cada vez que se agregaba un método nuevo (p.ej.
     * 'Pendiente' se guardaba como 'EFECTIVO', 'Multipago' como
     * 'TRANSFERENCIA' — ver elascenso/event api/registro.php). Con
     * formas_pagos.slug como identificador real, tipo_pago pasa a ser un
     * string libre que guarda ese slug directamente.
     *
     * Se usa SQL crudo (no ->change()) porque el proyecto no tiene
     * doctrine/dbal instalado.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE registrations MODIFY tipo_pago VARCHAR(50) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE registrations MODIFY tipo_pago ENUM('QR','TRANSFERENCIA','TIGO','EFECTIVO') NOT NULL");
    }
};
