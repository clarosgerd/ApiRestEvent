# Organizadores — CRUD + asignación por evento (sesión 11/08/2026)

## Pedido original

> necesito que incluyas a los organizadores, crud de organizadores y que
> cada evento podamos incluir su organizador, cuando se publica no
> podemos cambiar el organizador

## Estado previo (diagnóstico)

El modelo `Organizador` y la columna `eventos.organizador_id` ya existían
desde el 22/07/2026 (migración `2026_07_20_200004_create_organizadores_table`),
pero **sin ningún CRUD real**: ningún controlador exponía `/organizadores`,
`StoreEventosRequest` aceptaba `organizador_id` al crear un evento pero
`UpdateEventosRequest` no lo tenía para editar, y `EventoResource` lo
traía comentado (nunca se mostraba en el panel). Todo evento nuevo quedaba
pegado al organizador `id=1` por default (`CrearEventoAction`).

## Decisiones (por precedente, sin preguntar — coherentes con el resto de
la sesión)

- **CRUD de organizadores: solo `super_admin`.** Es un catálogo global de
  negocio (no scoped a un evento), mismo criterio que `Socios` — de hecho
  crear un evento (`POST /event`) ya era solo `super_admin` desde antes,
  así que asignar organizador al crear ya estaba implícitamente
  restringido.
- **Reasignar `organizador_id` en un evento existente: solo `super_admin`
  y solo si el evento sigue en borrador.** Una vez publicado queda fijo —
  el correo de dashboard (`EventoController::publicar()`) ya se le mandó
  al organizador original, reasignarlo después dejaría un evento
  "publicado" cuyo dueño real ya no coincide con quien fue notificado.
- Borrar un organizador con eventos asociados está bloqueado (409) — sin
  cascade en la FK (`eventos.organizador_id`), se pide desactivarlo
  (`activo=false`) en cambio, mismo patrón que `SocioController`.

## Bug encontrado en el camino

`Route::apiResource('/organizadores', OrganizadorController::class)` sin
`->parameters()` genera `{organizadore}` (el inflector de Laravel es en
inglés — `Str::singular('organizadores')` da `organizadore`, no
`organizador`). Como los métodos del controlador tipan
`Organizador $organizador`, el binding implícito de modelo no encontraba
el parámetro de ruta por nombre y en vez de fallar visiblemente inyectaba
un `Organizador` vacío (id `null`) — `destroy()` no bloqueaba eventos
asociados porque estaba chequeando el modelo vacío, no el real. Se arregló
con `->parameters(['organizadores' => 'organizador'])`. El lado de
admin-eventos no tiene el problema porque esos métodos reciben `int`, no
el modelo (binding posicional, no por nombre).

## Qué se tocó

- ApiRestEvent: `OrganizadorController`/`OrganizadorResource`/
  `Store`+`UpdateOrganizadorRequest` (nuevos), ruta `/organizadores`,
  `EventoResource` (expone `organizadorId`/`organizador`),
  `UpdateEventosRequest`/`EventoService`/`EventoController::update()`
  (guard de rol + inmutabilidad post-publicación).
- admin-eventos: `OrganizadorController` + vista `organizadores/index`
  (mismo patrón que `socios/index`), nav link, selects en
  `eventos/create`/`eventos/edit` (edit: solo editable para super_admin
  con el evento en borrador, si no read-only).
