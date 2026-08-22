# Informe: consolidación de admin-eventos + elascenso-blade dentro de ApiRestEvent

**Fecha:** 21–22/08/2026
**Rama:** `consolidacion-monolito` (worktree `ApiRestEvent-monolito`, mismo repo que `ApiRestEvent`)
**Estado:** Fase 1 y Fase 2 implementadas, verificadas y commiteadas. **Nada desplegado todavía.**

---

## 1. Objetivo

Eliminar el salto de red proxy → proxy entre `admin-eventos` / `elascenso-blade` y `ApiRestEvent`,
fusionando los tres en un solo monolito Laravel. La motivación explícita **no fue simplicidad de
deploy** (aunque es un beneficio real dado que el usuario no tiene SSH, solo File Manager de cPanel)
sino eliminar una clase entera de bugs de proxy — por ejemplo el problema de headers no reenviados
en hosting con `mod_lsapi` (ver `project_uat_mod_lsapi_auth_bug`).

**Alcance acordado**: solo `admin-eventos` y `elascenso-blade` se fusionan dentro de `ApiRestEvent`.
`elascenso/event` (el PHP monolítico viejo, hoy en producción) queda **deliberadamente afuera** —
sigue pegándole a `/api/v1/*` externa sin cambios.

**Regla dura del plan**: mover el archivo de controller sin más no alcanza. Cada uno tiene que dejar
de llamar `ApiRestEventClient::forward()` (HTTP real) y pasar a invocar directo el Service/Action que
ya existe del lado API — eso es lo que de verdad elimina el salto de red.

Plan completo original: `brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md`.

---

## 2. Arquitectura resultante

```
ApiRestEvent (monolito)
├── App\Http\Controllers\*            ← API pública /api/v1/* (sin cambios, fuente de verdad)
├── App\Http\Controllers\Admin\*      ← ex admin-eventos   (Fase 1, rutas /admin/*)
├── App\Http\Controllers\Inscripcion\*← ex elascenso-blade (Fase 2, rutas /, /eventos, /registro, ...)
├── routes/api.php                    ← sin tocar
├── routes/admin.php                  ← nuevo (Fase 1)
└── routes/inscripcion.php            ← nuevo (Fase 2)
```

Los tres bloques comparten la misma base de datos (una sola, sin proxy) y el mismo `vendor/`. Los
repos `elascenso/admin-eventos` y `elascenso-blade` no se borraron — quedan congelados/de respaldo
hasta que el monolito esté verificado en UAT.

---

## 3. Fase 1 — `admin-eventos` (panel de administración)

**43 controllers portados**, namespace `App\Http\Controllers\Admin\*`. Dividida en 5 sub-fases por
tamaño y riesgo:

| Sub-fase | Contenido | Tests acumulados |
|---|---|---|
| 1a | Catálogos globales (País/Ciudad/Sexo/Tipo-Subtipo evento/Relación contacto/Formas de pago) + login/logout del panel | 382/382 |
| 1b | Usuarios admin (CRUD) + auditoría + `RestrictCajeroToCaja` | 387/387 |
| 1c | Evento + 9 satélites (Categoría, Períodos de precio, Form Types, Souvenirs, Preguntas, Promo Codes, Coordenadas, Ruta, Auspiciadores, Agenda) | 397/397 |
| 1d | Caja de cobro presencial (12 rutas, apertura/cierre de turno, cobro pendiente) | 409/409 |
| 1e-i | Organizadores + Dashboard + Dashboard Inscripciones + Participantes/Detalle | 419/419 |
| 1e-ii | Numeración + Acreditación + ChronoTrack + Sesiones/Asistencia de congreso | 430/430 |
| 1e-iii | Bodega/Stock + Lista de espera + Delivery | 435/435 |
| 1e-iv | Presupuesto + Liquidación + Socios | 444/444 |
| 1e-v | Registro manual por CSV | **456/456** |

### Patrón central: `DelegatesToApiJson`

Cada `Admin\*Controller` **no reimplementa** CRUD/autorización/auditoría — llama directo al método
del controller de la API que ya hace todo eso (ej. `App\Http\Controllers\PaisController::index()`,
el mismo que usa `/api/v1/catalogos/paises`) y solo traduce la `JsonResponse` resultante a
vista/redirect Blade. El trait provee:

- `dataFrom()` — extrae `data` de una `JsonResponse` como array.
- `redirectFromApiResponse()` — traduce `{success, message|error}` a redirect + flash.
- `redirectToEventoTab()` — mismo patrón pero preservando la pestaña activa del editor de evento.
- `mergeAndValidate()` — resuelve un `FormRequest` a mano cuando el id del padre (evento/form_type)
  viaja por la URL anidada de `admin-eventos` pero el `FormRequest` de la API lo exige en el body.

### Bugs reales encontrados y arreglados durante la migración

1. **Prioridad de middleware de Laravel** (`Kernel::$middlewarePriority`) — `Authenticate` corre
   siempre antes que cualquier middleware "no listado" ahí, sin importar el orden declarado en la
   ruta. `InjectAdminSessionToken` (que copia `session('admin_token')` al header `Authorization`)
   necesitaba prioridad explícita para inyectar el header antes de que `auth:admins` lo leyera —
   arreglado con `prependToPriorityList()` en `bootstrap/app.php`.
2. **Nombre de route param inconsistente** (`{evento}` vs `{event}`) — recurrente en varias vistas
   portadas; el nombre del segmento de ruta tiene que calzar literal con el parámetro del método del
   controller para que el binding implícito funcione. Encontrado corriendo tests, no por lectura de
   código.
3. **Endpoint de la API que autoriza distinto de lo que el llamador necesita**
   (`PresupuestoCategoriaController::index()` exige `super_admin`, pero la pantalla de Presupuesto la
   puede ver un admin scoped a su evento). El proxy HTTP viejo degradaba en silencio
   (`?->json() ?? []`); la llamada in-process propaga la excepción real y tira abajo la página entera
   si no se ataja a mano.
4. **Sincronización de rama** — el worktree arrancó desde un punto de `questions` y esa rama siguió
   avanzando; antes de portar `RegistroManualController` se detectó que faltaba el commit de soporte
   de talleres en la carga CSV y se trajo con `git cherry-pick` puntual.

**456/456 tests en verde** al cierre de la Fase 1.

---

## 4. Fase 2 — `elascenso-blade` (shell público de inscripción)

**12 controllers** (8 proxies delgados + `HomeController` + 2 webhooks de pago), namespace
`App\Http\Controllers\Inscripcion\*`. Dividida en 2 sub-fases por naturaleza del riesgo (no por
tamaño — mucho más chico que la Fase 1).

### Sub-fase 2a — proxies simples

A diferencia de `Admin\*`, la mayoría de las rutas de `elascenso-blade` son **JSON puro** (`fetch()`
desde la SPA, sin redirect Blade) — no hizo falta ningún wrapper. `routes/inscripcion.php` apunta
**directo** a los controllers de la API que ya existen: `EventoController`, `PersonaController`,
`ClubController`, `PromoCodeController`, `ResultadoController`, `RegistrationController`. Laravel
resuelve `FormRequest`s y route-model-binding exactamente igual que si la ruta viviera en
`routes/api.php`.

Solo 3 piezas necesitaron código propio:

- **`HomeController`** — sirve el shell de la SPA (copy 1:1 de `home.blade.php` + `partials/*`,
  ~2000 líneas, cero JS tocado). Resuelve las meta tags OG/Twitter llamando
  `EventoController::show()` in-process en vez de por HTTP.
- **`TipoCambioController`** — no proxea ApiRestEvent (consulta una API pública de cambio aparte),
  port 1:1 sin cambios de lógica.
- **`ListaEsperaProxyController`** — único adaptador real: `elascenso-blade` manda `evento_id` en el
  body, pero `ListaEsperaController::store()` de la API lo espera en la URL vía route-model-binding.

Se mantuvieron los alias temporales `/api/*.php` que sigue usando el JS sin tocar de
`home.blade.php` (`$apiBase = 'api'`).

**484/484 tests en verde.**

### Sub-fase 2b — registro, pago y webhooks

Esta parte **sí tiene lógica propia real**: `RegistroProxyController`/`PagoProxyController` usan un
`RegistroValidacionService` que revalida/recalcula todo (categorías, souvenirs, promo, talleres,
totales) como capa de **UX temprana** — mensajes de error claros antes de tocar el create real, que
de todos modos vuelve a validar todo (defensa en profundidad). Esto se solapa con
`CrearInscripcionAction`/`RegistrationService` del lado API.

**Decisión confirmada explícitamente con el usuario** (vía pregunta directa, no asumida): mantener
`RegistroValidacionService` tal cual, y reemplazar solo el `forward()` HTTP **final** de cada
escritura por la Action/Service real, in-process:

| Antes (HTTP) | Ahora (in-process) |
|---|---|
| `forward('POST', '/registrations', ...)` | `CrearInscripcionAction::handle($dto)` |
| `forward('PUT', '/registrations/{ref}', ...)` | `ActualizarInscripcionAction::handle($ref, $data)` |
| `forward('PATCH', '.../update-paid', ...)` | `ActualizarInscripcionPagadaAction::handle($ref, $data)` |
| `forward('PATCH', '.../payment', ...)` | `RegistrationService::updatePaymentStatus()` |
| `forward('GET', '/registrations/{ref}')` | `Registration::where('referencia', ...)->first()` |
| `forward('GET', '/event/{id}')` | `EventoController::show()` in-process |
| `forward('GET', '/persona/me')` (auth_token) | `PersonalAccessToken::findToken()` (Sanctum directo) |

Se resuelven los `FormRequest`s (`StoreRegistrationRequest`, etc.) a mano vía
`Inscripcion\Concerns\ResolvesFormRequests::resolveWithBody()` — variante de `mergeAndValidate()`
que **reemplaza** todo el body en vez de fusionarlo (necesario porque `StoreRegistrationRequest`
espera un array envolvente `[0 => {...}]`).

Los 2 webhooks (`SipCallbackController`, `MultipagoCallbackController`) y `QrProviderService` se
portaron con el mismo criterio — este último es un port 1:1 sin cambios de lógica, ya que sigue
apuntando a las carpetas hermanas `elascenso/event/{sip,multipago}-payment-integration/` (el
worktree del monolito es también hermano directo de esas carpetas bajo `htdocs/`, así que la ruta
relativa resuelve igual sin tocar nada).

**Hallazgo real durante los tests** (no bug de esta fase, ya existía): la fixture HTTP-mockeada de
`elascenso-blade` usaba `tipo: 'no_integrado'` para el método de pago manual — un valor que nunca fue
válido contra el enum real de la base (`formas_pagos.tipo` solo admite `integrado`/`manual`), porque
esos tests nunca tocaban la BD real. Escribir tests contra la BD real de una vez lo hizo evidente.

**500/500 tests en verde** al cierre de la Fase 2 (incluye los 456 de Fase 1).

---

## 5. Patrones consolidados (útiles para cualquier trabajo futuro sobre este código)

- **Nunca reimplementar, siempre delegar** — cada controller nuevo llama al controller/Action/Service
  de la API que ya existe, in-process. Es la regla que de verdad elimina el salto de red.
- **Nombres de route param en inglés** cuando el `FormRequest` de la API lee `$this->route(...)`
  (`{event}` no `{evento}`, `{reference}` no `{referencia}`) — el nombre tiene que calzar exacto.
- **`Route::has()`** dejado a propósito en vistas ya portadas para secciones todavía no migradas — al
  agregar la sección real después, el link empieza a funcionar solo, sin tocar la vista vieja.
- **Cuidado con autorización asimétrica** — un endpoint de la API puede autorizar distinto de lo que
  el llamador necesita; el proxy HTTP viejo a veces degradaba en silencio, la llamada in-process
  propaga la excepción real.
- **Sincronizar el worktree** con la rama base (`questions`) antes de portar algo nuevo, no asumir
  que sigue al día.
- **Beneficios reales verificados** de eliminar el salto de red: ChronoTrack y la carga CSV ya no
  necesitan timeouts/reintentos largos configurados a mano; `QrProviderService::generateNew()`/
  `statusNew()` dejaron de depender de un loopback HTTP frágil en tests sin servidor.

---

## 6. Commits

Todo el trabajo está commiteado en el worktree (`ApiRestEvent-monolito`, rama
`consolidacion-monolito`), en 2 commits separados por fase para mantener la historia legible:

| Commit | Contenido | Archivos |
|---|---|---|
| `c608adf` | Fase 1 — admin-eventos dentro de ApiRestEvent | 104 archivos, +13 246 líneas |
| `5e7a85e` | Fase 2 — elascenso-blade dentro de ApiRestEvent | 25 archivos, +4 125 líneas |

`bootstrap/app.php` mezclaba cambios de ambas fases en el mismo archivo — se separó escribiendo a
mano la versión "solo Fase 1", commiteando, y recién después restaurando+commiteando la versión
completa con los agregados de Fase 2 (verificado con `git diff` que el segundo commit contiene
exactamente la porción de Fase 2, nada más).

**Nada desplegado.** Sigue solo en el worktree local, `.env` apuntando a `event_testing`
(automatizado) / `event_monolitico` (pruebas manuales por navegador, BD separada).

---

## 7. Pendiente / sin resolver

- **`brain/api_rest_event/Sin título 1.canvas`** aparece borrado en el working tree desde antes de
  esta sesión de consolidación — no se incluyó en ningún commit (no es parte del alcance de ninguna
  fase) y sigue como borrado-sin-commitear. Si fue un borrado intencional falta un `git rm` explícito
  para que quede reflejado en el historial; si fue accidental, se puede restaurar desde el commit
  anterior. No decidido.
- **Prueba manual end-to-end en navegador** (usando `event_monolitico`) — no hecha todavía.
- **Despliegue a UAT/producción** — no decidido ni iniciado. El usuario no tiene SSH, solo File
  Manager de cPanel (ver `project_uat_deploy_access`); con el monolito consolidado, el deploy pasa a
  ser una sola carpeta + un solo `.env` en vez de tres.
- **Alcance ya cerrado**: no queda ninguna otra fase planeada — `elascenso/event` (el PHP viejo)
  queda deliberadamente fuera de este proyecto de consolidación.

---

## 8. Números finales

- **43 + 12 = 55 controllers** portados desde 2 repos separados a un solo monolito.
- **500/500 tests en verde** (0 tests rotos, 0 regresiones detectadas en la suite existente de
  `ApiRestEvent` a lo largo de toda la consolidación).
- **~17 400 líneas** agregadas entre ambas fases (controllers + vistas + rutas + tests).
- **2 commits**, historia separada por fase.
- **0 despliegues** — todo verificado localmente, nada tocó UAT ni producción.
