# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/).

## 2026-09-03 — Costo unitario y Total en el Reporte de poleras

Pedido del usuario: la tabla Sexo/Talla/Cantidad del Reporte de poleras (dashboard del panel
autenticado y del link firmado del organizador) suma dos columnas nuevas.

### Added
- `ReporteInscritosData::agruparPoleras()` — `costoUnitario` (promedio real cobrado por fila,
  `montoTotal / cantidad`, no un precio fijo asumido) y `montoTotal` (suma de
  `souvenir_participantes.precio` real) por fila, más `totalMonto` en el resumen.
- Actualizado en los 2 lugares que renderizan este reporte: `organizador/dashboard.blade.php`
  (ApiRestEvent, link firmado) y `eventos/dashboard-inscripciones.blade.php` (admin-eventos,
  panel autenticado).

### Verified
- 2 tests nuevos/extendidos (`ReporteInscritosTest`, `ConfirmarPagoSitioTest`) — incluye un caso
  con precios distintos en la misma fila (confirma que `costoUnitario` es el promedio real, no
  "el último precio visto"). Confirmados con `git stash` que fallan sin el fix. 35 tests de
  archivos relacionados sin regresiones.

## 2026-09-03 — QR ilegible en el correo de pago pendiente (bug real, reportado 2 veces por WhatsApp)

Un usuario mandó captura de pantalla (segunda vez que le pasaba): el QR de referencia del correo
de "Registro recibido — Pago pendiente" le llegaba como imagen rota, sin poder leerlo.

### Root cause
`InscripcionPendienteMail` embebía el QR únicamente como `data:` URI en el `<img>` del cuerpo
HTML del correo, sin ningún PDF adjunto de respaldo. Muchos clientes de correo (Gmail web,
Outlook, varios webmail/gateways corporativos) bloquean imágenes `data:` por defecto — el QR
queda invisible/roto, sin ningún fallback disponible. `PagoConfirmadoMail` (pago YA confirmado)
sí adjuntaba un PDF con el mismo QR desde antes — dompdf no depende del bloqueo de imágenes del
cliente de correo, es confiable — pero `InscripcionPendienteMail` (pago pendiente) nunca lo tuvo.

### Fixed
- `resources/views/tickets/eticket.blade.php` parametrizado (`pdfTitle`/`statusLabel`/
  `statusColor`/`pdfFooterMsg`, default = el texto de siempre) para poder reusarlo en un
  comprobante de pago **pendiente** sin decir "✓ Pagado"/"Entrada electrónica" de algo que
  todavía no se pagó.
- `InscripcionPendienteMail` ahora adjunta el mismo PDF (`comprobante-{referencia}.pdf`), con
  estado "⏳ Pendiente". `PagoConfirmadoMail` sin cambio de comportamiento, solo pasa los
  parámetros nuevos explícitos.

### Verified
- 3 tests nuevos (`InscripcionPendienteMailQrPdfTest`) — confirmados con `git stash` que fallan
  sin el fix. 36 tests de archivos relacionados (correos, pago adicional, notificaciones, cobro
  en sitio) sin regresiones.

## 2026-09-03 — `tipos_evento.id`/`subtipos_evento.id` desbordaban `tinyint` en corridas completas de test

Encontrado mientras se verificaba el fix de arriba ("Mismo bug de talla..."): la nota "aparte" de
esa entrada se convirtió en su propio fix a pedido explícito del usuario ("arreglalo porfavor").

### Root cause
`tipos_evento.id`/`subtipos_evento.id` nacieron `tinyIncrements` (máx 255) — un catálogo tan
chico en producción (7 tipos, 18 subtipos) que en su momento pareció suficiente. El problema es
el AUTO_INCREMENT de InnoDB: no es transaccional, así que sobrevive los rollbacks de
`RefreshDatabase`. 36 archivos de test crean un `TipoEvento`/`SubtipoEvento` en su `setUp()`, y
el contador real de la tabla sube en cada intento — incluso los que se revierten. Alrededor del
test #256 de cualquier corrida completa (`php artisan test` sin filtro), el INSERT revienta con
`SQLSTATE[22003]: Numeric value out of range`.

### Fixed
- `tipos_evento.id`/`subtipos_evento.id` ensanchadas a `INT UNSIGNED` (mismo tipo que ya usa
  `categories.id`, otro catálogo de tamaño similar). Ensanchadas también las 3 columnas FK que
  apuntan acá (`subtipos_evento.tipo_evento_id`, `eventos.tipo_evento_id`,
  `eventos.subtipo_evento_id`) — MySQL exige que una FK y su columna referenciada compartan tipo.
  Puramente de tipo, ningún dato se renumera ni se pierde.
- Aplicada también a `event_prod_purga` y `event` (DBs locales reales) — conteos de filas
  verificados sin cambios antes/después de la migración.

### Verified
- 2 tests nuevos (`TiposEventoAutoIncrementRangoTest`) — insertan un id explícito > 255 (sin usar
  `ALTER TABLE ... AUTO_INCREMENT` dentro de un test: eso es DDL, hace commit implícito en MySQL
  y rompe el aislamiento transaccional de `RefreshDatabase` — se probó, dejó una fila filtrada
  fuera de cualquier rollback, limpiada a mano antes de reescribir el test con un INSERT normal).
  Confirmado con la migración temporalmente removida + `migrate:fresh` que ambos tests fallan sin
  el fix. Suite completa: 541 tests, sin regresiones, ya no desborda de punta a punta.

## 2026-09-03 — Mismo bug de talla "No shirt" en delivery, correo de confirmación y Detalle de inscritos

Extensión del bug de abajo ("Reporte de poleras mostraba 'No shirt'"): encontrado proactivamente
vía `grep -rn "\$p->polera\|\->polera\b"` mientras se arreglaba el CSV del organizador — el mismo
campo legacy se leía directo en 3 lugares más. Confirmado con el usuario antes de tocarlos
("Sí, arreglá los 3 también").

### Fixed
- Nueva `App\Support\TallaPoleraData` — extrae la resolución de talla (antes duplicada entre
  `ReporteInscritosData` y el CSV del organizador) a un colaborador compartido:
  `souvenirIdsPolera()` + `resolver()`, con el mismo fallback al campo legacy para eventos que
  todavía usan el flujo viejo (`form_types.hasshirt`).
- `DeliveryController` (`show()`, `exportCsv()`, `json()`, `indexForAdmin()`) y
  `organizador/delivery.blade.php` — la talla mostrada/exportada a la empresa de delivery ya no
  es el sentinel.
- `emails/partials/participantes.blade.php` — la línea "Camiseta:" del correo de confirmación de
  pago (compartida por todos los Mailables que usan este partial) ya muestra la talla real.
- `ParticipanteController::porEvento()` ("Detalle de inscritos" en admin-eventos) — mismo fix,
  con eager-load de `souvenirParticipante` agregado para no introducir N+1.

### Verified
- 6 tests nuevos: 2 en `ParticipantesPorEventoTest`, 3 en el nuevo `DeliveryControllerTest`
  (CSV + JSON), 1 en `EmailParticipantesPartialTest`. Confirmado con `git stash` que los 6
  fallan sin el fix (falso-negativo real). 76 tests de archivos relacionados
  (`ReporteInscritosTest`, `SouvenirInvisibleTest`, `ConfirmarPagoSitioTest`,
  `EditarInscripcionPagadaTallerCategoriaTest`, `PagoAdicionalSipTest`,
  `NotificacionServiceConcurrenciaTest`) sin regresiones.
- **Nota aparte, no relacionada a este fix**: la suite completa (`php artisan test` sin filtro)
  falla en ~54 tests con `SQLSTATE[22003]: Numeric value out of range... column 'id'` en
  `tipos_evento` — columna `tinyint unsigned` (máx 255) cuyo AUTO_INCREMENT no es transaccional
  en InnoDB, así que sobrevive los rollbacks de `RefreshDatabase` y desborda alrededor del test
  #256 de cualquier corrida completa. Preexistente, no causado por este cambio — confirmado
  corriendo los mismos archivos que fallan en la suite completa de forma aislada (pasan limpio) y
  viendo la misma falla en archivos no tocados por este commit. Pendiente: migrar la columna a un
  tipo normal (`unsignedBigInteger`/`id()`) — decisión del usuario, no se tocó en esta sesión.

## 2026-09-03 — Reporte de poleras mostraba "No shirt" + dashboard del organizador

Reportado por el usuario con capturas del dashboard: la columna "Talla" del Reporte de poleras
mostraba siempre "No shirt" en vez de las tallas reales.

### Root cause
`ReporteInscritosData::agruparPoleras()` leía la talla de `participantes.polera`, un campo
legacy — se eligió esa fuente el 15/08/2026 porque en ese momento tenía datos reales y el
sistema de souvenirs casi no se usaba. Esa base quedó obsoleta: eventos como el que motivó el
reporte ya modelan la polera como un souvenir normal (`requiere_talla=true`), y
`participantes.polera` queda siempre en el string sentinel `'No shirt'` que manda el frontend
(elascenso/event) cuando el flujo legacy no aplica. Las tallas reales viven en
`souvenir_participantes.talla`.

### Added / Fixed
- `souvenirs.es_polera` (boolean, default false) — flag opt-in por ítem: no hay forma de saber
  cuál souvenir es "la polera" cuando un form_type tiene más de un ítem con talla (ej. una
  mochila), así que el organizador lo marca a mano en admin-eventos. Requiere
  `requiere_talla=true` (validado en Store/UpdateSouvenirRequest).
- `ReporteInscritosData::agruparPoleras()` reescrito — ahora agrupa por
  `souvenir_participantes.talla` de los souvenirs `es_polera=true`, sexo sigue siendo
  `participantes.genero` (ese campo sí sigue poblado siempre).
- Dashboard del organizador (link firmado, sin login,
  `OrganizadorDashboardController::show()`) — pedido del usuario: ahora también muestra
  "Inscritos por categoría / distancia" con recaudación (`ReporteInscritosData::agruparPorCategoria()`),
  reusando el mismo reporte que ya existía en el panel autenticado. Distinto del "Por
  categoría" que ya tenía esa página (ese cuenta por estado de pago, sin dinero) — quedan las
  dos tablas, no se reemplaza ninguna.

### Verified
- 6 tests nuevos entre `ReporteInscritosTest`, `SouvenirInvisibleTest` y `ConfirmarPagoSitioTest`
  — reproduce el bug exacto (sentinel 'No shirt' no contamina el reporte), un souvenir con
  talla que no es la polera no aparece, validación cruzada `es_polera` requiere
  `requiere_talla`, el dashboard del organizador incluye la tabla nueva. Confirmado que fallan
  sin cada fix (`git stash`), incluida la reproducción exacta del bug real reportado. Suite
  completa de souvenirs/kit/stock/reporte (40 tests) sin regresiones.

## 2026-09-03 — Incidente UAT: condición de carrera duplicaba correos de notificación

Reportado por el usuario con un log real de producción: `SQLSTATE[23000]: Duplicate entry
'90314-pago_confirmado-email' for key registration_notifications_registration_id_tipo_canal_unique`.

### Root cause
`NotificacionService::enviarEmailSiNoEnviado()` chequeaba `yaEnviado()` (SELECT) y recién
registraba el envío (INSERT) DESPUÉS de mandar el correo. Dos requests casi simultáneos para el
mismo (registration_id, tipo, canal) — típicamente el webhook de pago de la pasarela + el
polling del frontend detectando el mismo pago un instante después, o un reintento de webhook —
podían pasar el SELECT los dos ANTES de que cualquiera hiciera el INSERT: **el correo se mandaba
dos veces** y recién el segundo INSERT crasheaba, demasiado tarde para evitar el duplicado. El
disparador de fondo: `RegistrationService::updatePaymentStatus()` nunca chequeaba si el estado
ya era el que se pedía, así que cualquier llamada redundante repetía todo el flujo de cero.

### Fixed
- `NotificacionService::reservarNotificacion()` — nuevo colaborador que usa `insertOrIgnore()`
  (email/WhatsApp, tabla con UNIQUE) como mutex atómico a nivel de BD: se reserva el lugar ANTES
  de mandar nada, no después. Reemplaza a `yaEnviado()`+`registrarEnvio()` en los 3 métodos que
  los usaban (email, WhatsApp externo, WhatsApp OpenWA) — misma clase de bug en los tres, aunque
  solo el de email se vio crashear en los logs.
- `notificarPagoAdicionalConfirmado()` (feature del 02/09) — mismo criterio con un UPDATE
  condicional (`WHERE notificado_at IS NULL`) en vez de insertOrIgnore, porque esa fila ya existe
  de antes (no tiene un UNIQUE por tipo/canal como registration_notifications).
- `RegistrationService::updatePaymentStatus()` — nueva guarda de idempotencia (si el estado ya es
  el pedido, no hace nada) — reduce la ventana de carrera además de evitar trabajo redundante.

### Verified
- 3 tests nuevos (`NotificacionServiceConcurrenciaTest`) — envío normal funciona; no manda ni
  crashea si la notificación ya estaba reservada (mismo para el correo de pago adicional).
  **Nota honesta**: la condición de carrera real requiere 2 conexiones a BD concurrentes — no se
  puede reproducir de forma determinística en PHPUnit (ejecución secuencial, una sola conexión),
  así que estos tests no "fallan sin el fix" de la forma que sí lo hicieron los bugs de esta
  semana — verifican que el mecanismo de reserva atómica es correcto (la garantía que, bajo
  concurrencia real, evita la carrera), no reproducen la carrera en sí.
- Suite completa de talleres/Caja/pago adicional/pendientes/purga (82 tests) sin regresiones.

## 2026-09-02 — Correo de confirmación de pago adicional (no existía)

Parte 2 del incidente de UAT de hoy (ver entry anterior, "SIP cobró un pago adicional que nunca
se aplicó"): el usuario preguntó cómo avisarle a Flor Banegas, la participante cuyo pago
adicional (`AD-B4NTW3BV`) sí se terminó aplicando bien — pero nunca le llegó ningún correo.
Investigando se confirmó que `ConfirmarPagoAdicionalAction` nunca disparó ningún correo desde que
existe (26/08/2026, PLAN-COBRO-SIP-ADICIONAL-26082026.md) — ni siquiera cuando el pago se aplica
sin problemas. No es un efecto del bug de hoy, es un hueco que estuvo ahí desde el principio.

### Added
- `NotificacionService::notificarPagoAdicionalConfirmado()` + `PagoAdicionalConfirmadoMail` +
  vista `emails/pago-adicional-confirmado.blade.php` — mismo espíritu que el correo de pago
  confirmado normal (`PagoConfirmadoMail`), pero sin PDF adjunto: monto adicional cobrado,
  método, y la lista completa de talleres que la inscripción tiene ahora.
- `pagos_adicionales_inscripcion.notificado_at` (nullable) — idempotencia PROPIA de este correo,
  a propósito NO reusa `registration_notifications` (única por registration_id+tipo): una misma
  inscripción puede tener varios pagos adicionales a lo largo del tiempo, y esa tabla compartida
  bloquearía el segundo aviso.
- `ConfirmarPagoAdicionalAction` ahora dispara la notificación al confirmar (después de aplicar
  el cambio real) — nunca tumba la confirmación si el correo falla.
- Comando `notificaciones:reenviar-pago-adicional {referencia} [--confirmar] [--forzar]` —
  reenvío puntual/retroactivo, dry-run por default, mismo patrón que
  `personas:purgar-canceladas-retroactivo`. Usado para el caso real de Flor Banegas.

### Verified
- 4 tests nuevos en `PagoAdicionalSipTest`: se envía al confirmar; no se reenvía en un reintento
  de SIP; NO se envía si el pago queda en 'error'; un segundo pago adicional de la MISMA
  inscripción manda su propio correo (prueba clave del diseño de idempotencia por pago, no por
  inscripción). Confirmado que 3 de los 4 fallan sin el hook-up (`git stash`).
- Vista renderizada localmente contra el pago adicional real de Flor Banegas
  (`event_prod_purga`, sincronizada desde UAT) — sin errores, muestra Bs1010.00 y los 3 talleres
  correctos. Comando probado en dry-run y en envío real contra el sandbox de Mailtrap local (no
  llega a su bandeja real) — falta correrlo de verdad en UAT con `--confirmar` para que a ella le
  llegue.

## 2026-09-02 — Incidente UAT: SIP cobró un pago adicional que nunca se aplicó

Reportado por el usuario con logs reales de `payment_callback_20260902.log`: en el congreso, un
participante editó su inscripción ya pagada para agregar un taller, pagó con QR (SIP cobró el
dinero de verdad), pero el cambio nunca se aplicó — la fila de `pagos_adicionales` quedó en
'error', sin correo, sin taller agregado. El usuario tuvo que recrear el registro a mano. 2 de 3
intentos reales (`AD-TLJCPR12`, `AD-OXGY7QB9`) quedaron stuck para siempre (cada reintento de
SIP repetía "Este pago adicional ya no está pendiente (estado: error)"); el tercero
(`AD-B4NTW3BV`) se recuperó solo porque alguien reactivó a mano el taller entre reintentos.

### Root cause

`ValidarSeleccionesTallerAction::runPorParticipante()`/`runCapacidad()` — llamadas por
`ActualizarInscripcionPagadaAction` en CADA edición de una inscripción pagada — revalidaban la
disponibilidad (`activo`/`permite_inscripcion`/cupo) de TODOS los talleres del participante,
incluidos los que ya tenía pagados de ANTES de esta edición. Como esa misma Action prohíbe quitar
un taller ya pagado ("No se pueden quitar talleres que ya fueron pagados"), si el organizador
deshabilitaba ese taller después (cupo lleno, lo que sea) el participante quedaba bloqueado para
editar CUALQUIER cosa de su inscripción, sin ninguna salida — ni siquiera para agregar un taller
totalmente distinto. En el flujo SIP esto es grave: el dinero ya se cobró antes de este chequeo.

### Fixed
- `ValidarSeleccionesTallerAction::run()`/`runPorParticipante()`/`runCapacidad()` — nuevo
  parámetro (mapa de sesiones ya seleccionadas antes de esta edición) que exime esas sesiones de
  los chequeos de disponibilidad/cupo; las genuinamente nuevas siguen validándose igual que
  siempre. `ActualizarInscripcionPagadaAction` arma y pasa ese mapa (movió el snapshot de
  `$participantesAnteriores` para que corra ANTES de la validación). `CrearInscripcionAction`
  (alta nueva, todo es nuevo) y `ActualizarInscripcionAction` (inscripción pendiente, todavía se
  puede quitar cualquier taller) no lo necesitan y siguen con la validación estricta de siempre.

### Verified
- 3 tests nuevos en `EditarInscripcionPagadaTallerCategoriaTest`: taller ya pagado que se
  deshabilita no bloquea agregar uno nuevo distinto; el mismo escenario con cupo reducido
  después de pagado; guard de que un taller GENUINAMENTE nuevo con `permite_inscripcion=false`
  se sigue rechazando igual que siempre. Los 3 confirmados que fallan sin el fix (`git stash`),
  con el mensaje de error exacto del incidente real. Suite completa de talleres/Caja/pago
  adicional (69 tests) sin regresiones.
- No hizo falta recuperar datos: las 2 filas 'error' del incidente (`AD-TLJCPR12`,
  `AD-OXGY7QB9`) ya las resolvió el usuario a mano recreando el registro — este fix es
  preventivo, para que no vuelva a pasar.

## 2026-09-02 — "Pagar en el evento (efectivo)" configurable en el pago adicional

Pedido del usuario, tras probar la pantalla de editar una inscripción pagada para agregar un
taller: "necesito la manera de que sacar 'pagar en el evento (efectivo)' de manera automática".
Antes de esto el radio de efectivo aparecía siempre junto al de QR (ver entry del
26/08/2026, "Cobro real por SIP del monto adicional") — nunca se podía sacar sin tocar código.

### Added
- `eventos.forzar_qr_pago_adicional` (boolean, default `false`) — mismo patrón que
  `fee_incluye_talleres`/`usd_precio_fijo`: ningún evento existente cambia de comportamiento
  (se siguen ofreciendo ambas opciones), el organizador lo prende puntualmente desde
  admin-eventos → editar evento → pestaña de datos generales.
- `elascenso/event/index.php` — cuando el flag está prendido, oculta el radio "Pagar en el
  evento (efectivo)" en la pantalla de edición de una inscripción pagada y deja QR
  preseleccionado como única opción visible. Cede ante `usdPrecioFijo` si ambos están
  prendidos a la vez: SIP todavía no soporta el cobro del adicional en eventos USD fijo (ver
  entry del 27/08/2026), así que ahí el efectivo sigue siendo obligatorio sin importar este
  flag nuevo.

### Fixed
- `elascenso/event/index.php::buildSummary()` — el "Costo de edición" mostrado en el resumen
  antes de confirmar duplicaba el precio del taller nuevo: `totTalleres` (fila "Total
  Talleres") ya es el total completo (viejo + nuevo), pero la fila "Costo de edición" volvía a
  sumar ese mismo taller nuevo (`costo_edicion` flat + `totTalleresNuevos`), inflando el GRAND
  TOTAL exactamente por el precio del taller agregado. Reportado por el usuario con un caso
  real (Bs1000 de más). El monto que realmente se cobra (mensaje "vas a pagar $X ahora" y
  `pago_adicional.php`) no tenía este bug — es puramente un error de visualización del
  resumen, ya coincidía con lo que calcula `CalcularCostoAdicionalAction` del lado del
  backend.

### Verified
- `ForzarQrPagoAdicionalTest` (4 tests nuevos) — default false, exposición en `EventoResource`,
  admin scoped puede activarlo, no interfiere con otros campos. Confirmado que 2 de los 4
  fallan sin el fix (`git stash` de Resource/Service/Request). Suite completa de
  `EventoFeePctTest`/`EventoOrganizadorInmutableTest` sin regresiones.
- Duplicación del "Costo de edición": recalculada a mano con datos reales de
  `event_prod_purga` (evento 1, registro `LA-C404D0CA`, taller "REASE: Manejo de Paro
  Intraoperatorio" Bs1000) — TOTAL GENERAL pasa de Bs3845.00 (con el bug) a Bs2845.00
  (correcto), diferencia exacta de Bs1000.00. Sin navegador disponible en este entorno para un
  click real; pendiente que el usuario confirme visualmente en su próxima prueba.

## 2026-09-01 — Ocultar Dirección/Ciudad/Teléfono/Alias por tipo de formulario

Pedido del usuario: "deberíamos colocar en form_type quitar esos campos de dirección, ciudad,
teléfono, etc. desde admin-eventos". Distinto del cambio del 31/08 (esos 4 campos + contacto de
emergencia pasaron a opcionales en TODOS los eventos) — esto además los saca del formulario,
configurable por tipo de formulario. Contacto de emergencia queda fuera de este cambio: ya tenía
su propio mecanismo de ocultar (`requiere_contacto_emergencia`).

### Added
- `form_types.campos_ocultos` (JSON, array de `direccion`/`ciudad`/`telefono`/`alias`, default
  `[]`) — un solo array en vez de 4 columnas boolean nuevas, mismo criterio que
  `eventos.secciones_orden`. Sin cambio de comportamiento para form_types existentes.
- Checkbox "Ocultar del formulario público" en `admin-eventos` (editar un form_type existente, y
  al agregar uno nuevo a un evento ya creado) — 4 casillas, una por campo. Sin checkbox en la
  creación nested del evento (`create.blade.php`), mismo criterio ya usado para
  `requiere_contacto_emergencia`.
- `applyCamposOcultos()` en `elascenso/event` — oculta el `.form-group` correspondiente cuando
  el campo está en `selectedFormType.camposOcultos`, llamado junto al resto de la visibilidad
  condicional por form_type en `buildEventUI()`. Alias comparte el mismo campo de BD que "Título"
  en form_types tipo congreso — ocultarlo saca a los dos, documentado en el checkbox.
- No hizo falta relajar nada en la validación de participantes: los 4 campos ya son `nullable`
  desde el 31/08/2026.

### Verified
- 4 tests nuevos (`FormTypeCamposOcultosTest`), suite completa sin regresiones (474/480, 6
  fallas = flake preexistente de `tipos_evento`).
- Verificado en vivo end-to-end contra `event_prod_purga`: `PUT /form-type/{id}` con
  `campos_ocultos` real, confirmado en la respuesta y en `GET /event/{id}` (público); HTML de
  `index.php` confirmado con los 4 `id` nuevos (`direccionGroup`/`ciudadGroup`/`telefonoGroup`,
  más `aliasGroup` que ya existía) y la función/llamada nuevas presentes; checkbox verificado
  renderizando (des)tildado correctamente en `admin-eventos` real. Datos de prueba revertidos
  después.

## 2026-09-01 — Registro real revertido por choque de email en syncPersonas()

Reportado por el usuario probando en vivo contra una copia de producción
(`event_prod_purga`): "No se pudo completar el registro, intenta nuevamente" con una
inscripción por lo demás perfectamente válida. Causa encontrada en `storage/logs/laravel.log`:
`personas.numero_documento` no tiene constraint único (solo `email` lo tiene), así que pueden
existir 2 cuentas `Persona` distintas con el mismo documento y emails distintos (dato real
encontrado: personas `90013`/`90153`). `RegistrationService::syncPersonas()` podía matchear la
cuenta EQUIVOCADA por documento e intentar pisarle el email con uno que ya era de la OTRA cuenta
— reventaba el `UNIQUE` de `personas.email` **dentro de la misma transacción que crea la
inscripción**, revirtiendo el alta entera por un problema de una tabla secundaria/derivada.

### Fixed
- `syncPersonas()` ahora prioriza matchear por `email` (el único campo realmente único) antes
  que por `numero_documento`, y todo el bloque queda envuelto en `try/catch` — `Persona` es una
  cuenta derivada (login/historial), nunca debe poder tumbar un registro real. Un fallo
  inesperado se loguea (`sync-personas-fallo`) en vez de relanzarse.

### Verified
- Reproducido primero el bug exacto con los datos reales que lo causaron (vía tinker contra
  `event_prod_purga`), confirmado que el fix lo resuelve sin tocar ninguna de las 2 cuentas
  ajenas. Test de regresión nuevo (`RegistrationTest::test_create_registration_no_falla_por_numero_documento_compartido_entre_2_personas`).
  Suite completa sin regresiones.

## 2026-09-01 — Purgar datos de Persona/Participante en inscripciones canceladas

Pedido del usuario: "necesitamos que cada vez que un participante se registra y su pago está
pendiente o cancelado sea liberado de participante y de la tabla persona... esta decisión puede
ser obtenida de un flag en evento". Diseñado en plan mode antes de implementar — ver
`PLAN-PURGAR-DATOS-PERSONA-CANCELADA-01092026.md`. 3 decisiones tomadas con el usuario: (1) el
borrado dispara SOLO al pasar a `cancelled`, nunca sobre un `pending` en curso (podría estar
pagando en ese instante); (2) `Persona` es una cuenta GLOBAL (sin FK a evento/registro,
compartida entre todas las inscripciones que esa persona hizo en cualquier evento) — antes de
borrarla se chequea que no tenga otra inscripción `paid`/`pending` vigente en NINGÚN evento; (3)
no retroactivo, solo aplica hacia adelante desde que se activa el flag.

### Added
- `eventos.mantener_datos_persona` (boolean, default `true` — comportamiento actual intacto
  para los eventos existentes hasta que un organizador lo apague).
- `PurgarDatosPersonaCanceladaAction` — se dispara desde
  `RegistrationService::updatePaymentStatus()` al pasar a `cancelled` (cubre tanto el cron de
  expiración como una cancelación manual), después de `notificarReversionCupo()` (el email
  todavía necesita el correo del participante). Borra el `Participante` (las 4 tablas hijas y la
  pivot de staff/ponente ya tenían `cascadeOnDelete()` a nivel de BD — confirmado en sus
  migraciones, no hizo falta borrarlas a mano) y, si esa identidad
  (`numero_documento`/`correo`) no tiene ninguna otra inscripción vigente en ningún evento,
  también la cuenta `Persona`. Salta participantes con un `Resultado` real cargado (ej.
  ChronoTrack) — no se borra un dato de carrera legítimo.
- Checkbox "Mantener datos de persona" en `admin-eventos/eventos/edit.blade.php` (nace tildado
  — a diferencia del resto de los flags de esa página, que nacen destildados). Sin checkbox en
  `create.blade.php` a propósito (mismo criterio que `aceptaUsd`/`talleresConCosto`): un evento
  nuevo usa el default `true` de la columna sin que el panel lo pise.

### Verified
- 5 tests nuevos (`PurgarDatosPersonaCanceladaTest`): flag apagado sin otra inscripción vigente
  → borra participante y persona; flag apagado con otra inscripción vigente en otro evento →
  borra participante, conserva persona; flag encendido (default) → no borra nada; transición a
  `paid` → nunca dispara el purge; participante con resultado cargado → no se toca.
- Verificado en vivo contra `event_uat_testing`: `PUT /event/{id}` con `mantenerDatosPersona:
  false` persiste correctamente (encontré y arreglé de paso que faltaba mapear la clave
  camelCase→snake_case en `EventoService::update()` y la regla de validación en
  `UpdateEventosRequest` — sin esto, el campo se hubiera descartado en silencio, mismo tipo de
  bug ya encontrado varias veces esta sesión con otros campos). Render del checkbox nuevo
  confirmado vía `admin-eventos` real (tildado por default en un evento sin tocar).
- Suite completa de ApiRestEvent: 469 passed, 6 failed (flake preexistente de `tipos_evento`, no
  relacionado).

## 2026-08-31 — Cupo de talleres secuestrado por inscripciones canceladas

Pedido del usuario: "revisa los cupos de talleres, en elascenso/event y admin-eventos el número
de cupos por taller muestra distinta información". Causa real: dos definiciones distintas de
"ocupado", ninguna correcta. `TallerSesionResource`/`ValidarSeleccionesTallerAction` contaban
CUALQUIER fila de `participante_taller_sesion` (incluidas inscripciones `cancelled`/`failed`);
`ReporteInscritosData` (admin-eventos) contaba solo `paid`, ignorando `pending`. Ninguna usaba
el criterio ya establecido en `FormType::inscritosVigentes()` (paid+pending, no cancelled/failed).

### Fixed
- `ValidarSeleccionesTallerAction::runCapacidad()` — el chequeo real que bloquea nuevas
  selecciones ahora excluye `cancelled`/`failed`. Antes, una inscripción `pending` que expiraba
  sola (`ExpirarInscripcionesPendientesAction` nunca borra `participante_taller_sesion`, solo
  cambia `pago_status`) dejaba el cupo de esa sesión "secuestrado" para siempre.
- `TallerSesionResource::ocupados/disponibles` — mismo filtro, para que el participante vea el
  cupo real en `GET /event/{id}`.
- `ReporteInscritosData::agruparPorTaller()` — `disponible` ahora cuenta paid+pending (vigentes);
  `cantidad`/`recaudación` siguen siendo exclusivamente `paid` (dinero cobrado, sin cambios en
  ese criterio, documentado en la clase).

### Verified
- 2 tests nuevos (`TallerSeleccionInscripcionTest`, `ValidarSeleccionesTallerAction` y
  `GET /event/{id}`) + 1 test existente actualizado (`ReporteInscritosTest`, esperaba
  `disponible=18` con un pendiente sin contar, ahora `17`). Suite completa: 464 passed / 6 failed
  (flake preexistente de `tipos_evento`, no relacionado — 2 más que antes solo porque los 2 tests
  nuevos viven en la misma clase flaky, confirmados en verde en aislamiento).

## 2026-08-31 — Género por catálogo + campos menos obligatorios

Dos pedidos del usuario juntos por compartir repos y sesión (independientes entre sí). Ver
`PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md`.

### Género por catálogo
El `<select>` de género en elascenso/event estaba hardcodeado con 4 opciones
(Masculino/Femenino/Non-binary/Prefer not to say), pero `participantes.genero` es un ENUM que
solo acepta `Masculino|Femenino|Otro` — las otras 2 opciones rompían el INSERT con un 500 crudo
de SQL si alguien las elegía (bug real, preexistente).

#### Added
- Tabla nueva `generos` (NO es `sexos` — esa respalda `categories.sexo_id`, concepto distinto,
  sin tocar). Seedeada con exactamente los 3 valores del ENUM actual (no se migra ese ENUM).
- `GET /generos` público (mismo split público/admin que `TipoEventoController`) + CRUD admin
  bajo `/catalogos/generos`, solo `super_admin`.
- `Rule::in` contra los géneros activos en los 4 `FormRequest` que validan un participante — un
  valor inválido ahora da 422 en vez del 500 crudo de antes. Test de regresión nuevo:
  `RegistrationTest::test_create_registration_rejects_genero_fuera_del_catalogo`.
- Pantalla nueva en admin-eventos (`/catalogos/generos`) para gestionar el catálogo.

### Campos menos obligatorios
Pasan a opcionales: **Dirección, Ciudad, Teléfono, Alias/Título y Contacto de emergencia
completo** (nombre/celular/relación) — no hacen a la identidad de la persona, a diferencia de
nombre/documento/email/fecha de nacimiento/categoría, que siguen obligatorios.

#### Changed
- `StoreRegistrationRequest`: `direccion`/`ciudad`/`telefono` pasan de `required` a `nullable`.
- Los 4 `FormRequest` (`Store/UpdateRegistrationRequest`, `UpdatePaidRegistrationRequest`,
  `StoreInscripcionCajaRequest`) ya no exigen el contacto de emergencia bajo ninguna condición —
  se eliminó el trait `ValidaContactoEmergenciaCondicional` (dead code tras el cambio). El flag
  `form_types.requiere_contacto_emergencia` sigue existiendo y sigue controlando si la sección
  se MUESTRA en el frontend, ya no si es obligatoria.
- Sin migraciones de BD: `RegistrationService::createParticipantFromData()` ya insertaba estos
  campos con fallback `?? ''`.

#### Verified
- 2 tests existentes actualizados para reflejar el nuevo comportamiento (antes esperaban 422,
  ahora esperan 201): `CajaTest::test_caja_acepta_inscripcion_nueva_sin_contacto_emergencia_por_default`,
  `RegistrationTest::test_create_registration_allows_missing_emergency_contact`.
- Suite completa: 464 passed, 4 failed (flake preexistente de `tipos_evento`, no relacionado).
- Verificado en vivo: `_registro_validacion.php` (elascenso/event) con un participante real sin
  alias/dirección/ciudad/teléfono/contacto de emergencia — sin error.

## 2026-08-31 — SIP multi-banco

Pedido del usuario: "hacemos el pago por SIP con un solo banco (Bisa) pero ahora incluiremos
otros dos bancos con distintas credenciales, mismo gateway; implementalo pero esto no debería
afectar Multipago". Ver `brain/api_rest_event/PLAN-SIP-MULTIBANCO-28082026.md` y
`DEPLOY-CHECKLIST-SIP-MULTIBANCO-31082026.md`. Multipago no fue tocado — todo lo agregado son
ramas `pasarela === 'sip'` nuevas.

### Added
- Tabla nueva `sip_bancos` (organizador_id nullable, credenciales SIP + callback, `activo`) —
  nunca expuesta por ningún Resource público (mismo criterio que `FormasPagoResource` con
  `config`). CRUD (`SipBancoController`, solo `super_admin`) con el patrón "dejar vacío = no
  cambiar" para los secretos, ya usado en `AdminUser::password`.
- Endpoints internos server-to-server nuevos bajo `/internal/*` (middleware
  `RequiresInternalSecret`, header `X-Internal-Secret` contra `INTERNAL_API_SECRET`,
  fail-closed): `GET /internal/event/{event}/sip-banco` resuelve el banco activo del
  organizador dueño del evento; `GET /internal/sip-bancos/callback-credenciales` lista
  credenciales de callback de todos los bancos activos, para que `elascenso/event` valide el
  webhook de SIP sin saber de antemano con qué banco se generó el QR.
- `PagoAdicionalController::show()` ahora siempre incluye `eventoId` en la respuesta (antes
  solo viajaba si `pago_status === 'paid'`).

### Verified
- 14 tests nuevos en `SipBancoTest.php` — en verde. Suite completa: 463 passed, 4 failed (las 4
  fallas son el flake preexistente y no relacionado de `tipos_evento`/AUTO_INCREMENT).
- No probado todavía en vivo contra el gateway SIP real con un banco alternativo cargado (ver
  checklist, §5).

### Gotcha real (corregido)
- Route model binding de Laravel exige que el nombre del parámetro del controller coincida con
  el segmento de la ruta — un mismatch (`{event}` vs `$evento`) hacía que Laravel inyectara un
  `Evento` en blanco en silencio (sin 404), rompiendo la resolución de banco para todos los
  organizadores sin que varios tests lo notaran (esperaban `null` por otro motivo).

## 2026-08-28 — Deshabilitar un taller sin ocultarlo

Pedido del usuario, diseñado en plan mode antes de implementar. Ver
`brain/api_rest_event/PLAN-TALLER-PERMITE-INSCRIPCION-28082026.md`. `talleres.activo=false` ya
ocultaba el taller por completo del participante; se agrega un estado distinto — visible en la
lista, pero no seleccionable — reutilizando el mismo patrón visual que "cupo lleno".

### Added
- `talleres.permite_inscripcion` (boolean, default `true`, aditivo — `activo` sin cambios).
- `TallerResource` expone `permiteInscripcion`; `ValidarSeleccionesTallerAction` rechaza la
  selección de un taller deshabilitado y lo excluye del chequeo de REQUIRED obligatorios.

### Fixed (solo para el campo nuevo, no se tocó "Activo")
- Un checkbox HTML sin marcar no manda nada en el POST — `admin-eventos` agrega un `<input
  type="hidden" value="0">` antes del checkbox `permite_inscripcion` para que destildarlo sí
  guarde `false`. El mismo bug ya existía en el checkbox "Activo" desde antes; documentado, no
  corregido, a la espera de que el usuario decida si quiere tocarlo.

### Verified
- 4 tests nuevos en `TallerSeleccionInscripcionTest.php` — 11/11 en verde en aislamiento. La
  suite completa mostró 4 fallas, todas la misma falla preexistente y no relacionada
  (`tipos_evento`, overflow de AUTO_INCREMENT) — confirmado que no es una regresión.
- Verificado en vivo contra el servidor local real (GET/POST reales, no solo tests).

## 2026-08-28 — Admin de evento asignado a varios eventos

Pedido del usuario, diseñado en plan mode antes de implementar. Ver
`brain/api_rest_event/PLAN-ADMIN-MULTI-EVENTO-28082026.md`. `admin_users.evento_id` seguía
siendo el "evento principal" del admin (sin cambios); se agregó una tabla pivote
(`admin_user_evento`) para eventos ADICIONALES, 100% opt-in — solo para rol `admin` (`cajero`
sigue con un único evento, decisión explícita del usuario).

### Added
- `AdminUser::eventosAdicionales()`/`eventoIds()`/`tieneAccesoAEvento()` — centraliza la regla de
  acceso que antes estaba repetida a mano en varios controllers.
- `evento_ids_adicionales` en `Store`/`UpdateAdminUserRequest`; `AdminUserController` sincroniza
  la pivote al crear/editar un admin.
- `AdminAuthController` expone `eventoIds` (evento principal + adicionales) en login/me.

### Fixed
- `AuthorizesEventoScope` y otros 2 puntos sueltos (`EventoController::souvenirsVisiblesScope()`,
  `AdminAuditLogController`) que comparaban `evento_id` a mano ahora usan la regla centralizada.

### Verified
- `AdminUserTest.php` (nuevo, 7 tests) + 1 caso end-to-end en `CajaTest.php`. Suite completa:
  448/450 (2 fallas = mismo flake preexistente y no relacionado, confirmado que pasa solo).

## 2026-08-27 — Categorías por form_type

Pedido del usuario ("las categorías deberían estar dentro de form_type"), analizado (impacto +
costo de migración) y luego diseñado en plan mode (`ExitPlanMode` aprobado) antes de implementar.
Ver `brain/api_rest_event/PLAN-CATEGORIAS-POR-FORM-TYPE-27082026.md`. Antes de este cambio, las
categorías eran 100% del evento — cualquier categoría se aceptaba para cualquier form_type del
mismo evento, sin chequeo alguno.

### Added
- FK real `categories.formulario_id → form_types.id` (la columna ya existía, nullable, desde la
  migración original, pero nunca se usó). `null` = categoría compartida por todos los
  form_types del evento (comportamiento previo, sin romper nada); con un valor, la categoría
  solo es válida para ESE form_type.
- `CategoryController` valida que el `formulario_id` recibido pertenezca al mismo evento de la
  categoría (store/update) — antes nada lo garantizaba.
- `CategoryFilter` con filtros `event_id`/`formulario_id`; `CategoryResource` expone
  `formulario_id`.

### Fixed
- `CrearInscripcionAction::validatePrecioCategoria()` y `RegistrationController::importarBulk()`
  (carga masiva CSV) ahora exigen que la categoría sea compartida o del form_type de la
  inscripción — antes solo chequeaban el evento.
- **Bug real más serio**: `ActualizarInscripcionPagadaAction` (cambio de categoría en una
  inscripción ya pagada) resolvía la categoría nueva con `Category::findOrFail()` sin filtrar NI
  SIQUIERA por evento — aceptaba la categoría de cualquier evento del sistema. Ahora exige mismo
  evento y form_type compatible.

### Verified
- `CategoryTest.php` (nuevo, 5 tests) + tests nuevos en `RegistrationTest.php` (2),
  `EditarInscripcionPagadaTallerCategoriaTest.php` (2) y `RegistroManualBulkTest.php` (1). Suite
  completa sin regresiones (1 falla preexistente y no relacionada — `TallerSeleccionInscripcionTest
  > cupo lleno rechaza nueva seleccion` — confirmada flaky, pasa sola en aislamiento).
- Vistas de `admin-eventos` (`eventos.edit`, `caja.nueva`, `registro-manual`) renderizadas con
  datos de un evento real vía `artisan tinker` + ejecución real del JS con jsdom, confirmando el
  filtro por form_type en los 3 selects de categoría (público, Caja, carga masiva).

### Fixed (28/08/2026, dato real, no código)
- Encontrado mientras se investigaba el reporte de abajo (no era la causa, pero era real): 6
  categorías (eventos 1 y 4) ya tenían `formulario_id` con un valor de ANTES de que esta feature
  existiera — la columna quedó de un intento abandonado en julio, nunca escrita por ningún código
  (confirmado con grep), pero poblada a mano/por import en algún momento sin representar ninguna
  asignación real (evento 4: "5K"→form_type "Individual", "10K"→form_type "Grupal", sin relación
  real entre nombre de categoría y tipo de formulario). Corregido con
  `UPDATE categories SET formulario_id = NULL` en esas 6 filas. Este hallazgo agregó el Paso 4
  (obligatorio) al checklist de deploy: chequear y limpiar el mismo tipo de dato huérfano en UAT
  antes de activar el filtro ahí.

### Fixed (28/08/2026) — bug real, no relacionado a esta feature
- Reportado por el usuario con una captura: la pantalla "Períodos de precio — Categoría" de
  `admin-eventos` mostraba siempre "Sin períodos cargados" y "Bs 0.00", sin importar si la
  categoría tenía períodos reales cargados — incluso el título caía al fallback genérico
  "Categoría" en vez del nombre real. Causa: `CategoryController::show()` devolvía el
  `CategoryResource` "pelado" (`return new CategoryResource($category)`), así que Laravel lo
  envolvía en su wrapper default (`{"data": {...}}`) en vez del wrapper explícito
  (`'category' => ...`) que ya usan `store()`/`update()` en este mismo controller. El único
  consumidor de este endpoint (`CategoryPricePeriodController::index()`, `admin-eventos`) leía los
  campos en la raíz de la respuesta — nunca los encontraba, sin importar los datos reales. Bug
  preexistente desde que la feature "Precios por período" se lanzó (12/08/2026), sin relación con
  `formulario_id`; se lo encontró recién ahora porque el usuario probó esa pantalla puntual al
  validar el trabajo de hoy. Corregido en ambos lados (`CategoryController::show()` +
  `CategoryPricePeriodController::index()`).

## 2026-08-27 — Detalle de cierre de caja (drill-down por turno) + filtro por cajero

Pedido del usuario, diseñado en plan mode (`ExitPlanMode` aprobado) antes de implementar. La
pantalla "Cierres de caja" (`admin-eventos`) ya mostraba una fila por turno con los totales
agregados y ya soportaba filtrar por fecha; el filtro por cajero existía en el backend
(`CajaTurnoController::index()`) pero nunca se expuso en el formulario, y no había forma de ver
QUÉ movimientos individuales componen el total de un turno.

### Added
- `GET /event/{event}/caja/turnos/{turno}` (`CajaTurnoController::show()`) — detalle de un turno
  con sus movimientos completos. Mismo scoping que `index()` (solo admin/super_admin).
- `app/Http/Resources/CajaMovimientoResource.php` (nuevo) — expone `tipo`, `monto`, `metodoPago`,
  `registrationReferencia` (para poder ir del movimiento a la inscripción real) por movimiento.
- `CajaTurnoResource` — campo `movimientos` nuevo, condicionado a `whenLoaded()` (ausente en
  `index()`, presente en `show()` — no infla la respuesta de la lista existente).

### Verified
- 2 tests nuevos en `CajaTest.php`: el detalle devuelve los movimientos completos con la
  referencia correcta; un admin de otro evento no puede ver el detalle de un turno ajeno (404).
  432/432 suite completa, sin regresiones.
- Gotcha real encontrado escribiendo los tests: mezclar `actingAsCajero()`/`actingAsAdmin()`
  dentro del MISMO test no cambia la identidad autenticada entre llamadas HTTP (el guard
  `admins` cachea el usuario resuelto la primera vez, no por header) — ningún otro test de este
  archivo lo hacía. Resuelto usando un solo actor por test (o creando el `CajaTurno` directo por
  modelo cuando hacía falta un segundo evento).

## 2026-08-27 — Verificado: Caja edita inscripción pendiente + agrega taller + cobra completo

Pedido del usuario: "revisamos caja con el escenario de pago pendiente adicionando un taller?".
Investigado end-to-end (`CajaController::editarPendiente()` → `ActualizarInscripcionAction` →
`CajaController::cobrarPendiente()`) — a diferencia del flujo de inscripción ya PAGADA (que cobra
solo el delta vía `ActualizarInscripcionPagadaAction`), acá no había ningún test que cubriera
"agregar un taller a una pendiente y después cobrarla completa".

### Verified (sin cambios de código — el flujo ya funcionaba bien)
- Test nuevo `test_editar_pendiente_agrega_taller_y_luego_se_cobra_completo` en `CajaTest.php`:
  edita una inscripción pendiente agregando un taller → `registration_totals.grand_total` queda
  actualizado (incluye el taller) → `cobrar-pendiente` cobra el monto COMPLETO actualizado (no el
  original desactualizado) → el taller queda `pago_pendiente=false` (correcto: se cobra todo de
  una, no queda ninguna deuda suelta). 430/430 suite completa.

## 2026-08-27 — Cobro SIP del adicional: rechazado para eventos USD fijo

El usuario preguntó si el cobro por SIP del monto adicional soporta eventos con precio fijo en
USD — investigado y confirmado que NO: ni `costo_edicion` (FormType) ni el cálculo de talleres de
`CalcularCostoAdicionalAction` tienen variante en USD, y `pago_adicional.php` (elascenso/event)
generaría el QR en Bs para un evento que, por regla del proyecto, cobra EXCLUSIVAMENTE en USD.
Decisión explícita del usuario: por ahora solo permitir modificar y pagar en efectivo en el
evento — arreglar esto de verdad (threadear la moneda por todo el flujo) queda para más adelante.

### Fixed
- `CalcularCostoAdicionalAction::handle()` rechaza con `DomainException` si el evento tiene
  `usd_precio_fijo` — el pago adicional nunca llega a crearse.

### Verified
- 1 test nuevo (`test_evento_usd_fijo_rechaza_el_pago_adicional_por_sip`), 429/429 suite completa
  sin regresiones.

## 2026-08-27 — Reporte de talleres confiable: separa lo cobrado de lo pendiente

Pedido del usuario: el reporte de talleres (CSV detalle + sección "Reporte de talleres" del
Dashboard) tiene que reflejar la nueva realidad de la feature de SIP — un taller agregado a una
inscripción pagada puede estar ya cobrado (inscripción original, Caja, o SIP confirmado) o
todavía pendiente de cobrarse en efectivo el día del evento (autoservicio, opción "pagar en el
evento") — y "recaudación" no debe mezclar las dos cosas.

**El obstáculo real**: `ActualizarInscripcionPagadaAction` borra y vuelve a crear TODOS los
`participante_taller_sesion` en cada edición (incluso los que no cambiaron) — no hay ningún
timestamp ni marca que sobreviva para distinguir "de la inscripción original" de "agregado
después". Sin una señal persistida en el momento en que se agrega el taller, era imposible
reconstruir esto después.

### Added
- Migración — columna `pago_pendiente` (boolean, default false) en `participante_taller_sesion`.
- `ActualizarInscripcionPagadaAction::handle()` — nuevo parámetro `bool $requierePagoEnSitio`:
  marca los talleres NUEVOS que agrega esa llamada. `true` solo desde
  `RegistrationController::updatePaid()` (autoservicio eligiendo "pagar en el evento" en vez de
  QR); `false` desde `CajaController::editarPagada()` (cobra en efectivo ahí mismo) y desde
  `ConfirmarPagoAdicionalAction` (SIP ya cobró online). Los talleres que YA existían antes de la
  edición conservan su valor anterior (snapshot antes del delete, restaurado después de recrear)
  — sin esto, cualquier edición posterior resetearía el flag a `false` sin que nadie haya cobrado
  nada.
- `ReporteInscritosData::agruparPorTaller()` — `cantidadPendiente`/`recaudacionPendiente`/
  `recaudacionCobrada` por fila y a nivel total.
- `ReporteInscritosData::detalleTalleres()` — `pagoPendiente` (bool) + `estadoPago` (texto
  "Pagado" / "Pendiente (efectivo en el evento)") por fila.

### Verified
- 3 tests nuevos en `EditarInscripcionPagadaTallerCategoriaTest.php` (autoservicio marca
  pendiente, Caja no, y el flag sobrevive a una edición posterior que no toca ese taller) + 1 en
  `ReporteInscritosTest.php`. 428/428 suite completa, sin regresiones.

## 2026-08-26 — Acreditación: reporta talleres y pagos (incluye adicionales por SIP)

Pedido del usuario: al no existir un correo de confirmación para el flujo de editar una
inscripción pagada (ver entrada anterior), pidió que al menos la acreditación (check-in) sea
"confiable" y muestre los talleres y pagos que hizo la persona — para que el staff en el evento
pueda ver el estado real en vez de depender de un correo que no existe.

### Changed
- `RegistrationController::checkinLookup()` (`GET /event/{event}/checkin/{reference}`) — ahora
  eager-carga `participants.talleresSesiones` y `totals`, y expone:
  - `participantes[].talleres` — mismo shape que `ParticipanteResource.talleres`
    (`ParticipanteTallerSesionResource`).
  - `tipoPago`, `monedaPago`, `tipoCambioAplicado`, `totalPagado`, `totales` — snapshot de lo
    cobrado por la inscripción base.
  - `pagosAdicionales[]` — TODOS los pagos adicionales de `pagos_adicionales_inscripcion` para
    esa inscripción (no solo los `paid`): un `pending`/`error` visible ahí es justo la señal que
    el staff necesita para resolverlo en el momento, en vez de que quede escondido.

### Verified
- 1 test nuevo (`test_lookup_incluye_talleres_del_participante_y_pagos_adicionales`) — crea un
  taller real + 2 pagos adicionales (`paid` y `error`) y confirma que ambos aparecen con su
  estado correcto. 7/7 `CheckinTest`, 424/424 suite completa, sin regresiones.
- Guardado defensivo: si una inscripción (vieja o de prueba) no tiene fila de `totals`, `totales`
  devuelve `null` en vez de romper el endpoint entero.

## 2026-08-26 — `PagoAdicionalController::show()` expone `created_at`

Necesario para el fallback simulado de 90s que se agregó en `elascenso/event/api/pago_status_adicional.php`
(ver `elascenso/event/CHANGELOG.md`, mismo día) — sin la fecha de creación del intento de pago, el
frontend no podía calcular cuánto tiempo pasó para decidir cuándo simular la confirmación en
entornos sin SIP configurado.

### Changed
- `PagoAdicionalController::show()` — agrega `created_at` (formateado) a la respuesta, siempre
  (no solo cuando `paid`).

## 2026-08-26 — Cobro real por SIP del monto adicional (agregar taller a inscripción pagada)

Pedido del usuario: "deberiamos habilitar el pago mediante sip, solo debe asegurarte que solo le
cobremos la adicion de taller + el costo de edicion". Hasta ahora el monto adicional (costo de
edición + taller nuevo) quedaba solo registrado, a pagar en efectivo el día del evento — ahora se
puede pagar online. Decisiones acordadas con el usuario en el chat: (1) **tabla nueva** dedicada,
no un campo padre/hijo en `registrations` (evita que reportes/dashboards que cuentan filas de
`registrations` tengan que aprender a excluir "hijas"); (2) el taller **no se agrega hasta que SIP
confirme el pago** — responde directamente a su preocupación ("¿y si pierde la conexión o se
desanima?"): si nunca paga, no hay nada que revertir, el cupo nunca se tocó. Alcance: solo
autoservicio (`elascenso/event`) — Caja ya resuelve esto en efectivo en el mostrador.

### Added
- Tabla `pagos_adicionales_inscripcion` (migración `2026_08_26_150000_...`) — `referencia` propia
  con prefijo `AD-` (nunca colisiona con `LA-` de una inscripción), `monto`, `participantes_payload`/
  `totales_payload` (snapshot exacto a aplicar), `qr_id`, `pago_status` (pending/paid/expired/error).
- `GenerarPagoAdicionalAction` — crea la fila `pending`, no toca la inscripción.
- `CalcularCostoAdicionalAction` — recalcula el monto server-side (costo_edicion + delta de
  talleres agregados vía `ResolverPrecioTallerData`); rechaza cambio de categoría o remoción de
  talleres ya pagados; nunca confía en el monto que manda el cliente.
- `ConfirmarPagoAdicionalAction` — idempotente; recién acá se aplica el cambio real, reusando
  `ActualizarInscripcionPagadaAction::handle(..., permiteCambioCategoria: false)` tal cual, sin
  duplicar lógica de negocio; si falla (ej. cupo lleno mientras tanto), marca `error` para revisión
  manual — el dinero ya lo cobró SIP en ese punto, no hay reembolso automático (fuera de alcance).
- `ExpirarPagosAdicionalesAction` + comando `notificaciones:expirar-pagos-adicionales` (cron cada 5
  min, mismo patrón que `ExpirarInscripcionesPendientesAction`) — ventana fija de 60 min; como
  nunca se aplicó nada, expirar es un no-op sobre la inscripción real.
- `PagoAdicionalController` + rutas `POST /registrations/{reference}/pagos-adicionales`,
  `PATCH /pagos-adicionales/{ref}/qr`, `GET /pagos-adicionales/{ref}`,
  `PATCH /pagos-adicionales/{ref}/confirmar`.
- `tests/Feature/PagoAdicionalSipTest.php` — 7 tests nuevos.

### Fixed
- **Bug real, preexistente, encontrado escribiendo estos tests** —
  `ValidarSeleccionesTallerAction::runCapacidad()` usaba `whereHas` en vez de `whereDoesntHave`
  para excluir la inscripción propia del conteo de cupo: al editar, el chequeo de capacidad solo
  contaba las filas de ESA MISMA inscripción (normalmente 0) e ignoraba por completo el consumo de
  todas las demás — el cupo/capacidad quedaba sin aplicar en CUALQUIER flujo de edición
  (`ActualizarInscripcionAction`/`ActualizarInscripcionPagadaAction`), no solo esta feature nueva.
  Ningún test existente lo detectaba porque el único que llamaba `runCapacidad()` pasaba `null`
  (camino de alta nueva). Corregido a `whereDoesntHave`; 422/422 tests en verde después del fix
  (cero regresiones).
- `RegistrationService::loadRelations()` (colaborador compartido) no eager-cargaba
  `participants.talleresSesiones.taller`/`.sesionCongreso` — el taller recién agregado no aparecía
  en la respuesta de `PagoAdicionalController::show()`/`confirmar()` (mismo bug de `whenLoaded()`
  ya encontrado y arreglado el mismo día para `mine()`/`lookupRegistration()`). Corregido acá una
  sola vez, compartido por las 3 Actions de inscripción que ya usan este helper.
- `ConfirmarPagoAdicionalAction`: la rama idempotente (pago ya `paid`, ej. SIP reintentando el
  callback) devolvía `$pago->registration` sin las mismas relaciones cargadas.

### Verified
- 7/7 tests nuevos en verde; suite completa 423/423 sin regresiones.
- El test de idempotencia confirma que un segundo `confirmar()` sobre un pago ya `paid` no vuelve a
  aplicar el taller ni a cobrar de nuevo.
- El test de cupo lleno confirma que si `ActualizarInscripcionPagadaAction` falla al confirmar, la
  fila queda `error` (no `pending` para siempre — bug real encontrado y arreglado en el propio
  desarrollo: envolver la Action en un `DB::transaction()` propio hacía rollback también del
  `$pago->update(['pago_status' => 'error'])` del `catch`, dejándola atascada en `pending`).

## 2026-08-26 — Bug: taller ya elegido no se prepoblaba al editar una inscripción

Reportado por el usuario probando la feature de agregar talleres a inscripción pagada: "también
debería prepopular el taller escogido". Causa raíz real (el fix de frontend del mismo día —
`editParticipant()` llamando a `renderTalleresSelector()` — era necesario pero no suficiente,
porque el dato en sí nunca llegaba): ni `RegistrationController::mine()` ni
`RegistrationService::lookupRegistration()` eager-cargaban `participants.talleresSesiones` —
`ParticipanteResource::talleres` (que usa `whenLoaded()`) quedaba directamente ausente del JSON.
Mismo bug ya se había encontrado y arreglado el 20/08 para `RegistrationController::show()`
(usado por Caja) — nunca se propagó a estos otros 2 endpoints, usados por el autoservicio.

### Fixed
- `RegistrationController::mine()` y `RegistrationService::lookupRegistration()` — agregado
  `participants.talleresSesiones.sesionCongreso`/`.taller` al eager-load.

### Verified
- 2 tests nuevos en `tests/Feature/EditarInscripcionPagadaTallerCategoriaTest.php`:
  `test_registrations_mine_incluye_talleres_del_participante`,
  `test_lookup_registration_incluye_talleres_del_participante` — ambos fallaban antes del fix
  (`talleres` vacío/ausente), pasan después. 10/10 tests del archivo, suite completa en verde.

## 2026-08-26 — Bug crítico: toda inscripción nueva se guardaba con `genero='Masculino'`
## 2026-08-26 — Bug crítico: toda inscripción nueva se guardaba con `genero='Masculino'`

Reportado por el usuario: "muchas personas de sexo femenino registraron masculino". Investigado
antes de tocar nada — la causa raíz **no** estaba en el formulario (que sí funciona bien, exige
elegir género y manda el valor correcto), sino en el backend: mismo patrón de bug ya documentado
para `moneda_pago`/`talleres` (24/08 y 19/08) — `StoreRegistrationRequest::rules()` nunca declaró
una regla para `participantes.*.genero`, así que `$request->validated()` lo descartaba en silencio
antes de llegar al DTO. `ParticipantDTO::fromArray()` y
`RegistrationService::createParticipantFromData()` caen a `?? 'Masculino'` cuando la clave no
llega — **toda inscripción nueva online, sin importar lo que la persona eligiera, terminaba
guardada como Masculino**. Los flujos de EDICIÓN (`UpdateRegistrationRequest`/
`UpdatePaidRegistrationRequest`) y Caja (`StoreInscripcionCajaRequest`) sí declaraban la regla —
el bug era exclusivo del alta nueva pública.

### Fixed
- `app/Http/Requests/StoreRegistrationRequest.php` — agregada `'*.participantes.*.genero' =>
  ['required','string']`.
- Bug secundario e independiente en Caja (`admin-eventos`): el `<select>` de género no tenía
  opción vacía ni `required`, y "Masculino" era la primera opción — si el cajero no lo tocaba,
  quedaba Masculino por default del navegador. Ver `admin-eventos` (sin CHANGELOG propio, este
  cambio queda documentado ahí en el código).

### Verified
- Test nuevo `RegistrationTest::test_create_registration_respeta_el_genero_elegido_no_siempre_masculino`
  — fallaba antes del fix (quedaba guardado 'Masculino' pese a mandar 'Femenino'), pasa después.
- 414/414 tests del suite completo en verde.

### Pendiente (no es un cambio de código — a confirmar con el usuario)
- **Datos ya corrompidos en producción/UAT**: toda inscripción online creada antes de este fix
  pudo haber guardado `genero='Masculino'` incorrectamente. No se tocó ningún dato existente — el
  fix solo previene nuevos casos. Corregir el histórico requeriría identificar qué inscripciones
  realmente son de mujeres (no hay forma automática de saberlo desde el sistema — el dato real se
  perdió al guardarse mal) y corregirlas a mano o pedirle a cada persona que confirme, decisión que
  le corresponde al usuario.

## 2026-08-25 — Feature: agregar talleres a una inscripción pagada + cambio de categoría en Caja
## 2026-08-25 — Feature: agregar talleres a una inscripción pagada + cambio de categoría en Caja

Caso real: personas inscritas al congreso sin haber elegido taller, que necesitan agregarlo
después de haber pagado. Antes de este cambio, `ActualizarInscripcionPagadaAction` (usada tanto
por el autoservicio del participante como por Caja) siempre cobraba un monto **fijo**
(`form_types.costo_edicion`), sin relación con lo que realmente cambió, y no bloqueaba ni cambio de
categoría ni remoción de un taller ya pagado en ningún lado.

Dos reglas de negocio distintas, confirmadas con el usuario: el **autoservicio** solo puede
AGREGAR talleres nuevos (nunca cambiar de categoría — el sistema no reembolsa diferencias, se
resuelve en caja el día del evento); **Caja** además puede cambiar de categoría, porque puede
cobrar/desembolsar la diferencia en efectivo ahí mismo. Ningún flujo permite quitar un taller ya
pagado.

### Added
- `ActualizarInscripcionPagadaAction::handle()` — nuevo parámetro `bool $permiteCambioCategoria`
  (default `false`). Snapshot del estado anterior (categoría/talleres por participante) tomado
  antes del `delete()`, correlacionado por posición contra `$data['participantes']`. Rechaza
  (`DomainException` → 422) si cambia el número de participantes, si cambia la categoría sin
  `permiteCambioCategoria`, o si falta algún `sesion_congreso_id` que el participante ya tenía. El
  `costo_adicion` devuelto ahora es real: `costo_edicion` (flat, como siempre) + precio real de los
  talleres agregados (leído de `ParticipanteTallerSesion.total`, ya resuelto server-side, nunca del
  precio que manda el cliente) + diferencia real de categoría (resuelta vía
  `PrecioVigenteData::paraCategoria()`, tampoco se confía en `precioCategoria` del cliente — puede
  generar un desembolso real de dinero). Puede quedar negativo.
- `CajaController::editarPagada()` — pasa `permiteCambioCategoria: true`; la condición para crear el
  `CajaMovimiento` pasó de `> 0` a `!= 0` para que también se registre una devolución (monto
  negativo) — `caja_movimientos.monto` ya es `decimal` con signo y `CajaTurno::sum('monto')` ya
  resta un negativo correctamente, sin cambios de esquema.

### Fixed
- **Bug real preexistente encontrado al escribir los tests de esta feature**:
  `RegistrationService::createParticipantFromData()` nunca tuvo los imports de
  `Evento`/`SesionCongreso`/`ParticipanteTallerSesion` — el bloque que persiste talleres tiraba
  `Class "App\Services\Evento" not found` en cuanto alguien intentaba agregar un taller al **editar**
  una inscripción existente (pending o paid). Nunca se notó porque el alta de una inscripción
  **nueva** con talleres usa otra ruta de código (`CrearInscripcionAction`), que sí tenía los
  imports correctos.

### Verified
- 413/413 tests en verde (405 preexistentes + 8 nuevos en
  `tests/Feature/EditarInscripcionPagadaTallerCategoriaTest.php`): autoservicio agrega taller y
  cobra flat+real, autoservicio rechaza cambio de categoría, ningún flujo puede quitar un taller
  pagado, edición sin cambios solo cobra el flat, Caja cambia a categoría más cara/más barata
  (positivo/negativo), Caja registra el `CajaMovimiento` negativo (antes no se creaba con el `> 0`
  viejo), taller nuevo que choca de horario con uno ya pagado se rechaza (reusa
  `ValidarSeleccionesTallerAction` tal cual, sin cambios).
- 1 falla intermitente preexistente en `RegistrationTest` (orden de ejecución, no relacionada a
  este cambio) confirmada al re-correr el suite completo dos veces — pasa en aislado y en la
  segunda corrida completa.

## 2026-08-25 — Feature: título de la persona en el CSV de talleres del Dashboard

Pedido del usuario: en `admin-eventos` → Dashboard → "Reporte de talleres" → "Descargar CSV
(detalle sin agrupar)", agregar el título académico de la persona (ej. "PhD.", "Dr.", "Lic.") como
columna, junto a nombre/apellido.

No hace falta columna nueva en BD: el título ya se captura hoy reusando el campo `alias` de
`participantes` — `elascenso/event/index.php` (`toggleAliasTituloMode()`) muestra ese campo como
"Título" con un `<select>` (Dr./Dra./Lic./Ing./Msc./Mgr./Est./PhD./Otro) en vez de texto libre
cuando el `form_type` es de tipo `congreso`, pero sigue escribiendo en la misma columna `alias`
(mismo patrón replicado en `admin-eventos/caja/_formulario.blade.php`). Este CSV es justamente el
de talleres (feature exclusiva de congresos), así que el reuso es coherente.

### Added
- `app/Support/ReporteInscritosData.php` (`detalleTalleres()`): nueva clave `participanteAlias` en
  cada fila (`$p->alias`), junto a `participanteNombre`/`participanteApellido`.

### Verified
- `php -l`; 405/405 tests de la suite completa en verde (incluye
  `ReporteInscritosTest::detalle_de_talleres_sin_agrupar_ordenado_por_fecha`, sin cambios
  necesarios — la clave nueva es aditiva).
- Verificado contra datos reales (`event_prod`, evento 1) vía tinker: la clave viaja correctamente
  en la estructura (vacía para los participantes reales de esa muestra, que no cargaron título —
  comportamiento esperado, no hay ningún participante con `alias` cargado en esa BD todavía).
- Ver `admin-eventos/CHANGELOG.md`... **nota**: `admin-eventos` no tiene `CHANGELOG.md` propio en
  este repo — el cambio del lado del CSV (headers + columna nueva en
  `DashboardInscripcionesController::csvTalleres()`) queda documentado acá mismo por ese motivo.
  Simulación exacta de `fputcsv()` con datos de prueba confirmó la salida
  `titulo,nombre,apellido` → `PhD.,Carlos Gerd,Claros`, igual al ejemplo pedido por el usuario.

## 2026-08-25 — Feature: orden configurable de secciones del evento (columna `secciones_orden`)

Columna nueva `eventos.secciones_orden` (JSON nullable, cast `array`) — array de hasta 9 claves
fijas (`description|calendar|countdown|media|sponsors|kitGallery|routeMap|agenda|formTypes`) en el
orden que el organizador eligió desde `admin-eventos` para los bloques de la pantalla de tipos de
formulario en `elascenso/event`. `null` = el frontend usa su orden por defecto (aditivo, cero
cambio para eventos existentes). Ver `elascenso/event/CHANGELOG.md` mismo día para el detalle
completo (admin-eventos + frontend).

### Added
- Migración `2026_08_25_180000_add_secciones_orden_to_eventos_table`.
- `Evento::$fillable`/`$casts` ('array'), `StoreEventosRequest`/`UpdateEventosRequest`
  (`seccionesOrden` nullable array, `in:` con las 9 claves válidas), `EventoService::update()` (map
  camelCase→snake_case), `EventoResource` (`seccionesOrden`) — mismo patrón end-to-end ya usado para
  `usd_precio_fijo` (19/08/2026).

### Verified
- `php -l` en los 6 archivos tocados.
- Migración corrida contra `event_prod` (dev). De paso se encontraron y reconciliaron 5 migraciones
  previas marcadas "Pending" cuyas columnas ya existían físicamente en esa BD (desincronización
  entre la tabla `migrations` y el esquema real, típico de una BD restaurada desde un dump de UAT
  sin pasar por `artisan migrate`) — se insertaron sus filas de tracking sin reejecutar su DDL,
  confirmando antes que cada columna objetivo ya existiera, y recién después se corrió la migración
  nueva sola.
- Round-trip del cast `array` verificado por tinker contra un evento real (`event_prod`): array de
  9 claves guardado y leído idéntico; revertido a `null` al terminar la prueba.
- **Pendiente**: prueba HTTP end-to-end (`GET /event/{id}` con el campo poblado) — el servidor local
  (`php artisan serve`) no estaba levantado al momento de este cambio.

## 2026-08-24 — Feature: "Pago pendiente (USD)" para eventos con precio fijo (link por correo, expira 24h)

Pedido del usuario: los eventos con `usdPrecioFijo=true` (ver
`brain/api_rest_event/Plan_pago_pendiente_USD` en `elascenso/event`) solo podían cobrarse vía SIP
o Multipago; se agregó una tercera opción manual — el participante queda `pending`, recibe un
correo con un link de pago **configurado por organizador** (no por evento, para no repetirlo
evento por evento) y si no paga en 24h la inscripción se cancela sola. Requisito explícito y
verificado en cada paso: cero impacto en el flujo de pago en Bs. Alcance: `ApiRestEvent`,
`admin-eventos`, `elascenso/event` — ver `elascenso/event/CHANGELOG.md` mismo día para el detalle
del frontend.

### Added
- `formas_pagos`: fila nueva `pendiente_usd` (`pasarela=manual_usd`, `tipo=manual`,
  `organizador_id=null`, catálogo de sistema) en `FormasPagoSeeder`.
- Columna nueva `organizador_formas_pago.link_pago` (nullable) — en la práctica solo se usa para
  la fila `pendiente_usd`. `Organizador::linkPagoPendienteUsd()` nuevo; `formasPagoSeleccionadas()`
  extendido con `withPivot('link_pago')`.
- `OrganizadorController::formasPago()`/`updateFormasPago()` exponen/guardan `linkPago`/
  `link_pago_pendiente_usd` por fila.
- `EventoResource::formasPago()` solo ofrece `pendiente_usd` si el evento es `usdPrecioFijo` **y**
  el organizador tiene un link cargado — evita ofrecer el método a medio configurar.
- `InscripcionPendienteMail` agrega `linkPago`/`expiraEn` (created_at + 24h) cuando
  `tipo_pago === 'pendiente_usd'`, sourced de `evento.organizador.linkPagoPendienteUsd()`; queda
  `null` para cualquier otro método (el correo en Bs no cambia). Bloque nuevo "Pagar ahora" +
  vencimiento en `emails/confirmacion.blade.php`.
- `RegistrationController::confirmarPagoManual()` — `PATCH /registrations/{referencia}/confirmar-pago-manual`,
  confirma manualmente un `pendiente_usd` en `pending` (idempotente si ya está `paid`, 422 si es
  otro método o ya no está pendiente). Consumido por el botón "Confirmar pago" nuevo en
  `admin-eventos` (ver abajo) — cualquier admin del organizador puede usarlo, no solo super_admin.
- `ParticipanteController::porEvento()` expone `tipoPago` y calcula `importe`/`importeTaller`/
  `importeTotal` en USD real (vía precio de categoría/taller) cuando `moneda_pago === 'USD'`, en
  vez del `subtotal` en Bs de siempre.
- Expiración a las 24h: **sin código nuevo** — reusa `form_types.tiempo_expiracion_min` y el ciclo
  existente `notificaciones:expirar-pendientes`; el organizador simplemente pone `1440` en el
  form_type. Trade-off aceptado: si ese form_type también ofrece SIP/Multipago, esos también
  esperan 24h en vez de su ventana corta actual.
- `admin-eventos`: input de link en la pantalla "Formas de pago" del organizador; botón "Confirmar
  pago" (con `confirm()`) en "Detalle de inscritos", visible solo para filas
  `pending`+`pendiente_usd`.

### Fixed
- **Bug real de fondo, preexistente, no de este feature**: `StoreRegistrationRequest` nunca
  declaraba `moneda_pago`/`tipo_cambio_aplicado`/`total_pagado` en `rules()`, así que
  `$request->validated()` los descartaba en silencio — **toda** inscripción en USD terminaba
  guardada como `moneda_pago='BOB'`, no solo las de este feature nuevo. Encontrado tras varias
  horas de descarte (JS, OPcache) hasta loguear el payload real dentro de `registro.php`. Fix: las
  3 reglas agregadas (`nullable`).
- **`FormTypeResource` nunca exponía `tiempo_expiracion_min`**: explica por qué el campo "Expira
  (min)" en `admin-eventos` estaba hardcodeado a 30 en la edición — no había forma de leer el
  valor real desde la API. Agregado al `toArray()`.
- **Auditoría "nada en Bs para `usdPrecioFijo`" (pedida explícitamente como revisión de todo el
  sistema)**: `confirmacion.blade.php` (total general), `emails/partials/participantes.blade.php`
  (categoría/taller por participante), `emails/partials/totales.blade.php` (desglose completo),
  `tickets/eticket.blade.php` (total general), `emails/recordatorio-pendiente.blade.php` (monto
  pendiente) — todos con rama `if (moneda_pago === 'USD') { ... } else { /* original intacto */ }`
  para no tocar un solo carácter del camino en Bs.
- `CrearInscripcionAction::validateMonedaPago()` rechaza `pendiente_usd` si la moneda declarada no
  es USD (defensa en profundidad, el frontend ya lo bloquea).

### Verified
- Migración/seeder corridos contra la BD de desarrollo (`event`), confirmado con el usuario antes
  de correr contra la real.
- `GET /event/{id}` (curl directo): `pendiente_usd` ausente sin link configurado, presente solo en
  eventos `usdPrecioFijo` del organizador correcto una vez cargado.
- Flujo completo en navegador contra `event_prod` (mirror local de la BD real de UAT): registro →
  correo con link real y vencimiento 24h → reporte/e-ticket en USD puro.
- Regresión explícita en Bs: SIP/Multipago/pendiente de un evento en Bs sin cambios de
  comportamiento; edición de `tiempo_expiracion_min` en un form_type en Bs ahora persiste el valor
  real (antes se reseteaba a 30) sin afectar nada más de ese formulario.

### Docs
- `ApiRestEvent-monolito` (worktree `consolidacion-monolito`) sincronizada el 25/08: 5 commits
  cherry-pickeados desde `questions` (incluye este feature completo) + porteo manual del lado
  admin-eventos a `App\Http\Controllers\Admin\*` (mismo botón/input, delegando en
  `RegistrationController::confirmarPagoManual()` vía `DelegatesToApiJson`, sin reimplementar
  lógica). 515/515 tests en verde antes y después. Sigue local, no desplegada.

## 2026-08-15 — Feature: CRUD de catálogos globales (País, Ciudad, Sexo, Tipo/Subtipo de evento, Relación de contacto)

El usuario pidió "crear tablas de catálogo país/ciudad/sexo/tipoevento/subtipoevento". Antes de
programar (plan mode) se encontró que 4 de esos 5 ya existían como tablas reales con datos
poblados (`paises`: 10, `ciudades`: 35, `tipos_evento`: 7, `subtipos_evento`: 18) pero **sin
ninguna pantalla de administración** — solo se podían tocar por BD directa. Solo "sexo" no
existía en absoluto. El usuario confirmó que lo que hacía falta era justamente eso: pantallas de
administración, no relacionar `categories.sexo_id` como FK real (queda fuera de alcance).

Durante la revisión del plan el usuario preguntó específicamente por el impacto en
`registrations`/`participantes` (confirmado: ninguno — `participantes.genero` es un campo
totalmente separado de `categories.sexo_id`) y notó que tampoco había catálogo para la relación
del contacto de emergencia — se agregó como sexto catálogo (`relaciones_contacto`), aditivo, sin
tocar `contacto_emergencia_participantes.relacion` (texto libre, con datos reales ya
inconsistentes: FAM/WIF/Familiar/FRI/Pareja/SPO/Hermano/HUS).

Los 6 catálogos son config global (no scoped por evento), solo `super_admin`, mismo patrón ya
usado por `Socio`/`Organizador`/`PresupuestoCategoria`.

### Added

- `ApiRestEvent`: migraciones `sexos`/`relaciones_contacto` (con seed inicial), modelos `Sexo`/
  `RelacionContacto`, controllers nuevos `PaisController`/`CiudadController`/`SexoController`/
  `SubtipoEventoController`/`RelacionContactoController` (CRUD completo, `assertIsSuperAdmin()`,
  auditoría vía `AdminAuditLogger`, `destroy()` bloquea con 409 si hay una relación dependiente en
  vez de soft-delete). `TipoEventoController` extendido con `adminIndex/store/update/destroy` sin
  tocar su `index()` público existente (activo-only, sin auth, consumido por el alta de evento).
  Rutas nuevas bajo prefijo `catalogos/` dentro de `auth:admins` (24 rutas), para no chocar con el
  `GET /tipos-evento` público.
- `tests/Feature/CatalogosGlobalesTest.php` — 11 tests (CRUD por catálogo, bloqueo de borrado con
  dependientes, 403 para admin no-superadmin, regresión explícita del endpoint público de
  tipos-evento). Suite completa: 275/275 en verde.
- `admin-eventos`: 6 controllers proxy nuevos (`PaisController`/`CiudadController`/
  `SexoController`/`TipoEventoController`/`SubtipoEventoController`/`RelacionContactoController`,
  mismo patrón que `SocioController`), 7 vistas (`catalogos/index` + una por catálogo, tabla con
  fila editable inline + tarjeta "+ Nuevo", Ciudad/Subtipo con `<select>` de su padre), rutas bajo
  `admin.superadmin`, link "Catálogos" en el menú.

**Verificado con datos reales** (no solo tests): sesión HTTP real completa (login super_admin con
CSRF+cookies) contra los 2 servidores locales — las 6 pantallas muestran los conteos reales
exactos (10/35/3/7/18/8), CRUD completo probado en vivo para Sexo (crear→editar→borrar) y Relación
de contacto (crear→borrar), y el caso de bloqueo real para País↔Ciudad (crear país+ciudad,
intentar borrar el país → 409 con el mensaje correcto → borrar ciudad → borrar país exitosamente).
Confirmado que el endpoint público `GET /tipos-evento` sigue exactamente igual. Datos de prueba
limpiados después, BD real vuelve a sus conteos originales exactos.

## 2026-08-15 — Feature: detalle de inscritos (drill-down desde el Dashboard)

Seguimiento del reporte de Modalidad/Categoría/Poleras (mismo día, ver entrada de abajo): el
usuario mostró un reporte legado con el listado fila-por-fila de cada inscrito (número, estado,
importe, CI, nombre, apellido, sexo, celular, fecha de inscripción, referencia, nacimiento,
distancia) y pidió llegar a ese detalle haciendo clic en las tarjetas de totales del Dashboard.

Planificado con `EnterPlanMode`/`ExitPlanMode` antes de programar. Se extendió el endpoint
existente en vez de crear uno nuevo — ya traía casi todos los campos, usado hoy por 2 pantallas de
edición (`NumeracionController`/`ParticipantesController` en admin-eventos) que no debían notar
ningún cambio.

### Added

- `ParticipanteController::porEvento()` — nuevo filtro opcional `pago_status`, campos nuevos
  `importe` (`participantes.subtotal`) y `fechaInscripcion` (`registration.fecha`), y **paginación
  opt-in** vía `per_page`: sin ese parámetro el comportamiento es idéntico al de siempre (sin
  `meta`, todo en una sola respuesta) — cero riesgo de regresión para los 2 llamadores existentes,
  que no lo mandan. `DashboardInscripcionesData::ESTADOS` pasó de `private` a `public` para que
  este filtro valide contra la misma lista sin duplicarla.
- `tests/Feature/ParticipantesPorEventoTest.php` — 5 tests (filtro por estado, campos nuevos,
  paginación activada vs. comportamiento por defecto sin paginar, scoping por evento). Suite
  completa: 264/264 en verde.
- `admin-eventos`: `ParticipantesDetalleController` (nuevo, no reutiliza `ParticipantesController`
  — esa es la pantalla de edición de contacto, otra UX) + vista
  `eventos/participantes-detalle.blade.php` (tabla + filtros por estado/categoría + paginación
  prev/next + descarga CSV sin paginar, mismo patrón de `NumeracionController::csvDownload()`).
  Rutas nuevas `participantes.detalle`/`participantes.detalle.csv`. Las 5 tarjetas del Dashboard de
  inscripciones ahora son links a esta pantalla, cada una con su filtro de estado correspondiente.

**Verificado con datos reales** (no solo tests): sesión HTTP real completa contra los 2 servidores
locales (login real con CSRF, cookies) usando un admin temporal scoped al evento 90007 — dashboard,
detalle sin filtro, detalle filtrado por `paid`, paginación (`per_page=2`, ambas páginas), y CSV,
todos verificados con datos reales antes de borrar el admin temporal. De paso se encontró que
`admin-eventos` tenía una **caché de rutas desactualizada** (`bootstrap/cache/routes-v7.php`,
generada antes de esta sesión) que hacía que las rutas nuevas devolvieran 404/405 hasta correr
`php artisan route:clear` — no relacionado con este feature, pero cualquier ruta nueva agregada
sin limpiar esa caché fallaría en silencio del mismo modo.

## 2026-08-15 — Feature: reporte de inscritos por modalidad/categoría + poleras

El usuario pidió un reporte parecido a uno de un sistema legado (4 tablas:
Modalidad/KIT, Distancia, Categoría, Poleras). Antes de construirlo se
comparó el pedido contra los datos reales (no solo el esquema), lo que
cambió 2 supuestos iniciales:

- **"Modalidad/KIT" no puede salir del souvenir elegido** — los souvenirs
  reales son ítems sueltos que un participante suma libremente (0, 1 o
  varios), no una elección excluyente; agrupar por souvenir rompe la
  propiedad de que todas las tablas sumen el mismo total. Se usa
  `form_types.name` en su lugar (único campo que reparte a cada inscrito
  en un solo grupo).
- **"Distancia" y "Categoría" son el mismo dato en este sistema** —
  `categories.name` ya es la distancia en los eventos reales ("5K", "10K",
  "21K", "7K"...), confirmado contra datos reales. No se duplica la tabla.
- **"Poleras" sale de los campos legacy `participantes.genero`/`polera`**
  (52/52 poblados en la BD real), no del sistema nuevo de souvenirs con
  talla/sexo genérico (casi sin uso real todavía).

Se agregó al dashboard de inscripciones existente (`GET
/event/{event}/dashboard-inscripciones`), no como pantalla nueva —
decisión del usuario para tener todo en un solo lugar.

### Added

- `app/Support/ReporteInscritosData.php` — agrupa participantes de
  inscripciones **pagadas** por tipo de formulario y por categoría (con
  Cantidad + Recaudación en dinero, sumando `participantes.subtotal`), y
  por sexo+talla de polera (Cantidad). Archivo hermano de
  `DashboardInscripcionesData` (cuenta por estado) y `BalanceEventoData`
  (suma dinero a nivel evento) — mezcla ambas cosas pero agrupadas, algo
  que ninguno de los otros 2 hace.
- `EventoController::dashboardInscripciones()` — nueva clave
  `reporteInscritos` en la respuesta.
- `tests/Feature/ReporteInscritosTest.php` — 2 tests (agrupación correcta
  con inscripciones pagadas/pendientes mezcladas, scoping por evento).
  Suite completa: 259/259 en verde.
- `admin-eventos`: `DashboardInscripcionesController`/
  `eventos/dashboard-inscripciones.blade.php` — 3 tablas nuevas debajo de
  las existentes ("Inscritos por modalidad", "Inscritos por categoría /
  distancia", "Reporte de poleras"), mismo estilo Tailwind ya usado en esa
  vista. Verificado contra datos reales del evento 90007 (4 inscritos
  pagados, 4 poleras) vía `curl` directo al endpoint, no solo el test
  sintético.

## 2026-08-15 — Fix: `403` falso en Caja al abrir turno, solo reproducible en UAT

Reportado por el usuario en UAT tras subir la feature de caja de cobro presencial
(`brain/api_rest_event/PLAN-CAJA-COBRO-PRESENCIAL-14082026.md`): un cajero
correctamente scoped a su evento recibía `403 "No tiene acceso a la caja de
este evento."` al abrir turno. No reproducible en local.

**Causa**: `AdminUser::evento_id` no tenía cast declarado, y
`AuthorizesEventoScope::assertCanWriteEvento()`/`assertCanOperarCaja()`
comparaban con `!==` (estricto). Según cómo el driver PDO de cada hosting
devuelva columnas enteras (nativo vs. "stringify", según la config de
emulación de prepares — este proyecto nunca la fija explícitamente),
`$admin->evento_id` podía llegar como `string` en UAT mientras el parámetro
de ruta llega como `int` → `"90013" !== 90013` da `true` → 403 aunque el
evento fuera el correcto. En local el driver devuelve `int` nativo, por eso
solo fallaba en UAT. Mismo patrón que el bug de `hasshirt` sin cast en
`FormTypeResource` (11/08).

### Fixed

- `app/Models/AdminUser.php` — agregado `'evento_id' => 'integer'` a
  `$casts` (normaliza el tipo sin importar el driver de cada entorno).
- `app/Http/Controllers/Concerns/AuthorizesEventoScope.php` — `(int)`
  explícito en las 2 comparaciones (`assertCanWriteEvento()` y
  `assertCanOperarCaja()`), como defensa adicional además del cast.
- Test de regresión en `tests/Feature/CajaTest.php` que simula el valor
  "stringificado" que devolvía el driver de UAT y confirma que el cast lo
  normaliza. Suite completa: 257/257 en verde.

## 2026-08-11 — Feature: certificados automáticos de congreso (Fase 2 de sesiones)

Segunda ronda de `brain/api_rest_event/PRD-Agenda-sessiones-onlycongresos.md`
— certificado automático al cierre de un evento tipo Congreso, con la
lista de sesiones a las que asistió cada participante (un solo
certificado por participante por evento, no uno por sesión, según lo
acordado con el usuario).

**⚠️ Este proyecto no tiene sandbox de email** (`MAIL_HOST=mail.inscrito.net`,
SMTP real de producción) — el comando nuevo, una vez activo en el
scheduler de un hosting con cron real, manda correos de verdad. Todo el
desarrollo/testing de esta feature usó `Mail::fake()`, cero envíos reales
disparados durante el trabajo.

### Added

- Tabla `certificados_congreso_enviados` (idempotencia por
  evento+participante). A diferencia de `NotificacionService`/
  `registration_notifications` (marca "enviado" aunque el SMTP falle),
  acá la fila **solo se crea si el envío tuvo éxito** — un fallo puntual
  se reintenta solo en la próxima corrida diaria, decisión deliberada
  documentada en la migración.
- `EnviarCertificadosCongresoAction` — un certificado por participante
  con asistencia registrada en el evento, agrupando todas sus sesiones
  asistidas; se salta a quien no tiene `correo`.
- `CertificadoCongresoMail` (adjunta PDF, mismo patrón que
  `PagoConfirmadoMail::build()`) + nueva rama `asistencia_congreso` en
  `tickets/certificados.blade.php` (ramas `participacion`/`asistencia`
  existentes intactas) — el PDF lista título/ponente/sala/fecha de cada
  sesión, no un genérico "asistió al evento".
- Comando `certificados:enviar-congreso`, programado `->daily()` en
  `routes/console.php` junto al resto de notificaciones (no hay urgencia
  de minutos — el trigger es el cierre del evento, ya evaluado una vez
  al día por `eventos:cerrar-finalizados`).
- `tests/Feature/EnviarCertificadosCongresoTest.php` — 6 tests, todos
  con `Mail::fake()` (patrón ya usado en `ExpirarInscripcionesPendientesTest`).
  Suite completa: 155/156 en verde (única falla, preexistente sin
  relación).

### Pendiente

- Correr la migración contra la BD real (dev, luego QA/producción).
- **No activar el comando en un scheduler con cron real sin antes
  revisar con el usuario qué eventos Congreso cerrados existen hoy** —
  el primer corrido mandaría certificados a cualquiera con asistencia
  registrada, incluyendo datos de prueba viejos si los hubiera.
- Sin modo `--dry-run` en el comando todavía — si se necesita antes de
  activarlo en un entorno con cron real, agregarlo aparte.

## 2026-08-11 — Feature: agenda y sesiones de congreso (config + check-in por sala)

Primera ronda de `brain/api_rest_event/PRD-Agenda-sessiones-onlycongresos.md`
— config de sesiones (ponente/sala/horario/cupo) + check-in de staff por
sesión (individual y masivo) + reporte de asistencia/concurrencia.
Certificados automáticos y "elegir sesiones durante el registro" quedan
explícitamente fuera de esta ronda (ver `elascenso/event/brain/`, sesión
11/08/2026).

### Added

- Tablas `sesiones_congreso` (FK opcional a `agenda_items` — cronograma
  visual existente, sin cupo/inscripción/asistencia, no se reusa como
  base) y `asistencia_sesion` (`staff_admin_user_id` explícito en la
  fila, a diferencia de `participantes.checked_in_at` que solo queda en
  el audit log genérico; unique por sesión+participante). Probadas
  contra `event_testing`, **no corridas contra la BD real** todavía.
- `SesionCongresoController` (CRUD scoped por evento, admin scoped a su
  evento o super_admin — no solo-superadmin) y
  `AsistenciaSesionController`: `lookup` (por referencia, scoped a
  evento+sesión), `checkin` individual (idempotente, gate de pago y de
  cupo), `checkinBulk` (parcial, no todo-o-nada — cada participante se
  evalúa por separado), `reporte` (% de concurrencia contra el total de
  pagados del evento, documentado por qué ese denominador).
- 14 tests Pest nuevos (`SesionCongresoTest`, `AsistenciaSesionTest`).
  Suite completa: 149/150 en verde (única falla, preexistente sin
  relación).
- Panel `admin-eventos`: "Sesiones de congreso" (CRUD), "Acreditar" por
  sesión (cámara + manual + check-in masivo, reusa `html5-qrcode` igual
  que la acreditación general) y "Reporte de asistencia". Link visible
  solo si el evento es tipo "Congreso / No aplica".

### Fixed (colateral, en el propio código de esta feature)

- `agenda_items.id` es `int unsigned` (no `bigint`, a diferencia del
  resto de las tablas) — la FK de `sesiones_congreso.agenda_item_id` se
  declaró con el tipo exacto en vez de `foreignId()` para no romper el
  `ALTER TABLE` (errno 150).

### Pendiente

- Correr las migraciones contra la BD real (dev, luego QA/producción).
- Certificados automáticos disparados por `checkin_at` — fase aparte
  (implica envío real de emails).
- Selección de sesiones durante el registro del participante — tocaría
  el monolito `elascenso/event` (activo en producción), fuera de alcance
  de "cambios en admin-eventos".

## 2026-08-11 — Feature: presupuesto de un evento (control financiero del organizador)

Ver `brain/api_rest_event/PRD-presupuesto_de_un_evento.md` (el archivo
tenía el contenido equivocado — el plan del ETL pegado por error — y se
corrigió en esta misma sesión). Distinta de la liquidación de utilidades
entre socios (más abajo): esta es del organizador por evento, no
solo-superadmin, y no reparte el service fee sino que el organizador
registra sus propios ingresos/gastos manuales.

### Added

- Tablas `presupuesto_categorias` (catálogo de rubros, solo super_admin,
  seed inicial Marketing/Logística/Premios como gasto y Patrocinio/
  Donación como ingreso) y `presupuesto_evento` (movimientos manuales,
  `tipo` denormalizado y validado contra la categoría al crear). Probadas
  contra `event_testing`, **no corridas contra la BD real** todavía.
- `BalanceEventoData::paraEvento()` — archivo hermano de
  `DashboardInscripcionesData` (mismo patrón de fachada estática), suma
  el neto del organizador por inscripciones (`inscripcion+donacion+souvenirs-descuentos`,
  **sin** el service fee, que nunca llega al organizador — se confirmó en
  `review-payment.js` que se suma arriba del precio, no se descuenta) más
  ingresos/gastos manuales, con la utilidad neta resultante.
- `GET/POST/PUT/DELETE /event/{event}/presupuesto(/{presupuesto})` — a
  diferencia de Socios/Liquidación, el admin scoped a su propio evento
  también puede operar (`assertCanWriteEvento`, no `assertIsSuperAdmin`).
  CRUD `/presupuesto-categorias` sí es solo super_admin.
- `EventoController::dashboardInscripciones()` y
  `OrganizadorDashboardController::show()` (el link firmado sin login del
  organizador) ahora incluyen `balance` en la respuesta — mismo dato,
  ambos lugares, sin duplicar el cálculo.
- `tests/Feature/PresupuestoCategoriaTest.php` (4 tests) y
  `PresupuestoEventoTest.php` (5 tests, incluye verificación explícita de
  que el balance excluye el fee). Suite completa: 135/136 en verde (única
  falla, preexistente sin relación).
- Panel `admin-eventos`: pantalla "Presupuesto" por evento (accesible por
  admin scoped o super_admin) y "Categorías de presupuesto" (superadmin),
  más la sección de balance agregada al dashboard de inscripciones
  existente.

### Pendiente

- Correr las migraciones contra la BD real (dev, luego QA/producción).
- Sin conversión de moneda — `moneda` es informativo, el balance asume
  que todos los montos de un evento están en la misma moneda.

## 2026-08-11 — Feature: consolidación financiera (liquidación de utilidades)

Primer pilar implementado del PRD
`brain/api_rest_event/PRD-Consolidacion-only-superadmin.md` (4 pilares:
financiero/estadístico/técnico/dashboard) — se arrancó por el financiero,
marcado en el propio PRD como el más crítico. Reparte el service fee ya
cobrado (`registration_totals.fee`) de un evento cerrado entre los socios
de PassToGo, solo super_admin.

### Added

- Tablas `socios` (config global, % editable sin deploy), `liquidaciones`
  (1 por evento, `evento_id` unique) y `liquidacion_detalles` (snapshot de
  nombre/% de cada socio al momento de liquidar — editar un socio después
  no reinterpreta liquidaciones viejas). Migraciones
  `2026_08_11_100000/100100/100200_*`, seed inicial de los 4 socios reales
  (Mario 40%/Carlitos 35%/Galia 15%/Norman 10%). Probadas contra
  `event_testing`, **no corridas contra la BD real** todavía.
- `LiquidarEventoAction` — `calcular()` (preview, no persiste, nunca
  lanza excepción de negocio) y `handle()` (persiste, valida evento
  cerrado + porcentajes de socios activos sumando 100%, último socio
  absorbe el residuo de redondeo para que la suma cierre exacta al
  centavo).
- `GET /event/{event}/liquidacion/preview`, `GET /event/{event}/liquidacion`,
  `POST /event/{event}/liquidacion`, y CRUD `/socios` — todos
  `assertIsSuperAdmin()`, auditados vía `AdminAuditLogger`.
- `tests/Feature/LiquidacionTest.php` (7 tests) y `SocioTest.php` (5
  tests). Suite completa: 126/127 en verde (única falla, preexistente sin
  relación con esta feature).
- Panel `admin-eventos`: pantalla "Socios" (CRUD) y "Liquidación" por
  evento (preview → confirmar → solo lectura), ambas bajo
  `admin.superadmin`.

### Pendiente

- Correr las migraciones contra la BD real (dev, luego QA/producción).
- Pilares 2-4 del PRD (keyword/histórico, tráfico web, dashboard de
  recaudación/aforo/stock) — fuera de alcance de esta ronda.
- Fase 2 del PRD ("conciliación con organizadores", comisión 4% al
  organizador) — explícitamente no implementada, el monto liquidado hoy
  es el service fee que ya se cobraba, no una comisión nueva.
- Sin flujo de "deshacer" una liquidación ya confirmada.

## 2026-08-10 — Feature: acreditación (check-in) escaneando el QR de referencia

El QR de referencia (e-ticket/PDF/email, `ReferenceQrService`) existía
hace tiempo pero no tenía ningún consumidor — usuario preguntó a dónde
llevaba, se investigó (solo codifica texto plano, sin URL) y se construyó
el primer consumidor real: un flujo de acreditación en el panel
(`admin-eventos`).

### Added

- Columna `checked_in_at` (timestamp, nullable) en `participantes` —
  migración `2026_08_10_160000_add_checked_in_at_to_participantes_table`,
  sin backfill (arranca en NULL, correcto). Probada contra `event_testing`,
  **no corrida contra la BD real** todavía.
- `GET /event/{event}/checkin/{reference}` (`RegistrationController::checkinLookup`)
  — busca la inscripción scoped al evento (404 si la referencia es de otro
  evento), devuelve participantes con categoría ya resuelta a nombre.
- `PATCH /participantes/{participante}/checkin` (`ParticipanteController::checkin`)
  — marca presente. Gate real: 422 si `pago_status !== 'paid'`. Reescanear
  a alguien ya acreditado no es un error — devuelve `alreadyCheckedIn:true`
  sin pisar el timestamp original. Auditado vía `AdminAuditLogger`.
  Ambos endpoints detrás de `auth:admins`, mismo scoping que Numeración
  (`assertCanWriteEvento`).
- `ParticipanteResource` y el array manual de `porEvento()` ganan
  `checkedInAt` (y `porEvento()` también `pagoStatus`, para el contador
  "X de Y acreditados" del panel).
- `tests/Feature/CheckinTest.php` — 6 tests (lookup con categoría
  resuelta, 404 evento equivocado, 422 sin pagar, éxito, reescaneo
  idempotente, 403 sin permiso). Suite completa: 114/115 en verde (única
  falla, preexistente sin relación).

### Pendiente

- Correr la migración contra la BD real (dev, luego QA/producción).
- No hay reconocimiento offline ni integración con `resultados.estado`
  (DNS/DNF) todavía — fuera de alcance de esta primera versión.

## 2026-08-10 — Fix: "Categoría: 1" sin resolver en email/PDF de ticket

Mismo bug de origen que el de Numeración/Participantes (`participantes.categoria`
guarda el ID, no el nombre) — acá afectaba a `resources/views/emails/partials/participantes.blade.php`,
compartida por el email de confirmación, el recordatorio de kit y el PDF
del e-ticket (los 3 mostraban el ID crudo, ej. "Categoría: 1", en vez del
nombre, "Categoría: 5K"). Se resuelve el nombre vía
`$registration->evento->categories`, con fallback al valor crudo si no
matchea ninguna categoría (dato legacy inconsistente o evento sin
categorías) — no rompe la vista en ese caso. Verificado renderizando la
vista contra una inscripción real (`categoria="28"` → `"10K"` en el HTML
resultante). Suite completa: 108/109 en verde (única falla, preexistente
sin relación).

## 2026-08-10 — Fix: filtro por categoría roto en Numeración/Participantes

Bug reportado: el filtro por categoría de las pantallas de Numeración de
corredor/chip y Participantes (`admin-eventos`) solo funcionaba con "Todas
las categorías". Causa: `participantes.categoria` guardaba el **ID** de la
categoría cuando la inscripción venía del registro online, pero el
**nombre** cuando venía de la carga masiva por CSV
(`RegistrationController::importarBulk`) — el filtro compara por ID, así
que solo encontraba a la minoría cargada por CSV. Detalle completo en
`elascenso/event/brain/BUG-FILTRO-CATEGORIA-NUMERACION-10082026.md`.

### Fixed

- `RegistrationController::importarBulk` ahora guarda `(string) $category->id`
  en vez de `$category->name`, igual que el registro online.

### Added

- Migración `2026_08_10_150000_backfill_participante_categoria_legacy_names_to_id`
  — normaliza los `participantes.categoria` ya existentes que quedaron
  guardados como nombre (no numéricos) al ID de la categoría que coincide
  por nombre (case-insensitive); los que ya son ID no se tocan. Probada
  contra `event_testing`, **no corrida contra la BD real** todavía — ver
  Pendiente.
- `tests/Feature/RegistroManualBulkTest.php` — 4 tests nuevos (guarda ID,
  matchea sin distinguir mayúsculas, categoría inexistente → 422, requiere
  rol super_admin). Suite completa: 108/109 en verde (la única falla es la
  preexistente sin relación, ya documentada en sesiones previas).

### Pendiente

- Correr la migración de backfill contra la BD real (dev y luego QA/
  producción) — contar el impacto primero
  (`SELECT COUNT(*) FROM participantes WHERE categoria NOT REGEXP '^[0-9]+$'`).

## 2026-08-10 — `hasDonation`/`hasPromoCode` de `eventos` a `form_types`

QA visual encontró que estos dos campos vivían a nivel de evento cuando
deberían vivir a nivel de form_type — un evento con 2 tipos de formulario
no podía permitir donación/promo en uno y no en el otro. Cambio aditivo
(Fase A): `eventos.hasDonation`/`hasPromoCode` **no se tocan todavía**,
para no romper a `admin-eventos`/`elascenso/event`/`elascenso-blade`
mientras migran. La limpieza final (borrar de `eventos`) es una Fase B
posterior, separada.

### Added
- `form_types.has_donation`/`has_promo_code` (boolean, default false) —
  migración con backfill: cada form_type existente hereda el valor que
  tenía su evento. Probada primero contra `event_testing` (1 evento/3
  form_types), no corrida contra la BD real de desarrollo todavía.
- `FormTypeDTO`/`StoreEventosRequest` (reglas anidadas `formTypes.*`)/
  `CrearEventoAction::createFormTypes()`: mismo patrón camelCase↔snake_case
  que ya usaban `hasTeam`/`hasDelivery`.
- CRUD standalone de form_type (`StoreFormTypeRequest`/
  `UpdateFormTypeRequest`/`FormTypeService::create()`): acepta los 2
  campos nuevos, snake_case (camino independiente del nested de evento).
- `FormTypeResource`: expone `hasDonation`/`hasPromoCode` por form_type.
- `FormType` model: agregado `$casts` (no existía ninguno antes).
- **Validación server-side nueva**: `RegistrationService::consumePromoCode()`
  ahora recibe `$formTypeId` y rechaza el código (`DomainException` → 422)
  si el form_type no tiene `has_promo_code=true`, aunque el código exista
  y no esté usado — antes no había ningún chequeo de elegibilidad en
  ApiRestEvent, solo la concurrencia. Defensa en profundidad: el frontend/
  proxy (`elascenso/event`, `elascenso-blade`) también lo valida ahora,
  pero esto no depende solo de eso.
- Tests nuevos: `FormTypeHasDonationPromoTest` (CRUD standalone),
  `EventoCreateTest` (creación anidada), `RegistrationTest` (2 casos del
  422 de promo nuevo) — 104/105 en verde (1 falla preexistente sin
  relación, ya documentada).

### Pendiente (Fase B, no en este cambio)
- Borrar `hasDonation`/`hasPromoCode` de `eventos`, `Evento` model,
  `EventoDTO`, `EventoResource`, `EventoFilter`, `EventoService::update()`
  — recién cuando `admin-eventos`/`elascenso/event`/`elascenso-blade` ya
  lean de `form_types` en producción.
- Corregir `EventoFilter` (clave `'hasDonation'` duplicada), `hasQuestion`
  duplicado en `FormType::$fillable`, y `FormType::evento()` usando `'id'`
  en vez de `'event_id'` — 3 bugs preexistentes encontrados de paso,
  documentados pero fuera de alcance de este cambio.

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
