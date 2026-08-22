<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 *
 * Los controllers de `Admin\*` NO reimplementan CRUD/autorización/auditoría
 * — llaman directo al método del controller de la API que ya hace todo eso
 * (`App\Http\Controllers\PaisController::index()`, etc., el mismo que usa
 * `/api/v1/catalogos/paises`) y solo traducen la `JsonResponse` resultante
 * a vista/redirect. Antes esa traducción vivía en `ApiRestEventClient` +
 * cada controller de `admin-eventos` (parseando una respuesta HTTP real);
 * ahora es la misma idea pero sobre una `JsonResponse` in-process, sin red
 * de por medio.
 */
trait DelegatesToApiJson
{
    /** Extrae `data` (u otra clave) de una JsonResponse como array asociativo. */
    protected function dataFrom(JsonResponse $response, string $key = 'data'): array
    {
        return $response->getData(true)[$key] ?? [];
    }

    /**
     * store()/update()/destroy() de los controllers de la API siempre
     * devuelven `{success, message|error, data?}` — esto es el mismo
     * `extractErrors()`/chequeo de `success` que antes vivía en cada
     * controller de `admin-eventos`, ahora leyendo la JsonResponse
     * in-process en vez de una respuesta HTTP.
     */
    protected function redirectFromApiResponse(JsonResponse $response, string $routeName, array $routeParams = []): RedirectResponse
    {
        $payload = $response->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withInput()->withErrors($this->extractErrors($payload));
        }

        return redirect()->route($routeName, $routeParams)->with('status', $payload['message'] ?? 'Operación realizada correctamente.');
    }

    /**
     * Fase 1c — todos los sub-recursos de un evento (categorías, form
     * types, souvenirs, preguntas, promo codes, coordenadas, ruta,
     * auspiciadores, agenda) redirigen de vuelta a `eventos.edit` con un
     * `#hash` de pestaña (mismo criterio que admin-eventos desde que
     * `eventos/edit.blade.php` pasó a tabs, 12/08/2026) — sin esto, guardar
     * algo en la pestaña "Categorías" te devuelve a la pestaña "Datos".
     */
    protected function redirectToEventoTab(JsonResponse $response, int|string $eventoId, string $hash, string $successMessage): RedirectResponse
    {
        $payload = $response->getData(true);
        $url = route('admin.eventos.edit', $eventoId).'#'.$hash;

        if (!($payload['success'] ?? false)) {
            return redirect($url)->withInput()->withErrors($this->extractErrors($payload));
        }

        return redirect($url)->with('status', $payload['message'] ?? $successMessage);
    }

    /**
     * Fase 1c — resuelve un FormRequest a mano CON datos extra fusionados
     * (típicamente el id del evento/form_type padre, que en admin-eventos
     * viaja por la URL anidada — ej. `/eventos/{evento}/categorias` — pero
     * el FormRequest de la API (`StoreCategoryRequest`) lo exige en el
     * BODY como `event_id`, porque su endpoint real es plano: `POST
     * /category`). Si se type-hintea el FormRequest directo en la firma
     * del método del Admin controller, Laravel lo valida ANTES de que el
     * cuerpo del método pueda fusionar nada — demasiado tarde. Este
     * helper replica a mano el mismo ciclo de vida que Laravel corre
     * automáticamente (`ValidatesWhenResolved`), sobre una copia del
     * request con los datos ya fusionados.
     *
     * @template T of \Illuminate\Foundation\Http\FormRequest
     * @param class-string<T> $formRequestClass
     * @return T
     */
    protected function mergeAndValidate(string $formRequestClass, Request $request, array $merge)
    {
        $request->merge($merge);

        $formRequest = $formRequestClass::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app('redirect'));
        $formRequest->validateResolved();

        return $formRequest;
    }

    private function extractErrors(array $payload): array
    {
        $errors = $payload['errors'] ?? null;
        if (is_array($errors)) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        return ['general' => $payload['error'] ?? 'Ocurrió un error.'];
    }
}
