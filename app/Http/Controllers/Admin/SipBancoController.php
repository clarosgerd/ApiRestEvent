<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SipBancoController as ApiSipBancoController;
use App\Http\Requests\StoreSipBancoRequest;
use App\Http\Requests\UpdateSipBancoRequest;
use App\Models\Organizador;
use App\Models\SipBanco;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD de bancos SIP (31/08/2026, sincronizado al monolito 14/09/2026) —
 * mismo patrón de delegación que el resto de Fase 1, solo accesible bajo
 * `admin.superadmin` (ver routes/admin.php). Los 4 campos secretos
 * (sip_password, sip_apikey, sip_apikey_servicio, callback_basic_password)
 * nunca vuelven en la respuesta de la API — el "dejar vacío para no
 * cambiar" ya lo maneja `SipBancoController::update()` del lado de la API
 * (mismo patrón que AdminUser::update() con `password`), así que este
 * wrapper no necesita repetir esa lógica.
 */
class SipBancoController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiSipBancoController $api): View
    {
        $bancos = $this->dataFrom($api->index());

        return view('admin.sip-bancos.index', compact('bancos'));
    }

    public function create(): View
    {
        return view('admin.sip-bancos.form', [
            'banco' => null,
            'organizadores' => $this->organizadores(),
            'action' => route('admin.sip-bancos.store'),
        ]);
    }

    public function store(StoreSipBancoRequest $request, ApiSipBancoController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.sip-bancos.index');
    }

    public function edit(ApiSipBancoController $api, SipBanco $sipBanco): View
    {
        return view('admin.sip-bancos.form', [
            'banco' => $this->dataFrom($api->show($sipBanco)),
            'organizadores' => $this->organizadores(),
            'action' => route('admin.sip-bancos.update', $sipBanco),
        ]);
    }

    public function update(UpdateSipBancoRequest $request, ApiSipBancoController $api, SipBanco $sipBanco): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $sipBanco), 'admin.sip-bancos.index');
    }

    public function destroy(ApiSipBancoController $api, SipBanco $sipBanco): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($sipBanco), 'admin.sip-bancos.index');
    }

    /**
     * Lista de organizadores para el select — lectura simple, sin
     * autorización propia (ya está detrás de `admin.superadmin`). Mismo
     * criterio que Admin\EventoController::organizadores().
     */
    private function organizadores(): array
    {
        return Organizador::orderBy('razon_social')->get()
            ->map(fn (Organizador $o) => ['id' => $o->id, 'nombre' => $o->nombre_comercial ?: $o->razon_social])
            ->toArray();
    }
}
