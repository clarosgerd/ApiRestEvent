        <div id="sub-form" class="sub-screen active">

          <!-- Bienvenida usuario logueado -->
          <div id="loginWelcome" class="login-welcome">
            <p><span data-i18n="registration.welcomePrefix">✅ Welcome,</span> <strong id="welcomeName"></strong><span id="welcomeSuffix" data-i18n="registration.welcomeSuffix">! Your data has been loaded.</span></p>
            <p id="editModeNotice" style="display:none;font-weight:600;margin-top:4px;"></p>
            <button class="btn-logout" onclick="logoutUser()" data-i18n="registration.logOut">Log out</button>
          </div>

          <!-- Banner login -->
          <div id="loginBanner" class="login-banner">
            <p data-i18n="registration.loginBannerMsg">Have an account? Log in to complete your registration faster!</p>
            <button class="btn-toggle" onclick="toggleLoginForm()" data-i18n="registration.loginBtn">Log in</button>
          </div>

          <!-- Formulario login colapsable -->
          <div id="loginFormCollapse" class="login-form-collapse">
            <div class="section-title">
              <div class="num" style="background:var(--secondary);">→</div>
              <h2 data-i18n="registration.loginTitle">Log into your account</h2>
            </div>
            <div id="loginApiError" class="alert alert-danger"></div>
            <div class="form-row">
              <div class="form-group">
                <label>Email</label>
                <input type="email" id="loginEmail" placeholder="jdoe@gmail.com"
                       onkeydown="if(event.key==='Enter')doLogin()">
              </div>
              <div class="form-group">
                <label>Password</label>
                <input type="password" id="loginPassword" placeholder="Password"
                       onkeydown="if(event.key==='Enter')doLogin()">
              </div>
            </div>
            <p style="font-size:12px;color:var(--muted);margin:-4px 0 12px;" data-i18n="account.passwordHint">Can't remember your password? If you already registered as a participant before, try your document number (DNI or passport).</p>
            <div class="btn-actions" style="margin-top:12px;">
              <button class="btn btn-primary" id="btnLogin" onclick="doLogin()" data-i18n="registration.loginAutoFill">Log in &amp; Auto-fill</button>
              <button class="btn btn-secondary" onclick="toggleLoginForm()" data-i18n="registration.cancelBtn">Cancel</button>
            </div>
          </div>

          <!-- Barra del evento activo -->
          <div id="eventBar" style="background:var(--light);border:1px solid var(--border);border-radius:var(--radius);padding:10px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <span style="font-size:14px;font-weight:600;color:var(--secondary);" id="eventBarName"></span>
            <span class="badge badge-primary" id="eventBarDate"></span>
          </div>

          <!-- Envuelve categoría + formulario de participante para poder bloquearlos juntos
               en modo solo-lectura (inscripción pagada, antes de "Solicitar cambios") -->
          <div id="participantEditableArea">
          <!-- ─── SECCIÓN 1: CATEGORÍA (condicional, solo si el form_type tiene requiereCategoria) ─── -->
          <div id="categorySection">
            <div class="section-title">
              <div class="num">1</div>
              <h2 data-i18n="registration.section1Title">Choose a Category</h2>
            </div>
            <p style="font-size:12px;color:var(--muted);margin-bottom:10px;" data-i18n="registration.priceIncludesFees">Price includes fees</p>
            <div class="category-grid" id="categoryGrid"></div>
            <span class="field-error" id="catError" data-i18n="registration.errCat">Please select a category to continue.</span>
          </div>

          <hr class="divider">

          <!-- ─── SECCIÓN 2: DATOS PARTICIPANTE ─── -->
          <div class="section-title">
            <div class="num">2</div>
            <h2 data-i18n="registration.section2Title">Participant Information</h2>
          </div>
          <p style="font-size:12px;color:var(--muted);margin-bottom:16px;" data-i18n="registration.requiredNote">* Required fields</p>

          <form id="participantForm" novalidate>
            <input type="hidden" id="editIndex" value="-1">

            <!-- Nombre / Apellido / Alias -->
            <div class="form-row">
              <div class="form-group">
                <label><span data-i18n="registration.firstName">First Name</span> <span class="req">*</span></label>
                <input type="text" id="nombre" placeholder="First Name" maxlength="50" autocomplete="off" data-i18n-ph="registration.firstName">
                <span class="field-error" id="err-nombre" data-i18n="registration.errRequired">Required field.</span>
              </div>
              <div class="form-group">
                <label><span data-i18n="registration.lastName">Last Name</span> <span class="req">*</span></label>
                <input type="text" id="apellido" placeholder="Last Name" maxlength="50" autocomplete="off" data-i18n-ph="registration.lastName">
                <span class="field-error" id="err-apellido" data-i18n="registration.errRequired">Required field.</span>
              </div>
              <div class="form-group" id="aliasGroup" style="flex:0;min-width:100px;max-width:120px;">
                <label><span id="aliasLabel" data-i18n="registration.alias">Alias</span> <span class="req">*</span></label>
                <input type="text" id="alias" placeholder="Alias" maxlength="20" autocomplete="off" data-i18n-ph="registration.alias">
                <!-- Título para eventos tipo congreso (20/08/2026, portado de
                     elascenso/event) — reusa el campo `alias` existente sin
                     agregar columna a la BD: el select solo pisa el valor de
                     #alias (oculto en ese modo), ver toggleAliasTituloMode(). -->
                <select id="aliasTitulo" style="display:none;">
                  <option value="" data-i18n-opt="registration.tituloSelect">Select…</option>
                  <option value="Dr.">Dr.</option>
                  <option value="Dra.">Dra.</option>
                  <option value="Lic.">Lic.</option>
                  <option value="Ing.">Ing.</option>
                  <option value="Msc.">Msc.</option>
                  <option value="Mgr.">Mgr.</option>
                  <option value="Est.">Est.</option>
                  <option value="PhD.">PhD.</option>
                  <option value="Otro" data-i18n-opt="registration.tituloOtro">Otro</option>
                </select>
                <input type="text" id="aliasTituloOtro" placeholder="Título" maxlength="20" autocomplete="off"
                       style="display:none;margin-top:6px;" data-i18n-ph="registration.tituloOtroPh">
                <span class="field-error" id="err-alias" data-i18n="registration.errRequiredShort">Required.</span>
              </div>
            </div>

            <!-- Género -->
            <div class="form-row">
              <div class="form-group" style="max-width:260px;">
                <label><span data-i18n="registration.gender">Gender</span> <span class="req">*</span></label>
                <select id="genero">
                  <option value="" data-i18n-opt="registration.genderPlaceholder">Select Gender</option>
                  <option value="Masculino" data-i18n-opt="registration.genderMale">Male</option>
                  <option value="Femenino" data-i18n-opt="registration.genderFemale">Female</option>
                  <option value="Non-binary" data-i18n-opt="registration.genderNonBinary">Non-binary</option>
                  <option value="Prefer not to say" data-i18n-opt="registration.genderPreferNot">Prefer not to say</option>
                </select>
                <span class="field-error" id="err-genero" data-i18n="registration.errRequired">Required field.</span>
              </div>
            </div>

            <!-- DNI / Pasaporte -->
            <div class="form-row">
              <div class="form-group" style="max-width:200px;">
                <label><span data-i18n="registration.docType">Document Type</span> <span class="req">*</span></label>
                <select id="tipoDocumento">
                  <option value="" data-i18n-opt="registration.relSelect">Select…</option>
                  <option value="DNI" data-i18n-opt="registration.docDNI">DNI</option>
                  <option value="Pasaporte" data-i18n-opt="registration.docPassport">Passport</option>
                </select>
                <span class="field-error" id="err-tipoDoc" data-i18n="registration.errRequired">Required field.</span>
              </div>
              <div class="form-group">
                <label><span data-i18n="registration.docNumber">Document Number</span> <span class="req">*</span></label>
                <input type="text" id="numeroDocumento" placeholder="Document number" maxlength="20" autocomplete="off" data-i18n-ph="registration.docNumber">
                <span class="field-error" id="err-numDoc" data-i18n="registration.errRequired">Required field.</span>
              </div>
            </div>

            <!-- Camiseta -->
            <div id="shirtSection" style="margin-bottom:16px;">
              <label><span data-i18n="registration.shirt">Shirt</span> <span class="req">*</span></label>
              <div class="radio-group">
                <label class="radio-option" id="lbl-polera-con">
                  <input type="radio" name="polera_opcion" value="con" id="conPolera"> <span data-i18n="registration.withShirt">With a T-shirt</span>
                </label>
                <label class="radio-option" id="lbl-polera-sin">
                  <input type="radio" name="polera_opcion" value="sin" id="sinPolera" checked> <span data-i18n="registration.withoutShirt">Without a shirt</span>
                </label>
              </div>
              <div id="shirtSizeContainer" style="margin-top:10px;max-width:200px;display:none;">
                <label><span data-i18n="registration.shirtSize">Shirt Size</span> <span class="req">*</span></label>
                <select id="tamanioPolera">
                  <option value="" data-i18n-opt="registration.selectSize">Select size</option>
                  <option value="XS">XS</option>
                  <option value="S">S</option>
                  <option value="M">M</option>
                  <option value="L">L</option>
                  <option value="XL">XL</option>
                  <option value="XXL">XXL</option>
                </select>
                <span class="field-error" id="err-shirt" data-i18n="registration.errShirt">Please select a size.</span>
              </div>
            </div>

            <!-- Fecha de Nacimiento — generada por PHP -->
            <div style="margin-bottom:16px;">
              <label><span data-i18n="registration.dateOfBirth">Date of Birth</span> <span class="req">*</span></label>
              <div class="form-row" style="margin-top:8px;margin-bottom:0;">
                <div class="form-group">
                  <label style="font-size:11px;text-transform:none;font-weight:400;" data-i18n="registration.day">Day</label>
                  <select id="date_birth_day">
                    <option value="" data-i18n-opt="registration.day">Day</option>
                    <?php for ($d = 1; $d <= 31; $d++): ?>
                    <option value="<?= $d ?>"><?= $d ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label style="font-size:11px;text-transform:none;font-weight:400;" data-i18n="registration.month">Month</label>
                  <select id="date_birth_month">
                    <option value="" data-i18n-opt="registration.month">Month</option>
                    <?php
                    $meses = ['January','February','March','April','May','June',
                              'July','August','September','October','November','December'];
                    foreach ($meses as $i => $m): ?>
                    <option value="<?= $i+1 ?>" data-month-index="<?= $i ?>"><?= $m ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label style="font-size:11px;text-transform:none;font-weight:400;" data-i18n="registration.year">Year</label>
                  <select id="date_birth_year">
                    <option value="" data-i18n-opt="registration.year">Year</option>
                    <?php for ($y = $anioActual; $y >= $anioMinimo; $y--): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                  <span id="edad_display" style="font-size:14px;font-weight:600;color:var(--primary);padding:8px 0;white-space:nowrap;"></span>
                  <input type="hidden" id="edad_calculada" value="">
                </div>
              </div>
              <span class="field-error" id="err-dob" data-i18n="registration.errDob">Date of birth is required.</span>
            </div>

            <!-- Email -->
            <div class="form-row">
              <div class="form-group full">
                <label><span data-i18n="registration.email">Email</span> <span class="req">*</span></label>
                <input type="email" id="email" placeholder="ex: jdoe@gmail.com"
                       maxlength="100" autocomplete="off">
                <span class="field-error" id="err-email" data-i18n="registration.errEmail">Valid email required.</span>
              </div>
            </div>

            <!-- Dirección / Ciudad -->
            <div class="form-row">
              <div class="form-group" style="flex:2;">
                <label><span data-i18n="registration.address">Address</span> <span class="req">*</span></label>
                <input type="text" id="direccion" placeholder="Street address" autocomplete="off" data-i18n-ph="registration.streetAddress">
                <span class="field-error" id="err-dir" data-i18n="registration.errRequired">Required field.</span>
              </div>
              <div class="form-group">
                <label><span data-i18n="registration.city">City</span> <span class="req">*</span></label>
                <input type="text" id="ciudad" placeholder="City" autocomplete="off" data-i18n-ph="registration.city">
                <span class="field-error" id="err-ciudad" data-i18n="registration.errRequired">Required field.</span>
              </div>
            </div>

            <!-- Teléfonos -->
            <div class="form-row">
              <div class="form-group">
                <label><span data-i18n="registration.phone">Phone</span> <span class="req">*</span></label>
                <input type="tel" id="telefono" autocomplete="off">
                <span class="field-error" id="err-tel" data-i18n="registration.errRequired">Required field.</span>
              </div>
              <div class="form-group">
                <label><span data-i18n="registration.emergencyPhone">Emergency Phone</span> <span class="req">*</span></label>
                <input type="tel" id="celular" autocomplete="off">
                <span class="field-error" id="err-cel" data-i18n="registration.errRequired">Required field.</span>
              </div>
            </div>

            <!-- Contacto de emergencia -->
            <div class="form-row">
              <div class="form-group">
                <label><span data-i18n="registration.emergencyContactName">Emergency Contact Name</span> <span class="req">*</span></label>
                <input type="text" id="nombre_emergencia" placeholder="Full name" maxlength="100" autocomplete="off" data-i18n-ph="registration.emergencyContactName">
                <span class="field-error" id="err-emerg" data-i18n="registration.errRequired">Required field.</span>
              </div>
              <div class="form-group">
                <label><span data-i18n="registration.emergencyRelationship">Emergency Relationship</span> <span class="req">*</span></label>
                <select id="relacion_emergencia">
                  <option value="" data-i18n-opt="registration.relSelect">Select…</option>
                  <option value="HUS" data-i18n-opt="registration.relHusband">Husband</option>
                  <option value="WIF" data-i18n-opt="registration.relWife">Wife</option>
                  <option value="CHI" data-i18n-opt="registration.relChild">Child</option>
                  <option value="FAT" data-i18n-opt="registration.relFather">Father</option>
                  <option value="MOT" data-i18n-opt="registration.relMother">Mother</option>
                  <option value="FRI" data-i18n-opt="registration.relFriend">Friend</option>
                  <option value="SPO" data-i18n-opt="registration.relSpouse">Spouse</option>
                  <option value="FAM" data-i18n-opt="registration.relFamily">Family Member</option>
                  <option value="COL" data-i18n-opt="registration.relColleague">Colleague</option>
                  <option value="LGN" data-i18n-opt="registration.relGuardian">Legal Guardian</option>
                  <option value="OTH" data-i18n-opt="registration.relOther">Other</option>
                </select>
                <span class="field-error" id="err-rel" data-i18n="registration.errRequired">Required field.</span>
              </div>
            </div>

            <hr class="divider">

            <!-- ─── EQUIPO (condicional, solo si el form_type tiene hasTeam) ─── -->
            <div id="teamSection" style="display:none;margin-bottom:16px;">
              <div class="form-row">
                <div class="form-group">
                  <label><span data-i18n="registration.teamLabel">Team</span> <span class="req">*</span></label>
                  <select id="equipo_id">
                    <option value="" data-i18n-opt="registration.teamSelect">Select…</option>
                  </select>
                  <span class="field-error" id="err-equipo" data-i18n="registration.errRequired">Required field.</span>
                </div>
              </div>
              <hr class="divider">
            </div>

            <!-- ─── DELIVERY DEL KIT (condicional, solo si el form_type tiene hasDelivery) ─── -->
            <div id="deliverySection" style="display:none;margin-bottom:16px;">
              <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                <input type="checkbox" id="quiere_delivery" style="width:auto;">
                <span data-i18n="registration.deliveryLabel">I want my kit delivered to my address</span>
              </label>
              <p style="font-size:12px;color:var(--muted);margin:6px 0 0;" data-i18n="registration.deliveryHint">We'll use the address you provided above.</p>
              <!-- Mapa de ubicación (12/08/2026, portado de elascenso/event)
                   — opcional, complementa la dirección de texto de arriba,
                   no la reemplaza. Mismo patrón técnico que el mapa de ruta
                   (Leaflet, ya cargado vía CDN). Solo se instancia al
                   tildar el checkbox — ver toggleDeliveryMap()/initDeliveryMap()
                   en registration.js. -->
              <div id="deliveryMapContainer" style="display:none;margin-top:10px;">
                <p style="font-size:12px;color:var(--muted);margin:0 0 6px;" data-i18n="registration.deliveryMapHint">Drag the pin to your exact location — helps the courier find you faster (optional).</p>
                <div id="deliveryMap" style="height:220px;border-radius:8px;"></div>
              </div>
              <hr class="divider">
            </div>

            <!-- ─── DONACIÓN (condicional) ─── -->
            <div id="donationSection" style="display:none;margin-bottom:16px;">
              <div class="section-title">
                <div class="num" style="font-size:16px;">♥</div>
                <h2><span data-i18n="registration.donationTitle">Donation</span> <span style="font-size:12px;font-weight:400;color:var(--muted);" data-i18n="registration.optional">(optional)</span></h2>
              </div>
              <div class="donation-presets">
                <button type="button" class="donation-btn" onclick="setDonation(5)">$5</button>
                <button type="button" class="donation-btn" onclick="setDonation(10)">$10</button>
                <button type="button" class="donation-btn" onclick="setDonation(20)">$20</button>
                <button type="button" class="donation-btn" onclick="setDonation(50)">$50</button>
              </div>
              <div class="form-row">
                <div class="form-group" style="max-width:160px;">
                  <label data-i18n="registration.customAmount">Custom Amount</label>
                  <input type="number" id="donacion" value="0" min="0" step="0.01"
                         oninput="clearDonationBtns()">
                </div>
              </div>
              <hr class="divider">
            </div>

            <!-- ─── CÓDIGO PROMOCIONAL ─── -->
            <div id="promoSection" style="margin-bottom:16px;">
              <label><span data-i18n="registration.promoCode">Promo Code</span> <span style="font-weight:400;text-transform:none;font-size:11px;" data-i18n="registration.promoOptional">(optional)</span></label>
              <div class="promo-row">
                <div class="form-group">
                  <input type="text" id="entered_promotion_code"
                         placeholder="Enter promo code" maxlength="30"
                         oninput="this.value=this.value.toUpperCase()"
                         onkeydown="if(event.key==='Enter'){event.preventDefault();applyPromo();}">
                </div>
                <button type="button" class="btn btn-secondary" id="btnApplyPromo" onclick="applyPromo()" data-i18n="registration.apply">
                  Apply
                </button>
              </div>
              <div class="promo-status ok"   id="promoOk"></div>
              <div class="promo-status fail"  id="promoFail"></div>
            </div>

            <hr class="divider">

            <!-- ─── SOUVENIRS ─── -->
            <div id="souvenirsSection">
              <div class="section-title">
                <div class="num" style="font-size:16px;">🛍</div>
                <h2><span data-i18n="registration.souvenirsTitle">Souvenirs</span> <span style="font-size:12px;font-weight:400;color:var(--muted);" data-i18n="registration.optional">(optional)</span></h2>
              </div>
              <div class="souvenir-grid" id="souvenirsGrid"></div>
              <hr class="divider">
            </div>

            <!-- ─── TALLERES (congresos, 20/08/2026, portado de elascenso/event) ───
                 Sección propia, separada de Souvenirs. El header/ayuda/aviso
                 quedan fijos acá; solo #talleresGrid se reemplaza dinámicamente
                 desde renderTalleresSelector(), igual patrón que #souvenirsGrid. -->
            <div id="talleres-section" style="display:none;margin-bottom:16px;">
              <div class="section-title">
                <div class="num" style="font-size:16px;">🎓</div>
                <h2><span data-i18n="registration.talleresTitle">Talleres</span> <span style="font-size:12px;font-weight:400;color:var(--muted);" data-i18n="registration.optional">(optional)</span></h2>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:-2px 0 4px;">
                <p class="talleres-help" style="margin:0;" data-i18n="registration.talleresHelp">Elegí el horario de cada taller. Los obligatorios necesitan al menos una selección. SOLO PUEDE ELEGIR 1 TALLER POR TURNO.</p>
                <button type="button" class="btn btn-secondary btn-sm" style="flex-shrink:0;white-space:nowrap;"
                        id="btnTalleresAgenda" data-i18n="registration.talleresAgendaBtn">📅 Agenda de talleres</button>
              </div>
              <div id="talleres-required-warning" class="talleres-required-warning" style="display:none;" data-i18n="registration.talleresRequiredMissing"></div>
              <!-- Precio USD fijo — aviso preventivo: los talleres no tienen
                   precio USD fijo, si el evento tiene ese modo activo el
                   backend rechaza la inscripción recién al confirmar el pago.
                   Este aviso lo dice antes, para no sorprender al participante. -->
              <div id="talleres-usd-fijo-aviso" class="talleres-required-warning" style="display:none;" data-i18n="registration.talleresUsdFijoAviso"></div>
              <div id="talleresGrid"></div>
              <hr class="divider">
            </div>

            <!-- ─── PREGUNTAS DINÁMICAS (condicional, según formTypes[].preguntas) ─── -->
            <div id="dynamicQuestionsSection" style="display:none;">
              <div class="section-title">
                <div class="num" style="font-size:16px;">📋</div>
                <h2 data-i18n="registration.additionalInfoTitle">Additional Information</h2>
              </div>
              <div id="dynamicQuestionsContainer"></div>
              <hr class="divider">
            </div>

            <!-- Botones -->
            <div class="btn-actions" id="participantFormActions" style="flex-wrap:wrap;gap:10px;">
              <button type="submit" id="btnGuardar" class="btn btn-primary" data-i18n="registration.saveParticipant">
                Save Participant →
              </button>
              <button type="button" id="btnCancelar" class="btn btn-secondary"
                      style="display:none;" onclick="cancelEdit()" data-i18n="registration.cancelEditing">
                Cancel editing
              </button>
              <span id="err-form-summary" style="display:none;font-size:12px;color:var(--danger);font-weight:600;align-self:center;" data-i18n="registration.errFormSummary">
                ⚠ Please complete all required fields above.
              </span>
            </div>
          </form><!-- /participantForm -->
          </div><!-- /participantEditableArea -->

          <!-- Lista de participantes registrados -->
          <div id="participantsList" style="margin-top:28px;display:none;">
            <div class="section-title">
              <div class="num" style="font-size:15px;">👥</div>
              <h2 data-i18n="registration.registeredParticipants">Registered Participants</h2>
            </div>
            <div id="participantsContainer"></div>

            <!-- Confirmación de costo de edición (solo inscripciones ya pagadas) -->
            <div id="paidEditGate" style="display:none;margin-top:16px;padding:14px 18px;background:#fff8e6;border:1px solid #f0c040;border-radius:var(--radius);">
              <p style="font-size:13px;color:#7a5400;margin-bottom:10px;" id="paidEditGateMsg"></p>
              <p style="font-size:13px;color:#7a5400;margin-bottom:10px;" id="paidEditGateSummary"></p>
              <button class="btn btn-primary" onclick="requestPaidChanges()" id="btnRequestChanges" data-i18n="registration.requestChangesBtn">Request changes</button>
            </div>

            <div id="participantsActions">
              <button class="add-participant-btn" id="btnAddAnother" onclick="addAnotherParticipant()" data-i18n="registration.addAnother">
                + Add another participant
              </button>
              <p id="maxParticipantsNote" style="display:none;font-size:13px;color:var(--muted);margin-top:8px;"></p>
              <div class="btn-actions" style="justify-content:flex-end;margin-top:20px;">
                <button class="btn btn-primary" onclick="goToReview()" data-i18n="registration.continueToReview">
                  Continue to Review →
                </button>
              </div>
            </div>
          </div>

        </div><!-- /sub-form -->
