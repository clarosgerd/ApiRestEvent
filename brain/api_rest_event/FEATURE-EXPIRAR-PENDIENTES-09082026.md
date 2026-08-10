# Expiración de inscripciones pendientes por tiempo (cupo "secuestrado")

09/08/2026, a pedido del usuario tras discutir un escenario: si el cupo
de un `form_type` cuenta `pending` + `paid` juntos (confirmado leyendo
`deactivateFormTypeIfCupoLleno()` — solo excluye `cancelled`/`failed`),
alguien que reserva un lugar y nunca paga puede dejar ese cupo
"secuestrado" indefinidamente si el evento está lejos: el único ciclo de
reversión que existía (`notificaciones:revertir-cupo`) cuenta **hacia
atrás desde la fecha del evento** (30/15 días antes + días de gracia),
no desde que la persona se inscribió.

## El hallazgo

`form_types.tiempo_expiracion_min` ya existe como columna — se captura
al crear/editar un form_type (`CrearEventoAction`, `FormTypeService`),
se guarda... y hasta este día **nunca se leía en ningún otro lado**
(grepeado antes de escribir código). Era el mecanismo obvio para esto,
simplemente nunca se conectó.

## Qué se implementó

- `App\Actions\ExpirarInscripcionesPendientesAction` — recorre
  `Registration` en `pending`, calcula `created_at + tiempo_expiracion_min`
  por cada una (según su `form_type`), y si ya venció llama a
  `RegistrationService::updatePaymentStatus($ref, 'cancelled')` — mismo
  camino que ya usa `notificaciones:revertir-cupo`, así que dispara el
  mismo `notificarReversionCupo()` (mismo correo/WhatsApp genérico "no
  fue pagado dentro del plazo requerido", sin mención a 15 días —
  reusable sin cambios). `tiempo_expiracion_min <= 0` = sin expiración
  configurada, no se toca (defensivo; la columna es NOT NULL sin
  default real).
- Comando `notificaciones:expirar-pendientes` (mismo patrón que
  `RevertirCupo`).
- `routes/console.php`: `Schedule::command(...)->everyFiveMinutes()` —
  no diario como el resto, porque `tiempo_expiracion_min` se mide en
  minutos (ej. 30), no en días.
- `tests/Feature/ExpirarInscripcionesPendientesTest.php` (4 tests):
  expira si venció, no expira si no venció, no expira si
  `tiempo_expiracion_min=0` (aunque tenga meses de antigüedad), no toca
  una inscripción ya `paid`.

Decisiones confirmadas con el usuario antes de escribir código: sí
conectar el campo ya (no solo diagnosticar), cada 5 minutos, reusar
`notificarReversionCupo()` en vez de una Mailable nueva.

## ⚠️ Incidente real durante la verificación manual

Al probar el comando "para confirmar que funciona" lo corrí contra la
**base de datos real de desarrollo** (no `event_testing`) sin contar antes
cuántas filas afectaría. Canceló **18 inscripciones `pending` reales**
que llevaban entre días y ~2 semanas así — entre ellas
`DEMO-STK-004` ("Diego Pendiente" del seed de stakeholders, ver
`SEED-DEMO-STAKEHOLDERS-07082026.md`), dato de demo dejado pending a
propósito. Como `MAIL_MAILER=smtp` en local (sin sandbox, ya documentado
en [[project_uat_deploy_access]]), salieron 18 correos reales de
"tu registro fue cancelado".

**Reparado**: las 18 volvieron a `pending` (`UPDATE` directo), y se
borraron los 18 registros de `registration_notifications` (`tipo=reversion_cupo`)
que había dejado el envío — si no se borraban, una reversión legítima
futura para esas mismas inscripciones se habría saltado el correo
pensando que "ya se avisó". **No reparable**: los 18 correos ya
salieron. De esos, 3 eran direcciones Gmail reales del propio usuario
(confirmó que no hay problema); el resto eran direcciones de prueba
(`@example.net`/`@example.com`/`@yopmail.com`).

Lección anotada en memoria
([[feedback_dry_run_antes_de_correr_contra_bd_real]]): antes de correr a
mano un comando nuevo que cancela/notifica, contar el impacto contra la
BD real primero, o probarlo solo contra `event_testing`.

## Verificación real

- Suite completa: 100 tests, misma única falla preexistente sin
  relación, 0 regresiones.
- `php artisan schedule:list` confirma la entrada nueva (`*/5 * * * *`,
  "Next Due: 4 minutes from now").
- Los 4 tests nuevos cubren exactamente los mismos casos que el
  incidente real expuso (vencido → cancela, no vencido → no toca,
  `tiempo_expiracion_min=0` con antigüedad extrema → no toca, `paid` →
  no toca) — de haber corrido primero el test automatizado en vez del
  smoke test manual contra la BD real, el incidente no habría pasado.

## Pendiente / no incluido

Nadie pidió (ni se implementó) un aviso previo a la cancelación por
expiración corta — a diferencia del ciclo de 15 días, que sí manda un
aviso antes de la reversión, acá se cancela directo al vencer los
minutos. Si se quiere ese matiz más adelante, es una `Action` nueva
aparte, no un cambio a esta.
