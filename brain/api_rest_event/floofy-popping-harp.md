# Buscador de eventos (simple + avanzado)

## Contexto

`requerimiento_crear_buscador.md` pide un buscador antes del listado de eventos
(`#screen-event-list`), con una lupa, más un panel de búsqueda avanzada con 5
filtros: estado, ubicación, tipo de evento, rango de precio y rango de fecha.
El documento sugiere que el filtrado se haga contra la API externa usando
parámetros como `nombre[li]=`, `category[eq]=`, `status[eq]=`, etc.

**Hallazgo clave (probado en vivo contra `http://127.0.0.1:8000/api/v1/event`):**
solo 3 filtros funcionan realmente en esa API: `nombre[eq|li]`, `category[eq|li]`
(el operador `li` requiere wildcards `%texto%`) y `publicado[eq]`. Los combos
probados para `status[eq]`, `location[eq|li]`, `tipo[eq]`, `date[gte]` y
`price[gte]` son aceptados sin error pero **no filtran nada** — la API
devuelve los 10 eventos sin importar el valor. Con solo 10 eventos totales
(4 publicados) en una sola página (`per_page: 15`), la solución más simple y
que cubre el 100% del requerimiento es **filtrar todo en el cliente** sobre
el array ya cargado por `loadAllEvents()`, en vez de depender de parámetros
de la API que en su mayoría no existen. Si el catálogo crece más allá de una
página en el futuro, esto habrá que revisarse (paginación + filtros reales
del lado servidor).

Decisiones ya confirmadas con el usuario:
- El buscador simple filtra por **nombre del evento O nombre de categoría**
  (un solo input).
- Todos los filtros (simple + avanzado) se aplican **automáticamente con
  debounce** (~300ms en texto; inmediato en combos/rangos), sin botón enviar.
- Los textos nuevos se integran al diccionario `translations.en/es/pt.events`
  existente, mismo patrón que `title/subtitle/loading/error`.

## Diseño

### Estado y filtrado (todo client-side)

- `loadAllEvents()` (`index.php:1884`) ya guarda el resultado normalizado en
  memoria antes de renderizar. Se agrega una variable global
  `let allEvents = []` que guarda el array completo (post `publicado==1`,
  post `normalizeEvento`) apenas se cargan los eventos — es la fuente única
  para todos los filtros, no se vuelve a pedir a la API al buscar.
- Nueva función `applyEventFilters()`:
  - Lee el texto del buscador simple + los 5 controles avanzados.
  - Filtra `allEvents` con un predicado combinado (AND entre todos los
    filtros activos):
    - **Texto simple**: `ev.name` o alguna `ev.categories[].name` contiene el
      texto (case-insensitive, sin acentos si es sencillo de normalizar).
    - **Estado**: combo con las 3 opciones ya usadas por `getStatusLabel()`
      (`open`, `coming_soon`, `closed`) — exact match contra `ev.status`.
    - **Ubicación**: input de texto — substring case-insensitive contra
      `ev.location`.
    - **Tipo de evento**: combo con la lista fija del requerimiento
      (deportivo, congreso, taller, ...) — el evento matchea si **alguno**
      de sus `ev.formTypes[].tipo` es igual al valor elegido (el campo
      `tipo` vive en `formTypes`, no en el evento — confirmado leyendo la
      respuesta real de la API).
    - **Rango de precio**: min/max — el evento matchea si **alguna** de sus
      `ev.categories[].price` cae dentro del rango.
    - **Rango de fecha**: desde/hasta — sobre `ev.date`, reutilizando
      `parseEventDate()` (`index.php:1652`) que ya existe para parsear ese
      campo en otros lados de la app.
  - Llama a `renderEventCards(resultado)` (ya existente, `index.php:1907`)
    y muestra/oculta un mensaje "sin resultados" nuevo cuando el array
    filtrado queda vacío.
- Debounce genérico simple (`setTimeout`/`clearTimeout`, patrón ya usado en
  `scheduleNextPoll`/`updatePollText`) para el input de texto y los rangos;
  los combos disparan `applyEventFilters()` directo en `onchange`.

### UI

- Nuevo bloque HTML dentro de `#screen-event-list`
  (`index.php:703-725`), entre `.p0-header` y `#eventsGrid`:
  - Input de texto con ícono de lupa (SVG inline, mismo estilo que los
    íconos ya usados en `.event-card-meta-row svg`) + placeholder
    traducido (`events.searchPlaceholder`).
  - Botón/ícono "Filtros avanzados" que alterna `display` de un panel
    colapsable (`#advancedFilters`, oculto por defecto) con los 5 campos:
    combo estado, input ubicación, combo tipo, dos inputs numéricos
    (precio min/max), dos inputs date (fecha desde/hasta).
  - Botón "Limpiar filtros" visible solo cuando hay algún filtro activo.
- CSS nuevo junto al bloque `.p0-header`/`.events-grid` (`index.php:361+`),
  reutilizando variables existentes (`--primary`, `--border`, `--radius`,
  `--light`, `--muted`) para que combine con el resto de la pantalla.

### i18n

- Se agregan claves nuevas dentro de `translations.en/es/pt.events`
  (`index.php:1358`, `~1435`, `~1512`): `searchPlaceholder`,
  `advancedToggle`, `clearFilters`, `noResults`, `filterStatus`,
  `filterLocation`, `filterType`, `filterPriceRange`, `filterDateRange`,
  más las 21 etiquetas de tipo de evento del requerimiento (deportivo,
  congreso, taller, corporativo, cultural, social, educativo, recreativo,
  religioso, gastronomico, musical, tecnologico, artes, literario,
  ambiental, salud, moda, teatro, cine, fotografia, danza, literatura) —
  estas últimas puede que no necesiten traducción real (son casi iguales
  en los 3 idiomas) pero se listan igual para consistencia con el patrón
  `data-i18n`.

## Archivos a modificar

- `index.php` — único archivo a tocar: HTML del buscador, CSS, JS
  (`allEvents`, `applyEventFilters`, debounce, wiring de eventos DOM) y las
  3 entradas de `translations.events`.
- No se toca `api/registro.php`, `api/email.php` ni la API externa — esta
  es una función 100% client-side sobre datos ya cargados.

## Verificación

- Con el servidor local corriendo (`http://localhost/elascenso/event/`) y
  la API mock activa (`http://127.0.0.1:8000`), probar en navegador (o vía
  Playwright, como en la sesión anterior):
  - Escribir un nombre parcial de evento → la grilla se reduce en vivo.
  - Escribir el nombre de una categoría (ej. "3k") sin que coincida con
    ningún nombre de evento → deben aparecer los eventos que tengan esa
    categoría.
  - Abrir el panel avanzado, elegir estado "Cerrado" → solo aparece el
    evento con `status: closed` (hay 1 en los datos actuales del mock).
  - Combinar 2+ filtros a la vez (ej. tipo + rango de precio) → el AND se
    aplica correctamente.
  - Filtro que no matchea nada → se ve el mensaje de "sin resultados", no
    una grilla vacía silenciosa.
  - Cambiar idioma (selector ya existente) → los textos del buscador
    cambian junto con el resto de la pantalla.
