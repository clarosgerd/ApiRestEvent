
[[implementacion_registro_evento]]]
#Diagnostico Tecnico - ApiRestEvent

**Fecha:** 2026-07-16
**Framework:** Laravel 12 + PHP 8.2
**Auth:** Sanctum 4.0
**DB Default:** SQLite (configurable MySQL/PostgreSQL)
**Tests:** PHPUnit 11.5 - 81 tests, 268 assertions (todos pasando)

---

## 1. Que es este proyecto

API REST para gestion de **eventos deportivos/culturales** con:
- Inscripciones de participantes con datos personales, contacto de emergencia, souvenirs
- Sistema de pagos (QR, transferencia, Tigo, efectivo) con estados (pending/paid/failed/cancelled)
- Codigos promocionales por evento
- Categorias, formularios, coordenadas GPS, rutas
- Autenticacion de personas via tokens Sanctum
- Notificaciones WhatsApp via OpenWA (colas)

**Contexto:** Bolivia (formatos de telefono CI/DNI/Pasaporte, ciudades Santa Cruz, Montevideo)

---

## 2. Estado real: ~30% funcional

### Controllers funcionales (3 de 15)

| Controller | Metodos implementados | Estado |
|---|---|---|
| `RegistrationController` | index, store, show, updatePayment, destroy | COMPLETO |
| `PersonaController` | index, show, register, login, logout | FUNCIONAL |
| `EventoController` | index, show | FUNCIONAL (lectura) |

### Controllers parcialmente rotos (5)

| Controller            | Problema                                                                              |
| --------------------- | ------------------------------------------------------------------------------------- |
| `CategoryController`  | ~~Falta importar Request, CategoryFilter, CategoryResource. Index/show retornan 500~~ |
| `FormTypeController`  | ~~Falta importar FormTypeCollection, FormTypeResource. Index/show retornan 500~~      |
| `RouteController`     | ~~Falta importar RouteCollection, RouteResource. Index/show retornan 500~~            |
| `PromoCodeController` | ~~Falta importar PromoCodeFilter, PromoCodeCollection~~                               |
| `SouvenirController`  | ~~Falta importar SouvenirFilter, SouvenirCollection, SouvenirResource~~               |

### Controllers totalmente rotos (2)

| Controller                       | Problema                                                                     |
| -------------------------------- | ---------------------------------------------------------------------------- |
| `SouvenirParticipanteController` | ~~Typo en imports: `SouverirParticipante` en vez de `SouvenirParticipante`~~ |
| `ParticipanteController`         | ~~`show()` ignora parametro de ruta, retorna todos los participantes~~       |

### Stubs vacios (6)

| Controller                                      | Estado                                          |
| ----------------------------------------------- | ----------------------------------------------- |
| `ContactoEmergenciaController`                  | ~~CRUD vacio~~                                  |
| `ContactoEmergenciaParticipanteController`      | ~~CRUD vacio~~                                  |
| `FormasPagoController`                          | CRUD vacio (tabla existe sin modelo/controller) |
| `RegistrationTotalController`                   | ~~CRUD vacio~~                                  |
| `EventoController` (store/update/destroy)       | ~~Vacio - type hint `Eventos` no existe~~       |
| `ParticipanteController` (store/update/destroy) | ~~Vacio~~                                       |

---

## 3. Bugs criticos

### BUG 1-5: Controllers sin imports

Todas estas clases son referenciadas pero nunca importadas con `use`:

- `CategoryController`: `Request`, `CategoryFilter`, `CategoryCollection`, `CategoryResource`
- `FormTypeController`: `FormTypeCollection`, `FormTypeResource`
- `RouteController`: `RouteCollection`, `RouteResource`
- `PromoCodeController`: `PromoCodeFilter`, `PromoCodeCollection`
- `SouvenirController`: `SouvenirFilter`, `SouvenirCollection`, `SouvenirResource`

**Impacto:** Los endpoints `index()` y `show()` de estos controllers retornan **Error 500** en produccion.

### BUG 6: EventoController type hints incorrectos

```php
// EventoController.php lineas 107, 115, 123
public function edit(Eventos $eventos)  // Eventos no existe, es Evento
public function update(UpdateEventosRequest $request, Eventos $eventos)
public function destroy(Eventos $eventos)
```

### BUG 7: RegistrationController import roto

```php
use App\DTP\ParticipantDTO;  // Deberia ser App\DTOs\ParticipantDTO
```

Import muerto - `ParticipantDTO` no se usa en el archivo, pero indica copy-paste descuidado.

### BUG 8: SouvenirParticipanteController imports con typo

```php
use App\Models\SouverirParticipante;           // Typo: "Souverir" -> "Souvenir"
use App\Http\Requests\StoreSouverirParticipanteRequest;  // No existe
use App\Http\Requests\UpdateSouverirParticipanteRequest; // No existe
```

### BUG 9: ParticipanteController::show() no funciona

```php
public function show(Participante $participante): JsonResponse
{
    // Ignora $participante y retorna TODOS los participantes
    $persona = Participante::all();
    return response()->json([...]);
}
```

### BUG 10: Persona extiende Model en vez de Authenticatable

```php
// Persona.php
use Illuminate\Foundation\Auth\User as Authenticatable; // Importa pero...
class Persona extends Model  // ...extiende Model (deberia ser Authenticatable)
```

El trait `HasApiTokens` necesita `Authenticatable`. Puede causar problemas con `createToken()`.

### BUG 11: Relaciones belongsTo con FK incorrecta

5 modelos tienen la misma equivocacion:

```php
// Category.php, Coordinate.php, Route.php, FormType.php, PromoCode.php
public function evento() {
    return $this->belongsTo('App\Models\Evento', 'id'); // 'id' es la PK del modelo, no la FK
}
// Deberia ser:
return $this->belongsTo('App\Models\Evento', 'event_id');
```

### BUG 12: RegistrationTotal falta import BelongsTo

```php
// RegistrationTotal.php linea 31
public function registration(): BelongsTo  // BelongsTo no importado
```

### BUG 13: EventoResource referencia atributo inexistente

```php
// EventoResource.php linea 30
'localTime' => Carbon::parse($this->localTime)->format('H:i:s'),
// No existe columna 'localTime' en la tabla eventos
```

---

## 4. Codigo de debug en produccion

### dd() comentados

| Archivo | Linea | Contenido |
|---|---|---|
| `RegistrationController.php` | 62 | `//dd($request);` |
| `RegistrationController.php` | 67 | `// dd( $request->validated()[0]);` |
| `RegistrationController.php` | 84 | `//dd($reference);` |
| `RegistrationController.php` | 92 | `//dd($registration);` |
| `RegistrationController.php` | 109 | `// dd($request);` |
| `RegistrationService.php` | 140 | `//  dd($dto);` |
| `EventoController.php` | 29 | `// dd($request);` |
| `EventoController.php` | 39 | `//dd($eventos);` |
| `EventoController.php` | 82 | `//dd($event);` |
| `PersonaController.php` | 53 | `//dd($validated->);` |
| `PersonaController.php` | 72 | `//dd($user);` |
| `PromoCodeController.php` | 88 | `// dd($promocode);` |
| `PromoCodeController.php` | 91 | `//dd($eventos);` |
| `PromoCodeController.php` | 94 | `//dd($collection);` |

### Codigo WhatsApp comentado

```php
// EventoController.php linea 26
//SendWhatsappMessageJob::dispatch('+59175925001@c.us', 'Hola, tu pedido esta listo');
```

---

## 5. Seguridad

| Riesgo                        | Detalle                                       |                       |
| ----------------------------- | --------------------------------------------- | --------------------- |
| **Sin auth en registrations** | Cualquiera puede crear/eliminar inscripciones |                       |
| **Sin auth en personas**      | Index y show de personas son publicos         |                       |
| **Sin rate limiting**         | Login y register sin proteccion fuerza bruta  |                       |
| **Tokens sin expiracion**     | `config/sanctum.php` tiene `expiration: null` |                       |
| **Password sin minimo**       | Register solo pide `required                  | string`, no minlength |
| **CORS habilitado**           | Configurado en HandleCors middleware          |                       |

---

## 6. Arquitectura

### Patrones aplicados

| Patron | Donde | Calidad |
|---|---|---|
| Service Layer | `RegistrationService` | Bien implementado con transacciones DB |
| DTOs | `app/DTOs/` (6 archivos) | Usados correctamente con `fromArray()` |
| Form Requests | `app/Http/Requests/` (32 archivos) | Buenos, mensajes en espanol |
| API Resources | `app/Http/Resources/` (24 archivos) | Incompletos - algunos no importados |
| Filters | `app/Filters/` (10 archivos) | Buena base abstracta `ApiFilter` |
| Policies | `app/Policies/` (2 archivos) | Solo Souvenir y SouvenirParticipante |

### DB Schema (20 migraciones)

| Tabla                               | Relacion                        | Estado    |
| ----------------------------------- | ------------------------------- | --------- |
| `eventos`                           | Padre de todo                   | OK        |
| `coordinates`                       | event_id FK                     | OK        |
| `routes`                            | event_id FK                     | OK        |
| `categories`                        | event_id FK                     | OK        |
| `form_types`                        | event_id FK                     | OK        |
| `souvenirs`                         | event_id FK                     | OK        |
| `promo_codes`                       | event_id FK, promo_code unique  | OK        |
| `personas`                          | Con HasApiTokens                | OK        |
| `contactos_emergencia`              | persona_id FK                   | OK        |
| `registrations`                     | evento_id FK, referencia unique | OK        |
| `registration_totals`               | registration_id FK cascade      | OK        |
| `participantes`                     | registration_id FK cascade      | OK        |
| `contacto_emergencia_participantes` | participante_id FK cascade      | OK        |
| `souvenir_participantes`            | participante_id FK cascade      | OK        |
| `formas_pagos`                      | Sin controller ni modelo activo | Pendiente |

---

## 7. Tests

### Cobertura actual

| Archivo | Tests | Estado |
|---|---|---|
| `ApiEndpointsTest.php` | 4 | Todos pasan |
| `AuthTest.php` | 16 | Todos pasan |
| `EventoTest.php` | 10 | Todos pasan |
| `RegistrationTest.php` | 28 | Todos pasan |
| `PromoCodeTest.php` | 4 | Todos pasan |
| `ReadEndpointsTest.php` | 13 | Todos pasan (algunos con fallback 500) |
| `ExampleTest.php` | 2 | Tests default Laravel |

### Que NO se testea

- Unit tests (solo `assertTrue(true)`)
- Controllers rotos (Category, FormType, Route) - los tests detectan el 500 pero no lo arreglan
- Edge cases de concurrent registration
- Comportamiento de transacciones DB bajo error
- Job de WhatsApp

---

## 8. TODOs pendientes (por prioridad)

### P0 - Inmediato (bugs que rompen funcionalidad)

- [x] Arreglar imports en `CategoryController` (agregar use statements)
- [x] Arreglar imports en `FormTypeController`
- [x] Arreglar imports en `RouteController`
- [x] Arreglar imports en `PromoCodeController`
- [x] Arreglar imports en `SouvenirController`
- [x] Corregir `Persona` para que extienda `Authenticatable` en vez de `Model`
- [x] Corregir relaciones `belongsTo` en Category, Coordinate, Route, FormType, PromoCode (FK `event_id`)

### P1 - Corto plazo

- [ ] Completar CRUD de Eventos (store/update/destroy)
- [x] Corregir type hints en `EventoController` (`Eventos` -> `Evento`)
- [ ] Completar CRUD de Participantes
- [x] Corregir `ParticipanteController::show()` para usar el parametro de ruta
- [x] Arreglar `SouvenirParticipanteController` (imports con typo)
- [ ] Eliminar imports muertos (`App\DTP\ParticipantDTO`)
- [ ] Arreglar `EventoResource` (atributo `localTime` inexistente)

### P2 - Medio plazo

- [ ] Agregar autenticacion a endpoints criticos (registrations, personas)
- [ ] Implementar rate limiting en login/register
- [ ] Configurar expiracion de tokens Sanctum
- [ ] Agregar minlength a password en register
- [ ] Completar CRUD de FormasPago (tabla existe, falta todo)
- [ ] Limpiar todos los `dd()` comentados
- [ ] Implementar politicas de autorizacion consistentes

### P3 - Largo plazo

- [ ] Activar WhatsApp job (actualmente comentado)
- [ ] Tests unitarios para DTOs, Services, Filters
- [ ] Documentacion OpenAPI/Swagger
- [ ] Paginacion consistente en todos los endpoints
- [ ] Filtros en endpoints que los soportan (Category, FormType, etc.)
