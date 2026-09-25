<?php

namespace App\Http\Controllers;

use App\Actions\ProvisionarCuentaExpositorAction;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreEmpresaExpositoraRequest;
use App\Http\Requests\UpdateEmpresaExpositoraRequest;
use App\Http\Resources\EmpresaExpositoraResource;
use App\Models\EmpresaExpositora;
use App\Models\Evento;
use App\Services\AdminAuditLogger;
use App\Support\LeadsCapturadosData;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SmartStand (25/09/2026) — gestión de las empresas expositoras de un evento
 * por el organizador (guard `admins`, scoping por AuthorizesEventoScope).
 * Molde: AuspiciadorController. Las cuentas de autoservicio las crea
 * ProvisionarCuentaExpositorAction al confirmarse el pago; acá se pueden
 * editar, desactivar, dar de alta a mano y reenviar credenciales.
 */
class EmpresaExpositoraController extends Controller
{
    use AuthorizesEventoScope;

    /** Ordenado por leads capturados (desc): ES el "ranking de atracción". */
    public function index(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento((int) $event->id);

        $empresas = EmpresaExpositora::where('evento_id', $event->id)
            ->withCount('leads')
            ->with('categoria')
            ->orderByDesc('leads_count')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success'    => true,
            'expositores' => EmpresaExpositoraResource::collection($empresas),
        ]);
    }

    public function store(StoreEmpresaExpositoraRequest $request, Evento $event, ProvisionarCuentaExpositorAction $provisionar): JsonResponse
    {
        $this->assertCanWriteEvento((int) $event->id);

        $data = $request->validated();
        $empresa = EmpresaExpositora::create([
            'evento_id'    => $event->id,
            'nombre'       => $data['nombre'],
            'email'        => $data['email'],
            'stand'        => $data['stand'] ?? null,
            'categoria_id' => $data['categoria_id'] ?? null,
            'activo'       => $data['activo'] ?? true,
            // Placeholder irrecuperable: reenviarCredenciales() la reemplaza
            // por una real antes de mandar el correo.
            'password'     => Hash::make(Str::random(40)),
        ]);

        $enviadas = $provisionar->reenviarCredenciales($empresa);

        AdminAuditLogger::log('create', 'empresa_expositora', $empresa->id, (int) $event->id, null, $empresa->toArray());

        return response()->json([
            'success'              => true,
            'message'              => $enviadas
                ? 'Expositor creado y credenciales enviadas por correo.'
                : 'Expositor creado, pero no se pudo enviar el correo. Usa "Reenviar credenciales".',
            'credencialesEnviadas' => $enviadas,
            'expositor'            => new EmpresaExpositoraResource($empresa->fresh()->load('categoria')),
        ], 201);
    }

    public function update(UpdateEmpresaExpositoraRequest $request, EmpresaExpositora $empresaExpositora): JsonResponse
    {
        $this->assertCanWriteEvento((int) $empresaExpositora->evento_id);

        $before = $empresaExpositora->toArray();
        $empresaExpositora->update($request->validated());

        // Al desactivar, se revocan los tokens vigentes: la app ya logueada
        // deja de funcionar de inmediato (el login ya rechaza cuentas inactivas).
        if (! $empresaExpositora->activo) {
            $empresaExpositora->tokens()->delete();
        }

        AdminAuditLogger::log('update', 'empresa_expositora', $empresaExpositora->id, (int) $empresaExpositora->evento_id, $before, $empresaExpositora->toArray());

        return response()->json([
            'success'   => true,
            'message'   => 'Expositor actualizado correctamente.',
            'expositor' => new EmpresaExpositoraResource($empresaExpositora->fresh()->load('categoria')),
        ]);
    }

    /**
     * No se borra una empresa con leads capturados (se perderían de forma
     * irrecuperable): se la desactiva en su lugar.
     */
    public function destroy(EmpresaExpositora $empresaExpositora): JsonResponse
    {
        $this->assertCanWriteEvento((int) $empresaExpositora->evento_id);

        if ($empresaExpositora->leads()->exists()) {
            return response()->json([
                'success' => false,
                'error'   => 'Esta empresa ya capturó contactos y no se puede eliminar. Desactívala en su lugar.',
            ], 409);
        }

        $before = $empresaExpositora->toArray();
        $empresaExpositora->tokens()->delete();
        $empresaExpositora->delete();

        AdminAuditLogger::log('delete', 'empresa_expositora', $empresaExpositora->id, (int) $empresaExpositora->evento_id, $before, null);

        return response()->json(['success' => true, 'message' => 'Expositor eliminado correctamente.']);
    }

    public function reenviarCredenciales(EmpresaExpositora $empresaExpositora, ProvisionarCuentaExpositorAction $provisionar): JsonResponse
    {
        $this->assertCanWriteEvento((int) $empresaExpositora->evento_id);

        if (! $provisionar->reenviarCredenciales($empresaExpositora)) {
            return response()->json([
                'success' => false,
                'error'   => 'No se pudo enviar el correo. Reintenta en unos minutos.',
            ], 502);
        }

        AdminAuditLogger::log('update', 'empresa_expositora', $empresaExpositora->id, (int) $empresaExpositora->evento_id, null, ['accion' => 'reenviar_credenciales']);

        return response()->json(['success' => true, 'message' => 'Credenciales reenviadas por correo (la contraseña anterior dejó de valer).']);
    }

    public function dashboard(EmpresaExpositora $empresaExpositora): JsonResponse
    {
        $this->assertCanWriteEvento((int) $empresaExpositora->evento_id);

        return response()->json([
            'success'   => true,
            'expositor' => new EmpresaExpositoraResource($empresaExpositora->load('categoria')),
            'data'      => LeadsCapturadosData::paraEmpresa($empresaExpositora),
        ]);
    }
}
