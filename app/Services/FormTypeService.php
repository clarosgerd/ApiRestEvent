<?php

namespace App\Services;

use App\Models\FormType;
use App\Models\FormularioCampos;
use App\Models\QuestionOptions;
use App\Models\Souvenir;
use Illuminate\Support\Facades\DB;

/**
 * CRUD suelto de form_type (alta de evento con nested `POST /event` sigue
 * viviendo en EventoService::createFormTypes() — esta clase duplica la
 * lógica de souvenirs/preguntas a propósito en vez de refactorizar ese
 * método, que ya está en uso; ver brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md.
 */
class FormTypeService
{
    public function create(array $data): FormType
    {
        return DB::transaction(function () use ($data) {
            $formType = FormType::create([
                'event_id'                   => $data['event_id'],
                'name'                       => $data['name'],
                'icon'                       => $data['icon'],
                'description'                => $data['description'],
                'tipo'                       => $data['tipo'] ?? 'deportivo',
                'cupo_total'                 => $data['cupo_total'],
                'precio_base'                => $data['precio_base'],
                'costo_edicion'               => $data['costo_edicion'],
                'tiempo_expiracion_min'       => $data['tiempo_expiracion_min'],
                'color'                      => $data['color'],
                'moneda'                     => $data['moneda'] ?? true,
                'activo'                     => $data['activo'] ?? true,
                'permite_lista_espera'       => $data['permite_lista_espera'] ?? true,
                'requiere_categoria'         => $data['requiere_categoria'] ?? true,
                'requiere_talla'             => $data['requiere_talla'] ?? true,
                'requiere_distancia'         => $data['requiere_distancia'] ?? true,
                // Deprecado (12/08) a favor de souvenirs `incluido=true` — el
                // default pasa a apagado; ver migración
                // 2026_08_12_150000_change_hasshirt_costo_polera_defaults.php.
                'hasshirt'                   => $data['hasshirt'] ?? false,
                'costo_polera'               => $data['costo_polera'] ?? 0.00,
                'permite_inscripcion_grupal' => $data['permite_inscripcion_grupal'] ?? false,
                'max_integrantes_grupo'      => $data['max_integrantes_grupo'] ?? 10,
                'descuento_registrante_pct'  => $data['descuento_registrante_pct'] ?? 0.10,
                'hasQuestion'                => $data['hasQuestion'] ?? false,
                'texto_boton'                => $data['texto_boton'] ?? 'Inscribirme',
                'has_team'                   => $data['has_team'] ?? false,
                'has_delivery'               => $data['has_delivery'] ?? false,
                'has_donation'               => $data['has_donation'] ?? false,
                'has_promo_code'             => $data['has_promo_code'] ?? false,
            ]);

            $this->createSouvenirs($formType, $data['souvenirs'] ?? []);
            $this->createPreguntas($formType, $data['preguntas'] ?? []);

            return $formType->load(['souvenirs', 'formularioCampos.options']);
        });
    }

    /**
     * Solo campos escalares — souvenirs/preguntas no se resincronizan acá
     * (se administran vía SouvenirController / preguntas del formulario).
     */
    public function update(FormType $formType, array $data): FormType
    {
        $formType->update($data);

        return $formType->load(['souvenirs', 'formularioCampos.options']);
    }

    private function createSouvenirs(FormType $formType, array $souvenirs): void
    {
        if (empty($souvenirs)) return;

        $data = array_map(fn ($s) => [
            'form_types_id' => $formType->id,
            'name'          => $s['name'],
            // NOT NULL sin default en la tabla `souvenirs` — '' evita el
            // error de SQL cuando no se manda ícono.
            'icon'          => $s['icon'] ?? '',
            'price'         => $s['price'],
        ], $souvenirs);

        Souvenir::insert($data);
    }

    private function createPreguntas(FormType $formType, array $preguntas): void
    {
        if (empty($preguntas)) return;

        foreach ($preguntas as $preguntaData) {
            $question = FormularioCampos::create([
                'form_types_id' => $formType->id,
                'nombre_campo'  => $preguntaData['nombre_campo'],
                'seccion'       => $preguntaData['seccion'] ?? null,
                'etiqueta'      => $preguntaData['etiqueta'] ?? null,
                'tipo_input'    => $preguntaData['tipo_input'] ?? null,
                'placeholder'   => $preguntaData['placeholder'] ?? null,
                'obligatorio'   => $preguntaData['obligatorio'] ?? false,
                'orden'         => $preguntaData['orden'] ?? null,
            ]);

            $this->createOptions($question, $preguntaData['options'] ?? []);
        }
    }

    private function createOptions(FormularioCampos $question, array $options): void
    {
        if (empty($options)) return;

        $data = array_map(fn ($o) => [
            'question_id' => $question->id,
            'option_text' => $o['option_text'],
            'order'       => $o['order'] ?? null,
        ], $options);

        QuestionOptions::insert($data);
    }
}
