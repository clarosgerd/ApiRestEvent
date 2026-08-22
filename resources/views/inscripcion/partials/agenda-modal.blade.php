<!-- ══ MODAL DE AGENDA DEL EVENTO (detalle completo, agrupado por día) ══ -->
<!-- Fondo neutro, mismo criterio que account-modal.blade.php. -->
<div id="agendaModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:500;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)closeAgendaModal()">
  <div class="card-body" role="dialog" aria-modal="true" aria-labelledby="agendaModalTitle" style="background:var(--white);border-radius:var(--radius);max-width:600px;width:100%;max-height:88vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(2,20,40,.35);">
    <button type="button" onclick="closeAgendaModal()" aria-label="Cerrar" data-i18n-aria="common.close" style="position:absolute;top:16px;right:18px;background:none;border:none;font-size:22px;line-height:1;cursor:pointer;color:var(--muted);">&times;</button>
    <div class="section-title">
      <div class="num" style="background:var(--secondary);">📅</div>
      <h2 id="agendaModalTitle" data-i18n="formTypes.agendaTitle">Agenda</h2>
    </div>
    <div id="agendaModalContent"></div>
  </div>
</div>
