# Implementacion: Sincronizar Participantes → Personas

**Fecha:** 2026-07-17
**Metodo:** Se ejecuta automáticamente al crear una inscripción (POST `/api/v1/registrations`)

---

## Objetivo

Al registrar una inscripción con sus participantes, crear o actualizar automáticamente un registro en la tabla `personas` por cada participante. Esto permite que los participantes tengan cuenta de usuario para futuros logins.

---

## Flujo

```
POST /api/v1/registrations
  → RegistrationService::create()
      1. Validar referencia única
      2. Validar participantes duplicados
      3. Crear Registration
      4. Crear Participantes + ContactosEmergencia + Souvenirs
      5. Crear RegistrationTotal
      6. syncPersonas() ← NUEVO
          → Por cada participante:
              Persona::updateOrCreate(
                  buscar: numero_documento,
                  actualizar: nombre, apellido, email, password(DNI), etc.
              )
      7. Retornar inscripción con relaciones cargadas
```

---

## Bugs corregidos

### 1. `app/Models/Persona.php`

| Bug | Antes | Después |
|---|---|---|
| No funcionaba Sanctum | `extends Model` | `extends Authenticatable` |
| Password visible en JSON | Sin `$hidden` | `$hidden = ['password', 'token']` |
| Import duplicado | `use HasFactory` dos veces | `use HasApiTokens, HasFactory` una vez |

### 2. `app/Services/RegistrationService.php`

| Bug | Antes | Después |
|---|---|---|
| Import fantasma | `use App\Models\EmergencyContact` | Eliminado |
| Import faltante | — | `use App\Models\Persona` + `use Illuminate\Support\Facades\Hash` |

---

## Mappeo de campos Participante → Persona

| Participante | Persona | Transformación |
|---|---|---|
| `numero_documento` | `numero_documento` | Campo de búsqueda (unique) |
| `tipo_documento` | `tipo_documento` | Directo |
| `nombre` | `nombre` | Directo |
| `apellido` | `apellido` | Directo |
| `alias` | `alias` | Directo |
| `genero` | `sexo` | Nombre de campo diferente |
| `correo` | `email` + `correo` | Ambos se llenan (email para login) |
| `direccion` | `direccion` | Directo |
| `ciudad` | `ciudad` | Directo |
| `telefono` | `telefono` | Directo |
| `fecha_nacimiento` | `fecha_nacimiento` | Directo |
| **`numero_documento`** | **`password`** | `Hash::make($numero_documento)` |

---

## Estrategia de duplicados

```php
Persona::updateOrCreate(
    ['numero_documento' => $participante->numero_documento],  // búsqueda
    [/* campos a crear/actualizar */]
);
```

- Si ya existe una Persona con ese `numero_documento` → **actualiza** los datos
- Si no existe → **crea** una nueva
- Evita duplicados cuando un participante se inscribe en varios eventos

---

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Models/Persona.php` | Fix `extends Authenticatable`, agregar `$hidden`, limpiar imports |
| `app/Services/RegistrationService.php` | Agregar imports `Persona`/`Hash`, método `syncPersonas()`, llamarlo en `create()` |

---

## Ejemplo

Si se registra una inscripción con este participante:

```json
{
  "nombre": "Juan",
  "apellido": "Pérez",
  "tipoDocumento": "CI",
  "numeroDocumento": "1234567",
  "correo": "juan@email.com",
  "genero": "Masculino",
  "direccion": "Av. Principal 123",
  "ciudad": "Santa Cruz",
  "telefono": "75925001",
  "nacimiento": { "year": 1990, "month": 5, "day": 15 }
}
```

Se crea/actualiza en `personas`:

```json
{
  "numero_documento": "1234567",
  "tipo_documento": "CI",
  "nombre": "Juan",
  "apellido": "Pérez",
  "sexo": "Masculino",
  "email": "juan@email.com",
  "correo": "juan@email.com",
  "password": "$2y$12$...",  // hash de "1234567"
  "direccion": "Av. Principal 123",
  "ciudad": "Santa Cruz",
  "telefono": "75925001",
  "fecha_nacimiento": "1990-05-15"
}
```

**Login posterior:** email `juan@email.com` / password `1234567`
