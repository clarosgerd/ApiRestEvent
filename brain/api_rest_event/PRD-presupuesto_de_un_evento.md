# PRD — Presupuesto de un evento

De acuerdo con las fuentes, la organización del presupuesto de un evento en PassToGo se gestionará a través de una nueva tabla denominada presupuesto_evento, diseñada para proporcionar al organizador un control financiero integral y un balance rápido de su actividad
.
Esta funcionalidad está planificada para la Fase 2 del proyecto y se organizará bajo los siguientes criterios técnicos y operativos:
1. Clasificación por Tipo e Ingresos/Gastos
El presupuesto no se limitará solo a las inscripciones, sino que permitirá registrar cualquier movimiento financiero del evento mediante una distinción clara entre:
Ingresos: Además de las inscripciones (que el sistema registra automáticamente), el organizador podrá añadir manualmente ingresos por patrocinios o donaciones
.
Gastos: Registro de todos los egresos operativos necesarios para la ejecución, como alquiler de chips, servicios de imprenta, compra de premios, entre otros
.
2. Estructura de la Información (Campos Clave)
Para asegurar una trazabilidad completa, cada entrada en el presupuesto se organizará con los siguientes datos:
Categorización: Clasificación por rubros (ej: "Marketing", "Logística", "Premios") para facilitar el análisis posterior
.
Detalle Económico: Registro exacto del monto, la moneda utilizada y la fecha de la transacción
.
Soporte Digital: El sistema permitirá incluir una URL al comprobante digital (recibo o factura), centralizando la documentación contable en un solo lugar
.
Responsabilidad: Se registrará qué usuario específico realizó el ingreso del dato para mantener un control de auditoría
.
3. Objetivo: El Balance del Evento
La organización de estos datos tiene como fin último la generación automática del Balance de Evento
. Este reporte cruzará los ingresos totales (inscripciones capturadas por la plataforma + ingresos extra manuales) contra los gastos registrados, permitiendo al organizador visualizar la utilidad neta real de su evento directamente desde el dashboard
.
Es importante notar que, aunque este presupuesto es para el control interno del organizador, el sistema también gestiona de forma paralela la conciliación financiera con PassToGo, donde se descuentan las comisiones del 4% y se liquidan las utilidades correspondientes a los socios de la plataforma
.

*(Nota 11/08/2026: ver `PRD-Consolidacion-only-superadmin.md` — el pilar 1
de ese PRD ya está implementado, pero reparte el 5% de service fee que ya
se cobraba, no la comisión del 4% al organizador que menciona este
párrafo. Son procesos paralelos y distintos, no el mismo cálculo — la
Fase 2 de "conciliación con organizadores" mencionada ahí sigue sin
implementar.)*