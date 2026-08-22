<?php

namespace App\Http\Controllers\Inscripcion\Concerns;

use Illuminate\Http\Request;

/**
 * Consolidación monolito (22/08/2026), Fase 2b — equivalente de
 * `Admin\Concerns\DelegatesToApiJson::mergeAndValidate()` pero para
 * controllers JSON puros (sin redirect/vista). Se duplica en vez de
 * reusar el trait de `Admin\*` a propósito: son namespaces distintos con
 * ciclos de vida distintos (uno traduce a redirect Blade, el otro no
 * traduce nada), y tocar el trait de Fase 1 para generalizarlo arriesga
 * código ya estable sin necesidad real.
 */
trait ResolvesFormRequests
{
    /**
     * Resuelve y valida un FormRequest a mano sobre el body ya armado acá
     * (reemplaza TODO el input, no lo fusiona) — necesario cuando el
     * FormRequest de la API espera una forma distinta a la que llega desde
     * afuera (ej. `StoreRegistrationRequest` espera un array envolvente
     * `[0 => {...}]`).
     *
     * @template T of \Illuminate\Foundation\Http\FormRequest
     * @param class-string<T> $formRequestClass
     * @return T
     */
    protected function resolveWithBody(string $formRequestClass, Request $request, array $body)
    {
        $request->replace($body);

        $formRequest = $formRequestClass::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app('redirect'));
        $formRequest->validateResolved();

        return $formRequest;
    }
}
