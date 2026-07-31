# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/).

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
