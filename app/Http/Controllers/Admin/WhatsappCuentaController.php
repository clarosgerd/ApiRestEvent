<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsappCuentaController as ApiWhatsappCuentaController;
use App\Http\Requests\StoreWhatsappCuentaRequest;
use App\Http\Requests\UpdateWhatsappCuentaRequest;
use App\Models\Organizador;
use App\Models\WhatsappCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD de cuentas de WhatsApp Business oficial (08/10/2026, sincronizado al
 * monolito) — mismo patrón de delegación que Admin\SipBancoController, solo
 * accesible bajo `admin.superadmin` (ver routes/admin.php). `access_token`
 * nunca vuelve en la respuesta de la API (ver WhatsappCuentaResource) — el
 * form de edición nunca lo prellena, y `update()` solo lo manda si el
 * usuario efectivamente escribió algo.
 */
class WhatsappCuentaController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiWhatsappCuentaController $api): View
    {
        $cuentas = $this->dataFrom($api->index(), 'data');

        return view('admin.whatsapp-cuentas.index', compact('cuentas'));
    }

    public function create(): View
    {
        return view('admin.whatsapp-cuentas.form', [
            'cuenta' => null,
            'organizadores' => $this->organizadores(),
            'action' => route('admin.whatsapp-cuentas.store'),
        ]);
    }

    public function store(StoreWhatsappCuentaRequest $request, ApiWhatsappCuentaController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.whatsapp-cuentas.index');
    }

    public function edit(ApiWhatsappCuentaController $api, WhatsappCuenta $whatsappCuenta): View
    {
        return view('admin.whatsapp-cuentas.form', [
            'cuenta' => $this->dataFrom($api->show($whatsappCuenta), 'data'),
            'organizadores' => $this->organizadores(),
            'action' => route('admin.whatsapp-cuentas.update', $whatsappCuenta),
        ]);
    }

    public function update(UpdateWhatsappCuentaRequest $request, ApiWhatsappCuentaController $api, WhatsappCuenta $whatsappCuenta): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $whatsappCuenta), 'admin.whatsapp-cuentas.index');
    }

    public function destroy(ApiWhatsappCuentaController $api, WhatsappCuenta $whatsappCuenta): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($whatsappCuenta), 'admin.whatsapp-cuentas.index');
    }

    /**
     * Lista de organizadores para el select — mismo criterio que
     * Admin\SipBancoController::organizadores().
     */
    private function organizadores(): array
    {
        return Organizador::orderBy('razon_social')->get()
            ->map(fn (Organizador $o) => ['id' => $o->id, 'nombre' => $o->nombre_comercial ?: $o->razon_social])
            ->toArray();
    }
}
