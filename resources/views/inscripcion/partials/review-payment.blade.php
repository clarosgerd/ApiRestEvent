        <div id="sub-review" class="sub-screen">
          <div class="section-title">
            <div class="num">2</div>
            <h2 data-i18n="registration.reviewTitle">Review &amp; Payment Summary</h2>
          </div>
          <div id="reviewParticipants"></div>

          <hr class="divider">

          <div class="section-title">
            <div class="num">3</div>
            <h2 data-i18n="registration.paymentSummaryTitle">Payment Summary</h2>
          </div>
          <div style="overflow-x:auto;">
            <table class="summary-table">
              <thead>
                <tr>
                  <th data-i18n="registration.colParticipant">Participant</th>
                  <th data-i18n="registration.colCategory">Category</th>
                  <th data-i18n="registration.colInscription">Inscription</th>
                  <th id="thDonation" data-i18n="registration.colDonation">Donation</th>
                  <th data-i18n="registration.colSouvenirs">Souvenirs</th>
                  <th id="thDiscount" data-i18n="registration.colDiscount">Discount</th>
                  <th data-i18n="registration.colSubtotal">Subtotal</th>
                </tr>
              </thead>
              <tbody id="summaryBody"></tbody>
              <tfoot>
                <tr><td colspan="6" class="text-right ft-label-cell" data-i18n="registration.totalInscription">Total Inscription</td><td id="ftInscription">$0.00</td></tr>
                <tr id="ftRowDonation"><td colspan="6" class="text-right ft-label-cell" data-i18n="registration.totalDonation">Total Donation</td><td id="ftDonation">$0.00</td></tr>
                <tr><td colspan="6" class="text-right ft-label-cell" data-i18n="registration.totalSouvenirs">Total Souvenirs</td><td id="ftSouvenirs">$0.00</td></tr>
                <tr><td colspan="6" class="text-right ft-label-cell" data-i18n="registration.serviceFee">Service Fee (5%)</td><td id="ftFee">$0.00</td></tr>
                <tr id="ftRowDiscount"><td colspan="6" class="text-right ft-label-cell" style="color:var(--success);" data-i18n="registration.totalDiscount">Total Discount</td><td id="ftDiscount" style="color:var(--success);">-$0.00</td></tr>
                <tr id="ftRowGroupDiscount" style="display:none;"><td colspan="6" class="text-right ft-label-cell" style="color:var(--success);" id="ftGroupDiscountLabel">Group discount</td><td id="ftGroupDiscount" style="color:var(--success);">-$0.00</td></tr>
                <tr id="ftRowEditCost" style="display:none;"><td colspan="6" class="text-right ft-label-cell" data-i18n="registration.editCostLabel">Editing cost</td><td id="ftEditCost">$0.00</td></tr>
                <tr class="grand-total">
                  <td colspan="6" class="text-right ft-label-cell" data-i18n="registration.grandTotal">GRAND TOTAL</td>
                  <td id="ftTotal">$0.00</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <hr class="divider">

          <!-- Selector de moneda de cobro (BOB / USD) — 20/08/2026, portado
               de elascenso/event (ver PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md).
               Solo se muestra si el evento tiene aceptaUsd=true. -->
          <div id="currencySelectorBlock" style="display:none;margin-bottom:16px;">
            <div class="section-title">
              <div class="num" style="font-size:16px;">💱</div>
              <h2 data-i18n="registration.paymentCurrencyTitle">Currency</h2>
            </div>
            <div class="currency-options" id="currencyOptionsContainer">
              <label class="currency-option selected" id="cur-BOB">
                <input type="radio" name="monedaPago" value="BOB" checked>
                <div class="currency-icon">🇧🇴</div>
                <div class="currency-label">
                  <strong>Bolivianos (Bs.)</strong>
                  <span class="currency-sublabel" data-i18n="registration.paymentCurrencyBob">Pago local</span>
                </div>
              </label>
              <label class="currency-option" id="cur-USD">
                <input type="radio" name="monedaPago" value="USD">
                <div class="currency-icon">🇺🇸</div>
                <div class="currency-label">
                  <strong>Dólares (US$)</strong>
                  <span class="currency-sublabel" data-i18n="registration.paymentCurrencyUsd">Extranjeros</span>
                </div>
              </label>
            </div>
            <p id="currencyHint" style="display:none;font-size:12px;color:var(--muted);margin-top:6px;"
               data-i18n="registration.paymentCurrencyMethodFiltered">
              Para pagar en USD solo están disponibles QR y Multipago.
            </p>
          </div>

          <!-- Método de pago (alta nueva) -->
          <div id="paymentMethodBlock">
            <div class="section-title">
              <div class="num">4</div>
              <h2 data-i18n="registration.paymentMethodTitle">Payment Method</h2>
            </div>
            <!-- Se renderiza dinámicamente en renderPaymentMethods() a partir
                 de currentEvent.formasPago (viene de la API externa: los
                 métodos del sistema y/o los propios del organizador). -->
            <div class="payment-methods" id="paymentMethodsContainer"></div>
            <p id="noPaymentMethodsMsg" style="display:none;color:var(--danger);font-size:13px;margin-top:8px;" data-i18n="registration.noPaymentMethods">
              This event has no payment methods available right now.
            </p>
          </div>

          <!-- Método de pago fijo (inscripciones pending existentes, no editable) -->
          <p id="paymentMethodFixedNote" style="display:none;font-size:14px;color:var(--secondary);font-weight:600;"></p>

          <!-- Confirmación de costo de edición (inscripciones pagadas) -->
          <div id="paidConfirmBlock" style="display:none;background:#fff8e6;border:1px solid #f0c040;border-radius:var(--radius);padding:16px 20px;margin-top:10px;">
            <p id="paidConfirmMsg" style="font-size:14px;color:#7a5400;margin-bottom:10px;font-weight:600;"></p>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#7a5400;">
              <input type="checkbox" id="paidConfirmCheckbox" onchange="updateConfirmButtonState();">
              <span data-i18n="registration.paidConfirmCheckboxLabel">I confirm this change and its additional cost.</span>
            </label>
          </div>

          <!-- Deslinde de responsabilidad (texto y PDF vienen de currentEvent.deslinde /
               currentEvent.deslinde_pdf_url — API externa). Oculto si el evento no carga texto. -->
          <div id="deslindeBlock" style="display:none;background:#fff8e6;border:1px solid #f0c040;border-radius:var(--radius);padding:16px 20px;margin-top:16px;">
            <p id="deslindeText" style="font-size:13px;color:#7a5400;margin-bottom:10px;white-space:pre-line;"></p>
            <a id="btnDeslindePdf" href="#" target="_blank" rel="noopener" style="display:none;font-size:13px;font-weight:600;color:var(--secondary);text-decoration:underline;margin-bottom:10px;" data-i18n="registration.deslindeDownloadPdf">⬇ Download waiver PDF</a>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#7a5400;">
              <input type="checkbox" id="deslindeCheckbox" onchange="updateConfirmButtonState();">
              <span data-i18n="registration.deslindeCheckboxLabel">I have read and accept the liability waiver above.</span>
            </label>
          </div>

          <div id="paymentApiError" class="alert alert-danger" style="margin-top:16px;"></div>

          <div class="btn-actions" style="justify-content:space-between;margin-top:28px;">
            <button class="btn btn-secondary" onclick="goBackToForm()" data-i18n="registration.backBtn">← Back</button>
            <button class="btn btn-success" id="btnConfirmPayment" onclick="confirmPayment()" data-i18n="registration.confirmPaymentBtn">
              ✓ Confirm Payment
            </button>
          </div>
        </div><!-- /sub-review -->
