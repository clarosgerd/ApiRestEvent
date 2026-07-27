# Informe técnico — Sistema de Notificaciones (para DevOps / DBA)

**Fecha:** 2026-07-26
**Repo:** `ApiRestEvent` (backend) + `elascenso/event` (frontend) — cambios en ambos, ver §8.
**Diseño completo:** `elascenso/event/brain/PLAN-NOTIFICACIONES.md`
**Estado:** las 8 fases implementadas y probadas en local (81/81 tests). **Nada de esto está commiteado ni desplegado todavía** — este informe es la guía para hacerlo.

---

## 1. Resumen ejecutivo

El envío de correo (confirmación de pago, pendientes, recordatorios) se movió del frontend (`elascenso/event`, PHP plano con `mail()`) al backend (`ApiRestEvent`, Laravel Mail). Se agregaron recordatorios automáticos (30/15 días, reversión de cupo, KIT), dos canales de WhatsApp (uno que solo inserta en una tabla para un software externo, otro que llama directo a una instancia de OpenWA), y un envío mensual de marketing. Todo corre por el Scheduler de Laravel, disparado por **una sola línea de cron** que hay que agregar en el hosting.

## 2. Checklist de despliegue (orden sugerido)

1. `composer install` (sin `--no-dev` si van a correr tests ahí; con `--no-dev` en producción) — se agregó `barryvdh/laravel-dompdf` y sus dependencias (`dompdf/dompdf`, etc.). Ver §6.
2. Completar variables de `.env` que faltan (§5) — especialmente **SMTP real** y confirmar el endpoint de **OpenWA de producción**.
3. `php artisan migrate` — 4 migraciones nuevas, todas aditivas, ninguna destructiva (§3).
4. Agregar la línea de cron en cPanel (§4) — un solo cron, todo lo demás vive en código.
5. Confirmar que el software externo de WhatsApp tiene acceso de lectura a la tabla `mensaje` en esta misma base de datos (§7).
6. Configurar `whatsapp_canal` y los días de recordatorio por organizador si difieren de los defaults (§9) — por default nadie tiene WhatsApp activo (`whatsapp_canal = 'ninguno'`).
7. Verificar que `storage/app/private/` sea escribible por el usuario del proceso PHP (ya debería serlo, es donde Laravel guarda otras cosas) — ahí se acumulan las copias de auditoría de correos (§8).

## 3. Migraciones nuevas

Todas en `ApiRestEvent/database/migrations/`, todas aditivas (agregan tablas/columnas, no tocan datos existentes, todas con default seguro):

| Archivo | Qué hace |
|---|---|
| `2026_07_26_151642_create_registration_notifications_table.php` | Tabla `registration_notifications` — log de qué se envió (tipo+canal) por inscripción, para no duplicar envíos. |
| `2026_07_26_155809_add_notificacion_config_to_organizadores_table.php` | 6 columnas nuevas en `organizadores` (§9). |
| `2026_07_26_203537_create_mensaje_table.php` | Tabla `mensaje` — cola de salida para el software externo de WhatsApp (§7). |
| `2026_07_26_210344_add_marketing_preferences_to_personas_table.php` | 2 columnas nuevas en `personas`: `acepta_marketing` (bool, default true), `ultimo_envio_marketing_at` (nullable). |

No hay backfill/seed necesario — todas las columnas tienen default.

## 4. Cron / Scheduler

**Una sola línea**, agregada una única vez vía el panel "Cron Jobs" de cPanel (no requiere SSH/terminal):

```
* * * * * php /ruta/a/ApiRestEvent/artisan schedule:run >> /dev/null 2>&1
```

A partir de ahí, todo lo que corre está definido en código (`routes/console.php`) y se puede cambiar sin tocar el cron de nuevo:

| Comando | Frecuencia | Qué hace |
|---|---|---|
| `eventos:cerrar-finalizados` | diario | Cierra eventos con `fecha_fin` pasada. |
| `form_types:desactivar-cupo-lleno` | diario | Red de seguridad — el chequeo principal ya corre en caliente al registrar. |
| `notificaciones:recordatorio-pendientes` | diario | Avisos de 30/15 días para pendientes de pago. |
| `notificaciones:revertir-cupo` | diario | Cancela pendientes cuyo plazo de gracia venció. |
| `notificaciones:recordatorio-kit` | diario | Recordatorio de recojo de KIT para pagados. |
| `queue:work --stop-when-empty` | cada 8h | Procesa la cola de jobs (`QUEUE_CONNECTION=database`) — necesario para que el canal WhatsApp OpenWA efectivamente envíe. |
| `notificaciones:marketing-mensual` | diario | Se autolimita por día del mes internamente (ver `dia_envio_marketing` en §9), por eso corre diario. |

**Importante:** sin el `queue:work` corriendo, los jobs de WhatsApp OpenWA se acumulan en la tabla `jobs` sin procesarse — no fallan, simplemente esperan. Si en algún momento se necesita más frecuencia que cada 8h para ese canal, es un cambio de una línea en `routes/console.php` (`->cron('0 */8 * * *')`), no requiere tocar cPanel.

## 5. Variables de entorno (`.env`)

### Pendientes de completar (bloquean producción)

```
MAIL_MAILER=smtp          # hoy está en "log" a propósito — no hay credenciales SMTP reales todavía
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
```
Mientras `MAIL_MAILER=log`, los correos no salen de verdad — quedan en `storage/logs/laravel.log`. **Nada se envía a destinatarios reales hasta que se complete esto.**

### A confirmar/ajustar para producción

```
OPENWA_BASE_URL=http://localhost:2785     # esto es la instancia de DESARROLLO — confirmar el endpoint real de producción
OPENWA_API_KEY=owa_k1_...                 # idem, la key de desarrollo no debería usarse en prod
OPENWA_SESSION_ID=bdfbdee8-...
```
Ya están wireados y probados contra la instancia de desarrollo (`http://localhost:2785`) — auth y conectividad confirmadas, incluyendo un envío real de WhatsApp a un número real. Antes de habilitar el canal `openwa` para cualquier organizador en producción, hay que apuntar estas 3 variables a la instancia/sesión reales. **El `OPENWA_SESSION_ID` cambia si la sesión se recrea** — si `queue:work` empieza a fallar con `404 Session not found`, es la primera causa a revisar (confirmar el id vigente vía `GET /api/sessions`, no asumir que el de `.env` sigue siendo el correcto).

**Bug conocido del servidor de OpenWA — monitorear `failed_jobs`:** se confirmó en pruebas (26/07/2026, con mensajes reales) que esta instancia a veces entrega el mensaje de WhatsApp correctamente y **recién después** devuelve `500 Internal Server Error` al responder. `SendWhatsappMessageJob` ya está corregido para no reintentar en ese caso específico (evita duplicados reales a personas), pero el job queda registrado en `failed_jobs` para revisión manual. Si `failed_jobs` empieza a acumular muchas entradas de `SendWhatsappMessageJob` con status 500, es esperable — no reintentar es intencional, no es un cron roto. Vale la pena reportarle este bug a quien mantiene el servidor de OpenWA en sí (fuera de este repo).

### Ya configuradas, sin acción

`QUEUE_CONNECTION=database` (tablas `jobs`/`failed_jobs` ya existen, son parte del scaffold estándar de Laravel).

## 6. Dependencias nuevas (composer)

`barryvdh/laravel-dompdf` (^3.1) — genera el PDF adjunto del correo de pago confirmado. Sin dependencias de sistema (no requiere wkhtmltopdf, Node, ni binarios) — funciona en un hosting cPanel sin acceso a terminal para instalar nada aparte de `composer install`.

## 7. Tabla `mensaje` — coordinación con el software externo de WhatsApp

Vive en la **misma base de datos** que `ApiRestEvent` (no es una BD separada). El software externo de WhatsApp solo necesita permiso de `SELECT`/`UPDATE` sobre esta tabla (nosotros solo hacemos `INSERT`, ellos actualizan `estado` cuando procesan). Si ese software corre con un usuario de MySQL distinto al de `ApiRestEvent`, hay que otorgarle esos permisos explícitamente.

Estructura (charset `utf8mb4`, no `latin1` como se pidió originalmente — a confirmar si el software externo lo requiere estrictamente en `latin1`, ver `PLAN-NOTIFICACIONES.md` §2.4):

```sql
id          int unsigned auto_increment primary key
celular     bigint unsigned null      -- dígitos únicamente, con código de país (ej. 59171234567)
mensaje     varchar(500) null
email       varchar(100) null         -- referencia, no se usa para enviar
tipo        varchar(3) not null       -- PEN|R30|R15|REV|KIT
estado      int not null default 0    -- lo actualiza el software externo
fecha       datetime default current_timestamp
prioridad   int not null default 5
```

## 8. Coordinación de despliegue entre repos

- `ApiRestEvent`: todos los cambios de backend (§2–§7).
- `elascenso/event` (frontend): se eliminó `api/email.php` y las llamadas a él desde `index.php` — el frontend ya no envía correo, solo notifica cambios de estado a la API externa (como ya hacía). **No depende de un endpoint nuevo** — el trigger vive del lado de `ApiRestEvent` en las mismas transiciones de estado que el frontend ya reportaba antes (creación de registro, confirmación de pago). Se puede desplegar el frontend en cualquier momento relativo al backend sin romper nada, pero **hasta que el backend tenga SMTP real, no sale ningún correo** (ni el que antes mandaba el frontend, que ya no existe).

## 9. Configuración pendiente por organizador

Nuevas columnas en `organizadores`, todas con default, pero **el default de `whatsapp_canal` es `'ninguno'`** — hay que setearlo explícitamente (`'openwa'` o `'externo'`) para cada organizador que deba usar WhatsApp:

| Columna | Default | Qué controla |
|---|---|---|
| `dias_recordatorio_pendiente_1` | 30 | 1er aviso de pago pendiente |
| `dias_recordatorio_pendiente_2` | 15 | 2do aviso (con advertencia de reversión) |
| `dias_gracia_reversion` | 3 | Días desde el 2do aviso hasta cancelar automáticamente |
| `dias_recordatorio_kit` | 5 | Recordatorio de recojo de KIT antes del evento |
| `dia_envio_marketing` | 15 | Día del mes en que sus personas reciben el correo de marketing |
| `whatsapp_canal` | `ninguno` | `ninguno` / `openwa` / `externo` — mutuamente excluyentes |

No hay panel de administración para editar esto todavía — es directo en BD (`UPDATE organizadores SET ... WHERE id = ?`) hasta que se construya una UI.

## 10. Almacenamiento — sin rotación automática

Cada correo enviado guarda una copia HTML en `storage/app/private/emails/` (formato `{referencia}_{tipo}_{fecha}.html`). No hay limpieza/rotación automática — va a crecer indefinidamente. Sugerido: un cron de housekeeping (fuera de este alcance) que archive o borre copias de más de N meses, cuando el volumen lo justifique.

## 11. Seguridad — URLs firmadas

El link de opt-out de marketing (`/marketing/opt-out/{persona}`) usa signed routes de Laravel, firmadas con `APP_KEY`. **Si `APP_KEY` se rota, todos los links de opt-out ya enviados en correos anteriores dejan de funcionar** (devuelven 403) — no es un problema de seguridad, pero cualquier persona con un correo de marketing viejo en su bandeja no podrá darse de baja con ese link después de una rotación de key. Tenerlo en cuenta si `APP_KEY` se rota por rutina.

## 12. Testing — nota para CI

El test suite (`php artisan test`, 81 tests) corre contra una base MySQL real dedicada, no SQLite (una migración usa `ALTER TABLE ... MODIFY`, sintaxis MySQL-only que SQLite no soporta). Configurado en `phpunit.xml`:

```
DB_CONNECTION=mysql
DB_DATABASE=event_testing   # separada de la BD real "event", RefreshDatabase la migra desde cero en cada corrida
```

Si hay un pipeline de CI, necesita provisionar esta base (`CREATE DATABASE event_testing`) con las mismas credenciales que usa localmente (root sin password) o las que se configuren — nunca apuntar esto a la base de producción/demo.
