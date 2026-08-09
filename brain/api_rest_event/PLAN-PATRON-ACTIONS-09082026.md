# Introducir el patrón Actions en ApiRestEvent

**Estado: planeado 09/08/2026, pendiente de retomar — no se implementó nada
todavía.** Este documento es el plan completo tal como quedó aprobado en
sesión; al retomarlo, seguir el roadmap de fases tal cual está.

## Contexto

El usuario pidió un plan para introducir el patrón Actions (una clase por
caso de uso de negocio, con un único punto de entrada) en `ApiRestEvent`.
No hay que inventar la convención desde cero: **ya existe un precedente
real en el repo**, `App\Actions\EnviarDashboardOrganizadorAction`
(namespace `App\Actions`, método público `handle()`, invocada como
`app(Action::class)->handle(...)` desde `EventoController::publicar()` y
por constructor-injection desde el Command `EnviarDashboardOrganizador`).
El plan es extender esa convención, no reemplazarla.

**Por qué hace falta**: `RegistrationService` concentró demasiadas
responsabilidades no relacionadas con el tiempo — 777 líneas, ~20 métodos
(crear/actualizar/eliminar/buscar inscripción, validar equipo, validar
delivery, validar duplicados, consumir/liberar promo codes, sincronizar
personas, sweep de cupo lleno). Cualquier cambio en una parte (ej. promo
codes) obliga a tocar un archivo que también maneja equipos y delivery, y
es difícil ubicar dónde vive cada caso de uso. `EventoService` (356
líneas) tiene el mismo síntoma en menor escala. El objetivo es que cada
caso de uso imperativo (crear, publicar, sincronizar, cancelar...) tenga
su propia clase de una sola responsabilidad, y que los Services que
sobrevivan queden solo para lógica compartida entre varias Actions
(validaciones, cálculo de totales), no para orquestar el caso de uso
completo.

## Convención (ya establecida — se documenta para que quede explícita, no se cambia nada del ejemplo existente)

- Namespace `App\Actions`, un archivo por clase, nombre `VerboSustantivoAction`
  (ej. `CrearInscripcionAction`, `PublicarEventoAction`).
- Método público único `handle(...)` con los argumentos tipados que
  necesita (Models/DTOs). Sin constructor con dependencias si no hace
  falta — usa Models/facades directo, igual que el ejemplo existente.
  Solo se inyecta un colaborador (otro Service, otra Action) cuando de
  verdad se reusa.
- Se invoca `app(Action::class)->handle(...)` desde Controllers, y por
  constructor-injection desde Commands — mismo patrón que ya existe.
- No cambia la capa de DTOs/FormRequests/Resources — las Actions consumen
  los DTOs que ya existen (`RegistrationDTO`, `EventoDTO`, etc.).
- No se instala ningún paquete (`lorisleiva/laravel-actions` u otro) — es
  100% convención propia, ya probada con el ejemplo existente.

## Cálculo de totales, souvenirs y fee de promo code — qué pasa con esa lógica

Pregunta explícita del usuario: si el refactor respeta las reglas de
negocio de cálculo de total, souvenirs y fee de promo code. Verificado
leyendo el código real de `RegistrationService`, la respuesta tiene 2
partes:

1. **El cálculo en sí (matemática de precio) no vive en `ApiRestEvent`.**
   `createTotals()` y la parte de `createParticipant()` que guarda
   `precio_categoria`/`donacion`/`promo_descuento`/`subtotal`/el precio de
   cada souvenir simplemente **persisten** los números que ya vienen
   calculados en el `RegistrationDTO` (`$dto->totals->*`,
   `$dto->categoryPrice`, `$dto->souvenirs[].price`, etc.) — no
   recalculan nada. La matemática real (precio de categoría + souvenirs +
   descuento de promo + fee 5%) se hace aguas arriba, en
   `elascenso/event/api/registro.php` (otro repo/capa, PHP puro), que es
   quien valida contra `ApiRestEvent` y calcula antes de mandar el
   `POST /registrations`. El plan de Actions **no toca esa capa** — sigue
   validando/calculando exactamente igual que hoy.
2. **Lo que sí es lógica de negocio real dentro de `ApiRestEvent`** es
   `consumePromoCode()` y `releasePromoCodes()`: el lock (`lockForUpdate`)
   sobre el código, el rechazo con `DomainException` si ya lo usó otra
   inscripción, y el marcado `usado=true`/`registration_id`. Esto se
   preserva tal cual — en la Fase 3, `CrearInscripcionAction` y
   `ActualizarInscripcionAction` **inyectan estos dos métodos como
   colaborador** (quedan en el `RegistrationService` reducido, no se
   duplican ni se reescriben en cada Action). Mismo criterio para
   `validateEquipo`/`validateDelivery`/`validateDuplicateParticipants` —
   son las reglas de negocio de validación, se comparten, no se copian.

En resumen: el refactor **no cambia ni un número ni una regla** de cómo se
calculan totales/souvenirs/fee/promo — solo mueve el código de "¿quién
orquesta el caso de uso completo?" (`RegistrationService::create()` como
Dios-método → `CrearInscripcionAction::handle()`), manteniendo intactos
los colaboradores que ya tienen la lógica de negocio real.

## Roadmap por fases (orden por impacto/riesgo, no por importancia)

### Fase 1 — Piloto de bajo riesgo (funciones ya autocontenidas)

- `EventoController::publicar()` (líneas 178-207) → `PublicarEventoAction::handle(Evento $evento)`:
  mueve la validación de estado + `$event->update(['publicado' => true])`
  + la llamada a `EnviarDashboardOrganizadorAction` + el
  `EventoNotification::create()` + `AdminAuditLogger::log()`. Deja el
  controller en unas pocas líneas y es el ejemplo más claro de una Action
  que compone otra Action ya existente.
- `EventoController::despublicar()` (líneas 214-239) → `DespublicarEventoAction`,
  mismo tratamiento (valida estado + participantes inscritos, cambia
  `publicado`, audita).
- `RegistrationService::sweepFormTypesCupoLleno()` +
  `deactivateFormTypeIfCupoLleno()` → `SweepFormTypesCupoLlenoAction`
  (hoy solo la usa un Command/cron, migración mecánica).

Riesgo bajo: son funciones ya aisladas, con tests de endpoint existentes.

### Fase 2 — Conversión mecánica de lo que ya tiene forma de Action

- `ChronoTrackSyncService::sincronizar()` (`app/Services/ChronoTrackSyncService.php`)
  → `SincronizarChronoTrackAction`. El Service que queda
  (`ChronoTrackClient`) sigue siendo un cliente HTTP puro, no una Action.
- `App\Support\ResultadosBulkImporter::importar()` (estático) →
  `App\Actions\ImportarResultadosAction` con `handle()` en vez de
  `importar()` estático, por consistencia — la usan tanto la carga manual
  como el sync de ChronoTrack, así que hay que actualizar los 2 call
  sites en el mismo cambio.

Riesgo bajo: son wrappers mecánicos de código que ya está aislado y con
una sola responsabilidad.

### Fase 3 — El núcleo de `RegistrationService` (mayor impacto, más cuidado)

- `createInTransaction()` + `createParticipant()` + `createTotals()` →
  `CrearInscripcionAction::handle(RegistrationDTO $dto): Registration`.
- `update()` + `createParticipantFromData()` +
  `validateDuplicateParticipantsFromData()` → `ActualizarInscripcionAction`.
- `updatePaidRegistration()` → `ActualizarInscripcionPagadaAction` (ya
  tiene su propio endpoint dedicado — `registro_actualizar_pagada.php` en
  el frontend — coincide 1 a 1 con un caso de uso real).
- Los métodos privados que hoy son "sub-pasos" compartidos
  (`validateEquipo`, `validateDelivery`, `validateDuplicateParticipants`,
  `consumePromoCode`, `releasePromoCodes`, `syncPersonas`) **no** se
  convierten en Actions — son helpers reusados por más de un caso de uso.
  Se quedan en un `RegistrationService` reducido (o se mueven a un
  `RegistrationValidationService` separado) que las Actions nuevas
  inyectan como colaborador, para no duplicar esa lógica.
- `lookupRegistration()` se queda en el Service tal cual — es lectura
  pura, no un caso de uso que muta estado (ver "Qué NO se toca").

**Punto de cuidado real**: `RegistrationService::create(RegistrationDTO $dto)`
tiene 2 callers hoy — el flujo normal de `registro.php` y la carga masiva
CSV del panel `admin-eventos` (`RegistrationService::create()` reusado
según el memo de carga masiva por CSV). Al extraer
`CrearInscripcionAction`, `RegistrationService::create()` debe quedar como
fachada delgada que delega a la Action (mismo nombre/firma pública), o
hay que actualizar los 2 call sites en el mismo cambio — no dejar un
caller usando el método viejo y otro el nuevo a mitad de camino.

## Qué NO se toca

- Controllers de solo lectura (`ResultadoController::porEvento/mios/comparativoCategoria`,
  dashboards, `OrganizadorDashboardController`, etc.) — el patrón Actions
  es para casos de uso que mutan estado, no para composición de queries.
  Se quedan en Services/Controllers como están.
- FormRequests, DTOs, Resources, Policies — sin cambios.
- Controllers ya delgados (Ops*, Category/Souvenir/Route/Coordinate CRUD
  simple, etc.) — no se les fuerza el patrón si no aportan nada (serían
  una Action de 3 líneas envolviendo un `Model::update()`, no vale la
  refactorización).
- La capa de cálculo de precios/totales/souvenirs/fee en
  `elascenso/event/api/registro.php` — vive en otro repo, no forma parte
  de este refactor.

## Verificación

- Antes y después de cada extracción: `php artisan test --filter=RegistrationTest`
  y `--filter=EventoTest` (ya existen en `tests/Feature/`, 506 y 113
  líneas respectivamente — cubren create/update/delete/lookup de
  inscripciones y el flujo de publicar/despublicar) deben seguir en
  verde sin cambios en las aserciones.
- Fase 3 en particular: además del test automatizado, correr un smoke
  test manual del flujo de carga masiva CSV en `admin-eventos` (el otro
  caller de `RegistrationService::create()`) contra un evento de prueba
  local, para confirmar que la fachada delgada sigue funcionando igual.

## Orden sugerido de ejecución

1. Fase 1 (piloto, una sesión corta — ya hay tests de endpoint).
2. Fase 2 (mecánica, se puede hacer en paralelo a la Fase 1).
3. Fase 3 (el grueso, requiere más cuidado por los 2 callers de `create()`).
