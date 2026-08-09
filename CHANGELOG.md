# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/).

## 2026-08-09 (continuación — Fase 2 de ChronoTrack)

DNS/DNF real, botón "Sincronizar ahora" en `admin-eventos`, leaderboard
completo por evento, y corrección del ranking de equipos para no mezclar
categorías/distancias — pedido explícito del usuario el mismo día del MVP.

### Added
- **Detección de DNS/DNF**: `ChronoTrackClient::intervalsParciales()` +
  `entriesDeCarrera()`; `ChronoTrackSyncService::sincronizar()` cruza
  `/entry` contra los intervals (completo + parciales) de cada carrera —
  bib en un checkpoint parcial pero no en el completo → `dnf`; en ningún
  lado → `dns`. Verificado contra el evento real 93491: 23 dns / 0 dnf
  (matemática exacta contra 299/280 y 114/109 confirmados vs. finishers).
- **`GET /event/{event}/resultados`** (`ResultadoController::porEvento`,
  auth:sanctum): leaderboard completo del evento por categoría — conteo
  inscritos/finished/dnf/dns, tabla con rank general + rank de género,
  equipos de esa categoría. Reemplaza a `comparativoCategoria()` (que solo
  daba la categoría propia) para el rediseño del frontend.
- **`POST /event/{event}/chronotrack/sincronizar`**
  (`ResultadoController::sincronizarChronoTrack`, mismo `assertCanWriteEvento`
  que el resto del panel): mismo camino que el comando artisan, para el
  botón nuevo del panel.

### Changed
- **`RankingEquipos::paraEvento()`** acepta un `$categoria` opcional que
  acota la suma de tiempos del equipo a integrantes de esa categoría —
  evita que un equipo "gane" solo por correr una distancia más corta
  mezclada en el mismo evento (issue documentado desde
  `project_demo_eventos_equipos_delivery`). `null` (default) mantiene el
  comportamiento viejo para `ClubController`. `resultadosEquipo()` ya pasa
  la categoría del participante.

### Fixed
- **`comparativoCategoria()` ordenaba mal desde que existen filas
  dns/dnf**: `tiempoASegundos(null)` da `0`, así que colaban en el primer
  puesto. Fix: filtrar `estado='finisher'` antes de ordenar.
- **`ResultadoController::mios()` devolvía el id crudo de categoría, no el
  nombre** (mismo bug que `DashboardInscripcionesData`, ver entrada
  anterior de hoy) — se agregó `categoriaId` (id, para matchear) y
  `categoria` ahora resuelve el nombre real.

### Verified
- Sync completo contra el evento real 93491: 412 resultados (389 finishers
  del MVP + 23 dns), 0 duplicados en una segunda corrida.
- `porEvento()` con datos sintéticos (5 participantes, 1 dns): rank general
  y rank de género exactos, dns excluido del leaderboard pero contado en
  las estadísticas.
- Botón del panel probado con Playwright contra `admin-eventos` local real:
  mismos números que la corrida por CLI. Timeout de
  `ApiRestEventClient::forward()` (default 15s) no alcanzaba para la
  cadena de llamadas reales a ChronoTrack — subido a 120s en esa llamada
  puntual.

## 2026-08-09

Sync de resultados desde ChronoTrack (MVP, solo lectura) y fix de nombres de
categoría en el dashboard de inscripciones, encontrado por el usuario en el
correo real de publicación durante una corrida de QA E2E en UAT.

### Added
- **Sync de resultados desde ChronoTrack** — nueva columna
  `eventos.chronotrack_event_id` (expuesta en alta/edición de evento, mismo
  patrón que `color_hex`); `App\Services\ChronoTrackClient` (auth "Test Auth
  Scheme" contra `api.chronotrack.com`, lista intervals de distancia
  completa y pagina resultados); `App\Services\ChronoTrackSyncService`
  (transforma resultados de ChronoTrack — bib/tiempo/posición — al formato
  del bulk existente); comando `chronotrack:sincronizar {evento}`
  (`App\Console\Commands\ChronoTrackSincronizar`). Solo consumo — no
  creamos ni administramos nada en ChronoTrack, el `chronotrack_event_id`
  lo obtiene el organizador allá. MVP a mano, sin botón en `admin-eventos`
  ni scheduler todavía. Probado en vivo contra un evento real de un cliente
  (389 finishers reales, idempotente).

### Changed
- **`ResultadoController::bulk()`**: la lógica de matching/upsert
  (chip→número de corredor→número de documento) se extrajo a
  `App\Support\ResultadosBulkImporter::importar()` — la usan tanto la carga
  manual (`bulk()`) como el sync de ChronoTrack nuevo, una sola fuente de
  verdad. Sin cambios de comportamiento/output en `bulk()`.

### Fixed
- **Dashboard de inscripciones mostraba el id de categoría, no el nombre**
  ("90009" en vez de "5K") — tanto en el panel `admin-eventos` como en el
  dashboard emailado sin login. `DashboardInscripcionesData::paraEvento()`
  agrupaba "Por categoría" por `$p->categoria` (el id) sin resolverlo a
  nombre, a diferencia de "Por tipo de formulario" (que sí tenía
  `nombresFormTypes`). Fix: nuevo `nombresCategorias` (mismo patrón), usado
  en `organizador/dashboard.blade.php` (tabla + botones de descarga CSV por
  categoría) y en `admin-eventos/.../dashboard-inscripciones.blade.php`.

### Verified
- `chronotrack:sincronizar` contra un evento real (2 intervals, 389
  resultados guardados con tiempo/posición exactos; corrido dos veces sin
  duplicar; participantes sin match reportados en `no_vinculados` sin
  romper el resto del import).
- Fix de categorías reverificado en vivo contra UAT en los dos lugares
  (panel y link firmado del correo): "5K"/"10K" en vez de los ids, botones
  de descarga con la misma etiqueta corregida.

## 2026-08-07

Dashboard de inscripciones y edición restringida de participante en el panel
`admin-eventos`; numeración de corredor/chip cargable al momento de la entrega
en el POS de `elascenso/delivery`; rediseño de gafetes a 7x5cm; fix de auth en
UAT para `admin-eventos`.

### Added
- **`GET /event/{event}/dashboard-inscripciones`** (`EventoController::dashboardInscripciones`,
  `auth:admins`): mismo conteo (total/pagados/pendientes/cancelados/fallidos, por categoría y
  por tipo de formulario) que ya se manda por correo al organizador, ahora también disponible
  autenticado dentro del panel. Lógica extraída a `App\Support\DashboardInscripcionesData::paraEvento()`
  (estático, mismo patrón que `RankingEquipos`/`ProgresoHistorico`), reusada tanto acá como en
  `OrganizadorDashboardController::show()`/`exportCsv()` (refactor sin cambiar el output — mismo
  conteo verificado antes/después).
- **`PATCH /event/v1/participantes/{participante}`** (`ParticipanteController::update`, antes un
  stub vacío sin ruta registrada): edición restringida de un participante desde el panel —
  whitelist real en `UpdateParticipanteRequest::rules()` (nombre, apellido, alias, correo,
  teléfono, dirección, ciudad, género, fecha de nacimiento, y `polera` solo si el participante ya
  tiene una asignada). Nunca toca categoría/precio/souvenirs/donación/promo/equipo/delivery/
  subtotal ni numeración de corredor/chip (eso sigue siendo `porEvento`/`numeracionBulk`/
  `updateNumeracion`). Auditado con `AdminAuditLogger`. `porEvento()` se extendió (mismo endpoint,
  no uno nuevo) para devolver también esos campos de contacto.
- **`GET /organizador/evento/{evento}/participantes/{documento}/numeracion`**
  (`OrganizadorDashboardController::actualizarNumeracionSitio`, link firmado, sin login): permite
  a `elascenso/delivery` empujar de vuelta número de corredor/chip cargados a mano en el POS de
  retiro en sitio, cuando el proveedor externo no llegó a tiempo — mismo patrón que
  `DeliveryController::updateEstado()`, pero matcheando por `numero_documento` (no hay id de
  participante en ese flujo). El CSV que `delivery` ya consumía
  (`OrganizadorDashboardController::exportCsv`) ahora trae 3 columnas nuevas:
  `NumeroCorredor`, `Chip`, `ActualizarNumeracionUrl` (esta última generada por fila).
- **Gafetes rediseñados a 7x5cm** (`tickets/gafetes.blade.php`), a pedido del organizador con una
  foto de referencia de sus gafetes físicos actuales: se sacaron logo del evento, nombre del
  evento, recuadro de foto y referencia; queda nombre arriba, QR abajo-izquierda y la
  categoría/rol duplicada abajo-derecha. `gafetesPdf()` pasa a `setPaper('a4', 'landscape')`
  (mismo patrón que `certificadosPdf`) — en A4 vertical con los márgenes default de dompdf, 3
  gafetes de 7cm por fila no entraban con margen.

### Fixed
- **Bug de modelo de caja en dompdf** encontrado al verificar el rediseño de gafetes: un
  `display: table` con filas de distinta cantidad de celdas (nombre = 1 celda, QR+rol = 2
  celdas) rompía el ancho/alto consistente de la tabla — el borde del gafete salía partido en
  dos cajitas en vez de un rectángulo único. Corregido usando un `div` de bloque normal con
  `width`/`height`/`border` explícitos para el gafete, y `display: table` solo para la fila
  interna QR+rol (mismo patrón ya probado en `.photo-box`/`.label`). Verificado con un PDF real
  contra el evento 59 antes y después del fix.

### Fixed
- **`/ops/enlaces` no exponía el link de "Participantes CSV"**
  (`organizador.dashboard.export`) — el único de los links firmados que
  necesita `elascenso/delivery` para configurar `/retiro` (POS de retiro en
  sitio), que no se puede generar en UAT con `php artisan
  organizador:generar-link` porque ahí no hay SSH. Agregado a
  `OpsLinkController::show()`, mismo patrón que los otros 3 links ya
  expuestos.

### Security
- **`admin-eventos` no mandaba `X-Auth-Token`** en `ApiRestEventClient::buildHeaders()` — el
  hosting cPanel de UAT corre PHP vía mod_lsapi, que no reenvía el header `Authorization` (bug ya
  documentado y arreglado en `elascenso/event` el 25/07, pero `admin-eventos` es un cliente nuevo
  que nunca recibió el mismo fix). Causaba `Unauthenticated.` en toda escritura del panel en UAT
  (crear/editar/publicar evento, etc.) aunque funcionara perfecto en local. Agregado el mismo
  header de respaldo que ya usa `elascenso/event`.

## 2026-08-05

Numeración de corredor/chip y carga masiva de inscripciones, ambas desde el panel
`admin-eventos` — ver `elascenso/event/brain/PLAN-NUMERACION-PANEL-05082026.md` y
`PLAN-REGISTRO-MANUAL-CSV-05082026.md`.

### Added
- **`GET /event/{event}/participantes`** (`ParticipanteController::porEvento`): primer
  listado real de participantes de un evento — no existía ninguno antes (el `index()` viejo
  estaba roto y sin ruta registrada). Filtrable por `?categoria=`. Devuelve `id`, `referencia`
  de la inscripción, datos personales básicos y `numeroCorredor`/`chip`.
- **`POST /event/{event}/registro-manual/bulk`** (`RegistrationController::importarBulk`),
  **solo `super_admin`**: crea una inscripción independiente por fila (`tipo_pago=pendiente`,
  `pago_status=pending`) a partir de un CSV subido desde el panel — evento, tipo de
  formulario y categoría fijos para todo el archivo, precio = solo categoría + cargo de
  servicio (5%), sin souvenirs/promo/donación/equipo/delivery. Rechaza de entrada un
  `form_type` con `has_team`. Reutiliza `RegistrationService::create()` tal cual, así que cada
  participante importado queda con su cuenta `Persona` sincronizada automáticamente, igual
  que un registro online. Responde con `creados[]`/`errores[]` por fila — una fila con error
  no aborta el resto del lote.

### Security
- **Numeración de corredor/chip ahora requiere autenticación** — `GET
  /event/{event}/participantes`, `PATCH .../numeracion` y `POST .../numeracion/bulk` (estos
  dos, agregados el 31/07 sin protección, ver `PLAN-RESULTADOS-EQUIPOS-31072026.md` §1) se
  movieron al grupo `auth:admins`, con `AuthorizesEventoScope::assertCanWriteEvento()` —
  mismo scoping que el resto del panel: `super_admin` sin restricción, `admin` solo su
  evento asignado. `POST /event/{event}/resultados/bulk` (cronometraje) se dejó
  deliberadamente sin auth — lo sube directo el software de cronometraje, sin panel de por
  medio.
- `POST /event/{event}/registro-manual/bulk` exige además `assertIsSuperAdmin()` — un `admin`
  scoped recibe 403 aunque tenga sesión válida.

### Fixed
- **`numeracionBulk` no dejaba borrar un número ya asignado dejando la celda en blanco.**
  Usaba `$item['numero_corredor'] ?? $participante->numero_corredor`; como una celda vacía
  del CSV llega como `null` explícito, `??` lo trataba igual que "la clave no vino" y
  mantenía el valor viejo. Cambiado a `array_key_exists()` por campo — si la clave está
  presente (aunque sea `null`), se asigna tal cual. Encontrado y arreglado el mismo día, tras
  confirmar con el usuario que corregir numeración ya asignada (chip fallado, corredor dado
  de baja) es el caso normal, no la excepción.

### Verified
- Playwright, navegador real, contra `event_testing`: scoping de permisos (403 para `admin`
  fuera de su evento, o intentando `registro-manual` sin ser `super_admin`), edición manual y
  por CSV de numeración (incluido borrar dejando la celda vacía), y carga masiva de
  inscripciones (2 creadas + 1 rechazada por documento duplicado, totales y `pago_status`
  correctos en base, `Persona` sincronizada). Datos de prueba de `registro-manual` borrados
  al cerrar (cada fila dispara un correo real); los de numeración se dejaron vivos como
  fixture (evento 63, "Carrera de Prueba Stepper").

### Added (continuación, mismo día — endpoint de consumo + tipo_evento_id real)
- **`tipo_evento_id`/`subtipo_evento_id` conectados de punta a punta** — antes hardcodeados en
  `1` para todo evento desde `EventoService::create()` (los 62 eventos reales quedan como
  estaban, sin tocar datos de producción; se corrigen a mano desde el panel de a uno).
  `EventoDTO`, `StoreEventosRequest`/`UpdateEventosRequest`, `EventoService::update()`
  (agregado al mapa de campos editables) y `EventoResource` (expone `tipoEventoId`/
  `tipoEvento`/`subtipoEventoId`/`subtipoEvento`, antes comentado).
- **Nueva fila de catálogo "Congreso / No aplica"** en `tipos_evento` (+ subtipo "General") —
  las 6 filas anteriores eran todas disciplinas deportivas, ninguna representaba un evento no
  deportivo, y la columna es `NOT NULL`. Ver
  `database/migrations/2026_08_05_120000_add_congreso_tipo_evento.php`.
- **`GET /tipos-evento`** (`TipoEventoController`, público): catálogo con subtipos anidados,
  para poblar selects — usado por `admin-eventos`.
- **`GET /event/consumo`** (`EventoController::consumo`, público, sin auth): eventos
  `estado_evento_id=closed` y de disciplina deportiva real (excluye "Congreso / No aplica"),
  con `coordinates`/`route`/`categories`, y solo participantes con `pago_status=paid` **y**
  `numero_corredor`/`chip` ambos asignados. Expone deliberadamente solo `genero`, `categoria`,
  `numeroCorredor`, `chip` — sin nombre/documento/correo/teléfono, por ser público sin login.
  `?evento_id=` opcional. Debe resolverse antes del `apiResource('/event', ...)` en
  `routes/api.php` (mismo cuidado que `/registrations/by-pay-order`).

### Fixed (continuación, mismo día)
- **`EventoService::update()` reventaba al guardar un evento con `longDescription`, `deslinde`,
  `imagen_portada_url` o `video_url` vacíos** (`Column '...' cannot be null`) — esas 4 columnas
  son `NOT NULL` sin default desde la migración original de `eventos`, pero
  `UpdateEventosRequest` las marca `nullable` (el panel debe poder vaciarlas). `create()` ya
  coercía `null → ''`; a `update()` le faltaba la misma coerción. Bug preexistente, no
  relacionado con lo de arriba — encontrado al verificar el guardado de `tipo_evento_id`.

## 2026-08-01

### Changed
- **Corrección de alcance en la personalización de gafetes** (mismo día, tras revisión del
  usuario): los gafetes **no** usan el color de marca del evento (`eventos.color_hex`) —
  usan `form_types.color`, por registro. Un evento con varios tipos de formulario
  (Individual, Grupal, Voluntario...) ahora distingue cada uno con su propio color en la
  mesa de acreditación, en vez de que todos los gafetes salgan iguales. Los certificados
  **no cambiaron** — siguen usando `eventos.color_hex` + `imagen_portada_url`, sin tocar.
  Validación de color centralizada en `EventoController::safeHex()`, usada por ambos
  endpoints (antes la validaba cada blade por separado). Probado contra el evento 59 real:
  `form_types.color` ya tenía un valor real (`#00bad2`, sin necesidad de datos de prueba) y
  se ve aplicado en los 6 gafetes; certificados se probó por separado con un `color_hex` de
  evento temporal (`#7b2cbf`, revertido después) para confirmar que sigue siendo
  independiente del form_type.

### Added
- **Cuadro para foto en gafetes** (`tickets/gafetes.blade.php`): recuadro `4cm x 4cm`
  centrado entre el rol y el QR, con borde punteado y etiqueta "Foto 4x4cm" — placeholder
  para pegar una foto física antes de imprimir (sin subida digital, es puro layout de
  impresión). Usa unidades `cm` directas, que dompdf respeta contra el tamaño físico de
  página, sin necesidad de convertir a px. Probado contra el evento 59 real (6 gafetes) —
  el recuadro entra cómodo en la grilla de 3 columnas sin desbordar la tarjeta.
- **Certificados de asistencia/participación en bulk** (`GET /event/{event}/certificados-pdf`):
  extensión directa del patrón de `gafetesPdf` (mismo filtro de inscripciones no
  canceladas/fallidas, mismo `Pdf::loadView`), un certificado por página en A4 horizontal. Dos
  variantes según `form_types.requiere_categoria` de la inscripción:
  - `true` (carreras): **certificado de participación**, solo para participantes con un
    `Resultado` cargado y `estado = 'finisher'` (dns/dnf/dsq quedan afuera, mismo criterio que
    el ranking de equipo) — incluye categoría (resuelta por nombre, no el id crudo),
    tiempo oficial y posición general/categoría.
  - `false` (congresos, staff, voluntariado): **certificado de asistencia** simple, para
    cualquier inscripción pagada, sin depender de resultados — usa `participantes.categoria`
    como rol (mismo campo que ya usa `gafetesPdf`, ahí guarda el nombre del form_type cuando
    no hay categoría real).
  - Probado end-to-end contra datos reales: evento 59 (6 finishers reales, categorías 5K/10K
    resueltas correctamente, sin páginas en blanco) y evento 8 (con una inscripción/participante
    de prueba creada y borrada después, para validar la rama de asistencia — no había ninguna
    registrada todavía contra los form_types `Voluntario`/`Ayudante`/`Staff`).
- **Personalización por evento de gafetes y certificados** ("Nivel 1", ver
  `elascenso/event/brain/informe-backend-producto-30072026.md` §9): logo y color de marca ya
  no están hardcodeados en los dos templates.
  - **`eventos.color_hex`** (columna nueva, string(7) nullable): a diferencia de `color_id`
    (columna vieja sin tabla de colores detrás, siempre `1` — nunca funcionó de verdad),
    este campo se guarda tal cual lo manda el organizador (`colorHex` en `POST /event`,
    ver `StoreEventosRequest`/`EventoDTO`/`EventoService::create()`). Sin valor, cae al navy
    `#022858` que ya usaban ambos templates. **Validado con regex hex en el propio blade**
    antes de interpolarlo dentro de `<style>` — es texto del organizador, y sin ese chequeo
    un valor no-hex podría romper el CSS del documento completo.
  - **Logo**: reusa `imagen_portada_url` (ya era un campo real end-to-end, a diferencia de
    `logo_url` que también estaba hardcodeado — se decidió no abrir un campo nuevo para esto,
    ver informe §9 para el detalle de la decisión). dompdf tiene `enable_remote` en `false`
    por defecto (protección anti-SSRF razonable, no se tocó a nivel global) — una URL externa
    cruda en el `<img>` del PDF salía como ícono roto. Se resolvió con
    `EventoController::logoDataUri()`: descarga la imagen server-side (`Http::timeout(5)`) y
    la pasa a la vista como data URI base64, mismo patrón que `ReferenceQrService` ya usa
    para el QR. Best-effort — si falla (timeout, 404, no es imagen), el PDF se genera igual,
    sin logo.
  - Probado con un color (`#b3261e`) e imagen (`picsum.photos`) temporales en el evento 59
    real, contra ambos endpoints — confirmado que el logo se embebe y el color se aplica en
    borde/nombre/rol/stats; datos de prueba revertidos a `NULL` después.
  - **`EventoController::update()` sigue siendo un stub vacío** (gap ya documentado, ver
    `project_features_resultados_equipos_delivery` en memoria) — hoy `colorHex` solo se puede
    fijar al crear el evento, no editarlo después, mismo límite que el resto de los campos.

## 2026-07-31

Cinco requerimientos nuevos del organizador (`elascenso/event/brain/PRD-Resultados`):
numeración de corredor/chip, resultados de carrera en bulk, inscripción individual con
equipo, delivery de kits, y gafetes/credenciales imprimibles. Todo sobre el stack actual,
sin esperar la migración a Blade (ver `brain/PLAN-MIGRACION-LARAVEL-BLADE-30072026.md`
en `elascenso/event`, que sigue pausada).

### Added
- **Numeración de corredor/chip** (`participantes.numero_corredor`/`chip`): actualización
  manual (`PATCH /registrations/{reference}/participantes/{participante}/numeracion`) y
  masiva por documento (`POST /event/{event}/participantes/numeracion/bulk`, reporta
  `no_encontrados`).
- **Resultados de carrera** (tabla `resultados` nueva): `POST /event/{event}/resultados/bulk`,
  matchea por chip → número de corredor → número de documento (en ese orden), upsert
  idempotente, filas sin match se guardan igual con `participante_id` null y se reportan en
  `no_vinculados` para resolver a mano.
- **Inscripción individual con equipo** (`form_types.has_team`, tabla `equipos`,
  `participantes.equipo_id`): opt-in del participante (no automático), catálogo de equipos
  precargado por el organizador (`GET/POST /event/{event}/equipos`), validado server-side en
  `RegistrationService::validateEquipo()` (equipo obligatorio y debe pertenecer al evento).
- **Delivery de kits** (`form_types.has_delivery`, `participantes.quiere_delivery`/
  `estado_delivery`): opt-in igual que equipos, validado en
  `RegistrationService::validateDelivery()`. Dashboard sin login vía link firmado
  (`DeliveryController`, mismo patrón que `OrganizadorDashboardController`): HTML
  (`GET /organizador/evento/{evento}/delivery`), CSV, y **JSON de consumo**
  (`GET .../delivery.json`) para que la empresa de delivery integre esta info a su propio
  sistema — incluye un link firmado por participante para marcar
  pendiente→confirmado→entregado (responde JSON o redirect según `Accept`). Comando
  `delivery:generar-link {evento}` imprime los 3 links.
- **Resultados del participante logueado** (`GET /personas/me/resultados`, `auth:sanctum`):
  reusa el matching de `RegistrationController::mine()` (documento/correo). Por evento:
  resultado propio, comparativo dentro de la misma categoría, y si tiene equipo — compañeros
  + ranking agregado del equipo (suma de tiempos de finishers) contra los demás equipos del
  evento.
- **Gafetes/credenciales en bulk** (`GET /event/{event}/gafetes-pdf`): PDF con un gafete por
  participante inscrito (excluye cancelados/fallidos) — nombre, categoría/rol, QR de la
  referencia para check-in (reusa `ReferenceQrService`, mismo QR que el e-ticket). Grilla de
  3 columnas, pensado para imprimir y cortar.
- `FormTypeDTO`/`EventoService::createFormTypes()` ahora aceptan `hasTeam`/`hasDelivery` en
  la creación de evento en un solo request (`POST /event`) — antes solo se podían activar
  editando el form_type después de creado.

### Fixed
- Ninguno de los endpoints nuevos tiene protección de auth todavía (mismo criterio que
  `EventoController` hoy) — decisión explícita del usuario, pendiente hasta que exista un
  sistema de roles/permisos completo (ver `brain/informe-backend-producto-30072026.md` §5).

### Verified
- Los 3 endpoints de numeración/resultados probados contra datos reales del evento 58
  (bulk con filas sin match, upsert idempotente confirmado reenviando el mismo bulk).
- Equipos: los 3 casos de validación probados end-to-end (sin equipo → 422, equipo de otro
  evento → 422, equipo válido → 201).
- Delivery: los 3 casos de opt-in probados (form_type sin delivery → 422, con delivery → 201
  con `estadoDelivery: "pendiente"`, sin pedirlo → 201 con `estadoDelivery: null`); transición
  completa pendiente→confirmado→entregado vía link firmado; firma inválida → 403.
- `/personas/me/resultados` probado con un escenario real (2 equipos, 3 participantes,
  resultados de carrera) — comparativo por categoría y ranking de equipo correctos.
- Todos los datos de prueba usados para verificar (menos la demo deliberada de abajo) se
  revirtieron después de cada prueba.

### Demo (persistente, no revertida)
- Evento 59 "Maratón Corporativo Andes 2026": 3 equipos, 6 participantes con resultados
  cargados. Estado corregido a `closed` (fecha 2026-07-20) porque ya tiene resultados
  oficiales. Login de demo: `ana.flores.demo@example.net` / `Demo12345`.
- Evento 60 "Carrera Nocturna Valle 2026": 5 participantes, 4 con delivery en distintos
  estados (pendiente/confirmado/entregado) para mostrar variedad real en el dashboard.
- Evento 8 "Jornada de Voluntariado": se le agregaron 6 participantes de demo
  (Ponente/Staff/Asistente) para poder probar el PDF de gafetes.

### Added (continuación, mismo día)
- **`form_types.requiere_categoria` conectado** (existía en la BD desde el inicio del
  proyecto, default `true`, pero nunca se exponía en `FormTypeResource` ni se usaba en el
  frontend — la selección de categoría era obligatoria siempre, sin excepción). Ahora se
  expone como `requiereCategoria` en la API; el frontend oculta la sección de categoría y
  salta su validación cuando es `false`.
- 3 form_types nuevos en el evento 8 (`Voluntario`, `Ayudante`, `Staff`, ids 87/88/89):
  `requiere_categoria=false`, sin camiseta/equipo/delivery, precio 0 — inscripción de solo
  información personal para roles de congreso. Creados directo en BD (sin pasar por
  `FormTypeController::store()`, que sigue siendo un stub vacío — agregar form_types a un
  evento ya creado no tiene endpoint todavía, decisión explícita de no construirlo ahora).

### Verified
- Registro real bajo `form_types_id=87` (Voluntario) sin categoría seleccionada: 201, con
  `categoria: "Voluntario"` y `precioCategoria: 0` guardados correctamente. Dato de prueba
  revertido después.

### Added (continuación, mismo día — correo de dashboard al crear evento)
- Nuevo `App\Actions\EnviarDashboardOrganizadorAction`: genera el link firmado de
  `organizador.dashboard` (y el de `delivery.dashboard` si algún `form_type` del evento tiene
  `has_delivery`), reúne destinatarios (`organizador.email` + `User::whereIn('role',
  ['admin','superadmin'])`) y envía una instancia nueva del Mailable por destinatario —mismo
  patrón anti-acumulación de `RecordatorioDashboardOrganizador`—.
- Nuevo `App\Mail\EventoDashboardMail` + vista `emails/evento-dashboard.blade.php`: igual
  estilo visual que el recordatorio de 15 días, pero de propósito general (se usa tanto al
  crear el evento como en reenvíos a demanda); el botón de delivery solo aparece si
  `$deliveryUrl` no es null.
- `EventoController::store()` ahora dispara esa acción justo después de crear el evento y
  registra un `EventoNotification` (`tipo: evento_creado_dashboard_organizador`) para
  trazabilidad — no se usa como gate de reenvío, solo auditoría.
- Nuevo comando `notificaciones:enviar-dashboard-organizador {evento}` para reenviar el
  correo a demanda en cualquier momento (organizador perdió el correo, quiere volver a
  compartir el link, etc.), sin esperar el aviso automático de 15 días.

### Verified
- Evento de prueba creado vía API con un `form_type` con `hasDelivery: true`: quedó registrado
  el `EventoNotification` correcto; `EnviarDashboardOrganizadorAction` intentó enviar a 2
  destinatarios (organizador + 1 admin). El envío al organizador falló (dato preexistente:
  `organizadores.id=1` tiene el email mal cargado como `gerdclarosgmail.com`, sin `@` — no se
  corrigió, es un problema de datos preexistente fuera de alcance). El envío al admin
  (`gerdclaros@gmail.com`) sí se procesó contra el SMTP real configurado en `.env` local —
  **ojo**: este entorno de desarrollo local manda correos reales de verdad, no hay
  sandbox/log driver, así que cualquier prueba futura que dispare estos flujos entrega un
  correo real a una bandeja real. Evento y datos de prueba borrados después.
- Comando `notificaciones:enviar-dashboard-organizador` verificado registrado en
  `php artisan list` (no se ejecutó de nuevo contra datos reales para evitar otro envío real).

### Fixed
- `organizadores.id=1` (el organizador default usado cuando no se manda `organizador_id`)
  tenía el email mal cargado (`gerdclarosgmail.com`, sin `@`) — corregido a
  `gerdclaros@gmail.com` en la base local y en UAT.

### Added (continuación, mismo día — Landing de Equipo/Club)
- Plan completo en `elascenso/event/brain/PLAN-CLUBES-31072026.md`. Nueva entidad
  `Club` (catálogo global, alta directo en BD por ahora, sin endpoint) independiente
  del `equipo` por-evento que ya existía — `equipos.club_id` (nullable) vincula un
  equipo de un evento puntual a un club persistente que participa en varios eventos.
- `App\Models\Club` con Sanctum (`HasApiTokens`), guard/provider `clubes` en
  `config/auth.php` (mismo patrón que `personas`).
- `ClubController`: `POST /club/login`, `POST /club/logout`, `GET /club/me`,
  `GET /club/me/landing` (login propio, sin depender de un link firmado).
- `EquipoController::store()` vincula automáticamente un `equipo` a un `club`
  existente si el nombre coincide (case-insensitive) — no crea clubes nuevos desde
  ahí, solo los vincula si ya existen en el catálogo.
- Extraída `App\Support\RankingEquipos` (antes vivía inline en
  `ResultadoController::resultadosEquipo()`) para reusar el cálculo de ranking de
  equipo tanto en "Mis Resultados" como en la landing del club.

### Verified
- Club de prueba vinculado por nombre al equipo ya existente del evento 59: login,
  landing (evento 59, ranking 2/3, 2 integrantes con tiempos) y logout, todo
  correcto.
- `personas/me/resultados` re-verificado después del refactor de `RankingEquipos`:
  mismo output exacto que antes del cambio.

### Pending
- Frontend de login/landing del club en `elascenso/event` — no implementado
  todavía.

### Added (continuación, mismo día — evento nace como borrador, publicar explícito)
- Un equipo externo va a desarrollar un frontend nuevo para administrar/crear eventos.
  Pregunta que surgió: ¿cómo se entera `ApiRestEvent` de cuándo activar el envío de los
  links privados (dashboard de organizador/delivery)? Se decidió que el disparador no sea
  la creación del evento sino una acción explícita de "publicar": **un evento creado nace
  como borrador** (`publicado` ahora defaultea a `false` en `EventoDTO::fromArray()`, antes
  era `true`) — representa el contrato entre el organizador y nosotros antes de firmarse;
  recién es elegible para publicarse una vez confirmado.
- `EventoController::store()` ya **no** dispara `EnviarDashboardOrganizadorAction` — se
  movió a un endpoint nuevo `PATCH /event/{event}/publicar`
  (`EventoController::publicar()`): pasa `publicado` de `false` a `true`, dispara el correo
  del dashboard, y registra `EventoNotification` (`tipo:
  evento_publicado_dashboard_organizador` — renombrado desde
  `evento_creado_dashboard_organizador` para reflejar el disparador correcto). Idempotente:
  si el evento ya está publicado, devuelve 422 sin reenviar el correo.

### Fixed
- Bug propio detectado en pruebas: el parámetro del nuevo método `publicar()` se llamaba
  `$evento` pero la ruta usa `{event}` — el resto de `EventoController` usa siempre
  `$event`, así que el route-model-binding implícito de Laravel no lo resolvía (nombres no
  coinciden) y llegaba `null`, rompiendo `EnviarDashboardOrganizadorAction::handle()`.
  Corregido a `$event` para que coincida con el resto del archivo.

### Verified
- Evento de prueba creado (ya sin body `publicado`): confirmado `publicado: false` y sin
  intento de envío de correo ni `EventoNotification`. `PATCH .../publicar`: primera vez 200
  (correo intentado, `EventoNotification` creada), segunda vez 422 ("Este evento ya está
  publicado."). Datos de prueba borrados después.

### Added (continuación, mismo día — cuadro comparativo de progreso entre eventos)
- Pedido a partir de la landing de club: si un participante corrió la misma categoría/
  distancia en más de un evento, ver si mejoró o no. Nuevo `App\Support\ProgresoHistorico`
  (`paraIdentidad(numeroDocumento, correo, categoria)`): busca todas las participaciones de
  esa misma persona (matcheada por documento o correo, igual criterio que `mios()`) en la
  **misma categoría exacta** — comparar 10K contra 5K no tiene sentido, así que distancias
  distintas no se cruzan — ordenadas cronológicamente por `evento.fecha_inicio`, marcando
  `mejora` (`true`/`false`/`null` en la primera) y `diferenciaSegundos` contra la
  participación anterior.
- Expuesto como `progreso` en `GET /personas/me/resultados` (`ResultadoController::mios()`)
  y en `GET /club/me/landing` (`ClubController::landing()`, por integrante) — mismo cálculo
  reusado en los dos endpoints.

### Verified
- Escenario real: evento de prueba nuevo (10K, fecha posterior al evento 59 demo),
  registrando a Ana Flores con un tiempo más rápido (00:40:00 vs su 00:42:15 del evento 59,
  misma categoría 10K). `progreso` en ambos endpoints devolvió las 2 participaciones en
  orden cronológico con `mejora: true` y `diferenciaSegundos: 135` en la segunda. Bruno
  Rojas (sin segunda carrera) siguió con un solo elemento y `mejora: null`, correcto. Todos
  los datos de prueba (evento, registro, participante, resultado) borrados después.
- Efecto secundario encontrado (no es un bug de esta sesión, comportamiento preexistente):
  `RegistrationService::syncPersonas()` regenera la contraseña de una `Persona` ya existente
  cada vez que se procesa una inscripción para su mismo documento/correo — el password de
  prueba de Ana tuvo que resetearse a mano después de re-registrarla para esta prueba.

## 2026-07-28

Diagnóstico del recordatorio de pago a 30 días (confirmado funcionando en la corrida de
medianoche), bug nuevo encontrado en esa misma corrida, correos 100% en español, y restauración
del QR de referencia en los correos de inscripción.

### Fixed
- **`notificaciones:revertir-cupo` crasheaba** con `TypeError: Carbon::rawAddUnit(): Argument #3
  ($value) must be of type int|float, string given` en `RevertirCupo.php:32`
  (`$notificacion->enviado_at->copy()->addDays($diasGracia)`). Causa: `Organizador::$casts` no
  casteaba los campos `unsignedSmallInteger` de configuración de notificaciones
  (`dias_gracia_reversion`, `dias_recordatorio_pendiente_1`/`_2`, `dias_recordatorio_kit`,
  `dia_envio_marketing`) — MySQL/PDO los devolvía como string y Carbon 3 es estricto en
  `addDays()`. Se agregaron los 5 campos como `'integer'` en `app/Models/Organizador.php`. Esto
  también corrige un bug silencioso paralelo en `MarketingMensual.php:66`
  (`now()->day !== $diaEnvio`, comparación estricta que con `$diaEnvio` string nunca sería `true`).
- **Correos hardcodeados en inglés**: pese a que el frontend (`elascenso/event`) soporta ES/EN/PT
  (default ES) y el negocio es 100% boliviano, los 6 `Mailable` (`PagoConfirmadoMail`,
  `InscripcionPendienteMail`, `RecordatorioPagoMail`, `RecordatorioKitMail`, `CupoRevertidoMail`,
  `MarketingEventoMail`) y sus vistas Blade (`emails/*.blade.php`, `tickets/eticket.blade.php`,
  `emails/partials/*`) tenían subjects, labels y fechas (`Carbon::format('F j, Y')`) fijos en
  inglés. Traducidos íntegramente a español, incluyendo formato de fecha localizado
  (`->locale('es')->translatedFormat('d \d\e F \d\e Y')`).
- **QR de referencia perdido en los correos de inscripción**: encontrado el commit exacto donde se
  perdió (`elascenso/event@da60e7e "Notificaciones de Correo"`, que borró `event/api/email.php` al
  migrar el envío de correos a este backend — ese archivo era el único que armaba el QR, agregado
  originalmente en `elascenso/event@43f6691 "incluir QR al pdf-email"`). Restaurado del lado del
  backend: nuevo `App\Services\ReferenceQrService::toBase64Png()` genera el mismo QR que codifica
  solo la referencia (p.ej. `LA-29B51728`, para check-in) que antes se generaba en el navegador.
  Incluido en `emails/confirmacion.blade.php` (compartida por `PagoConfirmadoMail` e
  `InscripcionPendienteMail`, cubre pago pendiente y confirmado) y en `tickets/eticket.blade.php`
  (PDF adjunto de la entrada, que no lo tenía ni antes de la migración).

### Added
- `composer require chillerlan/php-qrcode` — elegido sobre `simplesoftwareio/simple-qrcode` porque
  su salida PNG requiere Imagick (no instalado en este entorno ni típicamente disponible en
  hosting compartido cPanel), mientras que `chillerlan/php-qrcode` genera PNG vía GD (confirmado
  disponible tanto local como en el hosting de UAT).
- `App\Services\ReferenceQrService` (`toBase64Png(string $referencia, int $size = 200): string`).

### Verified
- `ReferenceQrService` probado de punta a punta: el PNG generado decodifica de vuelta a la
  referencia exacta (`(new QRCode())->readFromFile(...)` contra su propio output).
- Los 5 `Mailable` renderizados (`->render()`) contra un registro real sin excepciones;
  `PagoConfirmadoMail` genera además el PDF (`Pdf::loadView('tickets.eticket', ...)`) sin errores,
  con el QR embebido correctamente (verificado visualmente, PDF y HTML).
- `grep` de las cadenas en inglés conocidas (`Reference Number`, `Payment Method`, `Grand Total`,
  etc.) sobre todos los templates tocados — sin coincidencias reales.

### Pending
- Desplegar `app/Models/Organizador.php` (fix de casts) y `app/Services/ReferenceQrService.php` +
  `composer install` (nueva dependencia `chillerlan/php-qrcode`) a UAT. La corrida de
  `notificaciones:revertir-cupo` que falló en UAT no canceló las inscripciones vencidas
  correspondientes esa noche — considerar dispararla a mano una vez desplegado el fix.

## 2026-07-23

Exposición de `deslinde` y nuevo `deslinde_pdf_url` del evento, para el checkbox de deslinde de
responsabilidad agregado en `elascenso/event` (rama `updateticket`) antes de confirmar el pago;
nuevo recurso `auspiciadores` (sponsors) para el carrusel de logos que se agregó en ese mismo
repo; comando `demo:reset` para limpiar datos de demo; fix de un bug preexistente en
`participantes.categoria`; y códigos de promoción de un solo uso. Ver también el `CHANGELOG.md`
de `elascenso/event` para la mitad de estos cambios que vive en ese repo.

### Added
- Migración `2026_07_23_000000_add_deslinde_pdf_url_to_eventos_table`: columna
  `deslinde_pdf_url` (`varchar(500)`, nullable) en `eventos` — mismo patrón que
  `logo_url`/`gpx_url`/`icono_url` (URL que aloja el organizador; esta API no sube archivos).
- **Recurso `auspiciadores`** (uno-a-muchos con `eventos`, mismo patrón que `categories`/
  `promoCodes`): migración `2026_07_23_010000_create_auspiciadores_table` (`nombre`, `logo_url`,
  `contacto` nullable — un solo campo de link genérico, sin lógica de tipo del lado de la API —,
  `orden` nullable), modelo `Auspiciador` (relación `Evento::auspiciadores()` ordenada por
  `orden`), `AuspiciadorDTO`, `AuspiciadorResource`, y soporte en `EventoService::create()` /
  `EventoDTO` / `StoreEventosRequest` (`auspiciadores.*.nombre`/`logo_url`/`contacto`/`orden`).
- **`php artisan demo:reset`** (`app/Console/Commands/ResetDemoData.php`, nuevo, reutilizable):
  trunca eventos/inscripciones y todo lo que cuelga de ellos (`answers`, `questions`,
  `participantes`, `registrations`, `categories`, `auspiciadores`, `coordinates`, `routes`,
  `eventos`, etc.) para preparar una demo, sin tocar `personas`, `contactos_emergencia`,
  catálogos, ni tablas de infraestructura de Laravel. Pide confirmación salvo `--force`.
  Deliberadamente no usa `migrate:fresh`/`db:seed` (recrean/duplican datos fijos de los seeders).
  Detalle completo en `elascenso/event/brain/demo-reset-seed-datos-23072026.md`.
- **Códigos de promoción de un solo uso**: migración
  `2026_07_23_030000_add_usado_and_registration_id_to_promo_codes_table` (`usado` boolean,
  `registration_id` nullable FK a `registrations`). `RegistrationService::consumePromoCode()`
  (con `lockForUpdate()` dentro de la transacción existente — evita que dos inscripciones
  simultáneas con el mismo código pasen ambas la validación) y `releasePromoCodes()`, enganchados
  en `createParticipant()`/`createParticipantFromData()` — cubre alta nueva, edición de pendiente
  y edición de pagada con dos únicos puntos de enganche. `PromoCodeController::promoCode()`
  (`GET /promo/{event}/code/{code}`) ahora rechaza un código ya `usado` para dar feedback
  inmediato al frontend. Detalle completo en
  `elascenso/event/brain/promo-codes-un-solo-uso-23072026.md`.

### Changed
- `deslinde` (columna ya existente en `eventos`, `varchar(500)`) estaba comentada en
  `EventoResource` y `EventoService::create()` siempre la guardaba como `''` — nunca se pudo leer
  ni escribir desde fuera de la API. Ahora se expone en `GET /event`/`GET /event/{id}` y se acepta
  como campo de entrada opcional en `POST /api/v1/event` (`StoreEventosRequest`, `EventoDTO`),
  mismo patrón que `video`/`image`.
- `EventoResource`: agregado `deslinde_pdf_url` a la respuesta.
- `Evento::$fillable`: agregado `deslinde_pdf_url` (`deslinde` ya estaba).
- `EventoResource`: agregado `auspiciadores` (colección de `AuspiciadorResource`) a la respuesta.
- `EventoController@index`/`@show`: agregado `auspiciadores` a las listas de relaciones a
  cargar (`->with(...)`/`loadMissing([...])`) — estas dos, junto con
  `EventoService::loadRelations()` (usada solo en `store()`), son **tres puntos separados** donde
  hay que declarar cada relación nueva del evento; un recurso agregado solo en uno de los tres
  queda visible en `POST /api/v1/event` pero ausente en `GET /event`/`GET /event/{id}` (o
  viceversa) sin ningún error — se detectó así en la verificación de abajo.
- **Fix**: `participantes.categoria` estaba tipada `unsignedBigInteger` (pensada como FK a
  `categories.id`, nunca implementada así) pero siempre se le guardaba el **nombre** de la
  categoría (`"5K"`, `"General"`, `"VIP"`...); `ParticipantDTO::fromArray()` le hacía `(int)` a
  ese string, truncándolo silenciosamente (`"5K"` → `5`, `"General"` → `0`) — sin ningún error de
  validación. Migración `2026_07_23_020000_change_categoria_to_string_on_participantes_table`
  (`ALTER TABLE ... MODIFY categoria VARCHAR(255)`, con `DB::statement()` crudo porque
  `doctrine/dbal` no está instalado — mismo criterio que
  `2026_07_20_182731_fix_max_integrantes_grupo...`) + `ParticipantDTO`: propiedad `int $category`
  → `string $category`, sin el cast forzado. Encontrado al sembrar datos de demo para
  `elascenso/event`, no relacionado con `auspiciadores`/`deslinde`.
- **Fix**: `RegistrationController::store()`/`update()`/`updatePaid()` no capturaban
  `\DomainException` (solo `lookup()` lo hacía). El único motivo por el que
  `registro_actualizar.php` (`elascenso/event`) lograba mostrar el mensaje real de una
  `DomainException` era que `APP_DEBUG=true` en este entorno (Laravel expone `exception`/
  `message` crudos en modo debug) — en producción (`APP_DEBUG=false`) esto se rompía
  silenciosamente y el usuario solo veía un 502 genérico. Ahora los tres capturan
  `\DomainException` y devuelven `{success:false, error: "..."}` con `422`, mismo patrón que ya
  usaba `lookup()` — determinístico independientemente de `APP_DEBUG`. Encontrado al implementar
  el enforcement de códigos de promoción de un solo uso, del que dependía.

### Verified
- Probado en vivo con `php artisan serve` + `curl`: un evento creado vía `POST /api/v1/event` con
  `deslinde`/`deslinde_pdf_url` los devuelve correctamente tanto en la respuesta del POST como en
  `GET /event/{id}` posterior.
- `auspiciadores`: un evento creado con 3 auspiciadores fuera de orden (`orden` 2, 1, 3) los
  devuelve ordenados correctamente en el `POST`; el primer intento de `GET /event/{id}` los
  devolvía vacíos por el problema de relaciones no cargadas en `@show` descrito arriba —
  corregido y reverificado. Encontrado y corregido además un bug de nomenclatura: Eloquent
  pluralizaba `Auspiciador` → `auspiciadors` (reglas de inglés) en vez de `auspiciadores`,
  rompiendo el insert (`Table 'auspiciadors' doesn't exist`) hasta declarar `protected $table =
  'auspiciadores'` explícito en el modelo (mismo fix que ya tenía `Evento`).
- Pipeline completo de datos de demo corrido contra la BD real: `demo:reset` dejó `personas`
  intacta (110 antes/después del truncate) mientras `eventos`/`registrations` quedaron en 0;
  luego se cargaron 50 eventos + 40 inscripciones (20 paid, 20 pending) desde `elascenso/event`,
  con `personas` subiendo a 152 (por el registro de cada participante) sin ninguna baja. El fix
  de `categoria` se verificó con una inscripción de prueba antes/después (`"5"` → `"5K"`).
- Códigos de promoción de un solo uso probados en vivo: un código Gold (50%) usado en una
  inscripción queda `usado:true`; `GET /promo/{event}/code/...` y una segunda inscripción con el
  mismo código son rechazados con `422`/"ya fue utilizado"; editar la primera inscripción
  manteniendo el código no se rechaza a sí misma; cambiarlo a otro libera el viejo
  (`usado:false`) y consume el nuevo; dos `POST /registrations` simultáneos con el mismo código
  liberado — uno `201`, el otro `422` — confirma que `lockForUpdate()` evita la condición de
  carrera.
- Dataset de demo de `promo_codes` actualizado en vivo: se borraron los 18 códigos random
  sembrados por la corrida original de `generate_eventos_seed.js` (`elascenso/event`) y se
  cargaron 10 códigos fijos Gold/Plata/Cobre en el evento 1 (todos `usado:false`). De paso se
  limpiaron dos eventos de prueba ("Prueba Promo Unico Uso", ids 51/53) y sus 2 inscripciones de
  prueba que habían quedado de la verificación de la feature de un solo uso — no eran parte del
  dataset limpio de 50 eventos / 40 inscripciones. Detalle en
  `elascenso/event/brain/demo-reset-seed-datos-23072026.md` sección 6.

### Fixed
- **Incidente**: la BD apareció completamente vacía, incluida `personas` (tabla que ningún flujo
  de este proyecto vacía intencionalmente — `demo:reset` la excluye explícitamente). Al intentar
  restaurar con `php artisan db:seed` falló `PaisSeeder` con
  `Call to undefined function Database\Factories\fake()`: el `vendor/` de Composer había quedado
  incompleto, faltaba `fakerphp/faker` (declarado en `composer.json`/`composer.lock`, requerido
  por el helper `fake()` de Laravel que usan todos los seeders/factories). `composer install`
  restauró el paquete; `php artisan db:seed --force` restauró catálogos + 100 eventos + 100
  personas base; el pipeline de `brain/` de `elascenso/event` restauró los 50 eventos demo + 40
  inscripciones encima. Detalle completo (incluyendo el cambio de id del evento insignia de
  Gold/Plata/Cobre, de 1 a 151) en
  `elascenso/event/brain/demo-reset-seed-datos-23072026.md` sección 7.

## 2026-07-23 (Multipago)

Soporte de persistencia para la integración de Multipago (gateway de pago con iframe) en
`elascenso/event` — ver su `CHANGELOG.md` para el resto de la integración (creación de la orden,
pantalla de iframe, polling, callback), que vive del lado del frontend/API local.

### Added
- Migración `2026_07_23_040000_add_pay_order_number_to_registrations_table`: columna
  `registrations.pay_order_number` (`string`, nullable).
- `Registration::$fillable`: agregado `pay_order_number`.
- `RegistrationDTO`: nueva propiedad `?string $payOrderNumber`, parseada de
  `$data['pay_order_number'] ?? null`. `RegistrationService::create()` la persiste.
- `StoreRegistrationRequest`: regla `'*.pay_order_number' => ['nullable','string']` — sin esto,
  `RegistrationController::store()` (que usa `$request->validated()`, no `all()`) lo hubiera
  descartado en silencio antes de llegar al DTO.
- `RegistrationResource`: expone `pay_order_number` — así `elascenso/event` lo recibe al consultar
  `GET /registrations/{reference}` (usado por su `pago_status.php` para el polling).
- **`GET /registrations/by-pay-order/{payOrderNumber}`** (`RegistrationController::findByPayOrder()`,
  sin `auth:sanctum`, mismo criterio que `show()`): búsqueda inversa referencia↔pay_order_number,
  para que el callback de Multipago (que solo recibe `pay_order_number`, nunca la `referencia`
  interna) pueda encontrar a qué inscripción corresponde. Registrada **antes** de la ruta comodín
  `/registrations/{reference}` en `routes/api.php` (si no, Laravel matchea `by-pay-order` como si
  fuera un `{reference}`).

### Fixed
- A diferencia de SIP (donde el `alias` que se le pasa a la pasarela es la misma `referencia`, así
  que nunca hizo falta persistir nada), Multipago genera su propio `pay_order_number` sin relación
  con la `referencia` — y de paso se confirmó que `qr_id` (que `elascenso/event` ya le manda a esta
  API para SIP) nunca se persistía: no estaba en `Registration::$fillable`, se descartaba en
  silencio. SIP nunca lo necesitó porque el matching es `alias === referencia`; Multipago sí lo
  necesitaba, de ahí esta migración.

### Verified
- `php artisan route:clear` requerido tras agregar la ruta nueva — las rutas están cacheadas en
  este proyecto (`bootstrap/cache/routes-v7.php`), así que un `php artisan serve` ya corriendo no
  ve rutas agregadas después de arrancar sin limpiar el caché.
- `GET /registrations/{reference}` confirmado incluyendo `pay_order_number` (`null` por defecto);
  `PATCH` de prueba seguido de `GET /registrations/by-pay-order/{valor}` devuelve la `referencia`
  correcta.
- Smoke test end-to-end vía `api/registro.php` de `elascenso/event` con `tipoPago=multipago`
  (evento 151): la validación completa pasa, `RegistrationDTO`/`StoreRegistrationRequest` aceptan
  el nuevo campo sin descartarlo — el registro no llega a crearse porque Multipago devuelve error
  con las credenciales placeholder de esta cuenta (`multipago-payment-integration/.env`), lo cual
  es el comportamiento esperado y no un problema de esta persistencia.

## 2026-07-21

### Fixed
- **Migración `eventos`**: `tipo_evento_id`, `subtipo_evento_id` y `pais_id`
  estaban tipados como `INT UNSIGNED` mientras que las PK referenciadas
  (`tipos_evento.id`, `subtipos_evento.id`, `paises.id`) son
  `TINYINT UNSIGNED` / `SMALLINT UNSIGNED`. MySQL exige tipos idénticos en
  ambos lados de una FK, lo que causaba
  `SQLSTATE[HY000]: ... errno: 150 "Foreign key constraint is incorrectly
  formed"` al correr `2026_07_20_200005_add_foreign_keys_to_eventos_table`.
  Corregido en `database/migrations/2026_06_28_214848_create_eventos_table.php`.
- **CORS abierto a cualquier origen**: el proyecto nunca sobrescribió
  `config/cors.php`, así que usaba el default de Laravel
  (`allowed_origins => ['*']`). Se creó `config/cors.php` restringiendo el
  acceso a los orígenes reales que consumen la API:
  `https://events.inscrito.net` (frontend de producción) y
  `http://localhost` (frontend en XAMPP durante desarrollo local).
- **Entorno local**: `fakerphp/faker` (dependencia de `require-dev`) no
  estaba instalado en `vendor/` pese a figurar en `composer.lock` —
  síntoma de un `composer install --no-dev` previo — lo que rompía
  `fake()` al correr factories/seeders. Resuelto con `composer install`
  completo.

### Added
- Factories para las tablas de catálogo nuevas: `PaisFactory`,
  `CiudadFactory` (ciudad ligada a un país existente),
  `TipoEventoFactory`, `SubtipoEventoFactory` (subtipo ligado a un tipo
  existente), `OrganizadorFactory` (país/ciudad coherentes).
- Seeders correspondientes: `PaisSeeder` (10 países), `CiudadSeeder`
  (2–5 ciudades por país), `TipoEventoSeeder` y `SubtipoEventoSeeder`
  (catálogo curado: Carrera de Ruta, Trail Running, Ciclismo, Caminata,
  Triatlón, Natación, con sus subtipos reales), `OrganizadorSeeder`
  (8 organizadores). Conectados en `DatabaseSeeder` respetando el orden de
  dependencias FK: Países → Ciudades → Tipos → Subtipos → Organizadores →
  Eventos → Personas.
- `DEPLOY_CPANEL.md`: guía completa para desplegar el proyecto en hosting
  compartido con cPanel (document root, subida de archivos, base de datos,
  migraciones, permisos, cron para colas, checklist final), incluyendo una
  sección para cuentas que solo tienen File Manager (sin SSH/Terminal):
  cómo corregir credenciales de BD ante
  `Access denied for user 'root'@'localhost'` y cómo crear las tablas vía
  export/import de phpMyAdmin en vez de `artisan migrate`.
- `.env.production`: plantilla basada en el `.env` de desarrollo, con
  `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error`, `APP_KEY`
  nueva, placeholders para credenciales de BD de cPanel, y avisos sobre
  `OPENWA_BASE_URL`/`QR_BASE_*_URL` (apuntan a `localhost`, deben
  reemplazarse por URLs públicas reales antes de publicar) y sobre
  `MAIL_MAILER=log` (no envía correos reales).

### Changed
- `EventoFactory`: antes creaba un organizador/tipo/subtipo/país/ciudad
  **nuevo** por cada evento generado (`Model::factory()` inline), lo cual
  iba a chocar contra el límite de `TINYINT UNSIGNED` (255 filas) en
  `tipos_evento`/`subtipos_evento` al escalar. Ahora reutiliza filas
  existentes del catálogo (`inRandomOrder()->value('id')`, con fallback a
  crear una si la tabla está vacía) y garantiza coherencia
  ciudad↔país y subtipo↔tipo.

### Verified
- Smoke test de solo lectura en producción
  (`https://events.inscrito.net` / `https://api.inscrito.net`): frontend
  200 OK, `GET /api/v1/event` responde `success:true` con datos reales,
  SSL válido en ambos dominios, `.env` no accesible públicamente (404).

## 2026-07-20

Feature de tope/descuento de grupo para inscripciones (`elascenso/event`
rama `updateticket`), consolidada con soporte de promo codes por
porcentaje y una auditoría de la carga bulk. Ver también el
`CHANGELOG.md` de `elascenso/event` para la mitad de estos cambios que
vive en ese repo.

### Added
- `GET /api/v1/persona/me` (`auth:sanctum`): devuelve la persona
  autenticada por el Bearer token; usado por `elascenso/event` para
  validar sesión antes de confirmar una inscripción nueva. Registrado
  *antes* de `apiResource('/persona', ...)` para que Laravel no
  interprete `"me"` como `{persona}` (gotcha clásico de rutas literales
  vs. wildcards del mismo verbo).
- `GET /api/v1/registrations/mine` (`auth:sanctum`, mismo gotcha de
  routing): devuelve todas las inscripciones de la persona autenticada
  (match por `numero_documento`/`correo`), filtro opcional
  `?pago_status=`.
- Columna `descuento_registrante` en `registration_totals` — persistencia
  del descuento de grupo (`TotalsDTO`, `RegistrationService`,
  `TotalsResource`).
- Columna `descuento_registrante_pct` en `form_types` (`decimal(4,2)`,
  default `0.10`) — el descuento de grupo pasa de constante global a
  configurarse por `form_type`, igual que categorías/souvenirs/promoCodes.
- `promo_codes` gana `discount_type` (`fixed_price`|`percentage`, default
  `fixed_price`, no toca datos existentes) y `discount_percent` — soporte
  de porcentaje como alternativa al precio fijo. `PromoCodeFactory` gana
  el estado `->percentage($pct)`.
- `StoreEventosRequest`/`FormTypeDTO`/`EventoService`: reglas y
  propagación para `formTypes.*.permite_inscripcion_grupal`,
  `.max_integrantes_grupo`, `.descuento_registrante_pct` en la carga bulk
  vía HTTP — antes se descartaban silenciosamente aunque vinieran en el
  JSON. `promoCodes.*.price` pasa de `required_with` a `nullable` (un
  código `percentage` no necesita precio).

### Fixed
- **`form_types.max_integrantes_grupo` estaba tipado `boolean`** en la
  migración original pese a representar el número máximo de integrantes.
  Corregido a `INT UNSIGNED DEFAULT 10` vía SQL crudo (`Schema::table()
  ->change()` habría requerido `doctrine/dbal`, no instalado — se evitó
  la dependencia nueva). Se normalizaron los ~300 `form_types` ya
  sembrados que habían quedado en 0/1.
- `RegistrationTotal::$fillable` no incluía `descuento_registrante` →
  Eloquent lo descartaba en el mass-assignment del `create()`; el
  descuento se calculaba pero nunca se guardaba.
- `UpdateRegistrationRequest`/`UpdatePaidRegistrationRequest` no
  validaban `totales.descuento_registrante` (validación campo por campo,
  no por bloque) → el descuento se perdía específicamente al editar una
  inscripción pendiente/pagada (sí sobrevivía en el alta nueva).
- `FormTypeResource` no exponía `permite_inscripcion_grupal`/
  `max_integrantes_grupo`/`descuento_registrante_pct` — el frontend no
  tenía forma de leerlos aunque existieran en la BD.
- `PromoCodeDTO::fromArray()` forzaba `(float) ($data['price'] ?? 0)`,
  así que un promo code `percentage` (sin precio) quedaba con
  `price: "0.00"` en vez de `null`. Corregido para preservar `null`
  cuando no viene en el payload.
- `RegistrationResource` no exponía `form_types_id`, necesario para que
  "saltar directo" a una inscripción pendiente desde
  `/registrations/mine` supiera qué `selectedFormType` usar.
- `PersonaResource`: se agregó `id` (faltaba — sin él no había clave
  estable para atribuir el descuento a una cuenta) y se quitó `password`
  (devolvía el hash bcrypt en texto plano en `/persona/me` y
  `/persona/login`, sin uso real).
- **`fake()` roto de nuevo** (mismo síntoma que la entrada del
  2026-07-21 de arriba: `fakerphp/faker` en `require-dev` pero no
  instalado en `vendor/`) — encontrado en una auditoría aparte de la
  carga bulk vía Eloquent (`EventoSeeder` no podía crear un solo
  evento). Mismo fix: `composer install` completo (sin cambios en
  `composer.lock`).
- `ApiRestEvent/brain/eventos_seed_50.json` (copia paralela de los
  scripts de `elascenso/event/brain/`) estaba desactualizado — no tenía
  la regeneración con los campos de descuento de grupo/porcentaje.
  Sincronizado copiando el archivo actualizado.

### Security
- Rutas completamente abiertas (sin middleware) protegidas con
  `auth:sanctum`: `GET/PUT/PATCH/DELETE /persona/{id}`, `GET /persona`,
  `POST /persona` (store, sin uso real), `GET /registrations`,
  `POST /registrations`, `DELETE /registrations/{ref}`.
  **Deliberadamente sin tocar**: `PUT /registrations/{ref}` y
  `PATCH .../update-paid` (flujo de edición identificado por credenciales
  en el lookup, no por token — protegerlas rompería ese flujo sin un
  rediseño mayor), la maquinaria de QR servidor-a-servidor, y los
  endpoints que emiten el token (`login`, `register`, `logout`,
  `lookup`), que deben seguir públicos.
- Hallazgo pendiente (no corregido): las rutas ahora protegidas devuelven
  `500` (`Route [login] not defined`) en vez de `401` si el cliente no
  manda `Accept: application/json` (`Authenticate::redirectTo()` intenta
  armar una URL de login inexistente en esta API). No afecta a los
  llamadores reales actuales, pero vale la pena endurecerlo si se expone
  esta API a terceros.

### Verified
- 9/10/11 participantes → sin descuento / descuento de grupo aplicado y
  persistido / rechazado con 422.
- Rutas endurecidas → 401 sin token, 200 con token válido; endpoints
  públicos (login/register/logout/lookup) siguen accesibles;
  `/persona/me` ya no devuelve `password`.
- Carga bulk vía HTTP (`POST /api/v1/event`) con los campos nuevos de
  grupo y de promo `percentage` devuelve exactamente los valores
  enviados.
- `GET /registrations/mine` con y sin token pendiente de pago.
- Recreación manual de la cadena `EventoSeeder` (Evento + FormTypes +
  Categories + PromoCode vía factories) tras el fix de `fake()`, sin
  correr los 100 eventos completos.
