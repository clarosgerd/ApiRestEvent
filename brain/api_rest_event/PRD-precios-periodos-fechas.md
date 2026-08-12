# PRD — Precios por períodos y fechas

Sesión 11/08/2026. Diagnóstico previo en el chat: **no existe nada de
esto hoy** — no hay `fecha_desde`/`fecha_hasta`, no hay nombres de
período, no hay cambio automático. El precio es fijo, sin fecha, en dos
lugares (`form_types.precio_base`, `categories.price`). Este documento
planea construirlo.

## Hallazgos adicionales durante la investigación (no estaban en el diagnóstico original)

### 1. `form_types.precio_base` es puramente decorativo

Se muestra en la tarjeta de tipo de formulario ("A partir de $X") pero
**nunca se cobra**. El monto real de inscripción siempre sale de
`categories.price` (`_registro_validacion.php`:
`inscripcion = precioCategoria + precioPoleraValidado`). Si un form_type
tiene `requiereCategoria=false` (no pide categoría), el frontend manda
`precioCategoria = 0` — ese formulario cobra **$0** por la inscripción
en sí (solo cobraría por souvenirs/donación). Esto determinó la
decisión de dónde viven los períodos (ver más abajo): en `precio_base`
no tendría ningún efecto real para la mayoría de los eventos, que sí
usan categorías.

> **Actualizado 12/08/2026** — este diagnóstico describe el bug pero no
> decía qué debía cobrarse en su lugar; la sección 0 lo resuelve:
> `requiereCategoria=false` pasa a cobrar `precio_base` (deja de ser
> $0/decorativo). Los períodos siguen sin tocar `precio_base` — eso no
> cambia.

### 2. ApiRestEvent nunca revalida `precioCategoria` — confía ciegamente en el proxy

`CrearInscripcionAction`/`RegistrationService` guardan
`precio_categoria` tal cual llega en el request
(`StoreRegistrationRequest` solo valida que sea `numeric`, no que
coincida con `categories.price`). La única validación real de que el
precio coincida con la categoría vive en
`elascenso/event/api/_registro_validacion.php` — que es un proxy, no la
fuente de verdad. Alguien que le pegue directo a
`POST /api/v1/registrations` (sin pasar por `elascenso/event`) puede
mandar cualquier `precioCategoria` y ApiRestEvent lo acepta tal cual.
Esto no es nuevo de esta feature, pero construir precios por período
**sin** cerrar esta brecha significaría que el período es puramente
cosmético (se puede evadir) — se cierra como prerrequisito, mismo
criterio que el fix de `form_types.activo` en kit/tallas/stock.

## Decisiones ya tomadas con el usuario (11/08/2026)

1. **Los períodos viven en las categorías** (`categories`), no en
   `form_types.precio_base` — es donde vive el precio que realmente se
   cobra hoy.
2. **`categories.formulario_id` (dead field, ver diagnóstico anterior)
   NO se conecta esta ronda** — queda para otra ronda, no se mezcla con
   este trabajo.
3. **Fuera de período → fallback al último período vencido** — nunca se
   bloquea una venta por un hueco de configuración. Ver la regla
   completa de 3 niveles más abajo (agrega un 3er nivel para el caso
   "todavía no empezó ningún período", no cubierto explícitamente en la
   pregunta pero necesario para que la regla sea completa).
4. **Alcance: punta a punta**, incluyendo el frontend de inscripción de
   producción (`elascenso/event/index.php`).

## 0. Ajuste 12/08/2026 — de dónde sale el precio según `requiereCategoria`

Aclaración pedida por el usuario, cierra el hueco que dejaba abierto el
Hallazgo #1: hasta ahora ese hallazgo solo *describía* que
`form_types.precio_base` no se cobra nunca (queda en $0 cuando
`requiereCategoria=false`); no decía qué debía pasar en su lugar, y
"Fuera de alcance" lo daba por cerrado ("no tendría efecto real hoy").
Regla completa, sin huecos:

- **`requiereCategoria=true`** → el precio sale de la categoría:
  `PrecioVigenteData::paraCategoria()` (sección 2) — precio del período
  vigente hoy, o el fallback de 3 niveles ya definido ahí (vencido más
  reciente → `categories.price`). Sin cambios respecto al resto de este
  documento.
- **`requiereCategoria=false`** → el precio sale de
  `form_types.precio_base`, **sin períodos** (los períodos siguen
  viviendo solo en `categories`, decisión #1 de "Decisiones ya tomadas"
  arriba — esto no la reabre). Reemplaza el comportamiento actual: deja de
  cobrarse $0 por la inscripción en sí; `precio_base` deja de ser
  decorativo y pasa a ser el monto real, tal cual está guardado hoy (sin
  fallback adicional — si es 0, se cobra 0, igual que una categoría sin
  período configurado se queda con su `price` tal cual).

Esto **reemplaza** el segundo punto de "Fuera de alcance" de la versión
original de este PRD ("Períodos de precio sobre `form_types.precio_base`
— no tendría efecto real hoy"): sigue siendo cierto que no hay períodos
sobre `precio_base` en esta ronda, pero ya no es cierto que no tenga
efecto real — a partir de este ajuste sí se cobra.

**Impacto en datos existentes** — advertencia para el checklist de
despliegue (sección 6): hoy `precio_base` es un campo que el organizador
llena pensando que es solo informativo ("A partir de $X" en la tarjeta).
Antes de desplegar, listar los `form_types` con `requiereCategoria=false`
y `precio_base > 0` y confirmar con el organizador de cada evento que ese
monto es efectivamente lo que se debe cobrar — un valor puesto sin
cuidado ahí empezaría a cobrarse de verdad el día del deploy.

## 1. Modelo de datos

### `category_price_periods` (nueva)

Nombre en inglés, consistente con `categories`/`Category` (que ya está
en inglés, a diferencia de la mayoría de las tablas nuevas de esta
sesión que están en español).

| Columna | Tipo | Nota |
|---|---|---|
| `category_id` | FK `categories`, cascadeOnDelete | |
| `nombre` | string | "Preventa" / "Precio regular" / "Último día" — libre, no enum |
| `price` | decimal(10,2) | |
| `fecha_desde` | date | |
| `fecha_hasta` | date | |

Validación de escritura (Request, no constraint de BD — un rango de
fechas no se puede expresar como `UNIQUE`/`CHECK` portable):
- `fecha_hasta >= fecha_desde`.
- **Sin traslapes** con otro período de la misma categoría (excluyéndose
  a sí mismo en un update) — esto sigue siendo relevante aunque los
  huecos se toleren: dos períodos que se pisan sí dejarían ambiguo qué
  precio aplica, a diferencia de un hueco (que tiene una regla de
  fallback clara). Chequeo estándar de overlap:
  `NOT (existente.fecha_hasta < nuevo.fecha_desde OR existente.fecha_desde > nuevo.fecha_hasta)`.

Una categoría sin ninguna fila acá tiene **comportamiento actual, sin
cambios**: se cobra `categories.price` tal cual — mismo criterio de
compatibilidad que `item_stock` en kit/tallas/stock (sin filas = sin
control, no rompe eventos existentes).

## 2. Regla de "precio vigente" — `App\Support\PrecioVigenteData::paraCategoria()`

Aplica solo cuando `requiereCategoria=true` (ver sección 0). Cuando
`requiereCategoria=false` no hay tabla de períodos ni fallback que
calcular: el precio es `form_types.precio_base` tal cual, directo.

Por orden de prioridad:

1. **Sin períodos configurados** → `categories.price` (comportamiento
   actual).
2. **Hay un período cuyo rango contiene la fecha de hoy** → el precio de
   ese período.
3. **Ninguno vigente hoy, pero hay al menos uno ya vencido** (su
   `fecha_hasta` ya pasó) → el precio del **más reciente vencido**
   (decisión del usuario — nunca se bloquea una venta por un hueco).
4. **Ninguno vigente ni vencido** (todos los períodos configurados son
   futuros — ej. el organizador configuró la Preventa pero todavía no
   llegó su `fecha_desde`) → `categories.price` como último fallback.
   Este 3er caso no estaba explícito en la pregunta que se le hizo al
   usuario (que hablaba de huecos *entre* períodos y *después* del
   último), pero es necesario para que la regla no tenga un hueco lógico
   propio — si al usuario no le sirve este comportamiento para el caso
   "todavía no arrancó ningún período", avisar antes de construir.

Devuelve `{precio: float, periodo_nombre: ?string, periodo_fecha_hasta: ?string, periodos: [...]}`
— `periodo_nombre`/`periodo_fecha_hasta` nulos en los casos 1 y 4 (no
hay período real aplicándose, es el precio base).

## 3. API (ApiRestEvent)

- `CategoryResource`: se agregan `precio_vigente`, `periodo_vigente_nombre`,
  `periodo_vigente_fecha_hasta`, `periodos` (array completo, para que
  admin-eventos y el frontend puedan mostrar la tabla). **`price` (el
  campo crudo) no cambia de significado** — sigue siendo el valor
  guardado en la columna, a propósito: el formulario de edición de
  categoría en admin-eventos lee `price` para precargar el input, y si
  ese campo pasara a ser el precio computado de hoy, guardar sin querer
  sobrescribiría el precio base legado con el precio de hoy.
- `POST/PUT/DELETE /category/{id}/periodos` (admin, scoped al evento o
  super_admin) — CRUD de `category_price_periods`, mismo patrón de
  autorización que Souvenir/ItemStock
  (`assertCanWriteEvento`/`AuthorizesEventoScope`).

### Cierre de la brecha de revalidación (prerrequisito, Hallazgo #2)

`CrearInscripcionAction::createParticipant()` (o un método de
validación nuevo, mismo lugar donde ya se valida equipo/delivery) debe
ramificar por `formType.requiereCategoria` (ver sección 0):

1. **`requiereCategoria=true`**:
   1. Cargar la `Category` real por `participantDTO->category` (el id).
   2. Calcular `PrecioVigenteData::paraCategoria($category)`.
   3. Si `abs($precioVigente - $dto->categoryPrice) > 0.01` → rechazar
      con `DomainException` (422), mismo patrón que el resto de las
      validaciones de esa Action.
2. **`requiereCategoria=false`**:
   1. Si `abs($formType->precio_base - $dto->categoryPrice) > 0.01` →
      rechazar con `DomainException` (422), mismo patrón. No hay
      `Category` que cargar ni período que resolver — comparación
      directa contra `precio_base`.

Esto aplica **siempre**, no solo cuando hay períodos configurados —
cierra la brecha para cualquier form_type, tenga o no categoría, con o
sin períodos (defensa en profundidad real, no placebo).

## 4. `elascenso/event` — proxy y frontend

- `_registro_validacion.php`: hoy **no distingue `requiereCategoria` en
  absoluto** (no hay ninguna referencia al campo en este archivo) — el
  chequeo de categoría válida (`validarYCalcularParticipantes()`,
  `~línea 162`) se ejecuta siempre recorriendo `evento['categories']`
  buscando `id`+`price` que matcheen, lo cual para un form_type sin
  categoría nunca puede matchear contra algo real. Hay que agregar la
  rama que falta:
  - `requiereCategoria=true` (rama existente, ajustada): el chequeo de
    categoría válida pasa de comparar contra `cat['price']` a comparar
    contra `cat['precio_vigente']` — chequeo temprano (UX), la
    validación real atómica es la de ApiRestEvent de arriba.
  - `requiereCategoria=false` (rama nueva): comparar
    `p['precioCategoria']` contra `formType['precio_base']` en vez de
    recorrer `evento['categories']`.
- `index.php`:
  - Tarjeta de categoría: usa `cat.precio_vigente` (no `cat.price`) para
    el precio mostrado y para `dataset.price`. Si
    `cat.periodo_vigente_nombre` existe, se muestra un badge chico
    ("🔥 Preventa" / "⏰ Último día") — es la "comunicación de urgencia al
    participante" que pide el PRD original.
  - `guardarParticipante()` (`~línea 5267`): `precioCategoria` deja de
    hardcodearse en `0` cuando no hay `catEl` seleccionado (form_type
    con `requiereCategoria=false`) — pasa a
    `Number(selectedFormType?.precio_base ?? 0)`. La tarjeta de tipo de
    formulario ("A partir de $X", `~línea 3443`) deja de ser aspiracional:
    ese monto pasa a ser lo que realmente se cobra.

## 5. admin-eventos

- Sección de categorías en `eventos/edit.blade.php`: al lado de cada
  categoría, texto chico "Precio vigente hoy: $X (Preventa)" (o sin
  período si no hay ninguno configurado) + link "Períodos de precio →".
- Pantalla nueva `categorias/{id}/periodos` — tabla editable
  nombre/precio/fecha_desde/fecha_hasta, mismo estilo que
  `souvenirs/stock.blade.php` (fila calculada de "vigente ahora" al
  lado). Rechaza con mensaje claro si el rango se pisa con otro período
  ya cargado.

## 6. Testing y despliegue (mismo criterio que el resto de esta sesión)

- Tests Pest: regla de precio vigente (4 casos: sin períodos, vigente
  hoy, hueco→vencido más reciente, todos futuros→price), rechazo de
  overlap al crear/editar un período, cierre de la brecha de
  revalidación en `CrearInscripcionAction` (con y sin períodos
  configurados), CRUD scoped de períodos. Agregar la rama
  `requiereCategoria=false` (sección 0): cobra `precio_base` (antes
  $0), y rechazo 422 cuando `categoryPrice` mandado no matchea
  `precio_base`.
- Todo contra `event_testing` primero, nunca contra la BD real de
  desarrollo sin un ok explícito aparte.
- Deploy checklist + SQL standalone en `elascenso/event/brain/`, mismo
  formato que las rondas anteriores.
- **Advertencia de cambio de comportamiento** para el checklist: cerrar
  la brecha de revalidación (Hallazgo #2) hace que **cualquier**
  inconsistencia entre lo que manda el frontend y `categories.price`
  empiece a rechazarse con 422 — si hay algún cliente externo (no
  `elascenso/event`) pegándole directo a `POST /registrations` con un
  precio que no coincide exactamente, empieza a fallar. No debería haber
  ninguno hoy (el único cliente conocido es `elascenso/event`), pero
  vale la pena confirmarlo antes de subir a un entorno con tráfico real.

## Fuera de alcance (a propósito)

- Conectar `categories.formulario_id` (categorías scoped a un tipo de
  formulario específico) — decisión explícita del usuario, otra ronda.
- **Períodos de precio sobre `form_types.precio_base`** — sigue fuera de
  alcance (los períodos siguen viviendo solo en `categories`, sección
  1), pero ya **no** por el motivo original: desde el ajuste de la
  sección 0, `precio_base` sí se cobra de verdad para formularios sin
  categoría, solo que como valor fijo, sin variar por fecha. Si en el
  futuro se pide que también varíe por período, ahí sí valdría la pena
  extender `category_price_periods` (o una tabla análoga) a form_types.
- Notificación/countdown proactivo (ej. "quedan 3 días de Preventa,
  avisar por mail") — el badge en la tarjeta es pasivo, no hay ningún
  job ni email nuevo en este PRD.
