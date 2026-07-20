// Genera 50 eventos de ejemplo (eventos_seed_50.json) para cargar vía
// POST http://127.0.0.1:8000/api/v1/event (ApiRestEvent).
// Uso: node brain/generate_eventos_seed.js
const fs = require('fs');
const path = require('path');

const TIPOS = ['deportivo','congreso','taller','corporativo','cultural','social','educativo',
  'recreativo','religioso','gastronomico','musical','tecnologico','artes','literario',
  'ambiental','salud','moda','teatro','cine','fotografia','danza','literatura'];

const CIUDADES = ['Cochabamba','La Paz','Santa Cruz','Sucre','Tarija','Oruro','Potosí',
  'Trinidad','Cobija','El Alto'];

const LUGARES = ['Parque Urbano','Plaza Principal','Coliseo Municipal','Centro de Convenciones',
  'Estadio Olímpico','Campo Ferial','Malecón','Parque Metropolitano','Teatro Municipal','Autódromo'];

const NOMBRES_BASE = ['Carrera Solidaria','Maratón de la Ciudad','Desafío Trail','Copa Running',
  'Encuentro Cultural','Feria de Emprendedores','Congreso de Innovación','Taller de Fotografía',
  'Festival Gastronómico','Noche de Teatro','Torneo Deportivo','Caminata Familiar',
  'Ciclopaseo Nocturno','Expo Tecnología','Concierto Benéfico','Retiro de Bienestar',
  'Jornada de Voluntariado','Feria del Libro','Muestra de Arte','Encuentro de Danza'];

const COLORES = ['#00bad2','#00cc77','#0033aa','#7c8e91','#f4ff39','#6fe376','#8bf94b','#1a5e2b','#38b30f','#5ec485'];
const ICONOS_FORM = ['🏃','🚴','🎨','🎭','🎤','📷','🍽️','🧘','📚','🎓'];
const ICONOS_SOUV = ['👕','🧢','🎒','🥤','🏅','🎁'];

const STATUSES = ['open','open','open','closed','coming_soon']; // ponderado hacia "open"

function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
function randInt(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }
function randFloat(min, max, decimals = 2) {
  return Number((Math.random() * (max - min) + min).toFixed(decimals));
}
function randDateWithinDays(minDays, maxDays) {
  const d = new Date();
  d.setDate(d.getDate() + randInt(minDays, maxDays));
  d.setHours(randInt(6, 20), pick([0, 15, 30, 45]), 0, 0);
  return d.toISOString().slice(0, 19).replace('T', ' ');
}

// discount_type: 'fixed_price' (price = precio final a pagar, como antes) o
// 'percentage' (price null, se usa discount_percent). Se mezclan los dos para
// tener cobertura de ambos modelos en los datos de prueba.
function buildPromoCode(i) {
  const isPercentage = Math.random() < 0.4;
  return isPercentage
    ? { promo_code: `PROMO${i}`, price: null, discount_type: 'percentage', discount_percent: randFloat(0.05, 0.30, 2) }
    : { promo_code: `PROMO${i}`, price: randFloat(15, 300), discount_type: 'fixed_price', discount_percent: null };
}

function buildEvento(i) {
  const nombre = `${pick(NOMBRES_BASE)} ${i}`;
  const status = pick(STATUSES);
  // Eventos "closed" con fecha en el pasado; "open"/"coming-soon" en el futuro.
  const date = status === 'closed' ? randDateWithinDays(-120, -5) : randDateWithinDays(5, 180);
  const hasDonation = Math.random() < 0.5;
  const hasPromo = Math.random() < 0.4;

  const numCategorias = randInt(1, 3);
  const categories = Array.from({ length: numCategorias }, (_, idx) => ({
    name: ['5K', '10K', '21K', 'General', 'VIP', 'Estudiante'][idx] || `Categoria ${idx + 1}`,
    price: randFloat(20, 900),
    description: `Categoría ${idx + 1} de ${nombre}`,
    color: pick(COLORES),
  }));

  const numFormTypes = randInt(1, 2);
  const formTypes = Array.from({ length: numFormTypes }, (_, idx) => {
    const hasSouvenirs = Math.random() < 0.7;
    return {
      name: idx === 0 ? 'Individual' : 'Grupal',
      icon: pick(ICONOS_FORM),
      description: `Inscripción ${idx === 0 ? 'individual' : 'grupal'} para ${nombre}`,
      tipo: pick(TIPOS),
      cupo_total: randInt(20, 500),
      precio_base: randFloat(20, 400),
      costo_edicion: randFloat(0, 30),
      color: pick(COLORES),
      moneda: 1,
      permite_lista_espera: Math.random() < 0.5 ? 1 : 0,
      hasshirt: Math.random() < 0.6 ? 1 : 0,
      requiere_talla: Math.random() < 0.6 ? 1 : 0,
      // El form type "Grupal" (idx 1) habilita inscripción grupal; "Individual" (idx 0), no.
      permite_inscripcion_grupal: idx === 0 ? 0 : 1,
      max_integrantes_grupo: randInt(5, 20),
      descuento_registrante_pct: 0.10,
      souvenirs: hasSouvenirs
        ? Array.from({ length: randInt(1, 3) }, (_, sidx) => ({
            name: ['Camiseta', 'Gorra', 'Mochila', 'Botella', 'Medalla'][sidx] || `Souvenir ${sidx + 1}`,
            icon: pick(ICONOS_SOUV),
            price: randFloat(5, 50),
          }))
        : [],
    };
  });

  const evento = {
    name: nombre,
    description: `${nombre} — un evento imperdible en ${pick(CIUDADES)}.`,
    longDescription: `${nombre} es una actividad organizada en ${pick(LUGARES)}, pensada para toda la familia. Incluye todo lo necesario para una gran experiencia.`,
    date,
    localTime: `${String(randInt(6, 20)).padStart(2, '0')}:${pick(['00', '15', '30', '45'])}`,
    location: `${pick(LUGARES)}, ${pick(CIUDADES)}`,
    status,
    hasDonation,
    image: `https://picsum.photos/seed/evento${i}/800/450`,
    video: '',
    coordinates: [{ lat: randFloat(-22, -10, 4), lng: randFloat(-69, -57, 4) }],
    route: Math.random() < 0.4
      ? [
          { lat: randFloat(-22, -10, 4), lng: randFloat(-69, -57, 4), label: 'Salida' },
          { lat: randFloat(-22, -10, 4), lng: randFloat(-69, -57, 4), label: 'Meta' },
        ]
      : [],
    categories,
    formTypes,
    promoCodes: hasPromo ? [buildPromoCode(i)] : [],
  };

  return evento;
}

const eventos = Array.from({ length: 50 }, (_, idx) => buildEvento(idx + 1));

const outPath = path.join(__dirname, 'eventos_seed_50.json');
fs.writeFileSync(outPath, JSON.stringify(eventos, null, 2));
console.log(`Generados ${eventos.length} eventos en ${outPath}`);
