# Implementación: Actualización de Inscripción (solo pago pendiente/no pagado)

## Descripción
Se implementó el endpoint `PUT /api/v1/registrations/{reference}` para actualizar una inscripción completa, incluyendo participantes, contacto de emergencia, souvenirs, answers y totales. Solo se permite modificar inscripciones cuyo `pago_status` no sea `paid`.

## Límites de la modificación

### Cuándo se permite modificar
| `pago_status` | Permitido |
|---|---|
| `pending` | SÍ |
| `failed` | SÍ |
| `cancelled` | SÍ |
| `paid` | NO |

### Qué SÍ se puede modificar
- Datos de cada participante (nombre, apellido, correo, teléfono, dirección, ciudad, documento, género, polera, categoría, edad, nacimiento, donación, promociones, subtotal)
- Contacto de emergencia de cada participante
- Souvenirs de cada participante
- Answers de cada participante
- Totales de la inscripción (inscripción, donación, souvenirs, fee, descuento, grand_total)

### Qué NO se puede modificar
- `referencia` (clave de negocio única)
- `evento_id` (la inscripción está ligada al evento)
- `form_types_id` (el tipo de formulario está ligado a la inscripción)
- `pago_status` (se modifica solo vía `PATCH /registrations/{reference}/payment`)
- `fecha` (se preserva la fecha de creación)
- `evento_nombre` (ligado al evento)
- `tipo_pago` (ligado al método de pago original)

### Estrategia de actualización
**Reemplazo total de participantes:** se eliminan todos los participantes existentes y se re-crean con los nuevos datos. El cascade On Delete limpia automáticamente los hijos (contacto_emergencia, souvenirs, answers).

## Archivos modificados

### `app/Http/Requests/UpdateRegistrationRequest.php`
Reescrito completamente:
- `authorize()` retorna `true` (antes retornaba `false`)
- Reglas de validación para `participantes`, `participantes.*.answers`, `participantes.*.contacto_emergencia`, `participantes.*.souvenirs`, `totales`

### `app/Services/RegistrationService.php`
- Nuevo método `update(string $reference, array $data)`:
  1. Busca inscripción por referencia
  2. Valida que `pago_status !== 'paid'` (lanza `DomainException` si es paid)
  3. Valida documentos duplicados en el request
  4. Elimina participantes existentes (cascade limpia hijos)
  5. Elimina RegistrationTotal existente
  6. Re-crea participantes + contacto + souvenirs + answers
  7. Re-crea RegistrationTotal
  8. Ejecuta `syncPersonas()`
  9. Retorna con relaciones cargadas
- Nuevo método privado `createParticipantFromData(Registration, array)`: crea participante desde array raw (sin usar DTO)
- Nuevo método privado `validateDuplicateParticipantsFromData(array)`: valida documentos duplicados dentro del mismo request

### `app/Http/Controllers/RegistrationController.php`
- Nuevo método `update(UpdateRegistrationRequest, string)`:
  - Valida con `UpdateRegistrationRequest`
  - Llama a `$this->service->update()`
  - Retorna respuesta JSON con la inscripción actualizada
- Import de `UpdateRegistrationRequest`
- Eager load `participants.answers` agregado a `index()` y `show()`

### `routes/api.php`
- Agregada ruta: `PUT /api/v1/registrations/{reference}` → `RegistrationController@update`

## Ejemplo de request

```json
PUT /api/v1/registrations/LA-9167800F
Content-Type: application/json

{
    "participantes": [
        {
            "nombre": "Gerd",
            "apellido": "Claros",
            "genero": "Masculino",
            "tipoDocumento": "DNI",
            "numeroDocumento": "962633",
            "polera": "M",
            "precioPolera": 25,
            "nacimiento": {
                "dia": "8",
                "mes": "8",
                "anio": "1985"
            },
            "edad": 40,
            "correo": "gerd@gmail.com",
            "direccion": "AV Dòrbigny 333",
            "ciudad": "Cochabamba",
            "telefono": "+59178441410",
            "categoria": "1",
            "precioCategoria": 5.82,
            "donacion": 50,
            "promoDescuento": 0,
            "promoCodigo": "",
            "subtotal": 80.82,
            "contacto_emergencia": {
                "nombre": "Gerd Claros",
                "celular": "+59178441410",
                "relacion": "LGN"
            },
            "souvenirs": [
                { "id": "1", "nombre": "Remera", "precio": 25 }
            ],
            "answers": [
                {
                    "form_types_id": 1,
                    "question_id": 1,
                    "value": "Talla M"
                }
            ]
        }
    ],
    "totales": {
        "inscripcion": 5.82,
        "donacion": 50,
        "souvenirs": 25,
        "fee": 0.29,
        "descuento": 0,
        "grand_total": 81.11
    }
}
```

## Ejemplo de respuesta (200)

```json
{
    "success": true,
    "message": "Inscripción actualizada correctamente.",
    "data": {
        "referencia": "LA-9167800F",
        "evento_id": "1",
        "form_types_id": 1,
        "evento_nombre": "Carrera por la Vida",
        "pago_status": "pending",
        "participantes": [
            {
                "nombre": "Gerd",
                "apellido": "Claros",
                "answers": [
                    {
                        "form_types_id": 1,
                        "question_id": 1,
                        "value": "Talla M"
                    }
                ],
                "souvenirs": [
                    { "nombre": "Remera", "precio": 25 }
                ],
                "contacto_emergencia": {
                    "nombre": "Gerd Claros",
                    "celular": "+59178441410",
                    "relacion": "LGN"
                }
            }
        ],
        "totales": {
            "inscripcion": 5.82,
            "donacion": 50,
            "souvenirs": 25,
            "fee": 0.29,
            "descuento": 0,
            "grand_total": 81.11
        }
    }
}
```

## Respuesta de error (si ya está pagada)

```json
{
    "message": "No se puede modificar una inscripción ya pagada.",
    "exception": "DomainException"
}
```

## Flujo completo

```
PUT /api/v1/registrations/{reference}
  │
  ▼
UpdateRegistrationRequest (valida participantes, answers, totales)
  │
  ▼
RegistrationController::update()
  │
  ▼
RegistrationService::update(reference, data)
  │
  │  DB::transaction {
  │    1. Buscar Registration por reference
  │    2. Validar pago_status != 'paid'
  │    3. Validar duplicados de documentos
  │    4. Eliminar participantes (cascade limpia hijos)
  │    5. Eliminar RegistrationTotal
  │    6. Re-crear participantes + contacto + souvenirs + answers
  │    7. Re-crear RegistrationTotal
  │    8. syncPersonas()
  │    9. loadRelations()
  │  }
  │
  ▼
Response: { success, message, data: RegistrationCollectionResource }
```

## Cadena de cascade

```
registrations (referencia)
  └── participantes (DELETE → cascade)
        ├── contacto_emergencia_participantes (cascade)
        ├── souvenir_participantes (cascade)
        └── answers (cascade)
  └── registration_totals (DELETE → cascade)
```

## Archivos de rutas actualizados

```
GET    /api/v1/registrations                    → index
POST   /api/v1/registrations                    → store
GET    /api/v1/registrations/{reference}        → show
PUT    /api/v1/registrations/{reference}        → update    ← NUEVO
PATCH  /api/v1/registrations/{reference}/payment → updatePayment
DELETE /api/v1/registrations/{reference}        → destroy
```
