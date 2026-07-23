# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/).

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
