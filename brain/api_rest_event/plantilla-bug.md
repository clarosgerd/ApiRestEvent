# Bug: <resumen corto en una línea> — DD/MM/AAAA

> Plantilla para documentar bugs que afectan a `event` (frontend) y/o `ApiRestEvent` (backend). Copiar
> este archivo, renombrar como `bug-<slug-corto>-<entorno>-DDMMAAAA.md` (mismo patrón que
> `bug-auth-header-mod-lsapi-uat-25072026.md`) y borrar las secciones que no apliquen — no hace falta
> llenar todas si el bug es simple. Existe copia idéntica en `ApiRestEvent/brain/api_rest_event/` —
> usar la que esté más a mano, el contenido final vive donde se termine el diagnóstico (normalmente
> `event/brain/` si involucra al frontend, o el otro si es 100% backend).

## Metadata

- **Fecha:** DD/MM/AAAA
- **Reportado por:**
- **Entorno:** local / UAT / producción
- **Repo(s) afectado(s):** `event` / `ApiRestEvent` / ambos
- **Severidad:** bloqueante / alta / media / baja
- **¿Reproducible siempre?:** sí / no / intermitente (N/M intentos)

## Síntoma

Qué se ve (mensaje de error exacto, pantalla, endpoint) y quién lo sufre (todos los usuarios / un caso
específico / un organizador). Si se descartó que fuera otro bug ya conocido, decirlo explícitamente
(evita que alguien re-investigue algo ya resuelto).

## Diagnóstico

Pasos seguidos para aislar la causa, en orden, con la evidencia concreta de cada uno (requests
probadas, respuestas HTTP, queries a la BD, logs). Preferir "se probó X contra Y y devolvió Z" sobre
descripciones vagas — esta sección es la que más ahorra tiempo si el bug reaparece.

## Causa raíz

Una explicación técnica precisa de por qué pasa, no solo dónde. Si la causa es una limitación externa
(hosting, API de terceros, librería), decirlo — cambia qué tipo de fix es posible.

## Fix aplicado

Separar por repo si el fix tocó a los dos:

**`ApiRestEvent`:**
- Archivo — qué cambió y por qué (no repetir el código, solo la razón si no es obvia).

**`event`:**
- Archivo — qué cambió y por qué.

Si el fix requiere una acción manual fuera de código (variable de entorno nueva, migración, dato en
BD), listarla acá explícitamente con el valor/comando exacto.

## Verificación

Cómo se confirmó que el fix funciona — casos probados (local/UAT/prod según corresponda) y su
resultado real, incluyendo el caso que antes fallaba y ahora no.

## Pendiente / no bloqueante

Cabos sueltos que quedaron a propósito sin resolver (datos de prueba sin limpiar, workaround temporal,
limitación conocida que no bloquea el fix) — para que no se confundan con trabajo olvidado.
