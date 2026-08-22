  <div class="screen active" id="screen-event-list">

    <div class="p0-header">
      <h1 data-i18n="events.title">Available Events</h1>
      <p data-i18n="events.subtitle">Select the event you want to register for</p>
    </div>

    <!-- Buscador -->
    <div class="events-search-bar">
      <div class="search-input-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>
        </svg>
        <input type="text" id="eventSearchInput"
               placeholder="Search by event or category…"
               data-i18n-ph="events.searchPlaceholder"
               oninput="onEventFilterTextInput()">
      </div>
      <button type="button" class="btn-advanced-toggle" id="advancedToggleBtn" onclick="toggleAdvancedFilters()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M4 6h16M7 12h10M10 18h4"/>
        </svg>
        <span data-i18n="events.advancedToggle">Advanced search</span>
      </button>
      <button type="button" class="btn-clear-filters" id="clearFiltersBtn" style="display:none;" onclick="clearEventFilters()" data-i18n="events.clearFilters">Clear filters</button>
    </div>

    <!-- Panel de filtros avanzados -->
    <div class="advanced-filters-panel" id="advancedFiltersPanel" style="display:none;">
      <div class="filter-field">
        <label data-i18n="events.filterStatus">Status</label>
        <select id="filterStatus" onchange="applyEventFilters()">
          <option value="" data-i18n-opt="events.filterAny">Any</option>
          <option value="open" data-i18n-opt="events.statusOpen">Open</option>
          <option value="coming_soon" data-i18n-opt="events.statusComingSoon">Coming Soon</option>
          <option value="closed" data-i18n-opt="events.statusClosed">Closed</option>
        </select>
      </div>
      <div class="filter-field">
        <label data-i18n="events.filterLocation">Location</label>
        <input type="text" id="filterLocation" data-i18n-ph="events.filterLocationPh" placeholder="City, venue…" oninput="onEventFilterTextInput()">
      </div>
      <div class="filter-field">
        <label data-i18n="events.filterType">Event type</label>
        <select id="filterType" onchange="applyEventFilters()">
          <option value="" data-i18n-opt="events.filterAny">Any</option>
          <option value="deportivo" data-i18n-opt="events.types.deportivo">Deportivo</option>
          <option value="congreso" data-i18n-opt="events.types.congreso">Congreso</option>
          <option value="taller" data-i18n-opt="events.types.taller">Taller</option>
          <option value="corporativo" data-i18n-opt="events.types.corporativo">Corporativo</option>
          <option value="cultural" data-i18n-opt="events.types.cultural">Cultural</option>
          <option value="social" data-i18n-opt="events.types.social">Social</option>
          <option value="educativo" data-i18n-opt="events.types.educativo">Educativo</option>
          <option value="recreativo" data-i18n-opt="events.types.recreativo">Recreativo</option>
          <option value="religioso" data-i18n-opt="events.types.religioso">Religioso</option>
          <option value="gastronomico" data-i18n-opt="events.types.gastronomico">Gastronómico</option>
          <option value="musical" data-i18n-opt="events.types.musical">Musical</option>
          <option value="tecnologico" data-i18n-opt="events.types.tecnologico">Tecnológico</option>
          <option value="artes" data-i18n-opt="events.types.artes">Artes</option>
          <option value="literario" data-i18n-opt="events.types.literario">Literario</option>
          <option value="ambiental" data-i18n-opt="events.types.ambiental">Ambiental</option>
          <option value="salud" data-i18n-opt="events.types.salud">Salud</option>
          <option value="moda" data-i18n-opt="events.types.moda">Moda</option>
          <option value="teatro" data-i18n-opt="events.types.teatro">Teatro</option>
          <option value="cine" data-i18n-opt="events.types.cine">Cine</option>
          <option value="fotografia" data-i18n-opt="events.types.fotografia">Fotografía</option>
          <option value="danza" data-i18n-opt="events.types.danza">Danza</option>
          <option value="literatura" data-i18n-opt="events.types.literatura">Literatura</option>
        </select>
      </div>
      <div class="filter-field filter-field-range">
        <label data-i18n="events.filterPriceRange">Price range</label>
        <div class="range-inputs">
          <input type="number" id="filterPriceMin" min="0" placeholder="Min" oninput="onEventFilterTextInput()">
          <span>–</span>
          <input type="number" id="filterPriceMax" min="0" placeholder="Max" oninput="onEventFilterTextInput()">
        </div>
      </div>
      <div class="filter-field filter-field-range">
        <label data-i18n="events.filterDateRange">Date range</label>
        <div class="range-inputs">
          <input type="date" id="filterDateFrom" onchange="applyEventFilters()">
          <span>–</span>
          <input type="date" id="filterDateTo" onchange="applyEventFilters()">
        </div>
      </div>
    </div>

    <!-- Estado: cargando -->
    <div id="eventsLoading" class="events-loading">
      <div class="spinner-lg"></div>
      <p data-i18n="events.loading">Loading events…</p>
    </div>

    <!-- Estado: error -->
    <div id="eventsError" class="events-error" style="display:none;">
      <p data-i18n="events.error">⚠ Could not load events. Please try again.</p>
      <button class="btn btn-primary" style="margin-top:14px;" onclick="loadAllEvents()" data-i18n="events.retry">Retry</button>
    </div>

    <!-- Estado: sin resultados por filtro -->
    <div id="eventsNoResults" class="events-no-results" style="display:none;">
      <p data-i18n="events.noResults">No events match your search.</p>
    </div>

    <!-- Grid de tarjetas -->
    <div class="events-grid" id="eventsGrid" style="display:none;"></div>

    <!-- Paginación -->
    <div class="pagination-wrap" id="paginationWrap" style="display:none;">
      <p class="pagination-summary" id="paginationSummary"></p>
      <nav class="pagination" id="paginationNav" aria-label="Pagination"></nav>
    </div>

  </div><!-- /screen-event-list -->
