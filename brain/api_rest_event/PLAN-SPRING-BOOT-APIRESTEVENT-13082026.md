# Plan: versión Spring Boot de ApiRestEvent

13/08/2026 — a pedido del usuario ("queremos tener una versión java del apirestevent con
spring-boot"). Documento de planificación, todavía no arrancó la implementación.

## Contexto

`ApiRestEvent` (Laravel 12/PHP) es la fuente de verdad de todo el ecosistema Pass2Go: **47
controllers, 47 models, 91 migraciones, 4 guards de auth Sanctum (`personas`/`clubes`/`admins`/
`web`), 61 Form Requests, 36 API Resources**, más integraciones (WhatsApp vía `openwa`, backup a
Google Drive, PDFs con `dompdf`, QR con `chillerlan/php-qrcode`) y jobs programados (13 en
`routes/console.php`).

**4 repos en vivo dependen hoy de su contrato JSON exacto**: `elascenso/event` (proxy
`api/_external_proxy.php`), `admin-eventos` (`ApiRestEventClient`), `elascenso/delivery`
(`ApiRestEventClient` propio), y `elascenso-blade` (mismo cliente, pausado en Fase 4 pero con el
mismo contrato). Ninguno puede quedar roto durante la transición.

**Objetivo confirmado por el usuario**: reemplazo completo a futuro, misma estrategia que ya se
usó para portar el frontend a `elascenso-blade` (construir con paridad completa, correr en
paralelo, cortar cuando esté validado — Fase 4 de esa migración sigue pausada esperando el corte
real, mismo patrón "strangler fig" que se aplica acá). **La BD sigue siendo la misma MySQL**
(`event_testing` local / `inscrito_event` en UAT-producción) — evita sincronizar datos entre dos
fuentes de verdad mientras conviven los dos backends.

## Arquitectura

- **Stack**: Spring Boot 3.x (Java 21 LTS), Spring Web, Spring Data JPA + Hibernate (mismo MySQL,
  `ddl-auto: none` — el esquema lo sigue gobernando Laravel/sus migraciones mientras dure la
  convivencia, Spring solo mapea entidades a las tablas existentes, nunca las crea/altera).
- **Auth**: Spring Security + JWT, un `SecurityFilterChain` por guard (`personas`/`clubes`/
  `admins`), replicando el criterio de scoping que ya usan `AuthorizesEventoScope` (admin scoped a
  su propio evento vs. `super_admin`) y `NormalizeAuthTokenHeader` (aceptar `X-Auth-Token` como
  respaldo de `Authorization` — necesario si el hosting de destino tiene el mismo problema de
  mod_lsapi que UAT hoy, ver `project_uat_mod_lsapi_auth_bug` en la memoria del asistente).
- **Validación**: Jakarta Bean Validation (`@Valid` + DTOs), traduciendo 1 a 1 las reglas de cada
  `FormRequest::rules()`.
- **Repo nuevo**: `ApiRestEvent-java` (o el nombre que se prefiera), estructura estándar
  Maven/Gradle multi-módulo por dominio (`persona`, `evento`, `registro`, `delivery`, `admin`,
  etc.) — no un solo paquete gigante, para que cada fase del roadmap sea un módulo autocontenido.
- **Corte gradual (strangler fig)**: un reverse proxy (nginx) delante de ambos backends, que
  rutea por path — los endpoints ya portados y validados van a Spring Boot, el resto sigue yendo
  a Laravel. Esto permite migrar **endpoint por endpoint en producción real** sin que ningún
  cliente (los 4 repos) tenga que cambiar su `EXTERNAL_API_BASE` hasta el corte final completo.
- **Paridad = tests, no revisión visual** — mismo criterio que ya dio resultado real en
  `elascenso-blade` (80 tests Pest, encontró y arregló un bug real de verdad): un mismo suite de
  tests HTTP de contrato corre contra **los dos backends** (mismo request, mismo evento/datos de
  prueba en `event_testing`) y compara las respuestas — no se declara "portado" un endpoint sin
  ese test en verde contra ambos.

## Roadmap por fases (orden por lo que más bloquea al resto)

1. **Fase 0 — Esqueleto + auth**: proyecto Spring Boot, conexión a `event_testing`, los 4
   `SecurityFilterChain`, endpoints de login (`/persona/login`, `/club/login`, `/admin/login`) y
   `GET /event`, `GET /event/{id}` (público, sin auth) — es lo mínimo que `elascenso/event`
   necesita para poder navegar el listado de eventos apuntando al nuevo backend en un ambiente de
   prueba aislado.
2. **Fase 1 — Flujo de inscripción (crítico de dinero)**: `POST /registrations` y el cálculo de
   totales — equivalente Java de `CrearInscripcionAction`/`RegistrationService` (categorías por
   período, souvenirs con stock/talla/sexo, promo codes, descuento de grupo, delivery+mapa,
   `pago_status`). Esta es la lógica más sensible a errores de paridad — el suite de contrato
   tiene que cubrir cada rama (con/sin categoría, con/sin promo, con/sin stock) antes de tocar
   nada de tráfico real.
3. **Fase 2 — Pagos**: la generación de QR propia (`QR_PROVIDER=new`, `chillerlan/php-qrcode` →
   ZXing en Java) y los webhooks `payment_callback`/`payment_callback_multipago` (estos los
   disparan las pasarelas directamente, no los repos cliente — la firma/validación de cada una
   hay que replicarla exacto, no solo el shape del JSON).
4. **Fase 3 — Panel de administración**: los endpoints que usa `admin-eventos` (CRUD de
   evento/categoría/form-type/souvenir/promo-code/auspiciador/agenda, numeración, acreditación,
   presupuesto, liquidación, lista de espera, sesiones de congreso) — mismo patrón
   `AuthorizesEventoScope` ya descrito arriba.
5. **Fase 4 — Delivery, resultados, clubes**: endpoints que usan `elascenso/delivery`
   (`DeliveryController`, incluido el nuevo `indexForAdmin` del 12/08), sync de ChronoTrack,
   `/personas/me/resultados`, login/landing de clubes.
6. **Fase 5 — Ops**: jobs programados (`@Scheduled` de Spring, equivalente a
   `routes/console.php`), backup a Google Drive, WhatsApp, generación de PDFs (gafetes/
   certificados).
7. **Corte final**: cuando todas las fases pasan el suite de contrato de forma sostenida, mover
   el reverse proxy a 100% Spring Boot y dejar Laravel como fallback apagado (no borrado) por un
   tiempo, igual criterio conservador que la Fase 4 pausada de `elascenso-blade`.

## Riesgos a vigilar

- **Promo codes**: `RegistrationService::consumePromoCode()` tiene una condición de carrera real
  documentada (2 participantes de la misma inscripción usando el último cupo) — la versión Java
  necesita la misma garantía atómica (transacción + lock), no solo el mismo resultado en el caso
  feliz.
- **Booleans sin cast**: bug real encontrado el 12/08 (`FormTypeResource::hasshirt` sin `(bool)`,
  serializaba como string `"0"` en un hosting con otra config de PDO) — Jackson (Spring) serializa
  tipos Java fuertes por defecto, así que esa clase específica de bug no se replica en Java, pero
  es el tipo de discrepancia sutil que el suite de contrato tiene que estar diseñado para
  detectar en general.
- **Alcance real**: 47 controllers es mucho — este roadmap prioriza por lo que bloquea el flujo
  de inscripción real primero; features de bajo tráfico (liquidación, presupuesto, congreso)
  quedan al final a propósito, no porque no importen.

## Verificación (por fase, no al final)

Cada fase se da por "lista" cuando: (1) el suite de contrato HTTP corre en verde contra Spring
Boot apuntando a `event_testing`, con los mismos datos de prueba que ya usa el equipo, y (2) al
menos un flujo real de punta a punta (ej. Fase 1 = inscribirse de verdad y ver el registro en la
BD) se probó a mano una vez, mismo criterio que se usó en cada checkpoint de la migración a
`elascenso-blade`.

## No cubierto en este plan (a decidir después)

- Nombre final del repo y organización del monorepo/multi-repo.
- Si el corte final también incluye pasar la propiedad del esquema de Laravel a Flyway/Liquibase
  en Java, o si Laravel sigue siendo dueño de las migraciones para siempre.
- Infraestructura de despliegue del nuevo servicio (mismo hosting cPanel que hoy, o algo nuevo —
  Spring Boot no corre nativo en shared hosting cPanel como PHP, así que esto probablemente
  necesita un ambiente distinto, a definir).
