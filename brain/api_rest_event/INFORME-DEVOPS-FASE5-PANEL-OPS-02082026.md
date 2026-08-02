# Informe técnico — Fase 5 panel admin + Panel de Operaciones (para DevOps)

**Fecha:** 2026-08-02
**Repos:** `ApiRestEvent` (backend) + `elascenso` (contiene `event/` SPA y `admin-eventos/` panel, mismo repo Bitbucket, carpetas distintas)
**Estado:** todo implementado y probado en local (`ApiRestEvent` 81/81 tests, misma 1 falla preexistente sin relación). **Nada de esto está commiteado ni desplegado todavía** — este informe es la guía para hacerlo.
**Contexto previo:** `brain/api_rest_event/DEPLOY_CPANEL.md` (guía general de deploy, especialmente §8 "solo tienes File Manager, sin SSH" — se referencia mucho acá), `elascenso/event/brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md` (diseño completo del panel admin-eventos, Fases 0-5).

---

## 1. Resumen ejecutivo

Dos entregas sobre `ApiRestEvent`, ninguna toca `elascenso/event` (la SPA de inscripción no tuvo cambios esta ronda, sigue funcionando igual — ver §7):

1. **Fase 5 del panel `admin-eventos`**: agrega promo codes, coordenadas, ruta, auspiciadores y agenda al alta/edición de eventos, más "despublicar". Sin dependencias nuevas, sin cron nuevo — solo controladores/rutas/vistas nuevas y 2 tablas ya existentes (`auspiciadores`, `agenda_items`) que no tenían controlador.
2. **Panel de Operaciones (`/ops`)**, nuevo, vive **dentro de `ApiRestEvent`** (no es un repo aparte): login propio contra la tabla `users` (hasta ahora sin uso real) y 4 pantallas — Jobs, Logs, Backups (a Google Drive) y Enlaces (organizador/delivery). **Este panel es en sí mismo el workaround a la falta de SSH**: reemplaza en el navegador operaciones que antes solo se podían hacer por consola (`queue:retry`, `organizador:generar-link`, `delivery:generar-link`, `backup:run`).

**Lo único que falta para que el backup suba de verdad a Drive son credenciales de Google Cloud** (§9) — todavía no se probó el upload real, solo hasta el intento de conexión (falla ahí, como se espera sin credenciales).

## 2. Checklist de despliegue (orden sugerido)

1. **`ApiRestEvent` primero** (los otros dos dependen de sus endpoints/rutas):
   - `composer install` (agrega `spatie/laravel-backup`, `masbug/flysystem-google-drive-ext` y sus dependencias — ver §5).
   - Completar `.env` (§6).
   - Migrar (§4) — 1 tabla nueva, aditiva.
   - Setear password a la fila de `users` que va a operar `/ops` (§8.3 — no hay seeder, es a mano).
   - Cron: agregar 2 líneas a `routes/console.php` si el archivo de producción no se actualiza solo vía deploy (ya debería, es código versionado) — **no hace falta tocar el cron de cPanel**, la línea `schedule:run` que ya corre cada minuto (ver `DEPLOY_CPANEL.md` §7) toma los comandos nuevos automáticamente.
2. **`admin-eventos`**: sin cambios de Fase 5 que requieran nada especial de infraestructura — mismo deploy que ya está documentado (Blade, sin BD, `EXTERNAL_API_BASE` apuntando al `ApiRestEvent` ya desplegado).
3. **`elascenso/event`**: nada que desplegar de esta ronda — confirmar igual que la API pública (`GET /event/{id}`) sigue respondiendo igual (es 100% aditivo, ver §7).
4. Completar Google Cloud (§9) quedó **fuera del checklist de deploy en sí** — puede hacerse antes o después de subir el código, el primer `backup:run` real recién funciona cuando esté listo.

## 3. Qué cambió, por repo

### `ApiRestEvent`
- Fase 5: `AuspiciadorController`, `AgendaItemController` (nuevos, antes esas 2 entidades no tenían controlador aunque el modelo/tabla ya existían), `EventoController::despublicar()`, fix de `id` faltante en `CoordinateResource`/`RouteResource`/`PromoCodeResource`. Rutas nuevas bajo `/auspiciador`, `/agenda-item`, `/event/{event}/despublicar`.
- Panel `/ops`: `OpsAuthController`, `OpsJobController`, `OpsLogController`, `OpsBackupController`, `OpsLinkController` + vistas Blade en `resources/views/ops/`. `App\Providers\GoogleDriveServiceProvider` (disco `google` custom). Listeners `RecordSuccessfulBackup`/`RecordFailedBackup`/`RecordSuccessfulCleanup` (registrados a mano en `AppServiceProvider::boot()` — este proyecto no tiene auto-discovery de eventos activado).

### `elascenso/admin-eventos`
- Fase 5: 5 controladores nuevos (`PromoCode`, `Coordinate`, `Route`, `Auspiciador`, `AgendaItem`), secciones nuevas en `eventos/create.blade.php` y `eventos/edit.blade.php`, botón "Despublicar" en el dashboard. Sin cambios de infraestructura (sigue sin BD propia, sin dependencias nuevas).

### `elascenso/event`
- Sin cambios.

## 4. Migraciones nuevas

Todas en `ApiRestEvent/database/migrations/`, aditivas:

| Archivo | Qué hace |
|---|---|
| `2026_08_02_222116_create_backup_runs_table.php` | Tabla `backup_runs` — historial de corridas de backup/limpieza (`type`, `status`, `disk`, `filename`, `size_bytes`, `error_message`, `triggered_by_user_id` → `users`). La lee `/ops/backups`. |

**Fase 5 no agregó migraciones** — `auspiciadores`/`agenda_items` ya existían (tablas creadas en una fase anterior, solo les faltaba el controlador).

## 5. Dependencias nuevas (composer)

```
spatie/laravel-backup        ^9.3   (dump + zip + destinos Flysystem + limpieza por retención)
masbug/flysystem-google-drive-ext  ^2.5   (adaptador Google Drive para Flysystem 3)
```
Arrastran `google/apiclient`, `google/auth`, `firebase/php-jwt`, `spatie/db-dumper`, `spatie/temporary-directory` (transitivas, no requieren configuración propia). **De paso se subió `guzzlehttp/guzzle` a 7.15.2** (venía en 7.14.1, con 4 advisories de seguridad medium sin CVE — `composer audit` queda en 0 tras el bump, sin romper nada).

Sin dependencias de sistema nuevas más allá de `mysqldump` (ver §6 — probablemente ya está en el PATH en cPanel, a diferencia de XAMPP local).

## 6. Variables de entorno (`.env`, `ApiRestEvent`)

### Pendientes de completar (bloquean el backup real, no bloquean el resto)

```
GOOGLE_DRIVE_SERVICE_ACCOUNT_PATH=   # ruta al JSON de la cuenta de servicio (ver §9)
GOOGLE_DRIVE_FOLDER_ID=              # ID de la carpeta de Drive compartida con esa cuenta
```
Mientras estén vacías, `backup:run` falla al conectar el disco `google` (queda registrado como "Falló" en `/ops/backups`, no rompe nada más).

### A confirmar en el servidor real

```
MYSQLDUMP_BINARY_PATH=
```
Vacío por defecto — solo hace falta si `mysqldump` no está en el `PATH` del proceso PHP. En local (XAMPP) hubo que apuntarlo a `C:/xampp/mysql/bin` (con `/`, no `\` — el parser de dotenv rompe con backslashes sin escapar). **En cPanel probablemente no hace falta tocarlo** (la mayoría de los hosts Linux tienen `mysqldump` en el PATH del sistema), pero si `backup:run` falla con "mysqldump no reconocido"/"command not found", esta es la variable a setear — confirmar la ruta con soporte del hosting o probando `which mysqldump` si hay Terminal.

### Ya configuradas, sin acción

Nada más nuevo — el resto de `.env` no cambió.

## 7. Cron / Scheduler

**No hace falta agregar ni tocar ninguna línea de cron en cPanel** — la línea de `schedule:run` cada minuto ya documentada en `DEPLOY_CPANEL.md` §7 / `INFORME-DEVOPS-DBA-NOTIFICACIONES.md` §4 sigue siendo la única necesaria. Se agregaron 2 comandos nuevos a `routes/console.php` (código, se despliega solo):

| Comando | Frecuencia | Qué hace |
|---|---|---|
| `backup:run --only-db` | diario | Dump de la BD `event`, sube a Google Drive. |
| `backup:clean` | diario | Aplica la retención (14 diarios + 8 semanales) — corre después del run, nunca borra el backup más reciente. |

## 8. Sin SSH en cPanel — cómo operar cada pieza nueva

Esta sección asume que se sigue el mismo escenario que `DEPLOY_CPANEL.md` §8 (solo File Manager, sin Terminal/SSH).

### 8.1 Migrar `backup_runs` sin `artisan migrate`

Igual que en `DEPLOY_CPANEL.md` §8 — vía phpMyAdmin, exportar la estructura de esa tabla desde local (`event`) e importarla en la BD de producción. Es una tabla nueva sola, no hace falta exportar todo:

```sql
CREATE TABLE `backup_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL DEFAULT 'backup',
  `status` varchar(255) NOT NULL,
  `disk` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `error_message` text,
  `triggered_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `backup_runs_triggered_by_user_id_foreign` (`triggered_by_user_id`),
  CONSTRAINT `backup_runs_triggered_by_user_id_foreign` FOREIGN KEY (`triggered_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

También sirve la ruta temporal con token de `DEPLOY_CPANEL.md` §8 (`Artisan::call('migrate', ['--force' => true])`) si se prefiere correr la migración real — borrar el archivo apenas se usa.

### 8.2 Reintentar/gestionar jobs sin SSH

Antes esto requería `php artisan queue:retry {uuid}` por consola. Ahora: **`/ops/jobs`** — misma acción, desde el navegador. Ídem para eliminar jobs pendientes atascados o fallidos.

### 8.3 Setear la contraseña de la cuenta de `/ops` sin SSH

No hay seeder ni pantalla de alta todavía (a propósito, fuera de alcance — ver memoria `project_ops_panel_jobs_logs_backups`). Sin Terminal, la única vía es un `UPDATE` directo por phpMyAdmin:

```sql
UPDATE users SET password = '$2y$12$...' WHERE email = 'el-email-que-va-a-operar-ops';
```
El hash tiene que generarse con bcrypt (`Hash::make()`); si no hay forma de correrlo en el servidor, generarlo en local (`php artisan tinker --execute="echo Hash::make('la-contraseña');"`) y pegar el resultado en el `UPDATE`.

### 8.4 Generar enlaces de organizador/delivery sin SSH

Antes: `php artisan organizador:generar-link {evento}` / `delivery:generar-link {evento}`. Ahora: **`/ops/enlaces`** — elegís el evento de una lista, te da los 4 links con botón de copiar. Mismo resultado exacto (misma función `URL::signedRoute`), sin consola.

### 8.5 Ejecutar un backup manual sin SSH

Antes no existía forma de correr `backup:run` sin consola. Ahora: **`/ops/backups`** → botón "Ejecutar backup ahora" (síncrono, espera a que termine).

### 8.6 Ver logs sin File Manager

**`/ops/logs`** muestra las últimas 300 líneas de `scheduler.log`/`laravel.log` sin necesidad de entrar a `storage/logs/` por File Manager.

## 9. Google Cloud — pasos manuales pendientes (nadie más puede hacerlos)

1. Crear/elegir un proyecto en [Google Cloud Console](https://console.cloud.google.com).
2. Habilitar la **Google Drive API** para ese proyecto.
3. Crear una **cuenta de servicio** (IAM & Admin → Service Accounts) y descargar su clave JSON.
4. En Google Drive, crear una carpeta para los backups y compartirla con el email de la cuenta de servicio (termina en `...iam.gserviceaccount.com`, está en el JSON) como **Editor**.
5. Copiar el ID de esa carpeta (de la URL `drive.google.com/drive/folders/<ID>`).
6. Subir el JSON a `storage/app/google-drive-service-account.json` en el servidor (gitignored — nunca commitear) y completar `.env` (§6) con la ruta y el ID de carpeta.
7. Primera prueba real: `/ops/backups` → "Ejecutar backup ahora", o `php artisan backup:run --only-db` si hay consola.

## 10. Checklist final antes de dar por cerrado este deploy

- [ ] `composer install` corrido en `ApiRestEvent` sin errores (packages nuevos de §5)
- [ ] `backup_runs` existe en la BD de producción (§8.1)
- [ ] `.env` de `ApiRestEvent` con las variables de §6 (aunque `GOOGLE_DRIVE_*` queden vacías por ahora, no bloquean el resto)
- [ ] Fila en `users` con password seteado, puede loguear en `/ops/login`
- [ ] `/ops/jobs`, `/ops/logs`, `/ops/enlaces` cargan sin error 500
- [ ] `/ops/backups` → "Ejecutar backup ahora" corre y queda registrado en la tabla (falla esperada mientras no haya credenciales de Google — igual confirma que el resto del flujo funciona)
- [ ] `admin-eventos`: `/eventos/{id}/edit` muestra las secciones nuevas (coordenadas/ruta/promo codes/auspiciadores/agenda) sin error
- [ ] Botón "Despublicar" visible en el dashboard para eventos publicados
- [ ] `elascenso/event` sigue funcionando igual (`GET /event/{id}` responde con los mismos campos de siempre, más los nuevos si se cargaron)
- [ ] Cuando lleguen las credenciales de Google (§9): primer backup real sube a Drive y aparece en `/ops/backups` como "OK"
