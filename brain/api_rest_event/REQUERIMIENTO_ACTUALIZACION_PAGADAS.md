# Requerimiento: Actualización de inscripciones pagadas

**Fecha:** 2026-07-19
**Estado:** Implementado

---

## Descripción del requerimiento

Se requiere permitir la actualización de datos de una inscripción que tenga `pago_status = "paid"`. Actualmente, el sistema bloquea esta operación con un `DomainException`. La nueva funcionalidad debe:

1. Estar disponible en una **ruta separada** para no mezclarla con la actualización normal.
2. Requerir **confirmación explícita** del usuario porque implica un **costo adicional** de edición.
3. Registrar un **log de auditoría** con el usuario que realizó el cambio y el costo aplicado.

---

## Cambios realizados

### 1. Nueva migración: `audit_logs`

**Archivo:** `database/migrations/2026_07_19_000000_create_audit_logs_table.php`

Crea la tabla `audit_logs` para registrar las actualizaciones de inscripciones pagadas:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Clave primaria |
| `registration_id` | foreign key | FK a `registrations` (cascade delete) |
| `usuario` | string, nullable | Email o IP del usuario que realizó el cambio |
| `costo_adicion` | decimal(10,2) | Costo de edición aplicado (default: 0) |
| `created_at` | timestamp | Fecha del cambio |

### 2. Nuevo modelo: `AuditLog`

**Archivo:** `app/Models/AuditLog.php`

- Relación `belongsTo` con `Registration`
- Campos: `registration_id`, `usuario`, `costo_adicion`
- Cast: `costo_adicion` a `decimal:2`

### 3. Nuevo FormRequest: `UpdatePaidRegistrationRequest`

**Archivo:** `app/Http/Requests/UpdatePaidRegistrationRequest.php`

Extiende la validación de `UpdateRegistrationRequest` con:

- **`confirmacion`** (required, boolean): Debe ser `true` para proceder. Si es `false` o está ausente, la API rechaza con 422.

Mismas reglas de validación para `participantes` y `totales` que la actualización normal.

### 4. Nueva ruta

**Archivo:** `routes/api.php`

```
PATCH /api/v1/registrations/{reference}/update-paid
```

Controlador: `RegistrationController::updatePaid`

### 5. Relaciones en Registration

**Archivo:** `app/Models/Registration.php`

Se agregaron dos relaciones:

- `formType()`: `belongsTo(FormType::class, 'form_types_id')` - Para obtener el `costo_edicion`.
- `auditLogs()`: `hasMany(AuditLog::class)` - Para historial de cambios.

### 6. Nuevo método en RegistrationService

**Archivo:** `app/Services/RegistrationService.php`

Método `updatePaidRegistration(string $reference, array $data): array`

Flujo:
1. Busca la inscripción con su `formType` relacionado.
2. Valida que `pago_status === 'paid'`.
3. Obtiene `costo_edicion` desde `form_types`.
4. Valida participantes duplicados.
5. Elimina y recrea participantes y totales (patrón existente).
6. Sincroniza personas.
7. Crea registro en `audit_logs` con el usuario y costo.
8. Retorna `['registration' => ..., 'costo_adicion' => ...]`.

### 7. Nuevo método en RegistrationController

**Archivo:** `app/Http/Controllers/RegistrationController.php`

Método `updatePaid(UpdatePaidRegistrationRequest $request, string $reference): JsonResponse`

- Obtiene el usuario autenticado o la IP como respaldo.
- Llama a `$this->service->updatePaidRegistration()`.
- Retorna respuesta con campo `costo_adicion`.

### 8. Documentación en README

**Archivo:** `README.md`

Se documentó el nuevo endpoint con:
- Descripción de la funcionalidad
- Tabla de parámetros
- Ejemplo con curl
- Respuesta exitosa (con `costo_adicion`)
- Errores posibles (confirmación, no pagada, no encontrada)

---

## Respuesta del endpoint

```json
{
  "success": true,
  "message": "Inscripción pagada actualizada correctamente.",
  "costo_adicion": 25.00,
  "data": {
    "referencia": "REF-2026-001",
    "fecha": "2026-07-10 14:30:00",
    "evento_id": "1",
    "evento_nombre": "Maratón Ciudad 2026",
    "tipo_pago": "QR",
    "pago_status": "paid",
    "totales": { "..." : "..." },
    "participantes": [ "..." ]
  }
}
```

---

## Flujo del cliente (frontend)

```
1. GET /registrations/REF-001  →  pago_status: "paid", form_types_id: 5
2. GET /form-type/5            →  costo_edicion: 25.00
3. UI muestra: "Costo adicional por edición: 25.00. ¿Confirma?"
4. Usuario acepta →
   PATCH /registrations/REF-001/update-paid
   Body: { participantes: [...], totales: [...], confirmacion: true }
5. Respuesta: { costo_adicion: 25.00, data: { ... } }
```

---

## Archivos creados/modificados

| Archivo | Acción |
|---------|--------|
| `database/migrations/2026_07_19_000000_create_audit_logs_table.php` | **Crear** |
| `app/Models/AuditLog.php` | **Crear** |
| `app/Http/Requests/UpdatePaidRegistrationRequest.php` | **Crear** |
| `routes/api.php` | Editar |
| `app/Models/Registration.php` | Editar |
| `app/Services/RegistrationService.php` | Editar |
| `app/Http/Controllers/RegistrationController.php` | Editar |
| `README.md` | Editar |

---

## Notas técnicas

- El `costo_edicion` proviene de la tabla `form_types`, no del frontend.
- El campo `confirmacion` es obligatorio y debe ser `true`.
- El log de auditoría registra quién hizo el cambio y el costo aplicado.
- Se mantiene el patrón existente de delete-and-recreate para participantes/totales.
- El `pago_status` NO se modifica con esta ruta; se mantiene como `"paid"`.
