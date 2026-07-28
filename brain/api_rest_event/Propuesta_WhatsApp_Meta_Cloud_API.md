# Propuesta a futuro — Canal WhatsApp oficial (Meta Cloud API)

**Fecha:** 2026-07-27
**Estado:** Propuesta para evaluación — **no implementado, no presupuestado en firme**. No confundir con `INFORME-DEVOPS-DBA-NOTIFICACIONES.md` (eso ya está construido y probado; esto es un canal adicional a futuro).
**Repo:** `ApiRestEvent` (backend) — mismo lugar donde viven hoy los canales `openwa` y `externo`.

## 1. Por qué evaluar esto

Hoy `organizadores.whatsapp_canal` soporta `openwa` (instancia propia, automatiza WhatsApp Web) y `externo` (cola en tabla `mensaje`, la procesa un software de terceros). Ninguno de los dos es la API oficial de Meta. Riesgos conocidos de `openwa`, documentados en `INFORME-DEVOPS-DBA-NOTIFICACIONES.md` §5:

- No es oficial — corre sobre automatización de WhatsApp Web. El número usado puede ser bloqueado por Meta sin aviso ni proceso de apelación claro.
- Requiere sesión escaneada por QR y mantenida viva; si se cae, los mensajes se acumulan en la cola de jobs sin enviarse.
- Bug confirmado del servidor de OpenWA: a veces entrega el mensaje real y igual devuelve 500 (§5 del informe) — no es un canal 100% confiable para auditoría.

Con la cuenta de developer de Meta ya creada, la alternativa es agregar `meta` como tercer valor de `whatsapp_canal` — arquitectura ya definida en conversación previa (enum + `SendWhatsappMetaMessageJob` + templates aprobados). Este documento cubre específicamente **el tema de costos**, que es lo que falta para decidir si conviene y a qué escala.

## 2. Cómo cobra Meta (modelo vigente)

Fuente: búsqueda de referencias de pricing 2026 (ver Fuentes al final) — **no hay tarifa específica de Bolivia publicada en las fuentes consultadas**, hay que confirmarla en el rate card real antes de aprobar presupuesto (§5).

- Desde el **1 de julio de 2025**, Meta cobra **por mensaje entregado**, no por "conversación" de 24h como antes. El precio depende de la **categoría** de la plantilla y del **país del destinatario**.
- Categorías relevantes para nuestro caso (nuestros 5 tipos — `PEN`, `R30`, `R15`, `REV`, `KIT` — son notificaciones transaccionales, caen en **Utility**, no en Marketing):
  - **Utility** (recordatorios, confirmaciones): la más barata de las tres pagas. Admite descuento por volumen mensual.
  - **Marketing** (promociones): la más cara, **sin** descuento por volumen. No la usamos hoy — solo aplicaría si a futuro se quisiera mandar el envío mensual de marketing (`notificaciones:marketing-mensual`, hoy por correo) también por WhatsApp.
  - **Authentication** (OTP): no aplica a este proyecto, no mandamos códigos de verificación por WhatsApp.
  - **Service**: mensajes de texto libre respondiendo dentro de una conversación iniciada por el usuario — hoy gratis, pero ver el cambio de octubre 2026 abajo.
- Rango observado de **Utility** entre países: desde ~US$ 0.0008 (Colombia) hasta ~US$ 0.055 (Alemania). Bolivia no aparece en las fuentes revisadas — puede estar en cualquier punto de ese rango; **hay que confirmarlo en developers.facebook.com/docs (pricing) o en WhatsApp Manager → Facturación** antes de dar un número final.
- Meta actualiza el rate card **trimestralmente** (1er día de cada trimestre) — cualquier estimación de este documento debe revisarse cada 3 meses, no es un costo fijo de por vida.

### ⚠️ Cambio importante que ya está anunciado: 1 de octubre de 2026

Según las fuentes consultadas, a partir de esa fecha **todos** los mensajes salientes vía API se cobran — incluyendo texto libre y plantillas Utility **aunque se manden dentro de la ventana de servicio de 24h** (hoy esas son gratis). Esto no afecta a `openwa` (no es la API oficial de Meta), pero si migramos a `meta`, hay que asumir que **no va a existir un uso "gratis"** salvo que la fuente esté desactualizada — confirmar este punto puntualmente antes de presupuestar, porque cambia el cálculo de fondo.

## 3. Estimación de costo (con supuestos explícitos — no hay volumen real de producción todavía)

El dataset actual en la BD es de demo/UAT (ver memoria de sesiones anteriores: reset de BD para demo, dataset Multisport de 50 eventos), así que no hay una serie histórica real de cuántas notificaciones `PEN`/`R30`/`R15`/`REV`/`KIT` se disparan por mes. Tabla de referencia con 3 escenarios de volumen, usando un precio Utility placeholder de US$ 0.01–0.03/mensaje (punto medio del rango observado, **a confirmar con el rate card real**):

| Volumen mensual de notificaciones WhatsApp | Costo estimado (a US$ 0.01) | Costo estimado (a US$ 0.03) |
|---|---|---|
| 500 (piloto, 1-2 organizadores) | US$ 5 | US$ 15 |
| 2,000 (uso moderado) | US$ 20 | US$ 60 |
| 10,000 (todos los organizadores activos) | US$ 100 | US$ 300 |

Cada inscripción puede generar hasta 4 notificaciones WhatsApp (`PEN`, `R30`, `R15` o `REV`, `KIT`) — el volumen mensual real depende de cuántas inscripciones pendientes/nuevas hay por mes, no es 1:1 con inscripciones totales.

## 4. Comparación openwa vs Meta oficial

| | `openwa` (actual) | `externo` (actual) | `meta` (propuesto) |
|---|---|---|---|
| Costo directo por mensaje | US$ 0 (instancia propia) | Depende del proveedor externo (fuera de este análisis) | Por mensaje, según categoría/país (§2) |
| Riesgo de bloqueo del número | Alto — no oficial | Depende del proveedor | Bajo — canal oficial |
| Requiere aprobación de plantillas | No (texto libre) | No (texto libre) | Sí — 5 plantillas a aprobar antes de usar |
| Confiabilidad de entrega/auditoría | Media — bug conocido de falsos 500 | Depende del proveedor | Alta — API con status de entrega oficial |
| Límite de envío | Depende de la sesión de WhatsApp Web | Depende del proveedor | Por tier de calidad, escala con reputación |
| Control de encendido/apagado | `whatsapp_canal` por organizador | `whatsapp_canal` por organizador | Igual patrón + posible kill switch global (§5) |

## 5. Recomendación

1. **No migrar todo de una vez.** Habilitar `meta` como piloto en 1-2 organizadores (mismo mecanismo `whatsapp_canal = 'meta'` que ya existe para los otros canales), medir volumen real durante 1-2 meses.
2. Con datos reales de volumen, pedir la tarifa Utility exacta para Bolivia (rate card en WhatsApp Manager, o directamente vía el rep de Meta si hay uno asignado) y recalcular la tabla del §3 con el número real, no el placeholder.
3. Confirmar explícitamente el cambio del 1 de octubre de 2026 antes de decidir escalar — si en efecto todo pasa a ser pago, el costo deja de depender de "cuántos abren la ventana de 24h" y pasa a ser 100% proporcional al volumen de notificaciones que ya generamos hoy (más fácil de presupuestar, pero sin margen "gratis").
4. Agregar, además del `whatsapp_canal` por organizador, un **kill switch global** (`META_WHATSAPP_ENABLED` en `.env`) para poder cortar el gasto de inmediato sin tocar la BD si el volumen se dispara inesperadamente.
5. Mantener `openwa`/`externo` como fallback mientras dure el piloto — no depende de esta decisión de costos para seguir operando.

## Fuentes consultadas (julio 2026, verificar vigencia antes de presupuestar)

- [WhatsApp Business API Pricing: 2026 Complete Cost Guide](https://www.engagelab.com/blog/whatsapp-business-api-pricing)
- [WhatsApp API Pricing Explained (2026) — Authgear](https://www.authgear.com/post/whatsapp-api-pricing/)
- [Meta WhatsApp Business API Pricing (2026) — Rates by Country — FormBeep](https://formbeep.com/whatsapp-api-pricing/)
- [Precios WhatsApp Business API 2026 — cómo funciona el cobro per-message](https://guiawabusiness.cliengo.com/precios)
- [Conversation-based pricing (Deprecated) — Meta for Developers](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing/conversation-based-pricing)

Ninguna de estas es la fuente oficial de Meta con el rate card vigente — son agregadores de terceros. **Antes de comprometer presupuesto**, confirmar el número exacto para Bolivia en `developers.facebook.com` (sección pricing) o en WhatsApp Manager → Facturación de la cuenta.
