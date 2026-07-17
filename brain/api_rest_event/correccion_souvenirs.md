# Correccion: Souvenirs no se muestran en la respuesta

**Fecha:** 2026-07-17
**Problema:** Al hacer request a los endpoints de eventos, los datos de souvenirs no aparecian en la respuesta JSON.

---

## Causa raiz

La cadena de relaciones es: `Evento -> FormType -> Souvenir`

El problema tenia **4 componentes**:

1. El Eager Loading en `EventoController` solo cargaba `formTypes` sin anidar los `souvenirs` de cada formType.
2. El modelo `Souvenir` tenia la foreign key incorrecta en la relacion `belongsTo`.
3. `SouvenirController` tenia imports faltantes (Request, SouvenirFilter, SouvenirCollection, SouvenirResource).
4. `FormTypeController` tenia imports faltantes (FormTypeCollection, FormTypeResource) y cargaba la relacion incorrecta en `show()`.

---

## Archivos modificados

### 1. `app/Http/Controllers/EventoController.php`

**Cambio:** Eager loading anidado de `formTypes.souvenirs`

```php
// ANTES (index)
$eventos = $eventos->with('formTypes');

// DESPUES
$eventos = $eventos->with('formTypes.souvenirs');

// ANTES (show)
$event->loadMissing(['coordinates', 'routes', 'promoCodes','categories','formTypes'])

// DESPUES
$event->loadMissing(['coordinates', 'routes', 'promoCodes','categories','formTypes.souvenirs'])
```

### 2. `app/Models/Souvenir.php`

**Cambio:** Correccion de foreign key en relacion `belongsTo`

```php
// ANTES
return $this->belongsTo('App\Models\FormType', 'id');

// DESPUES
return $this->belongsTo('App\Models\FormType', 'form_types_id', 'id');
```

**Explicacion:** `'id'` era la PK del modelo, no la FK. La columna FK en la tabla `souvenirs` es `form_types_id`.

### 3. `app/Http/Controllers/SouvenirController.php`

**Cambio:** Agregar imports faltantes

```php
// ANTES
use App\Models\Souvenir;
use App\Http\Requests\StoreSouvenirRequest;
use App\Http\Requests\UpdateSouvenirRequest;

// DESPUES
use App\Models\Souvenir;
use App\Http\Requests\StoreSouvenirRequest;
use App\Http\Requests\UpdateSouvenirRequest;
use App\Http\Resources\SouvenirCollection;
use App\Http\Resources\SouvenirResource;
use App\Filters\SouvenirFilter;
use Illuminate\Http\Request;
```

### 4. `app/Http/Controllers/FormTypeController.php`

**Cambio 1:** Agregar imports faltantes

```php
// ANTES
use App\Models\FormType;
use App\Http\Requests\StoreFormTypeRequest;
use App\Http\Requests\UpdateFormTypeRequest;
use Illuminate\Http\Request;
use App\Filters\FormTypeFilter;

// DESPUES
use App\Models\FormType;
use App\Http\Requests\StoreFormTypeRequest;
use App\Http\Requests\UpdateFormTypeRequest;
use App\Http\Resources\FormTypeCollection;
use App\Http\Resources\FormTypeResource;
use Illuminate\Http\Request;
use App\Filters\FormTypeFilter;
```

**Cambio 2:** Corregir relacion cargada en `show()`

```php
// ANTES
$formType->loadMissing('formTypes')

// DESPUES
$formType->loadMissing('souvenirs')
```

---

## Resultado

Ahora al hacer request a un evento, la respuesta JSON incluye la estructura:

```json
{
  "formTypes": [
    {
      "id": 1,
      "name": "Formulario General",
      "souvenirs": [
        {
          "form_types_id": 1,
          "name": "Remera",
          "icon": "tshirt",
          "price": 25.00
        }
      ]
    }
  ]
}
```
