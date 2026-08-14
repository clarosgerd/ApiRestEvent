# Plan: vincular expositores/ponentes registrados a sus sesiones

13/08/2026 — gap detectado por el usuario tras cerrar
[[PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026]] ("qué pasa si soy expositor/disertante,
creo que hay un gap ahí?"). **✅ IMPLEMENTADO Y VERIFICADO el mismo día.**

## Contexto — el gap confirmado

`sesiones_congreso.ponente`/`ponente_cargo` son campos de **texto libre** — el expositor no es
un registro del sistema. Si alguien se inscribe normalmente bajo un `form_type` tipo "Ponente"/
"Expositor", nada lo conecta con el nombre escrito a mano en la sesión donde expone.

## Decisiones del usuario

- **Cardinalidad**: muchos a muchos (un ponente puede exponer en varias sesiones, una sesión
  puede tener varios ponentes/panel).
- **Quién vincula**: solo el organizador, después de la inscripción — mismo criterio que Staff.

## Diseño (reusa casi toda la infraestructura de Staff, mismo día)

- **`form_types.es_ponente`** — flag nuevo, mismo patrón que `es_staff`.
- **Tabla `sesion_congreso_staff` reusada, no una tabla nueva**: se le agregó una columna
  `rol` enum(`staff`,`ponente`) default `staff`, y el índice único pasó a ser
  (`sesion_congreso_id`, `participante_id`, `rol`) — permite que la misma persona figure como
  staff Y ponente de la misma sesión si hiciera falta (caso raro, no bloqueado a propósito).
- **`SesionCongresoStaffController`** (mismo controller) ahora acepta `rol` (`staff` por
  default, compatibilidad con lo ya construido) en query (GET/DELETE) o body (POST), valida
  contra `es_staff`/`es_ponente` según corresponda.
- **El campo de texto libre `ponente`/`ponente_cargo` no se tocó** — sigue siendo lo que se ve
  en el PDF/agenda pública tal cual, para expositores invitados que nunca se registraron. El
  vínculo a un `Participante` real es un dato adicional en paralelo, no un reemplazo.
- **`admin-eventos`**: segunda columna "Ponentes vinculados" en la pantalla de Sesiones, mismo
  patrón de chips + selector que "Staff asignado".
- **Checkbox "Es Ponente/Expositor"** agregado en los 3 formularios de `form_type`
  (alta de evento, alta de form_type suelto, edición) — mismo lugar donde se agregó
  "Es Staff/Ayudante" horas antes.

## Bug real encontrado y arreglado durante la implementación

`Illuminate\Http\Client\PendingRequest::delete($url, $data)` manda `$data` como **body JSON**,
no como querystring (a diferencia de lo que se asumió al escribir el primer intento) — el
`DELETE` que `admin-eventos` le manda a `ApiRestEvent` para desvincular llevaba `rol` en el
body, pero el controller lo leía con `$request->query('rol', ...)`, que solo mira la
querystring. Corregido a `$request->input('rol', ...)` en ambos controllers (ApiRestEvent y
admin-eventos), que cubre body JSON, form-urlencoded y querystring por igual. Detectado
verificando con un formulario HTML real (no solo con `deleteJson()` de los tests, que arma la
petición distinto) — mismo criterio de "probar con datos reales" que se viene aplicando toda la
sesión.

## Verificación

- 14 tests Pest (8 de staff + 6 nuevos de ponente/rol), todos en verde.
- Suite completa: 232/232.
- Verificación real end-to-end con formulario HTML + sesión de admin real: vincular un ponente
  a una sesión y desvincularlo, confirmado en BD en ambos pasos.

## No cubierto (igual que Staff)

- Self-select del ponente al registrarse.
- Notificación automática al ponente cuando se lo vincula a una sesión.
- Filtrar el `.ics` del calendario a las sesiones de un ponente (mismo pendiente cruzado que
  [[PLAN-CALENDARIO-ICS-EVENTO-13082026]] documentó para Staff).
