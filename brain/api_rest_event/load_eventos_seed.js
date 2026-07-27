// Carga eventos_seed_50.json en la base de datos vía
// POST http://127.0.0.1:8000/api/v1/event (ApiRestEvent) — respeta toda la
// validación real de StoreEventosRequest, igual que cualquier otro cliente.
// Cada formType del JSON ya incluye permite_inscripcion_grupal/
// max_integrantes_grupo/descuento_registrante_pct (inscripción grupal) — este
// script no necesita saber de esos campos, solo reenvía el objeto tal cual.
// Uso: node brain/load_eventos_seed.js
const fs = require('fs');
const path = require('path');

const API_URL = 'http://127.0.0.1:8000/api/v1/event';
const inputPath = path.join(__dirname, 'eventos_seed_50.json');
const eventos = JSON.parse(fs.readFileSync(inputPath, 'utf8'));

async function loadOne(evento, idx) {
  const res = await fetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(evento),
  });
  const data = await res.json().catch(() => ({}));
  if (res.ok && data.success) {
    return { ok: true, id: data.eventos?.id, name: evento.name };
  }
  return { ok: false, name: evento.name, status: res.status, message: data.message || JSON.stringify(data).slice(0, 200) };
}

(async () => {
  const results = [];
  for (let i = 0; i < eventos.length; i++) {
    const r = await loadOne(eventos[i], i);
    results.push(r);
    console.log(`[${i + 1}/${eventos.length}] ${r.ok ? 'OK  id=' + r.id : 'FAIL'} — ${r.name}${r.ok ? '' : ' — ' + r.message}`);
  }
  const okCount = results.filter(r => r.ok).length;
  console.log(`\nResumen: ${okCount}/${results.length} eventos creados correctamente.`);
  if (okCount < results.length) {
    console.log('Fallidos:');
    results.filter(r => !r.ok).forEach(r => console.log(`  - ${r.name}: ${r.message}`));
    process.exitCode = 1;
  }
})();
