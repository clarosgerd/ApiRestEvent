En el ecosistema de PassToGo, el concepto de consolidación se aplica en tres áreas fundamentales: la financiera, la estadística y la técnica, permitiendo que la plataforma pase de ser una simple herramienta de registro a un sistema integral de gestión de negocios
.
A continuación se detallan los pilares de la consolidación según las fuentes:
1. Consolidación y Conciliación Financiera
Es el proceso más crítico para la sostenibilidad del negocio y se gestiona principalmente a través del rol de Super Admin
.
Liquidación de Utilidades: Al cierre y conciliación de cada evento, el sistema realiza una consolidación financiera para distribuir automáticamente las utilidades entre los socios según los porcentajes definidos: Mario (40%), Carlitos (35%), Galia (15%) y Norman (10%)
.
Relación con Organizadores: En la Fase 2, se implementará un módulo de conciliación financiera para tener una vista global de los pagos recibidos, las comisiones devengadas por PassToGo (4% por ticket) y las transferencias realizadas hacia los organizadores
.
Trazabilidad de Transferencias: Se ha diseñado la tabla transferencias_pago específicamente para consolidar los flujos de "cobro de comisión" y "pago al organizador", permitiendo un control exacto de los saldos netos
.
2. Consolidación Estadística e Histórica
Esta funcionalidad, planificada para el futuro, busca dar a los organizadores una visión de largo plazo sobre sus eventos
.
Uso de Keywords: Se agregará una columna keyword a la tabla de eventos para agrupar o consolidar el historial de múltiples ediciones de un mismo evento (por ejemplo, todas las versiones anuales de una misma maratón)
.
Métricas de Crecimiento: Mediante esta consolidación por palabra clave, el sistema podrá generar gráficos de crecimiento anual, comparando inscritos, recaudación y participación a lo largo del tiempo
.
3. Consolidación de Datos Técnicos
Desde una perspectiva operativa y de eficiencia de almacenamiento, el sistema también aplica procesos de consolidación de datos:
Tráfico Web: El log detallado del tráfico web y el origen de las visitas a la landing de un evento se consolida al cierre del mismo. Esto permite mantener las métricas generales de conversión y origen sin saturar la base de datos con registros detallados que ya no son necesarios tras la finalización de la actividad
.
4. Consolidación Operativa en el Dashboard
El Dashboard del Organizador actúa como el punto de consolidación de información en tiempo real durante la vigencia de un evento
. En este panel se unifican datos provenientes de distintas tablas para mostrar de forma coherente el total de inscritos, la recaudación actual, el aforo disponible y el estado del stock de kits, permitiendo una toma de decisiones informada sin tener que revisar reportes separados