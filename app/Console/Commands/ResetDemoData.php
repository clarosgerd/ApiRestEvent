<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDemoData extends Command
{
    protected $signature = 'demo:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Truncate all eventos/registrations data (and everything hanging off them) to prepare for a fresh demo seed. Never touches personas, catalogs, or Laravel infra tables.';

    // Hijos antes que padres — aunque con FOREIGN_KEY_CHECKS=0 el orden exacto no importa,
    // se mantiene por legibilidad. No incluye personas/contactos_emergencia/catálogos
    // (paises, ciudades, tipos_evento, subtipos_evento, organizadores, formas_pagos,
    // organizador_formas_pago) ni tablas de infraestructura de Laravel.
    private const TABLES = [
        'answers',
        'question_options',
        'questions',
        'souvenir_participantes',
        'contacto_emergencia_participantes',
        'audit_logs',
        'participantes',
        'registration_totals',
        'registrations',
        'souvenirs',
        'promo_codes',
        'form_types',
        'categories',
        'auspiciadores',
        'coordinates',
        'routes',
        'eventos',
    ];

    public function handle(): int
    {
        $this->warn('Esto va a truncar: ' . implode(', ', self::TABLES));
        $this->line('NO se toca: personas, contactos_emergencia, catálogos, ni tablas de infraestructura.');

        if (!$this->option('force') && !$this->confirm('¿Continuar?')) {
            $this->info('Cancelado.');
            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
            $this->line("Truncated: {$table}");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Listo — datos de eventos/inscripciones limpiados, personas intacta.');
        return self::SUCCESS;
    }
}
