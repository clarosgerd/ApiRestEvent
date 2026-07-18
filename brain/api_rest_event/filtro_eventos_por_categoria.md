# Filtro de Eventos por Categoría

## Descripción

Se agregó la capacidad de filtrar eventos por el nombre de su categoría. Esto permite consultar todos los eventos que tengan una categoría específica (ej: "3k", "5k", "10k", etc.).

## Archivo modificado

`app/Http/Controllers/EventoController.php` - método `index()`

## Cambio realizado

Se agregó un bloque de filtro usando `whereHas` sobre la relación `categories` del modelo `Evento`. El filtro soporta dos operadores:

- **`eq`** - Búsqueda exacta del nombre de categoría
- **`li`** - Búsqueda parcial con `LIKE`

```php
// Filtro por nombre de categoría: ?category[eq]=3k
$categoryFilter = $request->query('category');
if (isset($categoryFilter['eq'])) {
    $eventos->whereHas('categories', function ($q) use ($categoryFilter) {
        $q->where('name', '=', $categoryFilter['eq']);
    });
} elseif (isset($categoryFilter['li'])) {
    $eventos->whereHas('categories', function ($q) use ($categoryFilter) {
        $q->where('name', 'like', '%' . $categoryFilter['li'] . '%');
    });
}
```

## Uso de la API

### Búsqueda exacta

```
GET /api/v1/event?category[eq]=3k
```

Devuelve todos los eventos que tengan una categoría con el nombre exacto "3k".

### Búsqueda parcial (LIKE)

```
GET /api/v1/event?category[li]=3k
```

Devuelve todos los eventos que tengan una categoría cuyo nombre contenga "3k" (ej: "3k", "3km", "Categoría 3k").

### Combinado con otros filtros

```
GET /api/v1/event?category[eq]=3k&publicado[eq]=1
```

Devuelve todos los eventos publicados que tengan la categoría "3k".

## Respuesta esperada

```json
{
    "success": true,
    "eventos": [...],
    "pagination": {
        "total": 5,
        "per_page": 15,
        "current_page": 1,
        "last_page": 1,
        "from": 1,
        "to": 5,
        "path": "http://..."
    }
}
```

## Relación involucrada

- **Modelo `Evento`**: tiene muchas `categories` (hasMany)
- **Modelo `Category`**: pertenece a un `Evento` (belongsTo, FK: `event_id`)
- **Tabla `categories`**: campo `name` utilizado para el filtro
