# Sistema de Notificaciones Multicanal — Resumen de Cambios

**Fecha:** 2026-07-19
**Estado:** Implementado y probado (81 tests, 269 assertions)

---

## Qué se hizo

Se implementó un sistema de notificaciones multicanal que envía alertas por **correo (SMTP)**, **notificaciones web (database)** y **WhatsApp (OpenWA)** en los eventos del ciclo de vida de inscripciones. Incluye dos jobs programados para recordatorios automáticos.

---

## Archivos creados

### Migración
| Archivo | Qué hace |
|---------|----------|
| `database/migrations/2026_07_19_000001_create_notifications_system.php` | Crea tabla `notifications` estándar de Laravel + agrega columna `notifications` (JSON) a `users` y `registrations` + agrega `pending_payment_notified_at` a `registrations` |

### Configuración
| Archivo | Qué hace |
|---------|----------|
| `config/notifications.php` | Define qué canales están habilitados (`mail`, `database`, `whatsapp`), días de recordatorio y código de país WhatsApp |

### Canal WhatsApp
| Archivo | Qué hace |
|---------|----------|
| `app/Notifications/Channels/WhatsAppChannel.php` | Canal custom de Laravel que resuelve el `chatId` desde el campo `celular` de Persona y dispatcha `SendWhatsappMessageJob` |
| `app/Notifications/Messages/WhatsAppMessage.php` | Value object para el contenido del mensaje WhatsApp |

### Notificaciones (6 clases)
| Archivo | Trigger | Canales |
|---------|---------|---------|
| `app/Notifications/RegistrationCreatedNotification.php` | Crear inscripción | mail + database + WhatsApp |
| `app/Notifications/PaymentConfirmedNotification.php` | Confirmar pago (updatePaid) | mail + database + WhatsApp |
| `app/Notifications/RegistrationCancelledNotification.php` | Eliminar inscripción | mail + database |
| `app/Notifications/PromoCodeAppliedNotification.php` | Validar código promocional | database + WhatsApp |
| `app/Notifications/EventReminderNotification.php` | Job programado (N días antes del evento) | mail + database + WhatsApp |
| `app/Notifications/PendingPaymentReminderNotification.php` | Job programado (N días antes, pago pendiente) | mail + database + WhatsApp |

### Jobs programados
| Archivo | Qué hace |
|---------|----------|
| `app/Jobs/SendEventReminderJob.php` | Busca eventos que comienzan en `EVENT_REMINDER_DAYS` días, envía recordatorio a participantes pagados |
| `app/Jobs/SendPendingPaymentJob.php` | Busca registros pendientes de pago cuyo evento es en `PENDING_PAYMENT_REMINDER_DAYS` días, envía aviso. Usa `pending_payment_notified_at` para no reenviar |

### Controller
| Archivo | Qué hace |
|---------|----------|
| `app/Http/Controllers/NotificationController.php` | `index()` lista notificaciones paginadas del usuario autenticado, `markAsRead()` marca una como leída |

---

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/Models/Registration.php` | Agregado `evento()` BelongsTo relationship + import `Evento` |
| `app/Models/Persona.php` | Agregado trait `Notifiable` (requerido para recibir notificaciones) |
| `app/Services/RegistrationService.php` | Dispatch de notificaciones en `create()`, `updatePaidRegistration()`, `delete()` + método privado `notifyParticipants()` que resuelve Personas desde participantes |
| `app/Http/Controllers/PromoCodeController.php` | Dispatch de `PromoCodeAppliedNotification` cuando un código es válido y el usuario está autenticado |
| `routes/api.php` | +`GET /api/v1/notifications` + `PUT /api/v1/notifications/{id}/read` (ambos con middleware `auth:sanctum`) |
| `routes/console.php` | +`Schedule::job(new SendEventReminderJob)->dailyAt('09:00')` + `Schedule::job(new SendPendingPaymentJob)->dailyAt('08:00')` |
| `config/queue.php` | Fix: driver `sync` estaba configurado como `database` (faltaba key `table`), ahora usa `driver => 'sync'` |
| `tests/Feature/RegistrationTest.php` | +`Notification::fake()` en `setUp()` |
| `tests/Feature/ApiEndpointsTest.php` | +`Notification::fake()` en `setUp()` |
| `.env.example` | +variables de notificaciones y WhatsApp |
| `README.md` | +documentación de endpoints de notificaciones + tabla de eventos + config |
| `brain/cambios_2026_07_19.md` | +sección 5 con detalle completo |

---

## Endpoints nuevos

| Método | Ruta | Descripción | Auth |
|--------|------|-------------|------|
| `GET` | `/api/v1/notifications` | Listar notificaciones del usuario (paginado) | Sanctum |
| `PUT` | `/api/v1/notifications/{id}/read` | Marcar notificación como leída | Sanctum |

---

## Cómo funciona el dispatch

```
RegistrationService::create()
    → syncPersonas() crea/actualiza Persona por cada participante
    → notifyParticipants() resuelve Persona por numero_documento o email
    → Notification::send($persona, new RegistrationCreatedNotification($registration))
    → Notification::send($registration, new RegistrationCreatedNotification($registration))
```

El patrón es el mismo para `delete()` y `updatePaidRegistration()`.

Para `PromoCodeController::promoCode()`, se despacha solo si `$request->user()` es una Persona autenticada.

---

## Configuración de canales

Las 6 notificaciones verifican `config('notifications.channels.*')` antes de incluir cada canal en `via()`. Si un canal está deshabilitado en `.env`, simplemente no se incluye.

---

## Fix de queue

El `config/queue.php` tenía el driver `sync` configurado con `'driver' => 'database'`, lo que causaba `Undefined array key "table"` en tests. Se corrigió a `'driver' => 'sync'`.
