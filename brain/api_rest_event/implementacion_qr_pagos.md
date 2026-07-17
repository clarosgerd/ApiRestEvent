# Implementacion: Generacion de QR para Pagos

**Fecha:** 2026-07-17
**Metodo:** GET `/api/v1/registrations/{reference}/generaQr`

---

## Objetivo

Generar un código QR en base64 para el pago de una inscripción, consumiendo una API externa de pagos cuyas credenciales están configuradas en el `.env`.

---

## Flujo de autenticación y generación

```
1. generarToken()
   POST → API externa (BASE_AUTH_URL)
   Body: { "username": "GERD", "password": "$ecret2026" }
   Retorna: { "token": "abc123..." }

2. generaQr()
   POST → API externa (BASE_API_URL + generaQr)
   Headers: Authorization: Bearer {token}, apikey: {APIKEY_SERVICIO}
   Body: {
     "referencia": "Prueba123",
     "callback": "000",
     "detalleGlosa": "Nombre del Evento",
     "monto": "150.00",
     "moneda": "BOB",
     "fechaVencimiento": "24/12/2024",
     "tipoSolicitud": "API"
   }
   Retorna: { "qr_base64": "data:image/png;base64,..." }
```

---

## Archivos creados

### 1. `config/qrpago.php`

Configuración centralizada con variables de entorno:

| Variable | Env | Descripción |
|---|---|---|
| `base_auth_url` | `QR_BASE_AUTH_URL` | URL de autenticación |
| `base_api_url` | `QR_BASE_API_URL` | URL base de la API de pagos |
| `username` | `QR_USERNAME` | Usuario de autenticación |
| `password` | `QR_PASSWORD` | Contraseña de autenticación |
| `apikey_test` | `QR_APIKEY_TEST` | API Key para testing |
| `apikey_servicio` | `QR_APIKEY_SERVICIO` | API Key para generar QR |
| `verify_ss` | `QR_VERIFY_SS_TEST` | Verificar SSL |
| `moneda` | — | Default `BOB` |
| `callback` | — | Default `000` |
| `tipo_solicitud` | — | Default `API` |

### 2. `app/Services/QrService.php`

Servicio dedicado con 3 métodos:

| Método | Descripción |
|---|---|
| `generarToken()` | POST a API externa con credenciales del .env |
| `estadoTransaccion($referencia, $token)` | Consulta estado de un pago |
| `generaQr($registration, $token)` | Genera QR con datos de la inscripción |

**Características:**
- Usa `Http` facade de Laravel (Guzzle incluido)
- Logging de errores con `Log::error()`
- Extracción flexible del token (`token`, `data.token`, `access_token`)
- Monto formateado desde `registration_totals.grand_total`
- Fecha de vencimiento: 7 días desde hoy

---

## Archivos modificados

### `app/Http/Controllers/RegistrationController.php`

| Cambio | Detalle |
|---|---|
| Nuevo import | `use App\Services\QrService` |
| Fix import | Eliminado `App\DTP\ParticipantDTO` (namespace incorrecto) |
| Constructor | Inyectado `QrService` junto a `RegistrationService` |
| Método `generarToken()` | **NUEVO** — Retorna token de autenticación |
| Método `generaQr()` | **NUEVO** — Retorna QR en base64 |
| Método `estadoTransaccion()` | **REESCRITO** — Ahora consulta estado vía API externa |

---

## Rutas

```
GET /api/v1/registrations/{reference}/generarToken      → Token de autenticación
GET /api/v1/registrations/{reference}/estadoTransaccion  → Estado de transacción
GET /api/v1/registrations/{reference}/generaQr           → Código QR base64
```

---

## Variables a agregar en `.env`

```env
QR_BASE_AUTH_URL=http://127.0.0.1:800/api/v1/autenticacion/
QR_BASE_API_URL=http://127.0.0.1:800/api/v1/
QR_USERNAME=GERD
QR_PASSWORD=$ecret2026
QR_APIKEY_TEST=de1ffc35c158f30e1f5dfba79f5ef47ba7885c932098668f
QR_APIKEY_SERVICIO=5aa3b42bba5e8ae13017605bc7bbdd7f0a8fc513a37ed4bb
QR_VERIFY_SS_TEST=true
```

---

## Ejemplo de uso

### 1. Generar token

```
GET /api/v1/registrations/Prueba123/generarToken
```

Respuesta:
```json
{
  "success": true,
  "message": "Token generado correctamente.",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }
}
```

### 2. Generar QR

```
GET /api/v1/registrations/Prueba123/generaQr
```

Respuesta:
```json
{
  "success": true,
  "message": "Código QR generado correctamente.",
  "data": {
    "qr": "data:image/png;base64,iVBORw0KGgo...",
    "referencia": "Prueba123",
    "monto": "150.00"
  }
}
```
