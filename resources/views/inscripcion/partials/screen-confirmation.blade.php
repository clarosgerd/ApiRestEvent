  <div class="screen" id="screen-confirmation">
    <div class="card">
      <div class="card-body" style="text-align:center;padding:48px 32px;">

        <!-- Estado: Esperando pago QR -->
        <div id="confirmPending" style="display:none;">
          <div style="font-size:64px;margin-bottom:16px;">📱</div>
          <h2 style="font-size:24px;font-weight:700;color:var(--secondary);margin-bottom:8px;" data-i18n="confirmation.pendingTitle">
            Waiting for Payment
          </h2>
          <p style="color:var(--muted);margin-bottom:20px;" data-i18n="confirmation.pendingMsg">
            Scan the QR code below to complete your payment. We are verifying automatically.
          </p>
          <div style="background:var(--light);border-radius:var(--radius);padding:16px 28px;display:inline-block;margin-bottom:24px;">
            <p style="font-size:12px;color:var(--muted);margin-bottom:4px;" data-i18n="confirmation.referenceNumber">Reference number</p>
            <p id="confirmRefPending" style="font-size:22px;font-weight:700;color:var(--primary);letter-spacing:3px;"></p>
          </div>
          <div style="margin-bottom:24px;">
            <img id="qrImage" src="" alt="Código QR de pago"
                 style="width:200px;height:200px;border:1px solid var(--border);border-radius:var(--radius);display:none;">
            <canvas id="qrCanvas" width="200" height="200"
                    style="border:1px solid var(--border);border-radius:var(--radius);display:none;"></canvas>
            <p id="qrSubtitle" style="font-size:11px;color:var(--muted);margin-top:8px;"></p>
          </div>
          <div style="background:#fff8e6;border:1px solid #f0c040;border-radius:var(--radius);padding:14px 20px;display:inline-flex;align-items:center;gap:10px;margin-bottom:16px;">
            <span class="spinner" style="border-color:rgba(176,125,0,.3);border-top-color:#b07d00;width:20px;height:20px;"></span>
            <span style="font-size:13px;color:#b07d00;font-weight:600;" id="pollStatusText">Checking payment status… Next check in 30s</span>
          </div>
          <div id="ticketQrBlock" style="display:none;text-align:center;margin-bottom:24px;padding-top:20px;border-top:2px dashed var(--border);">
            <p style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin:0 0 10px;letter-spacing:1px;">Ticket Reference QR</p>
            <img id="ticketQrImage" src="" alt="QR de referencia"
                 style="width:160px;height:160px;border:1px solid var(--border);border-radius:8px;">
            <p style="font-size:11px;color:var(--muted);margin:8px 0 0;">We already sent this reference QR to your email.</p>
          </div>
          <div id="confirmSummaryPending" style="text-align:left;max-width:480px;margin:0 auto 24px;"></div>
        </div>

        <!-- Estado: Esperando pago Multipago (iframe) -->
        <div id="confirmPendingMultipago" style="display:none;">
          <div style="font-size:64px;margin-bottom:16px;">💳</div>
          <h2 style="font-size:24px;font-weight:700;color:var(--secondary);margin-bottom:8px;" data-i18n="confirmation.pendingMultipagoTitle">
            Complete Your Payment
          </h2>
          <p style="color:var(--muted);margin-bottom:20px;" data-i18n="confirmation.pendingMultipagoMsg">
            Choose your payment method (Tigo Money, card, QR, or in-person) inside the window below. We are verifying automatically.
          </p>
          <div style="background:var(--light);border-radius:var(--radius);padding:16px 28px;display:inline-block;margin-bottom:24px;">
            <p style="font-size:12px;color:var(--muted);margin-bottom:4px;" data-i18n="confirmation.referenceNumber">Reference number</p>
            <p id="confirmRefPendingMultipago" style="font-size:22px;font-weight:700;color:var(--primary);letter-spacing:3px;"></p>
          </div>
          <div style="margin-bottom:24px;">
            <iframe id="multipagoIframe" src="" title="Multipago"
                    style="width:100%;max-width:420px;height:520px;border:1px solid var(--border);border-radius:var(--radius);"></iframe>
          </div>
          <div style="background:#fff8e6;border:1px solid #f0c040;border-radius:var(--radius);padding:14px 20px;display:inline-flex;align-items:center;gap:10px;margin-bottom:16px;">
            <span class="spinner" style="border-color:rgba(176,125,0,.3);border-top-color:#b07d00;width:20px;height:20px;"></span>
            <span style="font-size:13px;color:#b07d00;font-weight:600;" id="pollStatusTextMultipago">Checking payment status… Next check in 30s</span>
          </div>
          <div id="confirmSummaryPendingMultipago" style="text-align:left;max-width:480px;margin:0 auto 24px;"></div>
        </div>

        <!-- Estado: Registro guardado, pago pendiente sin pasarela (el usuario paga después) -->
        <div id="confirmPendingManual" style="display:none;">
          <div style="font-size:64px;margin-bottom:16px;">🕒</div>
          <h2 style="font-size:24px;font-weight:700;color:var(--secondary);margin-bottom:8px;" data-i18n="confirmation.pendingManualTitle">
            Registration Saved — Payment Pending
          </h2>
          <p style="color:var(--muted);margin-bottom:20px;" data-i18n="confirmation.pendingManualMsg">
            You can complete your payment anytime. Log in with your account and continue from where you left off.
          </p>
          <div style="background:var(--light);border-radius:var(--radius);padding:16px 28px;display:inline-block;margin-bottom:24px;">
            <p style="font-size:12px;color:var(--muted);margin-bottom:4px;" data-i18n="confirmation.referenceNumber">Reference number</p>
            <p id="confirmRefPendingManual" style="font-size:22px;font-weight:700;color:var(--primary);letter-spacing:3px;"></p>
          </div>
          <div id="confirmSummaryPendingManual" style="text-align:left;max-width:480px;margin:0 auto 24px;"></div>
          <button class="btn btn-primary" onclick="startOver()" data-i18n="confirmation.registerAnother">
            Register another participant
          </button>
        </div>

        <!-- Estado: Pago completado — E-Ticket -->
        <div id="confirmPaid" style="display:none;">
          <div style="font-size:64px;margin-bottom:16px;">🎉</div>
          <h2 style="font-size:24px;font-weight:700;color:var(--secondary);margin-bottom:8px;" data-i18n="confirmation.paidTitle">
            Payment Confirmed!
          </h2>
          <p style="color:var(--muted);margin-bottom:24px;" data-i18n="confirmation.paidMsg">
            Here is your e-ticket. Print or save it for your records.
          </p>

          <div class="eticket" id="eticketContainer"></div>

          <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:8px;">
            <button class="btn-print" onclick="window.print()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
              </svg>
              <span data-i18n="confirmation.printBtn">Print E-Ticket</span>
            </button>
            <button class="btn btn-primary" onclick="startOver()" data-i18n="confirmation.registerAnother">
              Register another participant
            </button>
          </div>
        </div>

      </div>
    </div>
  </div><!-- /screen-confirmation -->
