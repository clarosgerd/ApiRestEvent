  <div class="screen" id="screen-form-types">

    <!-- Cabecera con info del evento seleccionado + botón volver -->
    <div class="form-types-header">
      <button class="back-btn" onclick="backToEventList()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
        <span data-i18n="common.back">Back</span>
      </button>
      <div class="form-types-event-info">
        <h2 id="ftEventName"></h2>
        <p id="ftEventMeta"></p>
      </div>
    </div>

    <!-- Banner de vista previa (25/08/2026, portado de elascenso/event) — visible
         cuando currentEvent.publicado es false, ej. admin previsualizando un evento
         en borrador. Solo lectura, sin checkbox (a diferencia de #deslindeBlock).
         Ver renderPreviewBanner() en home.blade.php. -->
    <div id="previewBannerFormTypes" style="display:none;background:#fff8e6;border:1px solid #f0c040;border-radius:12px;padding:14px 20px;margin-bottom:12px;font-size:14px;color:#7a5400;font-weight:600;" data-i18n="registration.previewBannerMsg"></div>

    <!-- Descripción del evento -->
    <div id="ftEventDescription" style="background:var(--white);border-radius:12px;padding:20px 24px;margin-bottom:12px;box-shadow:var(--shadow);">
      <p style="font-size:14px;color:var(--text);line-height:1.7;margin:0;" id="ftEventDescText"></p>
    </div>

    <!-- Agregar al calendario (20/08/2026, portado de elascenso/event) —
         a diferencia de ftAgendaContainer (solo aparece con agenda_items de
         congreso), esto se muestra siempre que hay un evento cargado. -->
    <div id="ftCalendarContainer" style="background:var(--white);border-radius:12px;margin-bottom:12px;box-shadow:var(--shadow);padding:14px 24px;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;display:none;">
      <p style="font-size:13px;font-weight:700;color:var(--secondary);margin:0;" data-i18n="formTypes.addToCalendar">Agregar al calendario</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a id="ftCalendarIcsLink" href="#" class="btn btn-secondary btn-sm" data-i18n="formTypes.downloadIcs">Descargar .ics</a>
        <a id="ftCalendarGCalLink" href="#" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" data-i18n="formTypes.addToGoogleCalendar">Agregar a Google Calendar</a>
      </div>
    </div>

    <div id="ftEventCountdown" class="form-types-countdown" style="display:none;">
      <div class="countdown-card" id="ftCountdownCard" style="display:none;">
            <div class="countdown-segment">
              <div class="countdown-value" id="cdDays">00</div>
              <div class="countdown-label" data-i18n="countdown.days">Días</div>
            </div>
            <div class="countdown-divider"></div>
            <div class="countdown-segment">
              <div class="countdown-value" id="cdHours">00</div>
              <div class="countdown-label" data-i18n="countdown.hours">Horas</div>
            </div>
            <div class="countdown-divider"></div>
            <div class="countdown-segment">
              <div class="countdown-value" id="cdMinutes">00</div>
              <div class="countdown-label" data-i18n="countdown.minutes">Minutos</div>
            </div>
            <div class="countdown-divider"></div>
            <div class="countdown-segment">
              <div class="countdown-value" id="cdSeconds">00</div>
              <div class="countdown-label" data-i18n="countdown.seconds">Segundos</div>
            </div>
      </div>
      <div class="countdown-footer" id="ftCountdownFooter" style="display:none;"></div>
    </div>

    <!-- Video o imagen de la carrera -->
    <div id="ftMediaContainer" style="background:var(--white);border-radius:12px;overflow:hidden;margin-bottom:20px;box-shadow:var(--shadow);display:none;">
      <div style="padding:16px 24px 0;">
        <p style="font-size:14px;font-weight:700;color:var(--secondary);margin:0;" id="ftMediaTitle">Race Preview</p>
      </div>
      <div id="ftMediaContent" style="padding:12px 24px 20px;"></div>
    </div>

    <!-- Auspiciadores (carrusel de logos, viene de currentEvent.auspiciadores) -->
    <div id="ftSponsorsContainer" style="background:var(--white);border-radius:12px;overflow:hidden;margin-bottom:20px;box-shadow:var(--shadow);display:none;">
      <div style="padding:16px 24px 0;">
        <p style="font-size:14px;font-weight:700;color:var(--secondary);margin:0;" data-i18n="formTypes.sponsorsTitle">Sponsors</p>
      </div>
      <div id="ftSponsorsTrack" style="display:flex;gap:16px;align-items:center;overflow-x:auto;padding:12px 24px 20px;scroll-behavior:smooth;"></div>
    </div>

    <!-- Kit/tallas/stock (11/08/2026, portado 12/08/2026) — fotos de los
         ítems incluidos en el kit que traigan foto_url, ver
         PRD-kit-tallas-stock-lista-espera.md en ApiRestEvent. Agrega por
         evento (no por form_type todavía) porque esta pantalla es previa
         a elegir un tipo de formulario. -->
    <div id="ftKitGalleryContainer" style="background:var(--white);border-radius:12px;overflow:hidden;margin-bottom:20px;box-shadow:var(--shadow);display:none;">
      <div style="padding:16px 24px 0;">
        <p style="font-size:14px;font-weight:700;color:var(--secondary);margin:0;" data-i18n="formTypes.kitGalleryTitle">What's in the kit</p>
      </div>
      <div id="ftKitGalleryTrack" style="display:flex;gap:16px;overflow-x:auto;padding:12px 24px 20px;scroll-behavior:smooth;"></div>
    </div>

    <!-- Mapa de la ruta -->
    <div id="ftRouteMapContainer" style="background:var(--white);border-radius:12px;overflow:hidden;margin-bottom:20px;box-shadow:var(--shadow);display:none;">
      <div style="padding:16px 24px 0;">
        <p style="font-size:14px;font-weight:700;color:var(--secondary);margin:0;">Race Route</p>
        <p style="font-size:12px;color:var(--muted);margin:4px 0 0;">Interactive map with the race course</p>
      </div>
      <div id="ftRouteMap" style="height:320px;margin-top:12px;"></div>
    </div>

    <!-- Agenda del evento: tarjeta resumen + botón que abre el detalle completo
         en un modal (una agenda de varios días es demasiado larga para mostrar
         siempre desplegada dentro de la pantalla del evento). -->
    <div id="ftAgendaContainer" style="background:var(--white);border-radius:12px;margin-bottom:20px;box-shadow:var(--shadow);padding:16px 24px;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;display:none;">
      <div>
        <p style="font-size:14px;font-weight:700;color:var(--secondary);margin:0;" id="ftAgendaTitle" data-i18n="formTypes.agendaTitle">Agenda</p>
        <p style="font-size:12px;color:var(--muted);margin:4px 0 0;" id="ftAgendaSummary"></p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="openAgendaModal()" data-i18n="formTypes.agendaViewBtn">View agenda →</button>
        <a id="ftAgendaPdfLink" href="#" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" data-i18n="formTypes.agendaPdfBtn">Download PDF</a>
      </div>
    </div>

    <!-- Envuelve título + tarjetas de tipo de formulario en un solo bloque
         (25/08/2026, portado de elascenso/event) para que participen como unidad
         del orden configurable de secciones — ver applySeccionesOrder(). Ningún
         otro código referencia el contenedor padre de estos dos, así que el
         wrapper no afecta nada más. -->
    <div id="ftFormTypesSection">
      <div class="form-types-title">
        <span data-i18n="formTypes.title">Choose your registration type</span>
        <span data-i18n="formTypes.subtitle">— Select the form that best fits your participation</span>
      </div>

      <!-- Tarjetas de tipos de formulario -->
      <div class="form-types-grid" id="formTypesGrid"></div>
    </div>

  </div><!-- /screen-form-types -->
