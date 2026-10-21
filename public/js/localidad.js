let localidadEditadaAMano = false;
let temporizadorLocalidad = null;

const inputLocalidad = document.getElementById('localidad');

if (inputLocalidad) {
  //si ya viene con un valor guardado no se toca
  inputLocalidad.addEventListener('input', () => { localidadEditadaAMano = true; });
}

// Nombres que openstreet manda cuando no esta en su coso de datos, estan prohibidos
const NOMBRES_INVALIDOS = /^(Área de Promoción|Barrio Cerrado|Country Club|Urbanización)/i;

// localidades q no estan para buscar dentro del display_name
const LOCALIDADES_CONOCIDAS = [
  'Tortuguitas', 'Grand Bourg', 'Del Viso',
  'Pilar', 'José C. Paz', 'Malvinas Argentinas'
];

async function localidadDesdeCoordenadas(lat, lon) {
  const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}&zoom=14&addressdetails=1&accept-language=es`;
  const res = await fetch(url);
  if (!res.ok) return '';
  const data = await res.json();
  const a = data.address || {};

  let candidata = a.town || a.village || a.city || a.city_district
      || a.municipality || a.suburb || a.neighbourhood || a.county || '';

  //buscamos una localidad conocida dentro del display_name completo
  if (!candidata || NOMBRES_INVALIDOS.test(candidata)) {
    const encontrada = LOCALIDADES_CONOCIDAS.find(loc =>
      data.display_name && data.display_name.includes(loc)
    );
    if (encontrada) candidata = encontrada;
  }

  return candidata;
}

function actualizarLocalidad(lat, lon) {
  if (!inputLocalidad || localidadEditadaAMano) return;

  clearTimeout(temporizadorLocalidad);
  temporizadorLocalidad = setTimeout(async () => {
    try {
      const loc = await localidadDesdeCoordenadas(lat, lon);
      if (loc && !localidadEditadaAMano) inputLocalidad.value = loc;
    } catch (e) {
      console.warn('No se pudo obtener la localidad', e);
    }
  }, 1100);
}