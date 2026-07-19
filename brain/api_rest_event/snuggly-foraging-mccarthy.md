# Plan: Campos dinámicos del formulario de registro

## Contexto

`Requerimiento_modificacion_formulario_adcionarcamposdinamicos.md` pide que el
formulario de registro (Pantalla 3, `elascenso/event/index.php`) renderice
preguntas configurables por evento/tipo de formulario, que ya vienen en el
JSON de `GET /api/v1/event/{id}` bajo `formTypes[].preguntas[]`, y que las
respuestas viajen junto al resto del registro en
`POST /api/v1/registrations`. El formulario "de siempre" (nombre, apellido,
categoría, souvenirs, etc.) no debe modificarse — solo se agrega el bloque
dinámico.

Investigación confirmó que **el backend (`ApiRestEvent`) ya soporta todo
esto de punta a punta**, sin necesidad de tocar ese repo:
- `StoreRegistrationRequest.php:37-41` ya valida
  `participantes[].answers[].{form_types_id,question_id,value}`.
- `RegistrationService::createParticipant()`
  (`app/Services/RegistrationService.php:155-162`) ya persiste cada answer.
- Tanto `EventoController::index()` como `::show()` ya hacen
  `->with('formTypes.formularioCampos.options')` / `loadMissing([...])`, así
  que `preguntas[]` (con `options[]` ya parseado, no el string crudo
  `opciones`) llega completo tanto en el listado como en el detalle del
  evento.

El trabajo real es **100% frontend** (`index.php`), más un endurecimiento
opcional y barato en el `api/registro.php` local (que hoy re-envía cualquier
key extra del participante sin validarla, vía `array_merge($p, [...])` en
`registro.php:187-192`).

Decisiones ya tomadas con el usuario:
1. **Gating:** mostrar el bloque si `preguntas.length > 0` (se ignora
   `hasQuestion`, que en el seed actual no es confiable).
2. **`tipo_input=file`:** fuera de alcance — no se renderiza ese campo.
3. **Ubicación:** sección nueva al final del paso de participante, después
   de Souvenirs y antes de los botones "Save Participant", agrupada por
   `seccion` (encuesta/otro/personal/kit/etc.), sin agregar un paso al
   stepper.

## Diseño

### Contrato de datos con el backend

Cada respuesta se envía como un objeto independiente
`{ form_types_id, question_id, value }` (todo `value` es **string**, no hay
`option_id` — el valor guardado para radio/select/checkbox es el
`option_text` de la opción elegida, tal como acepta `AnswerDTO`). Para
`checkbox` (multi-selección), se envía **un objeto por opción marcada**, todos
con el mismo `question_id` — el backend simplemente crea una fila `Answer`
por cada entrada del array, así que múltiples filas con igual `question_id`
son válidas y es como ya se modela una respuesta multi-valor.

Convención de `id` en el DOM: `dynq_<question.id>` para campos de valor único
(text/email/date/tel/number/select), y `name="dynq_<question.id>"` compartido
entre los inputs radio/checkbox de una misma pregunta (con
`id="dynq_<question.id>_opt_<option.id>"` por opción). Esto permite volver a
leer/restaurar valores sin guardar estado aparte — siempre se itera
`selectedFormType.preguntas` (igual que ya se hace con
`selectedFormType.hasshirt` en `validateForm()`).

Mapeo de `tipo_input` → widget:
| `tipo_input` | Render |
|---|---|
| `radio`, `checkbox` | grupo de `<input>` dentro de `.radio-option`/`.radio-group` (clases ya existentes, genéricas — no son solo para radios), usando `options[]` |
| `select` | `<select>` con `options[]` |
| `file` | **no se renderiza** (fuera de alcance) |
| cualquier otro valor (`text`, `email`, `date`, `tel`, `number`, `url`, o desconocido) | `<input type="...">`, mapeando 1:1 si es un `type` HTML válido, si no `text` por defecto |

### Cambios en `elascenso/event/index.php`

**1. HTML — nuevo contenedor condicional**, insertado justo antes de
`<!-- Botones -->` (después del bloque `#souvenirsSection`, ~línea 1270):

```html
<div id="dynamicQuestionsSection" style="display:none;">
  <div class="section-title">
    <div class="num" style="font-size:16px;">📋</div>
    <h2 data-i18n="registration.additionalInfoTitle">Additional Information</h2>
  </div>
  <div id="dynamicQuestionsContainer"></div>
  <hr class="divider">
</div>
```

**2. Nueva función `renderDynamicQuestions(ft)`**, llamada desde
`buildEventUI()` (`index.php:2515`) justo después del bloque de souvenirs
(~línea 2557). Agrupa `ft.preguntas` por `seccion` (preservando el orden de
primera aparición), ordena cada grupo por `orden`, y por cada pregunta
delega a un helper `renderDynamicField(q)` que arma el HTML según la tabla
de arriba, reutilizando `.form-group`, `.form-row`, `.radio-group`,
`.radio-option`, `.field-error` (clases ya definidas en el `<style>` de
`index.php`, sin CSS nuevo). Si `preguntas` está vacío, oculta la sección
(mismo patrón que `donationSection`/`shirtSection` en `buildEventUI()`).
Todo texto proveniente de la API (`etiqueta`, `placeholder`, `option_text`)
pasa por `escHtml()` (`index.php:3511`), igual que el resto del formulario.

**3. `validateForm()`** (`index.php:2758`) — agregar, antes del `return ok`,
un loop sobre `selectedFormType?.preguntas` filtrando `obligatorio` truthy:
para radio/checkbox exige `querySelector('input[name="dynq_<id>"]:checked')`;
para el resto exige `document.getElementById('dynq_<id>').value` no vacío.
Reutiliza `showErr`/`hideErr`/`markInvalid`/`markValid` (ya genéricas por
`id`), con un span `#err-dynq-<id>` (`registration.errRequired` como texto,
key ya existente) por campo. Mismo patrón que la validación condicional de
camiseta (`index.php:2787-2793`).

**4. `saveParticipant()`** (`index.php:2807`) — al construir `participant`,
agregar `participant.answers = [...]` leyendo del DOM con la misma
convención de ids, mirando `selectedFormType.preguntas` (mismo patrón que la
lectura de `.souvenir-card.checked` unas líneas arriba). Campos de texto
vacíos y no obligatorios se omiten (no se manda answer vacía).

**5. `editParticipant(i)`** (`index.php:2925`) — al final, restaurar
`p.answers` en los inputs dinámicos (buscar por `question_id` +
`tipo_input`), y llamar `syncRadioStyles()` (`index.php:2005`, ya genérica
para cualquier `.radio-option` con un `<input>` adentro, sirve tal cual para
checkboxes también) para refrescar el estilo `.checked`.

**6. i18n** — agregar `additionalInfoTitle` a los 3 bloques de
`translations` (en/es/pt, junto a `souvenirsTitle`: líneas ~1563/1663/1763).
No hacen falta más keys: las etiquetas de los campos vienen del propio JSON
del evento (`etiqueta`), y el error reutiliza `registration.errRequired`.

**No se toca:** `normalizeEvento()` (ya preserva `formTypes` intacto),
`resetParticipantForm()` (el `form.reset()` que ya llama limpia los inputs
dinámicos porque son controles de formulario reales dentro de
`#participantForm`), `buildSummary()`/pantalla de revisión (el requerimiento
no pide mostrar las respuestas ahí).

### Cambios en `elascenso/event/api/registro.php`

Endurecimiento opcional pero consistente con el patrón ya usado ahí para
souvenirs (`registro.php:159-169`, que filtra souvenirs que no pertenecen al
`formType`): agregar, dentro del `foreach ($participantes as $idx => $p)`
(después del filtrado de souvenirs, ~línea 169), un filtrado análogo de
`answers` contra `$formType['preguntas']`, descartando cualquier
`question_id` que no pertenezca al tipo de formulario, y recalculando
`form_types_id` desde `$formType['id']` en vez de confiar en el valor que
mande el cliente:

```php
$ftQuestions = [];
foreach (($formType['preguntas'] ?? []) as $q) { $ftQuestions[(string)$q['id']] = true; }

$answersValidas = [];
foreach (($p['answers'] ?? []) as $ans) {
    $qid = (string)($ans['question_id'] ?? '');
    if ($qid === '' || !isset($ftQuestions[$qid])) continue;
    $answersValidas[] = [
        'form_types_id' => (int)($formType['id'] ?? 0),
        'question_id'   => (int)$qid,
        'value'         => (string)($ans['value'] ?? ''),
    ];
}
```

Y agregar `'answers' => $answersValidas` al `array_merge($p, [...])` de la
línea 187 (una key de `array_merge` sobrescribe la del array original, así
que esto reemplaza cualquier `answers` crudo del cliente por la versión
saneada). **No se agrega enforcement server-side de `obligatorio`** — queda
como validación client-side únicamente, igual alcance que lo decidido con
el usuario (no se pidió endurecer eso).

## Archivos a modificar

| Archivo | Cambio |
|---|---|
| `elascenso/event/index.php` | HTML del contenedor, `renderDynamicQuestions()`/`renderDynamicField()` nuevas, hooks en `buildEventUI()`, `validateForm()`, `saveParticipant()`, `editParticipant()`, 3 keys i18n nuevas |
| `elascenso/event/api/registro.php` | Filtrado/saneo de `answers` por participante, análogo al de souvenirs |
| `ApiRestEvent` | **Sin cambios** |

## Verificación

1. `curl http://127.0.0.1:8000/api/v1/event` → elegir un evento/formType con
   `preguntas` no vacío (el id 2 del requerimiento ya sirve de ejemplo).
2. Playwright headless contra `http://localhost/elascenso/event/index.php`
   (mismo enfoque ya usado para los filtros): seleccionar ese evento/tipo,
   verificar que aparece la sección "Additional Information" con los campos
   esperados (radio/checkbox/select/text agrupados por sección), completar
   los obligatorios, guardar el participante, editarlo y confirmar que los
   valores se restauran, y finalmente completar el registro.
3. Interceptar el `POST` a `${API_BASE}/registro.php` (Playwright
   `page.on('request')` o revisar `data/registros.json`/`network`) y
   confirmar que `participantes[].answers[]` viaja con
   `form_types_id`/`question_id`/`value` correctos.
4. Probar el caso de saneo: enviar con `curl` un `question_id` inventado
   directo a `registro.php` y confirmar que la respuesta guardada no lo
   incluye.
5. Caso obligatorio sin completar: confirmar que `validateForm()` bloquea el
   guardado y muestra el error inline en el campo dinámico.
6. Agregar una entrada breve a `TEST_PLAN.md` documentando estos casos
   manuales (el proyecto no tiene framework de tests automatizados).
