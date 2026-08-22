<!-- ══ MODAL DE AGENDA DE TALLERES (congresos, 20/08/2026, portado de
     elascenso/event) — distinta de #agendaModal (esa es la agenda GENERAL
     del evento: ponencias/keynotes/cronograma). Esta muestra solo las
     sesiones de talleres, ordenadas por fecha/hora, para que el
     participante vea el panorama completo antes de elegir. Mismo patrón
     de modal accesible (openModal()/closeModal()) que agenda-modal.blade.php. -->
<div id="talleresAgendaModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:500;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)closeTalleresAgendaModal()">
  <div class="card-body" role="dialog" aria-modal="true" aria-labelledby="talleresAgendaModalTitle" style="background:var(--white);border-radius:var(--radius);max-width:600px;width:100%;max-height:88vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(2,20,40,.35);">
    <button type="button" onclick="closeTalleresAgendaModal()" aria-label="Cerrar" data-i18n-aria="common.close" style="position:absolute;top:16px;right:18px;background:none;border:none;font-size:22px;line-height:1;cursor:pointer;color:var(--muted);">&times;</button>
    <div class="section-title">
      <div class="num" style="background:var(--secondary);">🎓</div>
      <h2 id="talleresAgendaModalTitle" data-i18n="registration.talleresAgendaTitle">Agenda de talleres</h2>
    </div>
    <div id="talleresAgendaModalContent"></div>
  </div>
</div>
