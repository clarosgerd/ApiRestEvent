# PRD — Kit, tallas, stock y lista de espera

Sesión 11/08/2026. Parte del diagnóstico pedido por el usuario sobre si
PassToGo cumple la descripción de "Gestión de kits, tallas y stock" —
ver conclusión completa en el chat: se cumple la mitad comercial
(souvenirs opcionales a la venta + cupo por formulario), pero **no
existe** stock por talla/sexo, filtrado en tiempo real de tallas
agotadas, fotos de los ítems del kit, ni lista de espera real. Este
documento planea construir esa mitad faltante.

## Hallazgo adicional durante la investigación (no estaba en el diagnóstico original)

Al diseñar cómo enganchar el stock a la automatización de cupos,
apareció un bug preexistente más serio de lo reportado: **el flag
`form_types.activo` que se apaga cuando se llena el cupo no lo lee
nada.** `RegistrationService::deactivateFormTypeIfCupoLleno()` (ver
`app/Services/RegistrationService.php:35-51`) sí calcula bien cuándo el
cupo se llenó y sí pone `activo = false` — pero:

- `FormTypeResource` no expone `activo` en el JSON.
- `EventoController::show()` carga `formTypes` sin filtrar por `activo`.
- El frontend (`renderFormTypes()` en `elascenso/event/index.php`) no
  chequea ningún campo de disponibilidad — renderiza lo que llega.
- Nada en `RegistrationService::create()` ni en
  `elascenso/event/api/registro.php` vuelve a comprobar `activo` antes
  de aceptar una inscripción nueva.

Es decir: hoy, cuando un formulario llena su cupo, la bandera se prende
en la base de datos pero **el formulario sigue ofreciéndose y aceptando
inscripciones sin límite real** — la única protección real es que
`deactivateFormTypeIfCupoLleno()` se llama de nuevo en cada alta y
vuelve a marcar `activo=false` (redundante, no bloquea nada). Esto no
es parte del pedido original pero hay que arreglarlo como prerrequisito:
la lista de espera automática que pide el PRD no tiene sentido si el
cupo "lleno" nunca bloqueó nada para empezar.

## Decisiones ya tomadas con el usuario (11/08/2026)

1. **Unificar "kit incluido" y "souvenirs opcionales" en un solo modelo**
   (`souvenirs` con flag `incluido`), en vez de dejar el kit incluido
   como texto libre sin stock. Un solo mecanismo de talla/sexo/stock/foto
   sirve para ambos casos.
2. **Terminología: "ítem", no "souvenir", de acá para adelante.** El
   modelo/tabla existente (`Souvenir`, `souvenirs`,
   `SouvenirParticipante`, `SouvenirResource`, las rutas
   `souvenirs.store/update/destroy` de admin-eventos) **no se renombra**
   — está metido en rutas y claves JSON que ya consume el frontend de
   producción, renombrarlo es riesgo sin beneficio real. Lo que cambia:
   toda tabla/columna/texto **nuevo** de este PRD usa "item"/"ítem"
   (ej. `item_stock`, no `souvenir_stock`), y toda pantalla u correo
   nuevo le dice al organizador/participante "ítem del kit", nunca
   "souvenir". Un ítem sigue siendo, técnicamente, una fila de
   `souvenirs` — el cambio es de vocabulario visible, no de esquema.
3. **Alcance de esta ronda: punta a punta**, incluyendo el frontend de
   inscripción de producción (`elascenso/event/index.php`) — no se
   difiere a una 2da ronda como se hizo con sesiones de congreso.
4. **Promoción de lista de espera: automática, con email.** Cuando se
   libera cupo o stock, un job detecta el hueco y notifica por correo a
   quien corresponda de la lista — no es un proceso manual del
   organizador. Esto reabre el mismo cuidado ya documentado con
   certificados de congreso: local ya tiene sandbox de Mailtrap
   configurado (`ApiRestEvent/.env`), QA/producción siguen con SMTP real
   hasta que alguien lo configure ahí también — ver
   [[project_sesiones_congreso]].

## Decisión de diseño que el usuario debería revisar antes de aprobar

**La promoción automática notifica, no reserva.** Cuando se libera un
cupo/talla, se le manda un correo a la primera persona pendiente de la
lista diciendo "ya hay lugar, inscribite" — **no se le bloquea el cupo
un tiempo mientras decide.** Es primero-en-llegar-primero-en-servir
después del aviso, igual que hoy el cupo en general se calcula
**contando en vivo**, no reservando con un contador que se resta/suma
(ver comentario en `RegistrationService::deactivateFormTypeIfCupoLleno`,
mismo criterio). Reservar temporalmente implicaría un sistema de holds
con expiración — no está pedido, se puede agregar después si en la
práctica la gente se queja de que el cupo se les escapa entre el correo
y la inscripción.

## 1. Modelo de datos

### `souvenirs` (extender, tabla existente)

Migración aditiva — agrega columnas, no rompe nada de lo que ya
funciona (compra de souvenirs opcionales sigue igual si no se les
configura talla/sexo/stock):

| Columna | Tipo | Nota |
|---|---|---|
| `incluido` | boolean, default `false` | `true` = viene en el precio base del form_type (kit), `false` = opcional a la venta (comportamiento actual) |
| `foto_url` | string, nullable | mismo patrón que `logo_url`/`imagen_portada_url` del evento — URL, no upload de archivo |
| `requiere_talla` | boolean, default `false` | si `true`, el ítem necesita que el participante elija una talla de las que tengan stock |
| `requiere_sexo` | boolean, default `false` | idem, para corte masculino/femenino/unisex |

### `item_stock` (nueva)

Nombre nuevo, sin arrastrar "souvenir" — ver la decisión de
terminología arriba. Una fila por combinación talla×sexo disponible de
un ítem. Si un ítem no requiere talla ni sexo (ej. una medalla), tiene
una sola fila con `talla=null, sexo=null`.

| Columna | Tipo | Nota |
|---|---|---|
| `souvenir_id` | FK `souvenirs`, cascadeOnDelete | el nombre de columna sigue `souvenir_id` porque apunta a la tabla `souvenirs` que no se renombra — es un detalle interno, no algo que el organizador vea |
| `talla` | string, nullable | `XS/S/M/L/XL/XXL` u otro catálogo libre — no se fuerza enum, cada organizador puede tener su propio rango |
| `sexo` | enum `['masculino','femenino','unisex']`, nullable | |
| `cantidad_total` | unsignedInteger | **no** es "cantidad disponible" — es el total cargado; el disponible se calcula contando en vivo, mismo criterio que `cupo_total` |
| unique | (`souvenir_id`,`talla`,`sexo`) | evita cargar la misma combinación dos veces por error |

**Por qué contar en vivo y no decrementar un contador**: mismo
razonamiento documentado en `RegistrationService` para `cupo_total` — un
contador que se resta al inscribir y se suma al cancelar es una fuente
de bugs de sincronización (doble resta, resta sin la suma
correspondiente si el flujo de cancelación tiene otro camino, etc.).
Contar `SouvenirParticipante` (el modelo existente, sin renombrar) de
inscripciones vigentes es la misma fuente de verdad que ya usa el
cupo — se generaliza el patrón, no se inventa uno nuevo.

### `souvenir_participantes` (extender, tabla existente)

Agrega `talla` (string, nullable) y `sexo` (string, nullable) — hoy la
fila no guarda qué talla/sexo eligió el participante, así que no hay
manera de saber qué stock específico se consumió.

### `lista_espera` (nueva)

| Columna | Tipo | Nota |
|---|---|---|
| `evento_id` | FK `eventos`, cascadeOnDelete | |
| `form_types_id` | FK `form_types`, cascadeOnDelete | |
| `souvenir_id` | FK `souvenirs`, nullable, cascadeOnDelete | si la espera es por una talla agotada puntual; `null` si es por cupo general lleno |
| `talla` / `sexo` | string/enum, nullable | qué combinación puntual esperaba, si aplica |
| `nombre`, `correo`, `telefono` | string | datos de contacto — quien se anota no necesariamente tiene cuenta (`Persona`) todavía |
| `estado` | enum `['pendiente','promovido','expirado','cancelado']`, default `pendiente` | |
| `promovido_at` | timestamp, nullable | |

Orden de promoción: FIFO por `created_at` dentro del mismo
`form_types_id`/`souvenir_id`/`talla`/`sexo`.

### Migración de datos — el "kit incluido" actual

`form_types.hasshirt`/`costo_polera`/`requiere_talla` **no se borran**
(quedan de solo lectura, por compatibilidad si algo externo los
consulta), pero dejan de ser la fuente de verdad. Migración de datos
(no de schema) que, por cada `FormType` con `hasshirt=true`, crea un
`Souvenir` con `incluido=true`, `name='Polera de kit'`,
`price=costo_polera ?? 0`, `requiere_talla=true`,
`requiere_sexo=false` (el sistema actual no distingue sexo en la polera
incluida — si el organizador la necesita segmentada por sexo, la edita
después desde el panel) y una fila de stock inicial "sin límite" —
concretamente, **no se crea ninguna fila en `item_stock`** para esos
ítems migrados: sin filas de stock, el sistema los trata como
"disponibilidad no controlada" (ver sección 2) para no bloquear de
golpe inscripciones que hoy funcionan sin ningún control de stock. El
organizador carga stock real cuando quiera empezar a controlarlo.

## 2. Regla de disponibilidad ("¿hay stock?")

Un ítem tiene **disponibilidad no controlada** si no tiene ninguna
fila en `item_stock` — se comporta como hoy, siempre disponible, sin
mostrar tallas (comportamiento de compatibilidad, no rompe eventos
existentes al desplegar esto).

Un ítem **con** filas en `item_stock` sí queda controlado:
`disponible(talla, sexo) = cantidad_total - count(SouvenirParticipante de inscripciones vigentes con ese souvenir_id+talla+sexo)`.
"Vigente" = mismo filtro que ya usa el cupo:
`pago_status NOT IN ('cancelled','failed')`.

## 3. API (ApiRestEvent)

- `SouvenirResource` (sin renombrar, ver decisión de terminología):
  agrega `incluido`, `foto_url`, `requiere_talla`, `requiere_sexo`, y
  `tallas` — un array `[{talla, sexo, disponible}]` calculado (vacío si
  el ítem no tiene stock controlado).
- `FormTypeResource`: agrega `activo` (**hoy no se expone, hay que
  agregarlo** — parte del arreglo del bug de la sección "Hallazgo
  adicional") y `cupoDisponible` (int, mismo cálculo que ya hace
  `deactivateFormTypeIfCupoLleno` pero expuesto, no solo usado
  internamente).
- `POST/PUT /souvenirs/{id}/stock` (admin, scoped al evento o
  super_admin) — CRUD de filas `item_stock`, mismo patrón de
  autorización que el resto de admin-eventos
  (`assertCanWriteEvento`/`AuthorizesEventoScope`). La ruta sigue bajo
  `/souvenirs/` porque opera sobre el recurso existente — solo la tabla
  de stock que administra es nueva.
- `POST /event/{event}/lista-espera` — anotarse (público, sin auth,
  como el resto del flujo de inscripción) — valida que el
  `form_types_id` realmente esté lleno/agotado antes de aceptar la
  anotación (si hay cupo, no tiene sentido anotarse, se le dice que se
  inscriba directamente).
- `GET /event/{event}/lista-espera` (admin) — listado con filtros, para
  ver el estado aunque la promoción sea automática.

### Arreglo del cupo (prerrequisito, sección "Hallazgo adicional")

- `RegistrationService::create()` (y el paso equivalente que hoy hace
  `resolverFormType()` en `elascenso/event/api/_registro_validacion.php`)
  deben rechazar la inscripción si `!$formType->activo` — con un 422
  claro, no un 500 ni un éxito silencioso.
- Este chequeo debe ir **dentro de una transacción con lock** (`lockForUpdate()`
  sobre el `FormType` al contar inscritos) para que dos inscripciones
  simultáneas contra el último cupo no pasen ambas — la misma condición
  de carrera aplica al stock de `item_stock`, se resuelve igual.

## 4. admin-eventos

- `eventos/edit.blade.php` — sección de ítems del kit extendida (hoy
  dice "Souvenirs" en el título de la sección, pasa a decir "Ítems del
  kit"): checkbox `incluido`, input `foto_url`, checkboxes
  `requiere_talla`/`requiere_sexo`.
- Pantalla nueva `souvenirs/{id}/stock` ("Stock del ítem" en el título) —
  tabla editable talla×sexo×cantidad, con fila calculada de "disponible
  ahora" (cantidad_total menos ocupado) al lado de cada una, para que el
  organizador vea el consumo sin tener que ir a otra pantalla.
- Pantalla nueva `eventos/{id}/lista-espera` — listado de anotados, con
  su estado (pendiente/promovido/expirado/cancelado); de solo lectura en
  esta ronda (la promoción es automática, no hay botón "promover a
  mano" — se puede agregar después si el organizador lo pide).

## 5. Frontend de inscripción (`elascenso/event/index.php`) — el cambio de mayor riesgo

- El bloque hardcodeado `#shirtSizeContainer` (líneas ~1551-1561, opciones
  XS-XXL fijas) deja de ser estático: se reemplaza por un render dinámico
  a partir de `item.tallas` del ítem `incluido=true` con
  `requiere_talla=true` de ese form_type — si no hay ninguno (evento
  viejo migrado sin filas de stock), cae al comportamiento actual
  (mismas 6 opciones fijas) para no romper eventos existentes.
- Cualquier ítem opcional con `requiere_talla`/`requiere_sexo` en el
  paso de selección de ítems del kit (la clave JSON sigue siendo
  `souvenirs`, ver decisión de terminología — el texto que ve el
  participante dice "ítems"): mismo patrón, solo se listan
  combinaciones con `disponible > 0`.
- `renderFormTypes()`: si `formType.activo === false` y
  `formType.cupoDisponible === 0`, la tarjeta se muestra pero
  deshabilitada ("Cupo lleno") — y si además
  `formType.permite_lista_espera`, un botón "Unirme a la lista de
  espera" abre un mini-formulario (nombre/correo/teléfono) que llama al
  endpoint nuevo, en vez de arrancar el flujo normal de inscripción.
- Foto del kit: si el evento tiene ítems `incluido=true` con
  `foto_url`, se muestra una galería simple en la pantalla de detalle
  del evento (antes de elegir form_type) — contenido nuevo, no reemplaza
  nada existente.

## 6. Job de promoción automática

`lista-espera:promover` (comando nuevo, mismo patrón que
`certificados:enviar-congreso`/`eventos:cerrar-finalizados` en
`routes/console.php`, `->daily()` — no hace falta tiempo real, la
persona ya está esperando hace rato si llegó a la lista):

1. Por cada `form_types_id` con filas `lista_espera` en estado
   `pendiente`: recalcula `cupoDisponible` (o `disponible(talla,sexo)`
   si la espera es por un ítem puntual).
2. Si hay hueco, toma tantos de la lista (FIFO) como huecos haya, les
   manda `ListaEsperaPromovidaMail` (nuevo Mailable, mismo patrón de PDF
   opcional si aplica — en este caso no lleva adjunto, es solo un aviso
   con el link directo a inscribirse) y marca `estado='promovido'`,
   `promovido_at=now()`.
3. Igual que certificados: la fila solo cambia de estado si el envío de
   correo tuvo éxito — un fallo de SMTP puntual se reintenta la próxima
   corrida diaria, no se pierde a la persona de la lista.

## 7. Testing y despliegue (mismo criterio que el resto de esta sesión)

- Todo contra `event_testing` primero, nunca contra la BD real de
  desarrollo sin un ok explícito aparte.
- Tests Pest nuevos para: cálculo de disponibilidad con/sin stock
  controlado, rechazo de inscripción cuando `activo=false` (el fix del
  bug), condición de carrera del último cupo/última talla (2 inserts
  concurrentes, solo 1 debe pasar), anotación a lista de espera solo
  cuando corresponde, promoción automática con `Mail::fake()` (nunca un
  envío real en tests, mismo criterio que certificados de congreso).
- Deploy checklist + SQL standalone en `elascenso/event/brain/`, mismo
  formato que las rondas anteriores (script SQL para QA sin `artisan`,
  advertencia sobre el comando nuevo en el scheduler).
- Antes de activar el comando `lista-espera:promover` en cualquier
  hosting con cron real: confirmar con el usuario qué eventos con lista
  de espera ya existente recibirían el primer envío (mismo cuidado que
  con certificados de congreso).

## Fuera de alcance (a propósito)

- Holds/reservas temporales de cupo tras la promoción (ver sección de
  decisión de diseño arriba) — se evalúa después si hace falta.
- Upload real de archivo para `foto_url` — sigue el patrón existente de
  campos de imagen por URL, no se agrega un sistema de subida de
  archivos nuevo.
- Botón de "promover a mano" en el panel — la promoción es 100%
  automática esta ronda; se agrega un botón manual solo si el usuario lo
  pide después de ver cómo funciona en la práctica.
