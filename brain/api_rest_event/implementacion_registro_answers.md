# Implementación: Registro de form_types_id y Answers en Inscripciones

## Descripción
Se implementó el registro de `form_types_id` en la inscripción y la creación de `answers` (respuestas del participante a preguntas adicionales del formulario) durante el proceso de inscripción mediante `POST /api/v1/registrations`.

## Estructura de datos anidada

```
POST /api/v1/registrations
└── form_types_id           ← NUEVO (obligatorio)
└── participantes[]
      ├── answers[]         ← NUEVO (nullable)
      │     ├── form_types_id
      │     ├── question_id
      │     └── value
      ├── souvenirs[]
      └── contacto_emergencia
```

## Archivos creados

### `app/DTOs/AnswerDTO.php`
DTO para las respuestas del participante.
- Propiedades: `formTypeId`, `questionId`, `value`
- Nota: `participante_id` se omite porque se asigna automáticamente al crear el participante

### `app/Http/Resources/AnswerResource.php`
Resource para serializar las answers en la respuesta JSON.
- Campos: `form_types_id`, `question_id`, `value`

## Archivos modificados

### `app/Models/Registration.php`
- Agregado `form_types_id` al array `$fillable` (antes se perdía silenciosamente)

### `app/Models/Answer.php`
- Corregido import faltante: `use Illuminate\Database\Eloquent\Relations\BelongsTo`

### `app/DTOs/ParticipantDTO.php`
- Agregada propiedad `$answers` (array de `AnswerDTO`)
- Agregado mapeo en `fromArray()`: `$data['answers'] ?? []`

### `app/Http/Requests/StoreRegistrationRequest.php`
Reglas de validación agregadas:
```
*.form_types_id                          → required|integer
*.participantes.*.answers                 → nullable|array
*.participantes.*.answers.*.form_types_id → required_with|integer
*.participantes.*.answers.*.question_id   → required_with|integer
*.participantes.*.answers.*.value         → required_with|string
```

### `app/Services/RegistrationService.php`
- Importado modelo `Answer`
- En `createParticipant()`: después de crear souvenirs, crea las answers con `Answer::create()` asignando `participante_id` automáticamente
- En `loadRelations()`: agregado `participants.answers` al eager load
- Eliminada doble validación redundante (el segundo `foreach` de `validateParticipantRegistration` se ejecutaba dos veces)

### `app/Http/Resources/ParticipanteResource.php`
- Importado `AnswerResource`
- Agregado `'answers' => AnswerResource::collection($this->whenLoaded('answers'))` al output

### `app/Http/Controllers/RegistrationController.php`
- Eliminado `dd($request)` de la línea 64 que bloqueaba completamente el endpoint `store()`

### `database/migrations/2026_07_05_151510_create_registrations_table.php`
- Corregido bug: `table->index('form_types_id')` → `$table->index('form_types_id')`

## Ejemplo de request

```json
POST /api/v1/registrations
Content-Type: application/json

[
    {
        "referencia": "LA-9167800F",
        "fecha": "2026-07-05 14:34:11",
        "evento_id": "1",
        "form_types_id": 1,
        "evento_nombre": "Carrera por la Vida",
        "tipo_pago": "QR",
        "pago_status": "pending",
        "totales": {
            "inscripcion": 5.82,
            "donacion": 50,
            "souvenirs": 69.6,
            "fee": 0.29,
            "descuento": 0,
            "grand_total": 125.71
        },
        "participantes": [
            {
                "nombre": "Gerd",
                "apellido": "Claros",
                "numeroDocumento": "962633",
                "correo": "gerd@gmail.com",
                "answers": [
                    {
                        "form_types_id": 1,
                        "question_id": 1,
                        "value": "BLABLA"
                    },
                    {
                        "form_types_id": 1,
                        "question_id": 2,
                        "value": "Otra respuesta"
                    }
                ],
                "souvenirs": [],
                "contacto_emergencia": {
                    "nombre": "Gerd Claros",
                    "celular": "+59178441410",
                    "relacion": "LGN"
                }
            }
        ]
    }
]
```

## Ejemplo de respuesta (con answers)

```json
{
    "success": true,
    "message": "Inscripción registrada correctamente.",
    "data": {
        "referencia": "LA-9167800F",
        "evento_id": "1",
        "form_types_id": 1,
        "evento_nombre": "Carrera por la Vida",
        "participantes": [
            {
                "nombre": "Gerd",
                "apellido": "Claros",
                "answers": [
                    {
                        "form_types_id": 1,
                        "question_id": 1,
                        "value": "BLABLA"
                    },
                    {
                        "form_types_id": 1,
                        "question_id": 2,
                        "value": "Otra respuesta"
                    }
                ],
                "souvenirs": [],
                "contacto_emergencia": { ... }
            }
        ],
        "totales": { ... }
    }
}
```

## Flujo completo

```
Request JSON
  └── participantes[].answers[]
        ↓
StoreRegistrationRequest (valida form_types_id + answers)
        ↓
RegistrationDTO → ParticipantDTO → AnswerDTO
        ↓
RegistrationService::create()
  ├── Registration::create() (con form_types_id)
  └── createParticipant()
        ├── Participante::create()
        ├── ContactoEmergenciaParticipante::create()
        ├── SouvenirParticipante::create()
        └── Answer::create() (con participante_id automático)
        ↓
loadRelations() → participants.answers
        ↓
ParticipanteResource → "answers" → AnswerResource
```

## Bugs corregidos en esta implementación

| # | Archivo | Bug | Corrección |
|---|---------|-----|------------|
| 1 | `RegistrationController.php:64` | `dd($request)` bloqueaba el endpoint store | Eliminado |
| 2 | `Answer.php` | Faltaba import `BelongsTo` | Agregado |
| 3 | `Registration.php` | `form_types_id` no estaba en `$fillable` | Agregado |
| 4 | `RegistrationService.php:42-44` | Doble validación redundante | Eliminado segundo loop |
| 5 | `create_registrations_table.php:36` | `table->index` sin `$` | Corregido a `$table->index` |

## Notas
- El campo `answers` es `nullable` — puede venir vacío `[]` o no enviarse
- `participante_id` en el request de answers es innecesario, se asigna automáticamente
- `form_types_id` en el top-level del request es obligatorio e identifica el tipo de formulario asociado a la inscripción
