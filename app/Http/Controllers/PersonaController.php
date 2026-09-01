<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use App\Filters\PersonaFilter;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\PersonaResource;
use App\Http\Resources\PersonaCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\RegisterPersonaRequest;
use App\Http\Requests\LoginPersonaRequest;

/**
 * CRUD de personas (21/08/2026) — solo super_admin. `Persona` es la
 * cuenta pública (login del sitio de inscripción, ver register()/login()
 * más abajo) y contiene PII real (documento, dirección, teléfono) — antes
 * de este cambio, index()/show() eran alcanzables por CUALQUIER token
 * autenticado (incluida otra Persona común vía `auth:sanctum` sin scope),
 * así que cualquier participante podía listar/ver los datos de cualquier
 * otro. store()/update()/destroy() eran stubs vacíos de
 * `make:controller --resource`, nunca implementados (los FormRequest
 * correspondientes tenían `authorize() => false`, ni siquiera eran
 * alcanzables). Mismo patrón de autorización que
 * Organizador/Socio/PresupuestoCategoria (catálogos globales,
 * `assertIsSuperAdmin()`).
 */
class PersonaController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->assertIsSuperAdmin();

      $filter = new PersonaFilter();
        $filterItems = $filter->transform($request); // [['column','operator','value']]
        $persona = Persona::where($filterItems);
        $persona = $persona->with('contactoEmergencia');
        $persona = $persona->paginate()->appends($request->query());
        $collection = PersonaResource::collection($persona);
            return response()->json([
                'success' => true,
                'persona' => $collection,
                'pagination' => [
                    'total' => $persona->total(),
                    'per_page' => $persona->perPage(),
                    'current_page' => $persona->currentPage(),
                    'last_page' => $persona->lastPage(),
                    'from' => $persona->firstItem(),
                    'to' => $persona->lastItem(),
                    'path'  => $persona->path(),
                  //  'page'  => $persona->lastPage(),

                ],
            ]);
    }
 public function register(RegisterPersonaRequest $request): JsonResponse
    {
        $validated = $request->validated();
//dd($validated->);
        // alias/direccion/ciudad/telefono/celular son `nullable` en
        // RegisterPersonaRequest (01/09/2026, ver comentario ahí) pero las
        // columnas de `personas` son NOT NULL sin default (migración
        // 2026_07_03_143925) — ConvertEmptyStringsToNull (middleware
        // global de Laravel) convierte un '' que llega del formulario en
        // null antes de esta validación, así que sin el `?? ''` acá el
        // INSERT reventaba con "Column 'alias' cannot be null" en vez de
        // simplemente guardar la cuenta con el campo vacío. Mismo
        // fallback que ya usa store() (alta admin) para direccion/ciudad.
        $user = Persona::create([
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'],
            'alias' => $validated['alias'] ?? '',
            'sexo' => $validated['sexo'],
            'tipo_documento' => $validated['tipo_documento'],
            'numero_documento' => $validated['numero_documento'],
            'fecha_nacimiento' => date('Y-m-d H:i:s', strtotime($validated['fecha_nacimiento'])),
            'correo' => $validated['email'],
            'direccion' => $validated['direccion'] ?? '',
            'ciudad' => $validated['ciudad'] ?? '',
            'telefono' => $validated['telefono'] ?? '',
            'celular' => $validated['celular'] ?? '',
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'token' => Str::random(40),
        ]);

//dd($user);
//$errors = $validator->errors();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'data'    => $user,
            'token' => $token,
        ], 201);

        
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePersonaRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();

        $persona = Persona::create([
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido'],
            // ?? '' y no ?? null: alias/direccion/ciudad/telefono/celular
            // son NOT NULL sin default en la tabla `personas` (ver mismo
            // comentario en register() más abajo).
            'alias' => $data['alias'] ?? '',
            'email' => $data['email'],
            // correo es un duplicado histórico de email (ver register()
            // más abajo, que ya hace lo mismo) — se mantienen sincronizados
            // para no dejar un valor viejo/distinto en una de las dos.
            'correo' => $data['email'],
            // Sin password: se genera una aleatoria — esta Persona la creó
            // un admin a mano, no necesariamente va a iniciar sesión ella
            // misma con esta cuenta.
            'password' => Hash::make($data['password'] ?? Str::random(24)),
            'sexo' => $data['sexo'],
            'tipo_documento' => $data['tipo_documento'],
            'numero_documento' => $data['numero_documento'],
            'fecha_nacimiento' => date('Y-m-d H:i:s', strtotime($data['fecha_nacimiento'])),
            'direccion' => $data['direccion'] ?? '',
            'ciudad' => $data['ciudad'] ?? '',
            'telefono' => $data['telefono'] ?? '',
            'celular' => $data['celular'] ?? '',
            'acepta_marketing' => $data['acepta_marketing'] ?? true,
            'token' => Str::random(40),
        ]);

        AdminAuditLogger::log('create', 'Persona', $persona->id, null, null, $persona->makeHidden(['password', 'token'])->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Persona creada correctamente.',
            'persona' => new PersonaResource($persona),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Persona $persona): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'persona' => new PersonaResource($persona->loadMissing('contactoEmergencia')),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Persona $persona)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePersonaRequest $request, Persona $persona): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $persona->makeHidden(['password', 'token'])->toArray();
        $data = $request->validated();

        // email/correo sincronizados igual que en store() — si cambia
        // email, correo lo sigue (mismo criterio que register()).
        if (array_key_exists('email', $data)) {
            $data['correo'] = $data['email'];
        }
        if (array_key_exists('fecha_nacimiento', $data)) {
            $data['fecha_nacimiento'] = date('Y-m-d H:i:s', strtotime($data['fecha_nacimiento']));
        }
        if (array_key_exists('password', $data)) {
            // password nullable en la validación: mandar el campo vacío
            // es "no cambiar la contraseña", no "borrarla" — personas no
            // permite password null.
            if (filled($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
        }

        $persona->update($data);

        AdminAuditLogger::log('update', 'Persona', $persona->id, null, $before, $persona->fresh()->makeHidden(['password', 'token'])->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Persona actualizada correctamente.',
            'persona' => new PersonaResource($persona->fresh()->loadMissing('contactoEmergencia')),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Persona $persona): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $persona->makeHidden(['password', 'token'])->toArray();
        $personaId = $persona->id;
        $persona->delete();

        AdminAuditLogger::log('delete', 'Persona', $personaId, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Persona eliminada correctamente.',
        ]);
    }

  public function login(LoginPersonaRequest $request): JsonResponse
    {
        $persona = Persona::where('email', $request->email)->first();
        if ( !$persona) {
        // The password is correct
        return response()->json([
            'success' => false,
            'error' => 'no existe el correo.',
           
        ]);
        }
        if (!Hash::check($request->password, $persona->password)) {
        // The password is correct
        return response()->json([
            'success' => false,
            'error' => 'Las credenciales proporcionadas son incorrectas.',
           
        ]);
        }

        $token = $persona->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'persona' => new PersonaResource($persona),
                'token' => $token,
            ],
        ]);

    }
public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new PersonaResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
       // Al usar auth('personas')->user() obtenemos la instancia correcta de Persona
        $persona = auth('personas')->user();

        if ($persona) {
            // Elimina el token con el que se está haciendo la petición actual
            $persona->currentAccessToken()->delete();

            return response()->json(['message' => 'Sesión cerrada con éxito']);
        }

        return response()->json(['message' => 'No autorizado'], 401);
    }

}
