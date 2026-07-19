# Guía de Producción — Sistema de Notificaciones Multicanal

**Versión:** 1.0
**Fecha:** 2026-07-19
**Para:** Equipo de IT / DevOps

---

## 1. Requisitos previos

| Componente | Versión mínima | Notas |
|------------|---------------|-------|
| PHP | 8.2+ | Extensiones: curl, json, mbstring |
| Laravel | 12.x | Queue worker corriendo |
| SQLite/MySQL | - | Base de datos existente |
| OpenWA | Instancia activa | Para envío de WhatsApp |
| Servidor SMTP | - | Para envío de correos reales |

---

## 2. Migraciones

Ejecutar la nueva migración:

```bash
php artisan migrate
```

Esto crea:
- Tabla `notifications` (estándar Laravel)
- Columna `notifications` (JSON) en tabla `users`
- Columna `notifications` (JSON) en tabla `registrations`
- Columna `pending_payment_notified_at` (timestamp nullable) en tabla `registrations`

**No afecta datos existentes.** Las columnas son nullable.

---

## 3. Variables de entorno (.env)

Agregar las siguientes variables:

```env
# === NOTIFICACIONES ===

# Canales habilitados (true/false)
NOTIFICATION_MAIL_ENABLED=true
NOTIFICATION_DATABASE_ENABLED=true
NOTIFICATION_WHATSAPP_ENABLED=true

# Recordatorios (días antes del evento)
EVENT_REMINDER_DAYS=1
PENDING_PAYMENT_REMINDER_DAYS=3

# WhatsApp - código de país para números de teléfono
WHATSAPP_COUNTRY_CODE=591

# === MAIL (SMTP real) ===

# Cambiar de 'log' a 'smtp' para envío real
MAIL_MAILER=smtp
MAIL_HOST=smtp.tudominio.com
MAIL_PORT=587
MAIL_USERNAME=tu-usuario@tudominio.com
MAIL_PASSWORD=tu-password-smtp
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="Nombre del Evento"

# === OPENWA (ya existente) ===

OPENWA_BASE_URL=http://localhost:2785
OPENWA_API_KEY=tu-api-key
OPENWA_SESSION_ID=tu-session-id
```

### Notas sobre cada variable

| Variable | Descripción | Valores posibles |
|----------|-------------|------------------|
| `NOTIFICATION_MAIL_ENABLED` | Habilita envío de correos | `true` / `false` |
| `NOTIFICATION_DATABASE_ENABLED` | Habilita almacenamiento en DB para lectura vía API | `true` / `false` |
| `NOTIFICATION_WHATSAPP_ENABLED` | Habilita envío vía OpenWA | `true` / `false` |
| `EVENT_REMINDER_DAYS` | Días antes del evento para enviar recordatorio | Entero (default: 1) |
| `PENDING_PAYMENT_REMINDER_DAYS` | Días antes del evento para avisar pago pendiente | Entero (default: 3) |
| `WHATSAPP_COUNTRY_CODE` | Código de país prependido a números sin código | String (default: 591) |
| `MAIL_MAILER` | Transporte de correo | `smtp`, `log`, `ses`, etc. |

---

## 4. Queue Worker

Las notificaciones implementan `ShouldQueue` y se procesan en background. **Es obligatorio que un queue worker esté corriendo.**

### Iniciar queue worker

```bash
php artisan queue:work --tries=3 --timeout=60
```

### Como servicio systemd (Linux)

Crear archivo `/etc/systemd/system/laravel-queue.service`:

```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/ruta/al/proyecto
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=60
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable laravel-queue
sudo systemctl start laravel-queue
```

### Como servicio Windows

Usar NSSM o crear un scheduled task que ejecute:
```
php artisan queue:work --tries=3 --timeout=60
```

---

## 5. Scheduler (Cron)

Los jobs de recordatorios se ejecutan diariamente. Configurar cron en el servidor:

```bash
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

Esto ejecuta:
- `SendEventReminderJob` a las **09:00** diario
- `SendPendingPaymentJob` a las **08:00** diario

### Verificar scheduler manualmente

```bash
php artisan schedule:list
php artisan schedule:run
```

---

## 6. Configuración SMTP

### Opción A: Gmail SMTP

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu@gmail.com
MAIL_PASSWORD=clave-de-aplicacion  # NO la contraseña normal
MAIL_ENCRYPTION=tls
```

Requiere habilitar "App Passwords" en la cuenta de Google.

### Opción B: Mailgun

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@tudominio.com
MAIL_PASSWORD=tu-password-mailgun
MAIL_ENCRYPTION=tls
```

### Opción C: Amazon SES

```env
MAIL_MAILER=ses
```

Configurar `config/services.php` con credenciales SES.

### Opción D: Servidor SMTP propio

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.tudominio.com
MAIL_PORT=587
MAIL_USERNAME=usuario
MAIL_PASSWORD=password
MAIL_ENCRYPTION=tls
```

---

## 7. OpenWA

### Verificar que OpenWA está corriendo

```bash
curl http://localhost:2785/api/session/qr
```

Si retorna un QR o estado de sesión, está activo.

### Variables OpenWA ya existentes

```env
OPENWA_BASE_URL=http://localhost:2785
OPENWA_API_KEY=owa_k1_xxxxx
OPENWA_SESSION_ID=xxxxx
```

### Deshabilitar WhatsApp (temporalmente)

Si OpenWA no está disponible, deshabilitar sin apagar el sistema completo:

```env
NOTIFICATION_WHATSAPP_ENABLED=false
```

Las notificaciones seguirán funcionando por mail y database.

---

## 8. Verificación post-deploy

### 8.1 Verificar migración

```bash
php artisan migrate --force
php artisan tinker --execute="echo Schema::hasColumn('registrations', 'pending_payment_notified_at') ? 'OK' : 'FAIL';"
```

### 8.2 Verificar config

```bash
php artisan tinker --execute="print_r(config('notifications'));"
```

Debe mostrar los 3 canales habilitados.

### 8.3 Verificar queue worker

```bash
php artisan queue:work --once
```

Si no hay errores, el worker está configurado correctamente.

### 8.4 Verificar scheduler

```bash
php artisan schedule:list
```

Debe mostrar los 2 jobs programados.

### 8.5 Test manual de notificación

```bash
php artisan tinker
```

```php
use App\Models\Persona;
use App\Notifications\RegistrationCreatedNotification;
use App\Models\Registration;

$persona = Persona::first();
$registration = Registration::with('totals', 'participants')->first();

Notification::send($persona, new RegistrationCreatedNotification($registration));
// Debería enviar mail + guardar en DB + enviar WhatsApp (si está habilitado)
```

---

## 9. Troubleshooting

### Error: "Undefined array key table"

El `config/queue.php` tiene el driver `sync` mal configurado. Verificar que la línea diga:
```php
'sync' => [
    'driver' => 'sync',  // NO 'database'
],
```

### Notificaciones no se envían

1. Verificar que el queue worker está corriendo: `php artisan queue:work`
2. Verificar `NOTIFICATION_MAIL_ENABLED=true` en `.env`
3. Verificar que `MAIL_MAILER` no sea `log` (eso solo guarda en storage/logs)
4. Verificar jobs fallidos: `php artisan queue:failed`

### WhatsApp no conecta

1. Verificar OpenWA corriendo: `curl http://localhost:2785/api/session/qr`
2. Verificar `NOTIFICATION_WHATSAPP_ENABLED=true`
3. Verificar que Persona tiene campo `celular` con número válido
4. Revisar logs: `storage/logs/laravel.log`

### Recordatorios no se ejecutan

1. Verificar cron: `crontab -l`
2. Verificar scheduler: `php artisan schedule:list`
3. Ejecutar manualmente: `php artisan schedule:run`
4. Verificar que hay eventos en la fecha objetivo

### Mail llega a spam

1. Configurar SPF, DKIM y DMARC en el dominio
2. Usar un servicio SMTP reputado (Mailgun, SES, SendGrid)
3. Verificar `MAIL_FROM_ADDRESS` coincide con el dominio configurado

---

## 10. Endpoints de notificaciones

| Método | Ruta | Descripción | Auth |
|--------|------|-------------|------|
| `GET` | `/api/v1/notifications` | Lista notificaciones del usuario (paginado, 15 por página) | Sanctum |
| `PUT` | `/api/v1/notifications/{id}/read` | Marca una notificación como leída | Sanctum |

---

## 11. Monitoring

### Logs a revisar

- `storage/logs/laravel.log` — Errores de envío
- Jobs fallidos: `php artisan queue:failed`
- Tabla `jobs` — Jobs en cola
- Tabla `failed_jobs` — Jobs que fallaron definitivamente

### Comandos útiles

```bash
# Ver jobs en cola
php artisan queue:monitor

# Reintentar jobs fallidos
php artisan queue:retry all

# Limpiar jobs completados
php artisan queue:prune --hours=48

# Ver notificaciones de un usuario
php artisan tinker --execute="App\Models\Persona::find(1)->notifications->toArray();"
```

---

## 12. Rollback

Si es necesario desactivar el sistema completo:

```env
NOTIFICATION_MAIL_ENABLED=false
NOTIFICATION_DATABASE_ENABLED=false
NOTIFICATION_WHATSAPP_ENABLED=false
```

Esto deshabilita todos los canales sin necesidad de eliminar código.

Para eliminar la migración (si es necesario):

```bash
php artisan migrate:rollback --step=1
```

---

## Archivos clave

```
app/
├── Notifications/
│   ├── Channels/
│   │   └── WhatsAppChannel.php
│   ├── Messages/
│   │   └── WhatsAppMessage.php
│   ├── RegistrationCreatedNotification.php
│   ├── PaymentConfirmedNotification.php
│   ├── RegistrationCancelledNotification.php
│   ├── PromoCodeAppliedNotification.php
│   ├── EventReminderNotification.php
│   └── PendingPaymentReminderNotification.php
├── Jobs/
│   ├── SendEventReminderJob.php
│   ├── SendPendingPaymentJob.php
│   └── SendWhatsappMessageJob.php (existente)
└── Http/Controllers/
    └── NotificationController.php

config/
└── notifications.php

routes/
├── api.php (+rutas notifications)
└── console.php (+scheduler)
```
