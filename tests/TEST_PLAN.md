# Test Plan - ApiRestEvent

**Proyecto:** API REST para gestion de eventos, inscripciones y participantes
**Framework:** Laravel 12 + PHPUnit 11.5
**Base de datos de testing:** SQLite in-memory
**Fecha:** 2026-07-16

---

## 1. Autenticacion (Persona)

### 1.1 Registro de Persona

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 1.1.1 | Registro exitoso | POST | /api/v1/persona/register | Todos los campos validos | 201 + token + datos usuario |
| 1.1.2 | Email duplicado | POST | /api/v1/persona/register | Email ya registrado | 422 + error unique |
| 1.1.3 | Campos obligatorios faltantes | POST | /api/v1/persona/register | Sin nombre | 422 + errores de validacion |
| 1.1.4 | Email formato invalido | POST | /api/v1/persona/register | email: "noemail" | 422 + error email |
| 1.1.5 | Payload vacio | POST | /api/v1/persona/register | {} | 422 + multiples errores |

### 1.2 Login

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 1.2.1 | Login exitoso | POST | /api/v1/persona/login | email + password correctos | 200 + token |
| 1.2.2 | Email inexistente | POST | /api/v1/persona/login | email no registrado | 200 + success=false + error |
| 1.2.3 | Password incorrecto | POST | /api/v1/persona/login | email correcto, password mal | 200 + success=false + error |
| 1.2.4 | Campos faltantes | POST | /api/v1/persona/login | Sin password | 422 |
| 1.2.5 | Email formato invalido | POST | /api/v1/persona/login | email: "bad" | 422 |

### 1.3 Logout

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 1.3.1 | Logout exitoso | POST | /api/v1/persona/logout | Token valido (Sanctum) | 200 + mensaje exito |
| 1.3.2 | Sin token | POST | /api/v1/persona/logout | Ninguno | 401 No autorizado |
| 1.3.3 | Token invalido | POST | /api/v1/persona/logout | Token fake | 401 |

### 1.4 Listar / Ver Personas

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 1.4.1 | Listar personas | GET | /api/v1/persona | - | 200 + paginacion |
| 1.4.2 | Ver persona por ID | GET | /api/v1/persona/{id} | ID valido | 200 + datos persona |
| 1.4.3 | Persona inexistente | GET | /api/v1/persona/99999 | ID inexistente | 404 |

---

## 2. Eventos

### 2.1 Listar Eventos

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 2.1.1 | Listar eventos (vacio) | GET | /api/v1/event | - | 200 + array vacio + paginacion |
| 2.1.2 | Listar eventos (con datos) | GET | /api/v1/event | Crear 3 eventos | 200 + 3 eventos + paginacion |
| 2.1.3 | Eager loading relaciones | GET | /api/v1/event | Crear evento con relaciones | 200 + categories, coordinates, etc. |

### 2.2 Ver Evento

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 2.2.1 | Ver evento valido | GET | /api/v1/event/{id} | ID existente | 200 + datos evento completo |
| 2.2.2 | Evento inexistente | GET | /api/v1/event/99999 | ID inexistente | 404 |
| 2.2.3 | Ver evento con relaciones | GET | /api/v1/event/{id} | Evento con coordinates, routes, etc. | 200 + todas las relaciones |

---

## 3. Registros / Inscripciones

### 3.1 Crear Inscripcion

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 3.1.1 | Inscripcion exitosa (1 participante) | POST | /api/v1/registrations | Payload valido | 201 + datos inscripcion |
| 3.1.2 | Inscripcion exitosa (multiples participantes) | POST | /api/v1/registrations | 3 participantes | 201 + 3 participantes creados |
| 3.1.3 | Referencia duplicada | POST | /api/v1/registrations | Referencia ya existente | Error por referencia duplicada |
| 3.1.4 | Participante duplicado en solicitud | POST | /api/v1/registrations | 2 participantes mismo documento | Error por duplicado |
| 3.1.5 | Referencia faltante | POST | /api/v1/registrations | Sin campo referencia | 422 |
| 3.1.6 | Participantes vacio | POST | /api/v1/registrations | participantes: [] | 422 |
| 3.1.7 | Datos participante incompletos | POST | /api/v1/registrations | Sin correo | 422 |
| 3.1.8 | Contacto emergencia faltante | POST | /api/v1/registrations | Sin contacto_emergencia | 422 |
| 3.1.9 | Totales faltantes | POST | /api/v1/registrations | Sin campo totales | 422 |
| 3.1.10 | Con souvenirs | POST | /api/v1/registrations | Participante con souvenirs | 201 + souvenirs guardados |
| 3.1.11 | Verificar datos en BD | POST | /api/v1/registrations | Crear inscripcion | Verificar tablas registration_totals, participantes, etc. |

### 3.2 Listar Inscripciones

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 3.2.1 | Listar (vacio) | GET | /api/v1/registrations | - | 200 + paginacion |
| 3.2.2 | Listar (con datos) | GET | /api/v1/registrations | Crear 2 inscripciones | 200 + 2 items |
| 3.2.3 | Filtrar por evento_id | GET | /api/v1/registrations?evento_id=X | Inscripciones en evento X | 200 + solo de evento X |
| 3.2.4 | Filtrar por pago_status | GET | /api/v1/registrations?pago_status=paid | Inscripciones pagadas | 200 + solo pagadas |
| 3.2.5 | Filtrar por tipo_pago | GET | /api/v1/registrations?tipo_pago=QR | Inscripciones QR | 200 + solo QR |

### 3.3 Ver Inscripcion

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 3.3.1 | Ver por referencia valida | GET | /api/v1/registrations/{ref} | Referencia existente | 200 + datos completos |
| 3.3.2 | Referencia inexistente | GET | /api/v1/registrations/FAKE-REF | Referencia fake | 404 |

### 3.4 Actualizar Pago

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 3.4.1 | Cambiar a paid | PATCH | /api/v1/registrations/{ref}/payment | pago_status: "paid" | 200 + status actualizado |
| 3.4.2 | Cambiar a failed | PATCH | /api/v1/registrations/{ref}/payment | pago_status: "failed" | 200 |
| 3.4.3 | Cambiar a cancelled | PATCH | /api/v1/registrations/{ref}/payment | pago_status: "cancelled" | 200 |
| 3.4.4 | Status invalido | PATCH | /api/v1/registrations/{ref}/payment | pago_status: "invalid" | 422 |
| 3.4.5 | Referencia inexistente | PATCH | /api/v1/registrations/FAKE/payment | pago_status: "paid" | 404 |

### 3.5 Eliminar Inscripcion

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 3.5.1 | Eliminar existente | DELETE | /api/v1/registrations/{ref} | Referencia existente | 200 + eliminada de BD |
| 3.5.2 | Referencia inexistente | DELETE | /api/v1/registrations/FAKE | Referencia fake | 404 |

---

## 4. Promo Codes

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 4.1 | Buscar promo code valido | GET | /api/v1/promo/{event_id}/code/{code} | Evento + codigo existentes | 200 + success=true + data |
| 4.2 | Promo code inexistente | GET | /api/v1/promo/1/code/FAKE | Codigo no existente | 200 + success=false + error |

---

## 5. Categorias (Lectura)

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 5.1 | Listar categorias | GET | /api/v1/category | - | 200 + paginacion |
| 5.2 | Ver categoria por ID | GET | /api/v1/category/{id} | ID existente | 200 + datos |

---

## 6. Form Types (Lectura)

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 6.1 | Listar form types | GET | /api/v1/form-type | - | 200 + paginacion |
| 6.2 | Ver form type por ID | GET | /api/v1/form-type/{id} | ID existente | 200 + datos |

---

## 7. Coordenadas (Lectura)

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 7.1 | Listar coordenadas | GET | /api/v1/coordinate | - | 200 + paginacion |
| 7.2 | Ver coordenada por ID | GET | /api/v1/coordinate/{id} | ID existente | 200 + datos |

---

## 8. Rutas (Lectura)

| # | Escenario | Metodo | Endpoint | Datos | Esperado |
|---|-----------|--------|----------|-------|----------|
| 8.1 | Listar rutas | GET | /api/v1/route | - | 200 + paginacion |
| 8.2 | Ver ruta por ID | GET | /api/v1/route/{id} | ID existente | 200 + datos |

---

## Resumen de Cobertura

| Modulo | Happy Path | Negativos | Validaciones | Total |
|--------|------------|-----------|--------------|-------|
| Auth (Persona) | 5 | 5 | 5 | 15 |
| Eventos | 4 | 2 | 0 | 6 |
| Inscripciones | 8 | 4 | 9 | 21 |
| Promo Codes | 1 | 1 | 0 | 2 |
| Categorias | 2 | 0 | 0 | 2 |
| Form Types | 2 | 0 | 0 | 2 |
| Coordenadas | 2 | 0 | 0 | 2 |
| Rutas | 2 | 0 | 0 | 2 |
| **TOTAL** | **26** | **12** | **14** | **52** |

---

## Archivos de Test

| Archivo | Descripcion | Tests |
|---------|-------------|-------|
| tests/Feature/AuthTest.php | Registro, login, logout, listado personas | 15 |
| tests/Feature/EventoTest.php | Listado y vista de eventos | 6 |
| tests/Feature/RegistrationTest.php | CRUD completo de inscripciones | 21 |
| tests/Feature/PromoCodeTest.php | Busqueda de codigos promocionales | 2 |
| tests/Feature/ReadEndpointsTest.php | Categorias, FormTypes, Coordenadas, Rutas | 8 |
| tests/Feature/ApiEndpointsTest.php | Tests existentes (se mantienen) | 4 |
