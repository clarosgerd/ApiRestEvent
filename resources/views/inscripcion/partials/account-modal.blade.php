<!-- ══ MODAL DE CUENTA (login / crear cuenta / inscripciones pendientes) ══ -->
<!-- Fondo neutro (negro translúcido), no un color de marca — un backdrop de
     modal es chrome, no contenido, y no debería competir con la paleta
     primary/secondary de la app (ver auditoría 10/08/2026 §6). -->
<div id="accountModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:500;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)closeAccountModal()">
  <div class="card-body" role="dialog" aria-modal="true" aria-labelledby="modalLoginTitle" id="accountModalDialog" style="background:var(--white);border-radius:var(--radius);max-width:480px;width:100%;max-height:88vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(2,20,40,.35);">
    <button type="button" onclick="closeAccountModal()" aria-label="Cerrar" data-i18n-aria="common.close" style="position:absolute;top:16px;right:18px;background:none;border:none;font-size:22px;line-height:1;cursor:pointer;color:var(--muted);">&times;</button>

    <!-- LOGIN -->
    <div id="modalLoginSection">
      <div class="section-title">
        <div class="num" style="background:var(--secondary);">→</div>
        <h2 id="modalLoginTitle" data-i18n="account.loginTitle">Log in</h2>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label><span data-i18n="account.email">Email</span></label>
          <input type="email" id="modalLoginEmail" data-i18n-ph="account.email" onkeydown="if(event.key==='Enter')modalLogin()">
        </div>
        <div class="form-group">
          <label><span data-i18n="account.password">Password</span></label>
          <input type="password" id="modalLoginPassword" data-i18n-ph="account.password" onkeydown="if(event.key==='Enter')modalLogin()">
        </div>
      </div>
      <p style="font-size:12px;color:var(--muted);margin:-6px 0 16px;" data-i18n="account.passwordHint">Can't remember your password? If you already registered as a participant before, try your document number (DNI or passport).</p>
      <div id="modalLoginError" class="alert alert-danger" style="display:none;margin-bottom:14px;"></div>
      <div class="btn-actions">
        <button class="btn btn-primary" type="button" style="width:100%;justify-content:center;" id="modalLoginBtn" onclick="modalLogin()" data-i18n="account.loginBtn">Log in</button>
      </div>
      <p style="text-align:center;font-size:13px;margin-top:18px;">
        <span data-i18n="account.noAccountPrompt">Don't have an account?</span>
        <a href="#" style="color:var(--primary);font-weight:600;" onclick="event.preventDefault();showRegisterSection()" data-i18n="account.createAccountLink">Create account</a>
      </p>
    </div>

    <!-- CREAR CUENTA -->
    <div id="modalRegisterSection" style="display:none;">
      <div class="section-title">
        <div class="num" style="background:var(--secondary);">→</div>
        <h2 id="modalRegisterTitle" data-i18n="account.registerTitle">Create account</h2>
      </div>
      <p id="modalRegisterHint" style="font-size:13px;color:var(--muted);margin:-8px 0 18px;"></p>

      <div class="form-row">
        <div class="form-group">
          <label><span data-i18n="account.firstName">First Name</span> <span class="req">*</span></label>
          <input type="text" id="regNombre" data-i18n-ph="account.firstName">
        </div>
        <div class="form-group">
          <label><span data-i18n="account.lastName">Last Name</span> <span class="req">*</span></label>
          <input type="text" id="regApellido" data-i18n-ph="account.lastName">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group" style="flex:0;min-width:140px;max-width:180px;">
          <label><span data-i18n="account.alias">Alias</span> <span class="req">*</span></label>
          <input type="text" id="regAlias" data-i18n-ph="account.alias">
        </div>
        <div class="form-group" style="max-width:220px;">
          <label><span data-i18n="account.gender">Gender</span></label>
          <select id="regSexo">
            <option value="Masculino" data-i18n-opt="account.genderMale">Masculino</option>
            <option value="Femenino" data-i18n-opt="account.genderFemale">Femenino</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group" style="max-width:200px;">
          <label><span data-i18n="account.docType">Document Type</span></label>
          <select id="regTipoDocumento">
            <option value="DNI" data-i18n-opt="account.docDNI">DNI</option>
            <option value="Pasaporte" data-i18n-opt="account.docPassport">Pasaporte</option>
            <option value="CI" data-i18n-opt="account.docCI">CI</option>
          </select>
        </div>
        <div class="form-group">
          <label><span data-i18n="account.docNumber">Document Number</span> <span class="req">*</span></label>
          <input type="text" id="regNumeroDocumento" data-i18n-ph="account.docNumber">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label><span data-i18n="account.birthDate">Date of Birth</span> <span class="req">*</span></label>
          <input type="date" id="regFechaNacimiento">
        </div>
        <div class="form-group">
          <label><span data-i18n="account.email">Email</span> <span class="req">*</span></label>
          <input type="email" id="regEmail" data-i18n-ph="account.email">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label><span data-i18n="account.address">Address</span> <span class="req">*</span></label>
          <input type="text" id="regDireccion" data-i18n-ph="account.address">
        </div>
        <div class="form-group">
          <label><span data-i18n="account.city">City</span> <span class="req">*</span></label>
          <input type="text" id="regCiudad" data-i18n-ph="account.city">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label><span data-i18n="account.phone">Phone</span></label>
          <input type="tel" id="regTelefono" data-i18n-ph="account.phone">
        </div>
        <div class="form-group">
          <label><span data-i18n="account.mobile">Mobile</span></label>
          <input type="tel" id="regCelular" data-i18n-ph="account.mobile">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label><span data-i18n="account.password">Password</span> <span class="req">*</span></label>
          <input type="password" id="regPassword" data-i18n-ph="account.password">
        </div>
      </div>

      <div id="modalRegisterError" class="alert alert-danger" style="display:none;margin-bottom:14px;"></div>
      <div class="btn-actions">
        <button class="btn btn-primary" type="button" style="width:100%;justify-content:center;" id="modalRegisterBtn" onclick="modalRegister()" data-i18n="account.registerBtn">Create account</button>
      </div>
      <p style="text-align:center;font-size:13px;margin-top:18px;">
        <span data-i18n="account.haveAccountPrompt">Already have an account?</span>
        <a href="#" style="color:var(--primary);font-weight:600;" onclick="event.preventDefault();showLoginSection()" data-i18n="account.loginLink">Log in</a>
      </p>
    </div>

    <!-- INSCRIPCIONES PENDIENTES -->
    <div id="modalPendingSection" style="display:none;">
      <div class="section-title">
        <div class="num" style="background:var(--success);">✓</div>
        <h2 id="modalPendingTitle"><span data-i18n="account.pendingGreeting">Hi,</span> <span id="modalPendingName"></span></h2>
      </div>
      <p style="font-size:13px;color:var(--muted);margin:-8px 0 16px;" data-i18n="account.pendingSubtitle">Your pending payment registrations:</p>
      <div id="modalPendingLoading" style="font-size:13px;color:var(--muted);" data-i18n="account.pendingLoading">Loading…</div>
      <div id="modalPendingList"></div>
      <p id="modalPendingEmpty" style="display:none;font-size:13px;color:var(--muted);" data-i18n="account.pendingEmpty">You don't have any pending payment registrations.</p>
      <div class="btn-actions" style="flex-direction:column;">
        <button class="btn btn-secondary" type="button" style="width:100%;justify-content:center;" onclick="showResultsSection()" data-i18n="results.viewResultsBtn">View my results</button>
        <button class="btn btn-secondary" type="button" style="width:100%;justify-content:center;" onclick="closeAccountModal()" data-i18n="account.continueBtn">Continue</button>
        <button type="button" style="width:100%;background:none;border:none;color:var(--muted);font-size:13px;cursor:pointer;text-decoration:underline;padding:6px;" onclick="modalLogout()" data-i18n="account.logoutBtn">Log out</button>
      </div>
    </div>
  </div>
</div>
