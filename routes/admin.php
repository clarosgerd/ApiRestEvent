<?php

use App\Http\Controllers\Admin\AcreditacionController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AgendaItemController;
use App\Http\Controllers\Admin\AsistenciaSesionController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuspiciadorController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CajaController;
use App\Http\Controllers\Admin\CategoriaController;
use App\Http\Controllers\Admin\CategoryPricePeriodController;
use App\Http\Controllers\Admin\ChronoTrackController;
use App\Http\Controllers\Admin\CiudadController;
use App\Http\Controllers\Admin\CoordinateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DashboardInscripcionesController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\EventoController;
use App\Http\Controllers\Admin\FormasPagoController;
use App\Http\Controllers\Admin\FormTypeController;
use App\Http\Controllers\Admin\ItemBodegaController;
use App\Http\Controllers\Admin\ItemStockController;
use App\Http\Controllers\Admin\ListaEsperaController;
use App\Http\Controllers\Admin\LiquidacionController;
use App\Http\Controllers\Admin\NumeracionController;
use App\Http\Controllers\Admin\OrganizadorController;
use App\Http\Controllers\Admin\PaisController;
use App\Http\Controllers\Admin\ParticipantesController;
use App\Http\Controllers\Admin\ParticipantesDetalleController;
use App\Http\Controllers\Admin\PreguntaController;
use App\Http\Controllers\Admin\PresupuestoCategoriaController;
use App\Http\Controllers\Admin\PresupuestoController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\RegistroManualController;
use App\Http\Controllers\Admin\RelacionContactoController;
use App\Http\Controllers\Admin\RouteController as AdminRouteController;
use App\Http\Controllers\Admin\SesionCongresoController;
use App\Http\Controllers\Admin\SexoController;
use App\Http\Controllers\Admin\SocioController;
use App\Http\Controllers\Admin\SouvenirController;
use App\Http\Controllers\Admin\SubtipoEventoController;
use App\Http\Controllers\Admin\TallerCongresoController;
use App\Http\Controllers\Admin\TipoEventoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Consolidación monolito (21/08/2026) — panel admin, ex admin-eventos
|--------------------------------------------------------------------------
| Ver ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
| Fase 1a (única migrada hasta ahora): catálogos globales. El resto de las
| rutas de admin-eventos (dashboard, usuarios, auditoría, eventos, Caja,
| etc.) todavía vive solo en el repo separado — se van agregando acá
| sub-fase por sub-fase, mismo prefijo `admin.*`.
|
| `admin.token` (App\Http\Middleware\Admin\InjectAdminSessionToken) copia
| `session('admin_token')` al header Authorization ANTES de `auth:admins`
| — así el guard Sanctum `admins` (el mismo que usa /api/v1/admin/*) resuelve
| el usuario exactamente igual que en la API externa, sin reimplementar
| nada de autenticación.
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // admin.restrict-cajero (Fase 1b) — un cajero solo puede navegar
    // admin.caja.* (todavía no migrado, Fase 1d) y admin.logout; el resto
    // de este grupo lo rechaza. Mismo criterio que admin-eventos.
    Route::middleware(['admin.token', 'auth:admins', 'admin.restrict-cajero'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Fase 1e-i — listado de eventos (super_admin ve todos, admin
        // scoped ve solo el suyo).
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Fase 1c — núcleo (evento + sub-recursos). eventos.edit/update/
        // destroy/publicar/despublicar/gafetes-pdf/certificados-pdf y TODOS
        // los sub-recursos quedan FUERA de admin.superadmin a propósito: un
        // admin scoped a su propio evento también los necesita (la API ya
        // aplica el scoping real vía AuthorizesEventoScope::assertCanWriteEvento()
        // dentro de cada controller delegado). Solo alta de evento nuevo
        // (eventos.create/store) es exclusiva de super_admin, ver más abajo.
        Route::get('/eventos/{event}/edit', [EventoController::class, 'edit'])->name('eventos.edit');
        Route::put('/eventos/{event}', [EventoController::class, 'update'])->name('eventos.update');
        Route::delete('/eventos/{event}', [EventoController::class, 'destroy'])->name('eventos.destroy');
        Route::post('/eventos/{event}/publicar', [EventoController::class, 'publicar'])->name('eventos.publicar');
        Route::patch('/eventos/{event}/despublicar', [EventoController::class, 'despublicar'])->name('eventos.despublicar');
        Route::get('/eventos/{event}/gafetes-pdf', [EventoController::class, 'gafetesPdf'])->name('eventos.gafetes-pdf');
        Route::get('/eventos/{event}/certificados-pdf', [EventoController::class, 'certificadosPdf'])->name('eventos.certificados-pdf');

        // Fase 1e-i — dashboard de inscripciones + reportes de participantes.
        Route::get('/eventos/{event}/dashboard', [DashboardInscripcionesController::class, 'show'])->name('eventos.dashboard');
        Route::get('/eventos/{event}/dashboard/talleres/csv', [DashboardInscripcionesController::class, 'csvTalleres'])->name('eventos.dashboard.talleres.csv');
        Route::get('/eventos/{event}/participantes', [ParticipantesController::class, 'index'])->name('participantes.index');
        Route::patch('/eventos/{event}/participantes/{participante}', [ParticipantesController::class, 'update'])->name('participantes.update');
        Route::get('/eventos/{event}/participantes/detalle', [ParticipantesDetalleController::class, 'index'])->name('participantes.detalle');
        Route::get('/eventos/{event}/participantes/detalle/csv', [ParticipantesDetalleController::class, 'csvDownload'])->name('participantes.detalle.csv');

        // Fase 1e-ii — Numeración, Acreditación, ChronoTrack, Sesiones/
        // Asistencia/Talleres de congreso (operación del día del evento).
        Route::get('/eventos/{event}/numeracion', [NumeracionController::class, 'index'])->name('numeracion.index');
        Route::get('/eventos/{event}/numeracion/csv', [NumeracionController::class, 'csvDownload'])->name('numeracion.csv.download');
        Route::post('/eventos/{event}/numeracion/csv', [NumeracionController::class, 'csvUpload'])->name('numeracion.csv.upload');
        Route::patch('/numeracion/{reference}/{participante}', [NumeracionController::class, 'update'])->name('numeracion.update');

        Route::get('/eventos/{event}/acreditacion', [AcreditacionController::class, 'index'])->name('acreditacion.index');
        Route::post('/eventos/{event}/acreditacion/lookup', [AcreditacionController::class, 'lookup'])->name('acreditacion.lookup');
        Route::patch('/eventos/{event}/acreditacion/{participante}', [AcreditacionController::class, 'checkin'])->name('acreditacion.checkin');

        Route::get('/eventos/{event}/resultados', [ChronoTrackController::class, 'index'])->name('chronotrack.index');
        Route::post('/eventos/{event}/resultados/sincronizar', [ChronoTrackController::class, 'sincronizar'])->name('chronotrack.sincronizar');

        Route::get('/eventos/{event}/sesiones', [SesionCongresoController::class, 'index'])->name('sesiones.index');
        Route::post('/eventos/{event}/sesiones', [SesionCongresoController::class, 'store'])->name('sesiones.store');
        Route::put('/eventos/{event}/sesiones/{sesion}', [SesionCongresoController::class, 'update'])->name('sesiones.update');
        Route::delete('/eventos/{event}/sesiones/{sesion}', [SesionCongresoController::class, 'destroy'])->name('sesiones.destroy');
        Route::get('/eventos/{event}/sesiones-reporte', [AsistenciaSesionController::class, 'reporte'])->name('sesiones.reporte');
        Route::post('/eventos/{event}/sesiones/{sesion}/staff', [SesionCongresoController::class, 'assignStaff'])->name('sesiones.staff.store');
        Route::delete('/eventos/{event}/sesiones/{sesion}/staff/{participante}', [SesionCongresoController::class, 'unassignStaff'])->name('sesiones.staff.destroy');

        Route::get('/eventos/{event}/sesiones/{sesion}/acreditacion', [AsistenciaSesionController::class, 'index'])->name('sesiones.acreditacion.index');
        Route::post('/eventos/{event}/sesiones/{sesion}/acreditacion/lookup', [AsistenciaSesionController::class, 'lookup'])->name('sesiones.acreditacion.lookup');
        Route::patch('/eventos/{event}/sesiones/{sesion}/acreditacion/{participante}', [AsistenciaSesionController::class, 'checkin'])->name('sesiones.acreditacion.checkin');
        Route::post('/eventos/{event}/sesiones/{sesion}/acreditacion/checkin-bulk', [AsistenciaSesionController::class, 'checkinBulk'])->name('sesiones.acreditacion.checkin-bulk');

        Route::get('/eventos/{event}/talleres', [TallerCongresoController::class, 'index'])->name('talleres.index');
        Route::post('/eventos/{event}/talleres', [TallerCongresoController::class, 'store'])->name('talleres.store');
        Route::put('/eventos/{event}/talleres/{taller}', [TallerCongresoController::class, 'update'])->name('talleres.update');
        Route::delete('/eventos/{event}/talleres/{taller}', [TallerCongresoController::class, 'destroy'])->name('talleres.destroy');

        // Fase 1e-iii — Bodega de stock, stock por talla/sexo de un ítem,
        // lista de espera (solo lectura) y mapa de delivery (solo lectura).
        Route::get('/eventos/{event}/bodega', [ItemBodegaController::class, 'index'])->name('bodega.index');
        Route::post('/eventos/{event}/bodega', [ItemBodegaController::class, 'store'])->name('bodega.store');
        Route::put('/eventos/{event}/bodega/{itemBodega}', [ItemBodegaController::class, 'update'])->name('bodega.update');
        Route::delete('/eventos/{event}/bodega/{itemBodega}', [ItemBodegaController::class, 'destroy'])->name('bodega.destroy');
        Route::post('/eventos/{event}/bodega/{itemBodega}/asignar', [ItemBodegaController::class, 'asignar'])->name('bodega.asignar');

        Route::get('/souvenirs/{souvenir}/stock', [ItemStockController::class, 'index'])->name('souvenirs.stock.index');
        Route::post('/souvenirs/{souvenir}/stock', [ItemStockController::class, 'store'])->name('souvenirs.stock.store');
        Route::put('/item-stock/{itemStock}', [ItemStockController::class, 'update'])->name('souvenirs.stock.update');
        Route::delete('/item-stock/{itemStock}', [ItemStockController::class, 'destroy'])->name('souvenirs.stock.destroy');

        Route::get('/eventos/{event}/lista-espera', [ListaEsperaController::class, 'index'])->name('lista-espera.index');

        Route::get('/eventos/{event}/delivery', [DeliveryController::class, 'index'])->name('delivery.index');

        // Fase 1e-iv — Presupuesto de un evento (admin scoped o super_admin,
        // igual que Numeración/Acreditación). Liquidación/Socios/Rubros del
        // presupuesto quedan dentro de admin.superadmin más abajo.
        Route::get('/eventos/{event}/presupuesto', [PresupuestoController::class, 'index'])->name('presupuesto.index');
        Route::post('/eventos/{event}/presupuesto', [PresupuestoController::class, 'store'])->name('presupuesto.store');
        Route::put('/eventos/{event}/presupuesto/{presupuesto}', [PresupuestoController::class, 'update'])->name('presupuesto.update');
        Route::delete('/eventos/{event}/presupuesto/{presupuesto}', [PresupuestoController::class, 'destroy'])->name('presupuesto.destroy');

        // Fase 1d — Caja de cobro presencial. admin/cajero/super_admin
        // pueden operar la caja (assertCanOperarCaja() del lado API, scoped
        // a su evento_id salvo super_admin) — a diferencia de eventos.* de
        // arriba, esto NO exige rol admin/super_admin acá, ver
        // Admin\CajaController. Los route params usan el nombre literal que
        // esperan UpdateRegistrationRequest/UpdatePaidRegistrationRequest
        // (`{reference}`, no `{referencia}`) — leen $this->route('reference')
        // directo, mismo motivo que {event} en eventos.* arriba.
        Route::get('/eventos/{event}/caja', [CajaController::class, 'index'])->name('caja.index');
        Route::post('/eventos/{event}/caja/turno/abrir', [CajaController::class, 'abrirTurno'])->name('caja.turno.abrir');
        Route::post('/eventos/{event}/caja/turno/{turno}/cerrar', [CajaController::class, 'cerrarTurno'])->name('caja.turno.cerrar');
        Route::get('/eventos/{event}/caja/nueva', [CajaController::class, 'nueva'])->name('caja.nueva');
        Route::post('/eventos/{event}/caja/nueva', [CajaController::class, 'storeNueva'])->name('caja.nueva.store');
        Route::get('/eventos/{event}/caja/buscar', [CajaController::class, 'buscarPage'])->name('caja.buscar');
        Route::get('/eventos/{event}/caja/buscar/resultados', [CajaController::class, 'buscar'])->name('caja.buscar.resultados');
        Route::get('/eventos/{event}/caja/persona', [CajaController::class, 'buscarPersona'])->name('caja.persona');
        Route::post('/eventos/{event}/caja/registrations/{reference}/cobrar-pendiente', [CajaController::class, 'cobrarPendiente'])->name('caja.cobrar-pendiente');
        Route::get('/eventos/{event}/caja/registrations/{reference}/editar', [CajaController::class, 'editar'])->name('caja.editar');
        Route::post('/eventos/{event}/caja/registrations/{reference}/editar', [CajaController::class, 'storeEditar'])->name('caja.editar.store');
        Route::get('/eventos/{event}/caja/registrations/{reference}/comprobante', [CajaController::class, 'eticket'])->name('caja.eticket');
        Route::get('/eventos/{event}/caja/cierres', [CajaController::class, 'cierres'])->name('caja.cierres');

        Route::post('/eventos/{evento}/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
        Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
        Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');

        Route::get('/categorias/{category}/periodos', [CategoryPricePeriodController::class, 'index'])->name('categorias.periodos.index');
        Route::post('/categorias/{category}/periodos', [CategoryPricePeriodController::class, 'store'])->name('categorias.periodos.store');
        Route::put('/categorias-periodos/{categoryPricePeriod}', [CategoryPricePeriodController::class, 'update'])->name('categorias.periodos.update');
        Route::delete('/categorias-periodos/{categoryPricePeriod}', [CategoryPricePeriodController::class, 'destroy'])->name('categorias.periodos.destroy');

        Route::post('/eventos/{evento}/formtypes', [FormTypeController::class, 'store'])->name('formtypes.store');
        Route::put('/formtypes/{formType}', [FormTypeController::class, 'update'])->name('formtypes.update');
        Route::delete('/formtypes/{formType}', [FormTypeController::class, 'destroy'])->name('formtypes.destroy');

        Route::post('/formtypes/{formType}/souvenirs', [SouvenirController::class, 'store'])->name('souvenirs.store');
        Route::put('/souvenirs/{souvenir}', [SouvenirController::class, 'update'])->name('souvenirs.update');
        Route::delete('/souvenirs/{souvenir}', [SouvenirController::class, 'destroy'])->name('souvenirs.destroy');

        Route::post('/formtypes/{formType}/preguntas', [PreguntaController::class, 'store'])->name('preguntas.store');
        Route::put('/preguntas/{pregunta}', [PreguntaController::class, 'update'])->name('preguntas.update');
        Route::delete('/preguntas/{pregunta}', [PreguntaController::class, 'destroy'])->name('preguntas.destroy');

        Route::post('/eventos/{evento}/promocodes', [PromoCodeController::class, 'store'])->name('promocodes.store');
        Route::put('/promocodes/{promoCode}', [PromoCodeController::class, 'update'])->name('promocodes.update');
        Route::delete('/promocodes/{promoCode}', [PromoCodeController::class, 'destroy'])->name('promocodes.destroy');

        Route::post('/eventos/{evento}/coordenadas', [CoordinateController::class, 'store'])->name('coordenadas.store');
        Route::put('/coordenadas/{coordinate}', [CoordinateController::class, 'update'])->name('coordenadas.update');
        Route::delete('/coordenadas/{coordinate}', [CoordinateController::class, 'destroy'])->name('coordenadas.destroy');

        Route::post('/eventos/{evento}/ruta', [AdminRouteController::class, 'store'])->name('ruta.store');
        Route::put('/ruta/{route}', [AdminRouteController::class, 'update'])->name('ruta.update');
        Route::delete('/ruta/{route}', [AdminRouteController::class, 'destroy'])->name('ruta.destroy');

        Route::post('/eventos/{evento}/auspiciadores', [AuspiciadorController::class, 'store'])->name('auspiciadores.store');
        Route::put('/auspiciadores/{auspiciador}', [AuspiciadorController::class, 'update'])->name('auspiciadores.update');
        Route::delete('/auspiciadores/{auspiciador}', [AuspiciadorController::class, 'destroy'])->name('auspiciadores.destroy');

        Route::post('/eventos/{evento}/agenda', [AgendaItemController::class, 'store'])->name('agenda.store');
        Route::put('/agenda/{agendaItem}', [AgendaItemController::class, 'update'])->name('agenda.update');
        Route::delete('/agenda/{agendaItem}', [AgendaItemController::class, 'destroy'])->name('agenda.destroy');

        Route::middleware('admin.superadmin')->group(function () {
            Route::view('/catalogos', 'admin.catalogos.index')->name('catalogos.index');

            Route::get('/eventos/create', [EventoController::class, 'create'])->name('eventos.create');
            Route::post('/eventos', [EventoController::class, 'store'])->name('eventos.store');

            // Fase 1e-v (última de la Fase 1e) — Carga masiva de
            // inscripciones por CSV.
            Route::get('/eventos/{event}/registro-manual', [RegistroManualController::class, 'index'])->name('registro-manual.index');
            Route::get('/eventos/{event}/registro-manual/plantilla', [RegistroManualController::class, 'plantilla'])->name('registro-manual.plantilla');
            Route::post('/eventos/{event}/registro-manual', [RegistroManualController::class, 'store'])->name('registro-manual.store');

            Route::get('/auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');

            // Fase 1e-iv — consolidación financiera: liquidación de
            // utilidades por evento + socios + catálogo de rubros del
            // presupuesto (todo config global o solo super_admin).
            Route::get('/eventos/{event}/liquidacion', [LiquidacionController::class, 'show'])->name('liquidacion.show');
            Route::post('/eventos/{event}/liquidacion', [LiquidacionController::class, 'store'])->name('liquidacion.store');

            Route::get('/socios', [SocioController::class, 'index'])->name('socios.index');
            Route::post('/socios', [SocioController::class, 'store'])->name('socios.store');
            Route::put('/socios/{socio}', [SocioController::class, 'update'])->name('socios.update');
            Route::delete('/socios/{socio}', [SocioController::class, 'destroy'])->name('socios.destroy');

            Route::get('/presupuesto-categorias', [PresupuestoCategoriaController::class, 'index'])->name('presupuesto-categorias.index');
            Route::post('/presupuesto-categorias', [PresupuestoCategoriaController::class, 'store'])->name('presupuesto-categorias.store');
            Route::put('/presupuesto-categorias/{categoria}', [PresupuestoCategoriaController::class, 'update'])->name('presupuesto-categorias.update');
            Route::delete('/presupuesto-categorias/{categoria}', [PresupuestoCategoriaController::class, 'destroy'])->name('presupuesto-categorias.destroy');

            // Fase 1e-i — CRUD de organizadores (config global, no por
            // evento) + sus formas de pago.
            Route::get('/organizadores', [OrganizadorController::class, 'index'])->name('organizadores.index');
            Route::post('/organizadores', [OrganizadorController::class, 'store'])->name('organizadores.store');
            Route::put('/organizadores/{organizador}', [OrganizadorController::class, 'update'])->name('organizadores.update');
            Route::delete('/organizadores/{organizador}', [OrganizadorController::class, 'destroy'])->name('organizadores.destroy');
            Route::get('/organizadores/{organizador}/formas-pago', [OrganizadorController::class, 'formasPago'])->name('organizadores.formas-pago');
            Route::put('/organizadores/{organizador}/formas-pago', [OrganizadorController::class, 'updateFormasPago'])->name('organizadores.formas-pago.update');

            Route::get('/usuarios', [AdminUserController::class, 'index'])->name('usuarios.index');
            Route::get('/usuarios/create', [AdminUserController::class, 'create'])->name('usuarios.create');
            Route::post('/usuarios', [AdminUserController::class, 'store'])->name('usuarios.store');
            Route::get('/usuarios/{user}/edit', [AdminUserController::class, 'edit'])->name('usuarios.edit');
            Route::put('/usuarios/{user}', [AdminUserController::class, 'update'])->name('usuarios.update');
            Route::delete('/usuarios/{user}', [AdminUserController::class, 'destroy'])->name('usuarios.destroy');

            Route::prefix('catalogos')->name('catalogos.')->group(function () {
                Route::get('/paises', [PaisController::class, 'index'])->name('paises.index');
                Route::post('/paises', [PaisController::class, 'store'])->name('paises.store');
                Route::put('/paises/{pais}', [PaisController::class, 'update'])->name('paises.update');
                Route::delete('/paises/{pais}', [PaisController::class, 'destroy'])->name('paises.destroy');

                Route::get('/ciudades', [CiudadController::class, 'index'])->name('ciudades.index');
                Route::post('/ciudades', [CiudadController::class, 'store'])->name('ciudades.store');
                Route::put('/ciudades/{ciudad}', [CiudadController::class, 'update'])->name('ciudades.update');
                Route::delete('/ciudades/{ciudad}', [CiudadController::class, 'destroy'])->name('ciudades.destroy');

                Route::get('/sexos', [SexoController::class, 'index'])->name('sexos.index');
                Route::post('/sexos', [SexoController::class, 'store'])->name('sexos.store');
                Route::put('/sexos/{sexo}', [SexoController::class, 'update'])->name('sexos.update');
                Route::delete('/sexos/{sexo}', [SexoController::class, 'destroy'])->name('sexos.destroy');

                Route::get('/tipos-evento', [TipoEventoController::class, 'index'])->name('tipos-evento.index');
                Route::post('/tipos-evento', [TipoEventoController::class, 'store'])->name('tipos-evento.store');
                Route::put('/tipos-evento/{tipoEvento}', [TipoEventoController::class, 'update'])->name('tipos-evento.update');
                Route::delete('/tipos-evento/{tipoEvento}', [TipoEventoController::class, 'destroy'])->name('tipos-evento.destroy');

                Route::get('/subtipos-evento', [SubtipoEventoController::class, 'index'])->name('subtipos-evento.index');
                Route::post('/subtipos-evento', [SubtipoEventoController::class, 'store'])->name('subtipos-evento.store');
                Route::put('/subtipos-evento/{subtipoEvento}', [SubtipoEventoController::class, 'update'])->name('subtipos-evento.update');
                Route::delete('/subtipos-evento/{subtipoEvento}', [SubtipoEventoController::class, 'destroy'])->name('subtipos-evento.destroy');

                Route::get('/relaciones-contacto', [RelacionContactoController::class, 'index'])->name('relaciones-contacto.index');
                Route::post('/relaciones-contacto', [RelacionContactoController::class, 'store'])->name('relaciones-contacto.store');
                Route::put('/relaciones-contacto/{relacionContacto}', [RelacionContactoController::class, 'update'])->name('relaciones-contacto.update');
                Route::delete('/relaciones-contacto/{relacionContacto}', [RelacionContactoController::class, 'destroy'])->name('relaciones-contacto.destroy');

                Route::get('/formas-pago', [FormasPagoController::class, 'index'])->name('formas-pago.index');
                Route::post('/formas-pago', [FormasPagoController::class, 'store'])->name('formas-pago.store');
                Route::put('/formas-pago/{formasPago}', [FormasPagoController::class, 'update'])->name('formas-pago.update');
                Route::delete('/formas-pago/{formasPago}', [FormasPagoController::class, 'destroy'])->name('formas-pago.destroy');
            });
        });
    });
});
