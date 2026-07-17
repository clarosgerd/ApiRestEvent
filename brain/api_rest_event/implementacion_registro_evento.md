# Implementacion: Registro de Evento con Relaciones Anidadas

**Fecha:** 2026-07-17
**Metodo:** POST `/api/v1/event`
**Arquitectura:** Service Layer + DTOs + Form Requests (misma estructura que RegistrationController)

---

## Flujo de datos

```
Request JSON
  → StoreEventosRequest (validacion)
  → EventoDTO::fromArray() (transformacion)
  → EventoService::create() (DB::transaction)
      → Evento::create()
      → Coordinate::insert() (masivo)
      → Route::insert() (masivo)
      → Category::insert() (masivo)
      → FormType::create() + Souvenir::insert() (por cada formType)
      → PromoCode::insert() (masivo)
  → loadRelations() (eager loading)
  → Respuesta JSON 201
```

---

## Archivos creados

### DTOs (`app/DTOs/`)

| Archivo | Campos |
|---|---|
| `EventoDTO.php` | nombre, descripcion, longDescription, fechaInicio, localTime, direccion, hasDonation, videoUrl, imagenPortadaUrl, coordinates[], routes[], categories[], formTypes[], promoCodes[] |
| `CoordinateDTO.php` | lat, lng |
| `RouteDTO.php` | lat, lng, label |
| `CategoryDTO.php` | name, price, description, color |
| `FormTypeDTO.php` | name, icon, description, tipo, cupoTotal, precioBase, color, moneda, permiteListaEspera, hasshirt, requiereTalla, souvenirs[] |
| `SouvenirFormDTO.php` | name, icon, price |
| `PromoCodeDTO.php` | promoCode, price |

### Service (`app/Services/`)

| Archivo | Metodos |
|---|---|
| `EventoService.php` | `create()`, `createCoordinates()`, `createRoutes()`, `createCategories()`, `createFormTypes()`, `createSouvenirs()`, `createPromoCodes()`, `loadRelations()` |

---

## Archivos modificados

### Modelos - Fillables actualizados

| Modelo | Campos agregados |
|---|---|
| `Evento.php` | `longDescription` |
| `Category.php` | `formulario_id`, `sexo_id`, `color`, `edad_min`, `edad_max`, `calculo_edad_id` |
| `FormType.php` | `tipo`, `cupo_total`, `precio_base`, `moneda`, `activo`, `permite_lista_espera`, `requiere_categoria`, `requiere_talla`, `requiere_distancia`, `hasshirt`, `permite_inscripcion_grupal`, `max_integrantes_grupo`, `permite_inscripcion_tercero`, `costo_edicion`, `tiempo_expiracion_min`, `texto_boton` |
| `PromoCode.php` | `status` |

### Controller

`EventoController.php` - Se inyecto `EventoService` via constructor y se implemento `store()`:

```php
public function __construct(
    private readonly EventoService $service
) {}

public function store(StoreEventosRequest $request): JsonResponse
{
    $dto = EventoDTO::fromArray($request->validated());
    $evento = $this->service->create($dto);

    return response()->json([
        'success' => true,
        'message' => 'Evento registrado correctamente.',
        'eventos' => new EventoResource($evento),
    ], 201);
}
```

### FormRequest

`StoreEventosRequest.php` - Se cambio `authorize()` a `true` y se agregaron reglas de validacion para todos los campos incluyendo arrays anidados (coordinates, route, categories, formTypes, souvenirs, promoCodes).

---

## Ejemplo de request

```json
POST /api/v1/event
Content-Type: application/json

{
  "name": "Carrera por la Vida 2025",
  "description": "Carrera benéfica de 5K y 10K",
  "longDescription": "Evento deportivo anual",
  "date": "2025-07-26 11:47:05",
  "localTime": "13:22:56",
  "location": "Santa Cruz de la Sierra",
  "hasDonation": false,
  "video": "eq4GIhnPFrs",
  "image": "https://example.com/portada.jpg",
  "coordinates": [
    { "lat": -17.7833, "lng": -63.1821 }
  ],
  "route": [
    { "lat": -17.7833, "lng": -63.1821, "label": "Punto de salida" },
    { "lat": -17.7900, "lng": -63.1900, "label": "Km 5" }
  ],
  "categories": [
    { "name": "5K", "price": 100, "description": "Carrera corta", "color": "#ff0000" },
    { "name": "10K", "price": 150, "description": "Carrera larga", "color": "#0000ff" }
  ],
  "formTypes": [
    {
      "name": "General",
      "icon": "🏃",
      "description": "Inscripcion general",
      "tipo": "deportivo",
      "cupo_total": 500,
      "precio_base": 100,
      "color": "#00ff00",
      "moneda": 1,
      "permite_lista_espera": 1,
      "hasshirt": 1,
      "requiere_talla": 1,
      "souvenirs": [
        { "name": "Remera", "icon": "👕", "price": 25 },
        { "name": "Medalla", "icon": "🏅", "price": 10 }
      ]
    }
  ],
  "promoCodes": [
    { "promo_code": "EARLY50", "price": 50 }
  ]
}
```

## Ejemplo de respuesta (201)

```json
{
  "success": true,
  "message": "Evento registrado correctamente.",
  "eventos": {
    "id": 1,
    "name": "Carrera por la Vida 2025",
    "date": "2025-07-26 11:47:05",
    "coordinates": [{ "lat": -17.7833, "lng": -63.1821 }],
    "route": [{ "lat": -17.7833, "lng": -63.1821, "label": "Punto de salida" }],
    "categories": [{ "name": "5K", "price": 100 }],
    "formTypes": [{
      "name": "General",
      "souvenirs": [
        { "form_types_id": 1, "name": "Remera", "icon": "👕", "price": 25 }
      ]
    }],
    "promoCodes": [{ "promo_code": "EARLY50", "price": 50 }]
  }
}
```
