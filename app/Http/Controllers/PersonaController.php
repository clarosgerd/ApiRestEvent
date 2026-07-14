<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use App\Filters\PersonaFilter;
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

class PersonaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
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
        $user = Persona::create([
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'],
            'alias' => $validated['alias'],
            'sexo' => $validated['sexo'],
            'tipo_documento' => $validated['tipo_documento'],
            'numero_documento' => $validated['numero_documento'],
            'fecha_nacimiento' => date('Y-m-d H:i:s', strtotime($validated['fecha_nacimiento'])),
            'correo' => $validated['email'],
            'direccion' => $validated['direccion'],
            'ciudad' => $validated['ciudad'],
            'telefono' => $validated['telefono'],
            'celular' => $validated['celular'],
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
    public function store(StorePersonaRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Persona $persona): JsonResponse
    {
        //
        //

        return response()->json([
            'success' => true,
            'persona' => new PersonaResource($persona->loadMissing('contactoEmergencia')),
        ]);
        
         //   return new PersonaResource($persona->loadMissing('contactoEmergencia'));

       
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
    public function update(UpdatePersonaRequest $request, Persona $persona)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Persona $persona)
    {
        //
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
