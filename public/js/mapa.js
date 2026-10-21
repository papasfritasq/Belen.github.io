const map = L.map('mi_mapa').setView([-34.6037, -58.3816], 13);

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(map);

let marcadorActual = null;

const inputDireccion = document.getElementById('direccion');
const inputUbicacionFinal = document.getElementById('ubicacion');
const inputLat = document.getElementById('lat');
const inputLon = document.getElementById('lon');
const listaSugerencias = document.getElementById('sugerencias-direccion');

let temporizadorBusqueda = null;

inputDireccion.addEventListener('input', function () {
    const texto = inputDireccion.value.trim();
    clearTimeout(temporizadorBusqueda);

    if (texto.length < 3) {
        ocultarSugerencias();
        return;
    }

    temporizadorBusqueda = setTimeout(() => buscarSugerencias(texto), 400);
});

document.addEventListener('click', function (evento) {
    if (!listaSugerencias.contains(evento.target) && evento.target !== inputDireccion) {
        ocultarSugerencias();
    }
});

function buscarSugerencias(texto) {
    const url = `https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=6&countrycodes=ar&q=${encodeURIComponent(texto)}`;

    fetch(url)
        .then(response => response.json())
        .then(mostrarSugerencias)
        .catch(function (error) {
            console.error('Error al buscar sugerencias:', error);
            ocultarSugerencias();
        });
}

function mostrarSugerencias(resultados) {
    listaSugerencias.innerHTML = '';

    if (!resultados || resultados.length === 0) {
        ocultarSugerencias();
        return;
    }

    resultados.forEach(function (lugar) {
        const item = document.createElement('li');
        item.textContent = lugar.display_name;
        item.addEventListener('click', function () {
            seleccionarLugar(lugar);
        });
        listaSugerencias.appendChild(item);
    });

    listaSugerencias.classList.add('visible');
}

function ocultarSugerencias() {
    listaSugerencias.classList.remove('visible');
    listaSugerencias.innerHTML = '';
}

//la usan seleccionarLugar() y el botón "Usar mi ubicación actual" (posts.js)
function centrarMapaEnUbicacion(lat, lon, textoPopup) {
    map.setView([lat, lon], 16);

    if (marcadorActual) {
        map.removeLayer(marcadorActual);
    }

    marcadorActual = L.marker([lat, lon]).addTo(map)
        .bindPopup(textoPopup)
        .openPopup();
}

//la usa el botón "Usar mi ubicación actual" en posts.js
function obtenerDireccionDesdeCoordenadas(lat, lon, callback) {
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`;

    fetch(url)
        .then(response => response.json())
        .then(function (data) {
            callback(data && data.display_name ? data.display_name : `${lat}, ${lon}`);
        })
        .catch(function (error) {
            console.error('Error en geocodificación inversa:', error);
            callback(`${lat}, ${lon}`);
        });
}

function seleccionarLugar(lugar) {
    const lat = parseFloat(lugar.lat);
    const lon = parseFloat(lugar.lon);

    centrarMapaEnUbicacion(lat, lon, `<b>Ubicación seleccionada:</b><br>${lugar.display_name}`);

    inputUbicacionFinal.value = lugar.display_name;
    inputLat.value = lat;
    inputLon.value = lon;

    if (typeof actualizarLocalidad === 'function') {
        actualizarLocalidad(lat, lon);
    }

    ocultarSugerencias();
    inputDireccion.value = '';
}

function buscarDireccion() {
    const texto = inputDireccion.value.trim();

    if (!texto) {
        mostrarToast('Por favor ingresá una dirección.', 'info');
        return;
    }

    const url = `https://nominatim.openstreetmap.org/search?format=json&countrycodes=ar&q=${encodeURIComponent(texto)}`;

    fetch(url)
        .then(response => response.json())
        .then(function (data) {
            if (data.length > 0) {
                seleccionarLugar(data[0]);
            } else {
                mostrarToast('No se encontró la dirección ingresada.', 'info');
            }
        })
        .catch(function (error) {
            console.error('Error al consultar la API:', error);
            mostrarToast('Ocurrió un error al realizar la búsqueda.', 'error');
        });
}

//si el evento ya tiene coordenadas guardadas, centramos el mapa al cargar
if (inputLat.value && inputLon.value) {
    const latInicial = parseFloat(inputLat.value);
    const lonInicial = parseFloat(inputLon.value);

    if (!isNaN(latInicial) && !isNaN(lonInicial)) {
        centrarMapaEnUbicacion(latInicial, lonInicial, '<b>Ubicación actual del evento</b>');
    }
}