# Plan: asignar ayudantes/staff a sesiones/disertantes de un congreso

13/08/2026 — gap detectado por el usuario mientras revisábamos
[[PLAN-CALENDARIO-ICS-EVENTO-13082026]] ("qué pasa con los participantes que se registraron
como staff o ayudante a un evento de congreso... cómo hago que un ayudante le dé soporte a un
disertante").

**✅ IMPLEMENTADO Y VERIFICADO (13/08/2026, misma sesión)** — ver
`elascenso/event/brain/DEPLOY-CHECKLIST-CALENDARIO-STAFF-13082026.md`. Incluye además el
checkbox "Es Staff/Ayudante" en el formulario de alta/edición de `form_type` en
`admin-eventos` (los 3 caminos: alta de evento nuevo, alta de form_type suelto, edición) —
no estaba en el alcance original del plan pero se cerró como gap detectado durante la
implementación, sin el cual el flag `es_staff` solo se podía activar editando la BD
directo. 8 tests Pest nuevos + SQL standalone verificado contra un esquema real (rollback +
reaplicación + `describe` de columnas, coincide exacto). **No corrido contra UAT/BD real
todavía.**

## Contexto — el gap confirmado

Hoy existen dos conceptos de "staff" que **no están conectados entre sí**:

1. **Participantes registrados como Staff/Ayudante/Voluntario**: son simplemente personas
   inscritas bajo un `form_type` como cualquier otro (`form_types.tipo` es un campo de texto
   libre sin reglas — el organizador le puede poner "Staff", "Ayudante", "Voluntario", lo que
   sea). El sistema no distingue estructuralmente a estos participantes de un corredor normal.
2. **`AsistenciaSesion.staff_admin_user_id`**: el "staff" que aparece acá es un **admin del
   panel** (`AdminUser`, login de `admin-eventos`) que hizo el check-in de alguien en una sesión
   — no tiene ninguna relación con los participantes del punto 1.

Y **`sesiones_congreso.ponente` es un campo de texto libre** — el disertante ni siquiera es un
registro del sistema, es solo un nombre en la sesión. Por lo tanto "que un ayudante le dé
soporte a un disertante" en la práctica es **"asignar un participante-ayudante a una sesión de
congreso"** (la sesión ya trae el nombre del disertante).

**Decisiones del usuario**:
- **Quién asigna**: solo el organizador, después de que la gente ya se inscribió — vía
  `admin-eventos`, no un picker en el formulario público de registro.
- **Cardinalidad**: muchos a muchos — un ayudante puede cubrir varias sesiones a lo largo del
  congreso, y una sesión puede tener más de un ayudante asignado.

## Diseño

### 1. `form_types.es_staff` (nuevo flag explícito)

Para no depender de comparar el string `form_types.tipo`/`name` contra "Staff"/"Ayudante"/
"Voluntario" (frágil, ya hubo un bug real de un booleano mal casteado por depender de
comparaciones implícitas — ver `project_bug_cast_bool_hasshirt_formtyperesource`), se agrega un
booleano explícito **`es_staff`** en `form_types`, mismo patrón que `has_team`/`has_delivery`
— el organizador lo marca al crear/editar el tipo de formulario "Staff"/"Ayudante" en
admin-eventos. Solo los participantes inscritos bajo un `form_type` con `es_staff = true` son
asignables a sesiones.

### 2. Nueva tabla pivote `sesion_congreso_staff`

Mismo patrón que `asistencia_sesion` (que ya vincula `participante_id` ↔ `sesion_congreso_id`,
pero para asistencia, no asignación):

| Columna | Tipo | Nota |
|---|---|---|
| `id` | bigint | |
| `sesion_congreso_id` | FK → `sesiones_congreso.id` | |
| `participante_id` | FK → `participantes.id` | debe pertenecer al mismo evento que la sesión |
| `asignado_por_admin_user_id` | FK → `admin_users.id`, nullable | auditoría de quién hizo la asignación |
| `created_at`/`updated_at` | timestamps | |

Constraint único en (`sesion_congreso_id`, `participante_id`) — no tiene sentido asignar al
mismo ayudante dos veces a la misma sesión.

Modelo `SesionCongresoStaff` (o relación `belongsToMany` directa sin modelo propio si no hace
falta guardar más que el vínculo) + relaciones nuevas:
- `SesionCongreso::staffAsignado()` → `belongsToMany(Participante::class, 'sesion_congreso_staff', ...)`.
- `Participante::sesionesApoyadas()` → `belongsToMany(SesionCongreso::class, 'sesion_congreso_staff', ...)`.

### 3. Endpoints nuevos en `ApiRestEvent` (guard `admins`, mismo patrón `AuthorizesEventoScope`)

- `GET /event/{event}/sesiones/{sesion}/staff` — lista de participantes asignados a esa sesión.
- `POST /event/{event}/sesiones/{sesion}/staff` — asigna un `participante_id` (valida que
  pertenezca al evento y que su `form_type.es_staff = true`, si no 422 con mensaje claro).
- `DELETE /event/{event}/sesiones/{sesion}/staff/{participante}` — desasigna.
- `GET /event/{event}/staff-disponible` — lista de participantes con `es_staff = true` del
  evento, para poblar el selector en el paso 4 (excluye a los ya asignados a esa sesión
  puntual, los incluye para otras).

Mismo criterio de scoping que el resto de endpoints de `admin-eventos` (`super_admin` o admin
del propio evento) — reusa `assertCanWriteEvento()`.

### 4. UI en `admin-eventos`

Extiende la pantalla de administración de **Sesiones** que ya existe (ver
`project_sesiones_congreso` en memoria) — por cada fila de sesión:
- Chips con los ayudantes ya asignados (nombre + botón "×" para desasignar).
- Control "+ Asignar ayudante" — selector con búsqueda de los participantes `es_staff` del
  evento, filtra automáticamente los ya asignados a esa sesión puntual.

No requiere pantalla nueva — es una extensión de la vista existente, mismo patrón de "editar
inline" que ya usan otras secciones del panel (ej. numeración).

## Valor operativo — usos posteriores (no en el alcance de este plan, solo para que quede
anotado el hilo)

- **Gafetes/credenciales**: el gafete de un ayudante (`gafetesPdf`, ya distingue color por
  `form_type`) podría imprimir qué sesión/sala apoya — útil para el día del evento, pero es una
  extensión aparte.
- **Roster imprimible por sala**: mismo patrón que el CSV de delivery
  (`DeliveryController::exportCsv`) — un CSV/PDF de "quién apoya qué sala" para el día del
  evento. No cubierto acá.
- **Cruce con [[PLAN-CALENDARIO-ICS-EVENTO-13082026]]**: el `.ics` de un participante Staff
  podría filtrarse a solo las sesiones que le tocan apoyar en vez de la agenda completa del
  congreso — mencionado como posible mejora futura del plan de calendario, no obligatorio para
  la primera versión de ninguno de los dos.

## Verificación

1. Crear un `form_type` "Staff" con `es_staff = true`, inscribir un participante ahí.
2. Desde `admin-eventos`, abrir la pantalla de Sesiones de un evento congreso, asignar ese
   participante a 2 sesiones distintas — confirmar que aparece en ambas y que un participante
   normal (sin `es_staff`) no aparece como opción para asignar.
3. Confirmar el constraint único: intentar asignar al mismo participante dos veces a la misma
   sesión debe fallar limpio (422), no un error de SQL crudo.
4. Desasignar desde un chip — confirmar que desaparece de esa sesión sin afectar la otra
   asignación del mismo participante.

## No cubierto en este plan (a decidir después)

- Self-select del ayudante durante el registro público (el usuario explícitamente lo descartó
  para esta primera versión — "solo el organizador, después").
- Notificación automática al ayudante cuando se le asigna una sesión (email/WhatsApp) — no
  pedido, se puede agregar después reusando `NotificacionService`/`WhatsappExternoService` que
  ya existen.
- Los 3 usos operativos de la sección anterior (gafetes, roster imprimible, filtro de ICS).
