# Implementación: Incluir Preguntas y Opciones en Formulario de Evento

## Descripción
Se implementó el flujo completo para crear preguntas (`FormularioCampos`) y opciones de pregunta (`QuestionOptions`) de forma anidada al registrar un evento mediante `POST /api/v1/event`.

## Estructura de datos anidada

```
POST /api/v1/event
└── formTypes[]
      ├── souvenirs[]
      └── preguntas[]              ← NUEVO
            └── options[]          ← NUEVO
```

## Archivos creados

### `app/DTOs/QuestionOptionDTO.php`
DTO para las opciones de cada pregunta.
- Propiedades: `optionText`, `order`

### `app/DTOs/FormularioCamposDTO.php`
DTO para cada pregunta del formulario.
- Propiedades: `nombreCampo`, `seccion`, `etiqueta`, `tipoInput`, `placeholder`, `obligatorio`, `orden`, `options[]`

## Archivos modificados

### `app/DTOs/FormTypeDTO.php`
- Agregada propiedad `preguntas` (array de `FormularioCamposDTO`)
- Mapeo en `fromArray()` con `FormularioCamposDTO::fromArray()`

### `app/Http/Requests/StoreEventosRequest.php`
Reglas de validación agregadas:
```
formTypes.*.preguntas                          → nullable|array
formTypes.*.preguntas.*.nombre_campo           → required_with|string|max:255
formTypes.*.preguntas.*.seccion                → nullable|in:personal,kit,encuesta,legal,otro
formTypes.*.preguntas.*.etiqueta               → nullable|string
formTypes.*.preguntas.*.tipo_input             → nullable|in:text,email,tel,date,number,select,checkbox,radio,textarea,file
formTypes.*.preguntas.*.placeholder            → nullable|string
formTypes.*.preguntas.*.obligatorio            → nullable|boolean
formTypes.*.preguntas.*.orden                  → nullable|integer
formTypes.*.preguntas.*.options                → nullable|array
formTypes.*.preguntas.*.options.*.option_text  → required_with|string
formTypes.*.preguntas.*.options.*.order        → nullable|integer
```

### `app/Services/EventoService.php`
- Importados modelos `FormularioCampos` y `QuestionOptions`
- En `createFormTypes()`: llamada a `createFormularioCampos()` después de `createSouvenirs()`
- Nuevo método `createFormularioCampos(FormType, array)`: crea cada pregunta y sus opciones
- Nuevo método `createQuestionOptions(FormularioCampos, array)`: inserción bulk de opciones
- En `loadRelations()`: agregado `formTypes.formularioCampos.options` al eager load

### `app/Http/Controllers/EventoController.php`
- `index()`: eager load `formTypes.formularioCampos.options`
- `show()`: eager load `formTypes.formularioCampos.options`

### `app/Models/FormularioCampos.php`
- Corregido import: `QuestionOption` → `QuestionOptions`

### `database/factories/QuestionOptionsFactory.php`
- Completada `definition()` con `question_id`, `option_text`, `order`

### `database/factories/FormularioCamposFactory.php`
- Agregado import de `QuestionOptions`
- Nuevo método `hasQuestionOptions(int $count)`

### `database/factories/FormTypeFactory.php`
- Agregado import de `FormularioCampos`
- Nuevo método `hasFormularioCampos(int $count)` que crea preguntas con opciones

### `database/seeders/EventoSeeder.php`
- Encadenado `->hasFormularioCampos(3)` en el factory de FormType

### `database/seeders/DatabaseSeeder.php`
- Comentado `FormularioCamposSeeder` (ya se crean anidados desde EventoSeeder)
- Removido import no utilizado

## Ejemplo de request

```json
POST /api/v1/event
Content-Type: application/json

{
  "name": "Carrera por la Vida 2025",
  "description": "Carrera benéfica de 5K y 10K",
  "date": "2025-07-26 11:47:05",
  "location": "Santa Cruz de la Sierra",
  "formTypes": [
    {
      "name": "General",
      "icon": "🏃",
      "cupo_total": 500,
      "precio_base": 100,
      "souvenirs": [
        { "name": "Remera", "icon": "👕", "price": 25 }
      ],
      "preguntas": [
        {
          "nombre_campo": "Talla Remera",
          "etiqueta": "Seleccione su talla",
          "placeholder": "Ej: M",
          "obligatorio": true,
          "tipo_input": "select",
          "orden": 1,
          "options": [
            { "option_text": "S", "order": 1 },
            { "option_text": "M", "order": 2 },
            { "option_text": "L", "order": 3 },
            { "option_text": "XL", "order": 4 }
          ]
        },
        {
          "nombre_campo": "Genero",
          "etiqueta": "Genero",
          "obligatorio": false,
          "tipo_input": "radio",
          "orden": 2,
          "options": [
            { "option_text": "Masculino", "order": 1 },
            { "option_text": "Femenino", "order": 2 }
          ]
        }
      ]
    }
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
    "formTypes": [{
      "name": "General",
      "souvenirs": [
        { "name": "Remera", "icon": "👕", "price": 25 }
      ],
      "preguntas": [
        {
          "id": 1,
          "nombre_campo": "Talla Remera",
          "etiqueta": "Seleccione su talla",
          "tipo_input": "select",
          "obligatorio": true,
          "options": [
            { "id": 1, "question_id": 1, "option_text": "S", "order": 1 },
            { "id": 2, "question_id": 1, "option_text": "M", "order": 2 },
            { "id": 3, "question_id": 1, "option_text": "L", "order": 3 },
            { "id": 4, "question_id": 1, "option_text": "XL", "order": 4 }
          ]
        }
      ]
    }]
  }
}
```

## Datos de prueba (seeders)

Al ejecutar `php artisan migrate:fresh --seed`:
- 10 eventos
- 30 formTypes (3 por evento)
- 90 preguntas (3 por formType)
- 270 opciones (3 por pregunta)

## Flujo completo

```
Request JSON
  └── formTypes[].preguntas[].options[]
        ↓
StoreEventosRequest (validación)
        ↓
FormTypeDTO → FormularioCamposDTO → QuestionOptionDTO
        ↓
EventoService::createFormTypes()
  └── FormularioCampos::create()
        └── QuestionOptions::insert()
        ↓
loadRelations() → formTypes.formularioCampos.options
        ↓
FormTypeResource → "preguntas" → FormularioCamposResource → "options"
```

## Notas
- Los campos `form_types_id` y `question_id` se asignan automáticamente del padre, no se envían en el request
- Las preguntas y opciones se crean dentro de la misma transacción del evento (`DB::transaction`)
- La eliminación en cascada está configurada: al borrar un `FormType` se eliminan sus preguntas, y al borrar una pregunta se eliminan sus opciones
