# API REST Event

API REST desarrollada en Laravel para gestionar eventos, personas y registros de participantes de forma organizada y escalable.

## Descripción general

Esta aplicación expone endpoints para:

- administrar eventos, coordenadas, rutas, categorías, tipos de formulario y códigos promocionales;
- gestionar personas y autenticación con Sanctum;
- registrar inscripciones con participantes, contactos de emergencia, souvenirs y totales;
- consultar y filtrar registros con paginación;
- actualizar el estado de pago de una inscripción;
- actualizar inscripciones pagadas con costo adicional y confirmación del usuario;
- generar códigos QR para pago y consultar estado de transacciones;
- validar códigos promocionales por evento.

## Tecnologías utilizadas

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- SQLite por defecto (configurable)
- Vite para assets frontend

## Estructura del proyecto

- app/Http/Controllers: controladores de la API
- app/Http/Requests: validaciones de entrada
- app/Http/Resources: respuestas estandarizadas
- app/Models: modelos Eloquent
- app/Services: lógica de negocio, especialmente para inscripciones
- app/DTOs: objetos de transferencia de datos
- routes/api.php: definición de endpoints
- database/migrations: estructura de la base de datos

## Requisitos previos

- PHP 8.2 o superior
- Composer 2.x
- Node.js 18+ y npm

## Instalación

1. Clona el repositorio.
2. Instala dependencias PHP:

   ```bash
   composer install
   ```

3. Crea el archivo de entorno:

   ```bash
   cp .env.example .env
   ```

4. Genera la clave de la aplicación:

   ```bash
   php artisan key:generate
   ```

5. Ejecuta las migraciones:

   ```bash
   php artisan migrate
   ```

6. Instala dependencias frontend:

   ```bash
   npm install
   ```

7. Compila los assets:

   ```bash
   npm run build
   ```

8. Inicia el servidor:

   ```bash
   php artisan serve
   ```

La API quedará disponible en:

```text
http://127.0.0.1:8000
```

## Rutas principales

La API se encuentra bajo el prefijo:

```text
/api/v1
```

---

## Endpoints

### Eventos

#### Listar eventos

```
GET /api/v1/event
```

**Parámetros de query (opcionales):**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Número de página (default: 1) |
| `per_page` | int | Elementos por página (default: 15) |
| Filtros personalizados | - | Se aplican vía `EventoFilter` |

**Respuesta 200:**

```json
{
  "success": true,
  "eventos": [
    {
      "id": 1,
      "name": "Maratón Ciudad 2026",
      "date": "2026-09-15 08:00:00",
      "localTime": "08:00:00",
      "location": "Av. Principal 123",
      "coordinates": [
        { "lat": "-17.7833", "lng": "-63.1821" }
      ],
      "route": [
        { "lat": "-17.7833", "lng": "-63.1821", "label": "Punto de salida" }
      ],
      "status": 1,
      "image": "https://example.com/portada.jpg",
      "video": "https://youtube.com/watch?v=abc",
      "description": "Maratón anual de la ciudad",
      "longDescription": "Descripción completa del evento...",
      "hasDonation": true,
      "categories": [
        { "id": 1, "name": "5K", "price": 50.00, "description": "Categoría 5 kilómetros", "color": "#e67e22" }
      ],
      "formTypes": [
        {
          "id": 1,
          "name": "Challenge Series",
          "icon": "🎯",
          "description": "Tipo deportivo",
          "tipo": "deportivo",
          "cupo_total": 500,
          "precio_base": 80.00,
          "color": "#e67e22",
          "moneda": 1,
          "permite_lista_espera": 1,
          "requiere_categoria": 1,
          "requiere_talla": 1,
          "souvenirs": [
            { "id": 1, "name": "Medalla", "icon": "🏅", "price": 15.00 }
          ]
        }
      ],
      "promoCodes": [
        { "id": 1, "event_id": 1, "price": 10.00 }
      ]
    }
  ],
  "pagination": {
    "total": 50,
    "per_page": 15,
    "current_page": 1,
    "last_page": 4,
    "from": 1,
    "to": 15,
    "path": "http://127.0.0.1:8000/api/v1/event"
  }
}
```

---

#### Obtener evento por ID

```
GET /api/v1/event/{event}
```

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `event` | int | ID del evento |

**Respuesta 200:**

```json
{
  "success": true,
  "eventos": {
    "id": 1,
    "name": "Maratón Ciudad 2026",
    "date": "2026-09-15 08:00:00",
    "localTime": "08:00:00",
    "location": "Av. Principal 123",
    "coordinates": [ ... ],
    "route": [ ... ],
    "status": 1,
    "image": "https://example.com/portada.jpg",
    "video": "https://youtube.com/watch?v=abc",
    "description": "Maratón anual de la ciudad",
    "longDescription": "Descripción completa del evento...",
    "hasDonation": true,
    "categories": [ ... ],
    "formTypes": [ ... ],
    "souvenirs": [ ... ],
    "promoCodes": [ ... ]
  }
}
```

---

### Personas y autenticación

#### Registrar persona

```
POST /api/v1/persona/register
```

**Content-Type:** `application/json`

**Body:**

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `nombre` | string | Sí | Nombre de la persona (max: 255) |
| `apellido` | string | Sí | Apellido (max: 255) |
| `email` | string | Sí | Correo electrónico (debe ser único) |
| `password` | string | Sí | Contraseña |
| `alias` | string | Sí | Alias o apodo (max: 255) |
| `sexo` | string | Sí | Sexo (max: 255) |
| `tipo_documento` | enum | Sí | Tipo de documento: `DNI`, `CI`, `Pasaporte` |
| `numero_documento` | string | Sí | Número de documento (max: 255) |
| `fecha_nacimiento` | date | Sí | Fecha de nacimiento (formato: `YYYY-MM-DD`) |
| `correo` | string | Sí | Correo de contacto (max: 255) |
| `direccion` | string | Sí | Dirección (max: 255) |
| `ciudad` | string | Sí | Ciudad (max: 255) |
| `telefono` | string | No | Teléfono (max: 255) |
| `celular` | string | No | Celular (max: 255) |

**Ejemplo:**

```bash
curl -X POST http://127.0.0.1:8000/api/v1/persona/register \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Juan",
    "apellido": "Pérez",
    "email": "juan@example.com",
    "password": "secret123",
    "alias": "juanp",
    "sexo": "Masculino",
    "tipo_documento": "DNI",
    "numero_documento": "12345678",
    "fecha_nacimiento": "1990-05-15",
    "correo": "juan@example.com",
    "direccion": "Calle Principal 123",
    "ciudad": "Santa Cruz",
    "telefono": "3-456789",
    "celular": "76543210"
  }'
```

**Respuesta 201:**

```json
{
  "success": true,
  "message": "Usuario registrado exitosamente",
  "data": {
    "id": 1,
    "email": "juan@example.com",
    "nombre": "Juan",
    "apellido": "Pérez",
    "token": "aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567890"
  },
  "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ..."
}
```

**Errores posibles:**

- `422` - Errores de validación:

```json
{
  "success": false,
  "message": "Validation errors occurred.",
  "data": {
    "email": ["Este email ya está registrado."],
    "nombre": ["El nombre es obligatorio."]
  }
}
```

---

#### Iniciar sesión

```
POST /api/v1/persona/login
```

**Content-Type:** `application/json`

**Body:**

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `email` | string | Sí | Correo electrónico |
| `password` | string | Sí | Contraseña |

**Ejemplo:**

```bash
curl -X POST http://127.0.0.1:8000/api/v1/persona/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "juan@example.com",
    "password": "secret123"
  }'
```

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Login exitoso",
  "data": {
    "persona": {
      "email": "juan@example.com",
      "nombre": "Juan",
      "apellido": "Pérez",
      "alias": "juanp",
      "sexo": "Masculino",
      "tipoDocumento": "DNI",
      "numeroDocumento": "12345678",
      "dia": "15",
      "mes": "05",
      "anio": "1990",
      "correo": "juan@example.com",
      "direccion": "Calle Principal 123",
      "ciudad": "Santa Cruz",
      "telefono": "3-456789",
      "celular": "76543210",
      "contacto_emergencia": {
        "nombre": "María Pérez",
        "celular": "76543211",
        "relacion": "FAT"
      }
    },
    "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ..."
  }
}
```

**Errores posibles:**

```json
{
  "success": false,
  "error": "no existe el correo."
}
```

```json
{
  "success": false,
  "error": "Las credenciales proporcionadas son incorrectas."
}
```

---

#### Cerrar sesión

```
POST /api/v1/persona/logout
```

**Autenticación requerida:** Sí (Bearer Token via Sanctum)

**Headers:**

```
Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ...
```

**Respuesta 200:**

```json
{
  "message": "Sesión cerrada con éxito"
}
```

**Respuesta 401:**

```json
{
  "message": "No autorizado"
}
```

---

#### Listar personas

```
GET /api/v1/persona
```

**Parámetros de query (opcionales):**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Número de página (default: 1) |
| `per_page` | int | Elementos por página (default: 15) |
| Filtros personalizados | - | Se aplican vía `PersonaFilter` |

**Respuesta 200:**

```json
{
  "success": true,
  "persona": [
    {
      "email": "juan@example.com",
      "nombre": "Juan",
      "apellido": "Pérez",
      "alias": "juanp",
      "sexo": "Masculino",
      "tipoDocumento": "DNI",
      "numeroDocumento": "12345678",
      "dia": "15",
      "mes": "05",
      "anio": "1990",
      "correo": "juan@example.com",
      "direccion": "Calle Principal 123",
      "ciudad": "Santa Cruz",
      "telefono": "3-456789",
      "celular": "76543210",
      "contacto_emergencia": {
        "nombre": "María Pérez",
        "celular": "76543211",
        "relacion": "FAT"
      }
    }
  ],
  "pagination": {
    "total": 25,
    "per_page": 15,
    "current_page": 1,
    "last_page": 2,
    "from": 1,
    "to": 15,
    "path": "http://127.0.0.1:8000/api/v1/persona"
  }
}
```

---

#### Obtener persona por ID

```
GET /api/v1/persona/{persona}
```

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `persona` | int | ID de la persona |

**Respuesta 200:**

```json
{
  "success": true,
  "persona": {
    "email": "juan@example.com",
    "nombre": "Juan",
    "apellido": "Pérez",
    "alias": "juanp",
    "sexo": "Masculino",
    "tipoDocumento": "DNI",
    "numeroDocumento": "12345678",
    "dia": "15",
    "mes": "05",
    "anio": "1990",
    "correo": "juan@example.com",
    "direccion": "Calle Principal 123",
    "ciudad": "Santa Cruz",
    "telefono": "3-456789",
    "celular": "76543210",
    "contacto_emergencia": {
      "nombre": "María Pérez",
      "celular": "76543211",
      "relacion": "FAT"
    }
  }
}
```

---

### Inscripciones

#### Listar inscripciones

```
GET /api/v1/registrations
```

**Parámetros de query (opcionales):**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Número de página (default: 1) |
| `per_page` | int | Elementos por página (default: 15) |
| `evento_id` | int | Filtrar por ID de evento |
| `pago_status` | string | Filtrar por estado de pago: `pending`, `paid`, `failed`, `cancelled` |
| `tipo_pago` | string | Filtrar por tipo de pago: `QR`, `TRANSFERENCIA`, `TIGO`, `EFECTIVO` |

**Respuesta 200:**

```json
{
  "items": [
    {
    "referencia": "REF-2026-001",
    "fecha": "2026-07-10 14:30:00",
    "evento_id": "1",
    "form_types_id": "1",
    "evento_nombre": "Maratón Ciudad 2026",
      "tipo_pago": "QR",
      "pago_status": "paid",
      "totales": {
        "inscripcion": 150.00,
        "donacion": 20.00,
        "souvenirs": 30.00,
        "fee": 5.00,
        "descuento": 10.00,
        "grand_total": 195.00
      },
      "participantes": [
        {
          "nombre": "Juan",
          "apellido": "Pérez",
          "alias": "juanp",
          "genero": "Masculino",
          "tipoDocumento": "DNI",
          "numeroDocumento": "12345678",
          "polera": "M",
          "precioPolera": 30.00,
          "nacimiento": {
            "dia": "15",
            "mes": "05",
            "anio": "1990"
          },
          "edad": 36,
          "correo": "juan@example.com",
          "direccion": "Calle Principal 123",
          "ciudad": "Santa Cruz",
          "telefono": "76543210",
          "contacto_emergencia": {
            "nombre": "María Pérez",
            "celular": "76543211",
            "relacion": "FAT"
          },
          "souvenirs": [
            { "participante_id": 1, "nombre": "Medalla", "precio": 15.00 }
          ],
          "categoria": "1",
          "precioCategoria": 50.00,
          "donacion": 20.00,
          "promoDescuento": 10.00,
          "promoCodigo": "EARLY2026",
          "subtotal": 190.00
        }
      ]
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

---

#### Crear inscripción

```
POST /api/v1/registrations
```

**Content-Type:** `application/json`

**Body:**

Se envía como un array con un objeto de inscripción:

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `referencia` | string | Sí | Referencia única de la inscripción |
| `fecha` | date | Sí | Fecha de la inscripción |
| `evento_id` | int | Sí | ID del evento |
| `form_types_id` | int | Sí | ID del tipo de formulario asociado |
| `evento_nombre` | string | Sí | Nombre del evento |
| `tipo_pago` | enum | Sí | Tipo de pago: `QR`, `TRANSFERENCIA`, `TIGO`, `EFECTIVO` |
| `pago_status` | enum | Sí | Estado de pago: `pending`, `paid`, `failed`, `cancelled` |
| `totales` | object | Sí | Totales de la inscripción (ver abajo) |
| `participantes` | array | Sí | Lista de participantes (min: 1) |

**Totales:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `inscripcion` | float | Costo de inscripción |
| `donacion` | float | Monto de donación |
| `souvenirs` | float | Total de souvenirs |
| `fee` | float | Comisión/tarifa |
| `descuento` | float | Descuento aplicado |
| `grand_total` | float | Total general |

**Participante:**

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `nombre` | string | Sí | Nombre |
| `apellido` | string | Sí | Apellido |
| `correo` | string | Sí | Correo electrónico |
| `numeroDocumento` | string | Sí | Número de documento |
| `categoria` | int | Sí | ID de categoría |
| `precioCategoria` | float | Sí | Precio de la categoría |
| `edad` | int | Sí | Edad del participante |
| `nacimiento` | object | Sí | Fecha de nacimiento |
| `nacimiento.dia` | int | Sí | Día |
| `nacimiento.mes` | int | Sí | Mes |
| `nacimiento.anio` | int | Sí | Año |
| `alias` | string | No | Alias o apodo |
| `genero` | string | No | Género (default: "Masculino") |
| `tipoDocumento` | string | No | Tipo de documento (default: "DNI") |
| `polera` | string | Sí | Talla de polera |
| `precioPolera` | float | Sí | Precio de la polera |
| `direccion` | string | Sí | Dirección |
| `ciudad` | string | Sí | Ciudad |
| `telefono` | string | Sí | Teléfono |
| `donacion` | float | Sí | Donación del participante |
| `promoDescuento` | float | Sí | Descuento promocional |
| `promoCodigo` | string | No | Código promocional |
| `subtotal` | float | Sí | Subtotal del participante |
| `contacto_emergencia` | object | Sí | Contacto de emergencia |
| `contacto_emergencia.nombre` | string | Sí | Nombre del contacto |
| `contacto_emergencia.celular` | string | Sí | Celular del contacto |
| `contacto_emergencia.relacion` | string | Sí | Relación: `FAT` (padre), `MOT` (madre), `BRO` (hermano), `SIS` (hermana), `WIF` (esposa), `HUS` (esposo), `SON` (hijo), `DAU` (hija), `FRI` (amigo) |
| `souvenirs` | array | No | Lista de souvenirs del participante |

**Souvenir del participante:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | int | ID del souvenir |
| `nombre` | string | Nombre del souvenir |
| `precio` | float | Precio del souvenir |

**Ejemplo:**

```bash
curl -X POST http://127.0.0.1:8000/api/v1/registrations \
  -H "Content-Type: application/json" \
  -d '[
    {
      "referencia": "REF-2026-001",
      "fecha": "2026-07-10T14:30:00",
      "evento_id": 1,
      "form_types_id": 1,
      "evento_nombre": "Maratón Ciudad 2026",
      "tipo_pago": "QR",
      "pago_status": "pending",
      "totales": {
        "inscripcion": 80.00,
        "donacion": 20.00,
        "souvenirs": 15.00,
        "fee": 5.00,
        "descuento": 0.00,
        "grand_total": 120.00
      },
      "participantes": [
        {
          "nombre": "Juan",
          "apellido": "Pérez",
          "correo": "juan@example.com",
          "numeroDocumento": "12345678",
          "categoria": 1,
          "precioCategoria": 80.00,
          "edad": 36,
          "nacimiento": { "dia": 15, "mes": 5, "anio": 1990 },
          "alias": "juanp",
          "genero": "Masculino",
          "tipoDocumento": "DNI",
          "polera": "M",
          "precioPolera": 30.00,
          "direccion": "Calle Principal 123",
          "ciudad": "Santa Cruz",
          "telefono": "76543210",
          "donacion": 20.00,
          "promoDescuento": 0.00,
          "promoCodigo": "",
          "subtotal": 115.00,
          "contacto_emergencia": {
            "nombre": "María Pérez",
            "celular": "76543211",
            "relacion": "WIF"
          },
          "souvenirs": [
            { "id": 1, "nombre": "Medalla", "precio": 15.00 }
          ]
        }
      ]
    }
  ]'
```

**Respuesta 201:**

```json
{
  "success": true,
  "message": "Inscripción registrada correctamente.",
  "data": {
    "referencia": "REF-2026-001",
    "fecha": "2026-07-10 14:30:00",
    "evento_id": "1",
    "evento_nombre": "Maratón Ciudad 2026",
    "tipo_pago": "QR",
    "pago_status": "pending",
    "totales": {
      "inscripcion": 80.00,
      "donacion": 20.00,
      "souvenirs": 15.00,
      "fee": 5.00,
      "descuento": 0.00,
      "grand_total": 120.00
    },
    "participantes": [
      {
        "nombre": "Juan",
        "apellido": "Pérez",
        "alias": "juanp",
        "genero": "Masculino",
        "tipoDocumento": "DNI",
        "numeroDocumento": "12345678",
        "polera": "M",
        "precioPolera": 30.00,
        "nacimiento": { "dia": "15", "mes": "05", "anio": "1990" },
        "edad": 36,
        "correo": "juan@example.com",
        "direccion": "Calle Principal 123",
        "ciudad": "Santa Cruz",
        "telefono": "76543210",
        "contacto_emergencia": {
          "nombre": "María Pérez",
          "celular": "76543211",
          "relacion": "WIF"
        },
        "souvenirs": [
          { "participante_id": 1, "nombre": "Medalla", "precio": 15.00 }
        ],
        "categoria": "1",
        "precioCategoria": 80.00,
        "donacion": 20.00,
        "promoDescuento": 0.00,
        "promoCodigo": "",
        "subtotal": 115.00
      }
    ]
  }
}
```

**Errores posibles:**

- `422` - Errores de validación:

```json
{
  "success": false,
  "message": "Validation errors occurred.",
  "data": {
    "0.referencia": ["La referencia es obligatoria."],
    "0.participantes": ["Debe existir al menos un participante."]
  }
}
```

- `500` - Referencia duplicada:

```json
{
  "message": "La referencia REF-2026-001 ya existe.",
  "exception": "Exception"
}
```

- `500` - Participante duplicado en el evento:

```json
{
  "message": "El participante Juan Pérez con documento 12345678 (DNI) ya está registrado en el evento 1.",
  "exception": "DomainException"
}
```

---

#### Obtener inscripción por referencia

```
GET /api/v1/registrations/{reference}
```

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `reference` | string | Referencia de la inscripción |

**Respuesta 200:**

```json
{
  "success": true,
  "data": {
    "referencia": "REF-2026-001",
    "fecha": "2026-07-10 14:30:00",
    "evento_id": "1",
    "evento_nombre": "Maratón Ciudad 2026",
    "tipo_pago": "QR",
    "pago_status": "paid",
    "totales": {
      "inscripcion": 80.00,
      "donacion": 20.00,
      "souvenirs": 15.00,
      "fee": 5.00,
      "descuento": 0.00,
      "grand_total": 120.00
    },
    "participantes": [ ... ]
  }
}
```

---

#### Actualizar estado de pago

```
PATCH /api/v1/registrations/{reference}/payment
```

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `reference` | string | Referencia de la inscripción |

**Body:**

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `pago_status` | string | Sí | Nuevo estado: `pending`, `paid`, `failed`, `cancelled` |

**Ejemplo:**

```bash
curl -X PATCH http://127.0.0.1:8000/api/v1/registrations/REF-2026-001/payment \
  -H "Content-Type: application/json" \
  -d '{
    "pago_status": "paid"
  }'
```

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Estado de pago actualizado.",
  "data": {
    "referencia": "REF-2026-001",
    "fecha": "2026-07-10 14:30:00",
    "evento_id": "1",
    "evento_nombre": "Maratón Ciudad 2026",
    "tipo_pago": "QR",
    "pago_status": "paid",
    "totales": { ... },
    "participantes": [ ... ]
  }
}
```

**Errores posibles:**

- `422` - Estado inválido:

```json
{
  "message": "The selected pago_status is invalid.",
  "errors": {
    "pago_status": ["The selected pago_status is invalid."]
  }
}
```

---

#### Eliminar inscripción

```
DELETE /api/v1/registrations/{reference}
```

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `reference` | string | Referencia de la inscripción |

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Inscripción eliminada correctamente."
}
```

---

#### Actualizar inscripción (solo pendientes)

```
PUT /api/v1/registrations/{reference}
```

Actualiza una inscripción que **no** esté pagada. Si `pago_status` es `"paid"`, retorna error.

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `reference` | string | Referencia de la inscripción |

**Body:** Misma estructura que el endpoint de crear inscripción (participantes + totales).

**Ejemplo:**

```bash
curl -X PUT http://127.0.0.1:8000/api/v1/registrations/REF-2026-001 \
  -H "Content-Type: application/json" \
  -d '{
    "participantes": [ ... ],
    "totales": { ... }
  }'
```

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Inscripción actualizada correctamente.",
  "data": { ... }
}
```

**Errores posibles:**

- `500` - Inscripción ya pagada:

```json
{
  "message": "No se puede modificar una inscripción ya pagada.",
  "exception": "DomainException"
}
```

---

#### Actualizar inscripción pagada (con costo adicional)

```
PATCH /api/v1/registrations/{reference}/update-paid
```

Permite modificar los datos de una inscripción que ya tiene `pago_status: "paid"`. Se aplica un **costo adicional** de edición definido en `form_types.costo_el usuario debe confirmar explícitamente el cambio.

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `reference` | string | Referencia de la inscripción |

**Body:**

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `confirmacion` | boolean | Sí | Debe ser `true` para proceder. Si es `false`, la API rechaza la operación. |
| `participantes` | array | Sí | Lista de participantes (misma estructura que actualización normal) |
| `totales` | object | Sí | Totales de la inscripción (misma estructura que actualización normal) |

**Ejemplo:**

```bash
curl -X PATCH http://127.0.0.1:8000/api/v1/registrations/REF-2026-001/update-paid \
  -H "Content-Type: application/json" \
  -d '{
    "confirmacion": true,
    "participantes": [
      {
        "nombre": "Juan",
        "apellido": "Pérez",
        "correo": "juan@example.com",
        "numeroDocumento": "12345678",
        "categoria": 1,
        "precioCategoria": 80.00,
        "edad": 36,
        "nacimiento": { "dia": 15, "mes": 5, "anio": 1990 },
        "polera": "M",
        "precioPolera": 30.00,
        "subtotal": 115.00,
        "contacto_emergencia": {
          "nombre": "María Pérez",
          "celular": "76543211",
          "relacion": "WIF"
        }
      }
    ],
    "totales": {
      "inscripcion": 80.00,
      "donacion": 20.00,
      "souvenirs": 15.00,
      "fee": 5.00,
      "descuento": 0.00,
      "grand_total": 120.00
    }
  }'
```

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Inscripción pagada actualizada correctamente.",
  "costo_adicion": 25.00,
  "data": {
    "referencia": "REF-2026-001",
    "fecha": "2026-07-10 14:30:00",
    "evento_id": "1",
    "evento_nombre": "Maratón Ciudad 2026",
    "tipo_pago": "QR",
    "pago_status": "paid",
    "totales": { "..." : "..." },
    "participantes": [ "..." ]
  }
}
```

**Errores posibles:**

- `422` - Confirmación no enviada:

```json
{
  "message": "Debe confirmar que acepta el costo adicional de edición para proceder.",
  "errors": {
    "confirmacion": ["Debe confirmar que acepta el costo adicional de edición para proceder."]
  }
}
```

- `404` - Inscripción no encontrada o no pagada:

```json
{
  "message": "No query results for model [App\\Models\\Registration]."
}
```

- `422` - Dominio: inscripción no pagada:

```json
{
  "message": "Esta operación solo aplica a inscripciones pagadas."
}
```

---

#### Buscar inscripción por credenciales

```
POST /api/v1/registrations/lookup
```

Busca la inscripción de un usuario en un evento y tipo de formulario específicos. Si el usuario tiene inscripción, devuelve los datos completos de la inscripción. Si el usuario está registrado pero no tiene inscripción en ese evento/formulario, devuelve solo los datos de la persona.

**Content-Type:** `application/json`

**Body:**

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `email` | string | Sí | Correo electrónico de la persona |
| `password` | string | Sí | Contraseña de la persona |
| `evento_id` | int | Sí | ID del evento a buscar |
| `form_type_id` | int | Sí | ID del tipo de formulario a buscar |

**Ejemplo:**

```bash
curl -X POST http://127.0.0.1:8000/api/v1/registrations/lookup \
  -H "Content-Type: application/json" \
  -d '{
    "email": "daphne04@example.org",
    "password": "123456",
    "evento_id": 1,
    "form_type_id": 1
  }'
```

**Respuesta 200 — Con inscripción encontrada (`type: "registration"`):**

```json
{
  "success": true,
  "type": "registration",
  "data": {
    "referencia": "REF-2026-001",
    "fecha": "2026-07-10 14:30:00",
    "evento_id": "1",
    "evento_nombre": "Maratón Ciudad 2026",
    "tipo_pago": "QR",
    "pago_status": "paid",
    "totales": {
      "inscripcion": 80.00,
      "donacion": 20.00,
      "souvenirs": 15.00,
      "fee": 5.00,
      "descuento": 0.00,
      "grand_total": 120.00
    },
    "participantes": [
      {
        "nombre": "Daphne",
        "apellido": "...",
        "correo": "daphne04@example.org",
        "numeroDocumento": "12345678",
        "categoria": "1",
        "precioCategoria": 80.00,
        "contacto_emergencia": { ... },
        "souvenirs": [ ... ]
      }
    ]
  }
}
```

**Respuesta 200 — Sin inscripción, solo persona (`type: "persona"`):**

Se genera un token de autenticación (igual que el login) para que la persona pueda usar la API.

```json
{
  "success": true,
  "type": "persona",
  "data": {
    "email": "daphne04@example.org",
    "nombre": "Daphne",
    "apellido": "...",
    "alias": "daphne",
    "sexo": "Femenino",
    "tipoDocumento": "CI",
    "numeroDocumento": "12345678",
    "nacimiento": { "dia": "15", "mes": "05", "anio": "1990" },
    "correo": "daphne04@example.org",
    "direccion": "...",
    "ciudad": "...",
    "telefono": "...",
    "celular": "...",
    "contacto_emergencia": {
      "nombre": "...",
      "celular": "...",
      "relacion": "FAT"
    }
  },
  "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ..."
}
```

**Errores posibles:**

- `401` — Credenciales inválidas:

```json
{
  "success": false,
  "error": "Credenciales inválidas."
}
```

- `422` — Errores de validación:

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field is required."],
    "evento_id": ["The evento id field is required."],
    "form_type_id": ["The form type id field is required."]
  }
}
```

---

### Pagos QR

#### Generar token de autenticación

```
GET /api/v1/registrations/generarToken
```

Genera un token de autenticación para el gateway de pagos QR.

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Token generado correctamente.",
  "data": { ... }
}
```

---

#### Generar código QR para pago

```
GET /api/v1/registrations/{reference}/generaQr
```

Genera un código QR para el pago de una inscripción. Usa el `grand_total` de los totales como monto a cobrar.

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `reference` | string | Referencia de la inscripción |

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Código QR generado correctamente.",
  "data": { ... }
}
```

---

#### Consultar estado de transacción

```
GET /api/v1/registrations/{reference}/estadoTransaccion
```

Consulta el estado de una transacción de pago QR por su referencia.

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `reference` | string | Referencia de la inscripción |

**Respuesta 200:**

```json
{
  "success": true,
  "data": { ... }
}
```

---

### Códigos promocionales

#### Validar código promocional

```
GET /api/v1/promo/{id}/code/{promocode}
```

Valida si un código promocional es válido para un evento específico.

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | int | ID del evento |
| `promocode` | string | Código promocional a validar |

**Respuesta 200:**

```json
{
  "success": true,
  "data": { ... }
}
```

---

### Coordenadas

#### Listar coordenadas

```
GET /api/v1/coordinate
```

**Parámetros de query (opcionales):**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Número de página (default: 1) |
| `per_page` | int | Elementos por página (default: 15) |
| Filtros personalizados | - | Se aplican vía `CoordinateFilter` |

**Respuesta 200:**

```json
{
  "data": [
    {
      "lat": "-17.7833",
      "lng": "-63.1821"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

#### Obtener coordenada por ID

```
GET /api/v1/coordinate/{coordinate}
```

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `coordinate` | int | ID de la coordenada |

**Respuesta 200:**

```json
{
  "lat": "-17.7833",
  "lng": "-63.1821"
}
```

---

### Rutas

#### Listar rutas

```
GET /api/v1/route
```

**Parámetros de query (opcionales):**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Número de página (default: 1) |
| `per_page` | int | Elementos por página (default: 15) |
| Filtros personalizados | - | Se aplican vía `RouteFilter` |

**Respuesta 200:**

```json
{
  "data": [
    {
      "lat": "-17.7833",
      "lng": "-63.1821",
      "label": "Punto de salida"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

#### Obtener ruta por ID

```
GET /api/v1/route/{route}
```

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `route` | int | ID de la ruta |

**Respuesta 200:**

```json
{
  "lat": "-17.7833",
  "lng": "-63.1821",
  "label": "Punto de salida"
}
```

---

### Categorías

#### Listar categorías

```
GET /api/v1/category
```

**Parámetros de query (opcionales):**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Número de página (default: 1) |
| `per_page` | int | Elementos por página (default: 15) |
| Filtros personalizados | - | Se aplican vía `CategoryFilter` |

**Respuesta 200:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "5K",
      "price": 50.00,
      "description": "Categoría 5 kilómetros",
      "color": "#e67e22"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

#### Obtener categoría por ID

```
GET /api/v1/category/{category}
```

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `category` | int | ID de la categoría |

**Respuesta 200:**

```json
{
  "id": 1,
  "name": "5K",
  "price": 50.00,
  "description": "Categoría 5 kilómetros",
  "color": "#e67e22"
}
```

---

### Tipos de formulario

#### Listar tipos de formulario

```
GET /api/v1/form-type
```

**Parámetros de query (opcionales):**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Número de página (default: 1) |
| `per_page` | int | Elementos por página (default: 15) |
| Filtros personalizados | - | Se aplican vía `FormTypeFilter` |

**Respuesta 200:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Challenge Series",
      "icon": "🎯",
      "description": "Tipo deportivo",
      "tipo": "deportivo",
      "cupo_total": 500,
      "precio_base": 80.00,
      "color": "#e67e22",
      "moneda": 1,
      "permite_lista_espera": 1,
      "requiere_categoria": 1,
      "requiere_talla": 1
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

**Valores posibles para `tipo`:** `deportivo`, `congreso`, `taller`, `corporativo`, `cultural`, `social`, `educativo`, `recreativo`, `religioso`, `gastronomico`, `musical`, `tecnologico`, `artes`, `literario`, `ambiental`, `salud`, `moda`, `teatro`, `cine`, `fotografia`, `danza`, `literatura`

---

#### Obtener tipo de formulario por ID

```
GET /api/v1/form-type/{form_type}
```

**Parámetros:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `form_type` | int | ID del tipo de formulario |

**Respuesta 200:**

```json
{
  "id": 1,
  "name": "Challenge Series",
  "icon": "🎯",
  "description": "Tipo deportivo",
  "tipo": "deportivo",
  "cupo_total": 500,
  "precio_base": 80.00,
  "color": "#e67e22",
  "moneda": 1,
  "permite_lista_espera": 1,
  "requiere_categoria": 1,
  "requiere_talla": 1
}
```

---

### Participantes

#### Listar participantes

```
GET /api/v1/persona (vía apiResource)
```

> Nota: Los participantes se gestionan internamente a través de las inscripciones. No hay rutas apiResource independientes para participantes en las rutas definidas actualmente.

---

## Códigos de respuesta HTTP

| Código | Descripción |
|--------|-------------|
| `200` | Operación exitosa |
| `201` | Recurso creado exitosamente |
| `401` | No autorizado (token inválido o ausente) |
| `422` | Error de validación |
| `404` | Recurso no encontrado |
| `500` | Error interno del servidor |

---

## Enums utilizados

### Tipo de documento (Persona)
`DNI`, `CI`, `Pasaporte`

### Tipo de pago (Registration)
`QR`, `TRANSFERENCIA`, `TIGO`, `EFECTIVO`

### Estado de pago (Registration)
`pending`, `paid`, `failed`, `cancelled`

### Relación contacto de emergencia
`FAT` (Padre), `MOT` (Madre), `BRO` (Hermano), `SIS` (Hermana), `WIF` (Esposa), `HUS` (Esposo), `SON` (Hijo), `DAU` (Hija), `FRI` (Amigo)

### Tipo de formulario
`deportivo`, `congreso`, `taller`, `corporativo`, `cultural`, `social`, `educativo`, `recreativo`, `religioso`, `gastronomico`, `musical`, `tecnologico`, `artes`, `literario`, `ambiental`, `salud`, `moda`, `teatro`, `cine`, `fotografia`, `danza`, `literatura`

---

## Endpoints no implementados (próximamente)

Los siguientes endpoints están definidos en las rutas pero sus métodos están vacíos:

- `POST /api/v1/event` - Crear evento
- `PUT /api/v1/event/{event}` - Actualizar evento
- `DELETE /api/v1/event/{event}` - Eliminar evento
- `POST /api/v1/coordinate` - Crear coordenada
- `PUT /api/v1/coordinate/{coordinate}` - Actualizar coordenada
- `DELETE /api/v1/coordinate/{coordinate}` - Eliminar coordenada
- `POST /api/v1/route` - Crear ruta
- `PUT /api/v1/route/{route}` - Actualizar ruta
- `DELETE /api/v1/route/{route}` - Eliminar ruta
- `POST /api/v1/category` - Crear categoría
- `PUT /api/v1/category/{category}` - Actualizar categoría
- `DELETE /api/v1/category/{category}` - Eliminar categoría
- `POST /api/v1/form-type` - Crear tipo de formulario
- `PUT /api/v1/form-type/{form_type}` - Actualizar tipo de formulario
- `DELETE /api/v1/form-type/{form_type}` - Eliminar tipo de formulario

---

## Pruebas

Se verificó el estado actual del proyecto con:

```bash
php artisan test
```

Resultado verificado: 81 pruebas ejecutadas correctamente (269 assertions).

## Observaciones de desarrollo

El proyecto ya cuenta con una base sólida para un API de gestión de eventos, pero todavía es conveniente mejorar:

- completar los métodos CRUD que actualmente están en estado base;
- reforzar la documentación de endpoints y ejemplos de respuesta;
- añadir más pruebas para los flujos críticos de inscripción y autenticación;
- revisar la coherencia de autenticación de la entidad Persona con Sanctum;
- implementar autenticación con Sanctum en endpoints de inscripciones (actualmente abiertos).

## Licencia

Este proyecto se distribuye bajo la licencia MIT.
