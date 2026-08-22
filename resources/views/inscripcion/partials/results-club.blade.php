  <div class="screen" id="screen-results">
    <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <div class="section-title" style="margin-bottom:0;">
          <div class="num" style="background:var(--secondary);">🏁</div>
          <h2 data-i18n="results.pageTitle">My Results</h2>
        </div>
        <button class="btn btn-secondary btn-sm" type="button" onclick="showScreen('screen-event-list')" data-i18n="common.back">← Back</button>
      </div>

      <p id="resultsLoading" style="font-size:14px;color:var(--muted);" data-i18n="results.loading">Loading…</p>
      <p id="resultsEmpty" style="display:none;font-size:14px;color:var(--muted);" data-i18n="results.empty">No results available yet.</p>
      <div id="resultsList" style="display:flex;flex-direction:column;gap:24px;"></div>
    </div>
  </div><!-- /screen-results -->

  <div class="screen" id="screen-club-login">
    <div class="card-body" style="max-width:420px;margin:0 auto;">
      <div class="section-title">
        <div class="num" style="background:var(--secondary);">🏳️</div>
        <h2 data-i18n="club.loginTitle">Club login</h2>
      </div>
      <div class="form-group">
        <label><span data-i18n="club.emailLabel">Email</span></label>
        <input type="email" id="clubLoginEmail" data-i18n-ph="club.emailLabel" onkeydown="if(event.key==='Enter')clubLogin()">
      </div>
      <div class="form-group">
        <label><span data-i18n="club.passwordLabel">Password</span></label>
        <input type="password" id="clubLoginPassword" data-i18n-ph="club.passwordLabel" onkeydown="if(event.key==='Enter')clubLogin()">
      </div>
      <div id="clubLoginError" class="alert alert-danger" style="display:none;margin-bottom:14px;"></div>
      <div class="btn-actions">
        <button class="btn btn-primary" type="button" style="width:100%;justify-content:center;" onclick="clubLogin()" data-i18n="club.loginBtn">Log in</button>
      </div>
      <p style="text-align:center;margin-top:16px;">
        <a href="#" onclick="event.preventDefault();showScreen('screen-event-list')" data-i18n="club.backLink">← Back</a>
      </p>
    </div>
  </div><!-- /screen-club-login -->

  <div class="screen" id="screen-club-landing">
    <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <div class="section-title" style="margin-bottom:0;">
          <div class="num" style="background:var(--secondary);">🏳️</div>
          <h2 id="clubLandingTitle" data-i18n="club.pageTitle">Club History</h2>
        </div>
        <button class="btn btn-secondary btn-sm" type="button" onclick="clubLogout()" data-i18n="club.logoutBtn">Log out</button>
      </div>

      <p id="clubLandingLoading" style="font-size:14px;color:var(--muted);" data-i18n="club.loading">Loading…</p>
      <p id="clubLandingEmpty" style="display:none;font-size:14px;color:var(--muted);" data-i18n="club.empty">No event history yet.</p>
      <div id="clubLandingList" style="display:flex;flex-direction:column;gap:24px;"></div>
    </div>
  </div><!-- /screen-club-landing -->
