# Guion de Demo para Stakeholders — Sistema de Inscripción a Eventos

Este documento es un checklist en lenguaje de negocio para mostrar el sistema en una demo en
vivo, paso a paso. No asume conocimiento técnico: cada punto describe qué hacer, qué mostrar, y
qué destacar frente al stakeholder. Para el detalle técnico de cómo está construido cada punto,
ver `TEST_PLAN.md` (checklist de desarrollo) y los documentos individuales en `brain/`.

**Evento preparado para la demo**: "Encuentro Cultural 1" (id 151) — tiene auspiciadores, mapa,
texto de deslinde y los 10 códigos de promoción Gold/Plata/Cobre. El resto del catálogo (50
eventos en total) sirve para mostrar variedad y probar el buscador/filtros.

> Nota: el nombre/id del evento insignia puede cambiar si se vuelve a correr el pipeline de seed
> (`brain/generate_eventos_seed.js` + `load_eventos_seed.js`) — siempre es el primer evento
> generado del lote de 50 demo, identificable porque es el único con los 10 códigos
> Gold/Plata/Cobre. Confirmar el id actual antes de la demo si hay dudas.

---

## 0. Preparación (antes de empezar)

- [ ] Confirmar que la aplicación abre en el navegador sin errores
- [ ] Confirmar que hay conexión a internet (el mapa necesita cargar los tiles; los logos de
  auspiciadores de esta demo son offline, no dependen de internet)
- [ ] Ubicar el evento "Encuentro Cultural 1" (id 151) en el catálogo (buscarlo por nombre si hace
  falta)
- [ ] Confirmar cuántos de los 10 códigos Gold/Plata/Cobre siguen sin usar antes de empezar — cada
  código es de un solo uso permanente, así que una demo anterior puede haber consumido alguno
- [ ] Si ya no quedan códigos suficientes para mostrar el punto 4 completo, pedir que se recargue
  el dataset de demo (ver `brain/demo-reset-seed-datos-23072026.md`) antes de presentar

---

## 1. Catálogo de eventos

- [ ] Mostrar la pantalla inicial: la lista de eventos con imagen, fecha y ubicación de cada uno
- [ ] Escribir un texto en el buscador (ej. "Feria") y mostrar que la lista se filtra en el momento
- [ ] Abrir los filtros avanzados y mostrar las opciones: estado (Abierto/Cerrado/Próximamente),
  tipo de evento, rango de precio, rango de fechas, ubicación
- [ ] Pasar de la página 1 a la página 2 de resultados y volver — mostrar que no hay un techo
  invisible de eventos ocultos
- [ ] Cambiar el idioma en el header (Español/English/Português) y mostrar que toda la pantalla
  se traduce, no solo algunos textos
- [ ] Cambiar la moneda (Bs/USD/BRL) y mostrar que los precios se recalculan al instante

## 2. Detalle del evento — "Encuentro Cultural 1"

- [ ] Abrir el evento desde el catálogo
- [ ] Mostrar la cuenta regresiva hasta la fecha del evento
- [ ] Mostrar la imagen o video de vista previa del evento
- [ ] Señalar el carrusel de auspiciadores — explicar que cada logo puede llevar a la página del
  auspiciador si el organizador cargó un link de contacto
- [ ] Mostrar el mapa interactivo con la ubicación/ruta del evento
- [ ] Elegir el tipo de inscripción "Individual" para continuar

## 3. Formulario de inscripción

- [ ] Completar los datos del participante: nombre, documento, fecha de nacimiento, contacto,
  contacto de emergencia
- [ ] Elegir una categoría de inscripción
- [ ] Si el tipo de inscripción lo pide, mostrar la selección de talla de polera
- [ ] Si el evento tiene souvenirs configurados, agregar uno y mostrar cómo se suma al total
- [ ] Mostrar el campo de donación opcional y cómo se refleja en el total
- [ ] Si el organizador configuró preguntas adicionales para este evento, completarlas y mostrar
  que el sistema no deja continuar si falta una obligatoria
- [ ] Guardar el participante y mostrarlo en la lista de inscritos
- [ ] *(Opcional, si el tipo de formulario permite inscripción grupal)* Agregar un segundo
  participante y señalar el descuento de grupo aplicado automáticamente

## 4. Códigos de promoción — tarjetas Gold/Plata/Cobre

- [ ] En la pantalla de revisión, ingresar uno de los códigos aún sin usar (Gold = 50% de
  descuento, Plata = 30%, Cobre = 20%) y presionar "Aplicar"
- [ ] Mostrar que el descuento se refleja de inmediato en el total
- [ ] Explicar el concepto de negocio: el organizador entrega tarjetas físicas con un código
  impreso; cada tarjeta solo puede canjearse una vez, sin importar quién la use
- [ ] Completar esa inscripción (con pago pendiente o QR) para dejar el código "canjeado"
- [ ] Intentar usar el **mismo código** en una segunda inscripción y mostrar el rechazo:
  *"Este código de promoción ya fue utilizado."*
- [ ] **Punto clave para el stakeholder**: aclarar que esto también protege contra dos personas
  intentando usar la misma tarjeta al mismo tiempo — el sistema solo deja pasar a una, nunca a
  las dos, incluso si ambas lo intentan en el mismo segundo

## 5. Deslinde de responsabilidad

- [ ] En la pantalla de Revisión y Pago, mostrar el bloque de deslinde con el texto cargado por el
  organizador para este evento
- [ ] Mostrar el botón para descargar el PDF del deslinde
- [ ] Mostrar que el botón "Confirmar Pago" queda deshabilitado hasta marcar el checkbox de
  aceptación
- [ ] Marcar el checkbox y mostrar que el botón se habilita recién en ese momento

## 6. Métodos de pago y confirmación

- [ ] Mostrar el resumen final: inscripción, donación, souvenirs, comisión de servicio, descuento
  aplicado, y total a pagar
- [ ] Elegir un método de pago (QR o "Pago pendiente")
- [ ] Confirmar el pago
- [ ] Si se eligió QR: mostrar la pantalla de espera con el código y el conteo regresivo
- [ ] Mostrar la pantalla de confirmación final con el e-ticket, y la opción de imprimirlo

## 7. Gestión de una inscripción existente *(si hay tiempo)*

- [ ] Iniciar sesión con una cuenta que ya tenga una inscripción **pendiente** (el dataset de demo
  trae 20 inscripciones pendientes de ejemplo)
- [ ] Mostrar que el formulario se precarga automáticamente con los datos guardados
- [ ] Modificar algo (ej. la donación) y volver a confirmar
- [ ] Repetir con una cuenta que tenga una inscripción ya **pagada** (20 de ejemplo también) —
  mostrar que los datos aparecen en modo solo lectura, y que hay que confirmar el costo adicional
  antes de poder editarla

## 8. Cierre

- [ ] Resumir lo mostrado: catálogo con búsqueda/filtros/paginación, multi-idioma, multi-moneda,
  formulario con categorías/souvenirs/donaciones/preguntas personalizadas, auspiciadores, mapa,
  deslinde legal con PDF descargable, códigos de promoción de un solo uso, métodos de pago, y
  edición de inscripciones existentes
- [ ] Espacio para preguntas del stakeholder

---

## Criterios de aceptación

- [ ] El catálogo permite encontrar eventos por texto, filtros y páginas sin perder resultados
- [ ] El detalle de un evento muestra auspiciadores y mapa cuando el organizador los cargó, y no
  deja espacios vacíos cuando un evento no los tiene
- [ ] El formulario calcula el total correctamente sumando categoría, souvenirs, donación y
  descuentos
- [ ] Un código de promoción se puede usar exactamente una vez — sin excepciones, ni con dos
  intentos simultáneos
- [ ] El deslinde de responsabilidad bloquea la confirmación de pago hasta ser aceptado
- [ ] La inscripción se confirma correctamente y genera un e-ticket válido
- [ ] Una inscripción existente (pendiente o pagada) puede consultarse y editarse iniciando sesión
