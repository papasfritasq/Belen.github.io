

var mapaConfig = null;
var marcadorConfig = null;
var mapaConfigIniciado = false;

var inputDireccionConfig = document.getElementById('cfg-direccion');
var inputLatConfig = document.getElementById('cfg-lat');
var inputLonConfig = document.getElementById('cfg-lon');
var inputDireccionTextoConfig = document.getElementById('cfg-direccion-texto');
var listaSugerenciasConfig = document.getElementById('cfg-sugerencias');
var estadoGeoConfig = document.getElementById('cfg-estado-geo');
var textoUbicacionActual = document.getElementById('cfg-ubicacion-actual-texto');
var btnGuardarConfig = document.getElementById('cfg-btn-guardar');
var estadoGuardarConfig = document.getElementById('cfg-guardar-estado');
var btnGeoConfig = document.getElementById('cfg-btn-geo');

var temporizadorBusquedaConfig = null;

function actualizarTextoUbicacionActual() {
    var direccion = inputDireccionTextoConfig.value.trim();
    if (direccion !== '') {
        textoUbicacionActual.textContent = direccion;
        textoUbicacionActual.parentElement.classList.remove('vacia');
    } else {
        textoUbicacionActual.textContent = 'Todavía no configuraste tu ubicación.';
        textoUbicacionActual.parentElement.classList.add('vacia');
    }
}
actualizarTextoUbicacionActual();

function iniciarOActualizarMapaConfig() {
    if (!mapaConfigIniciado) {
        var latInicial = parseFloat(inputLatConfig.value);
        var lonInicial = parseFloat(inputLonConfig.value);
        var hayUbicacionPrevia = !isNaN(latInicial) && !isNaN(lonInicial);

        var centroInicial = hayUbicacionPrevia ? [latInicial, lonInicial] : [-34.6037, -58.3816];
        var zoomInicial = hayUbicacionPrevia ? 15 : 11;

        mapaConfig = L.map('mapa_config').setView(centroInicial, zoomInicial);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(mapaConfig);

        if (hayUbicacionPrevia) {
            marcadorConfig = L.marker(centroInicial).addTo(mapaConfig)
                .bindPopup('<b>Tu ubicación actual</b>');
        }

        //permite marcar la ubicación tocando directamente el mapa
        mapaConfig.on('click', function (evento) {
            establecerUbicacionConfig(
                evento.latlng.lat,
                evento.latlng.lng,
                null
            );
            buscarDireccionInversa(evento.latlng.lat, evento.latlng.lng);
        });

        mapaConfigIniciado = true;
    } else {
        setTimeout(function () {
            mapaConfig.invalidateSize();
        }, 50);
    }
}

function establecerUbicacionConfig(lat, lon, nombre) {
    inputLatConfig.value = lat;
    inputLonConfig.value = lon;

    if (nombre !== null) {
        inputDireccionTextoConfig.value = nombre;
        actualizarTextoUbicacionActual();
    }

    mapaConfig.setView([lat, lon], 15);

    if (marcadorConfig) {
        mapaConfig.removeLayer(marcadorConfig);
    }

    marcadorConfig = L.marker([lat, lon]).addTo(mapaConfig)
        .bindPopup('<b>Ubicación seleccionada</b>' + (nombre ? '<br>' + nombre : ''))
        .openPopup();

    //zi el usuario mueve el marcador, se considera "sin guardar" hasta que apriete Guardar
    estadoGuardarConfig.textContent = '';
    estadoGuardarConfig.className = '';
}

function buscarDireccionInversa(lat, lon) {
    var url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`;

    fetch(url)
        .then(function (respuesta) { return respuesta.json(); })
        .then(function (datos) {
            if (datos && datos.display_name) {
                inputDireccionTextoConfig.value = datos.display_name;
                actualizarTextoUbicacionActual();
            }
        })
        .catch(function (error) {
            console.error('Error en reverse geocoding:', error);
        });
}


inputDireccionConfig.addEventListener('input', function () {
    var texto = inputDireccionConfig.value.trim();

    clearTimeout(temporizadorBusquedaConfig);

    if (texto.length < 3) {
        ocultarSugerenciasConfig();
        return;
    }

    temporizadorBusquedaConfig = setTimeout(function () {
        buscarSugerenciasConfig(texto);
    }, 400);
});

document.addEventListener('click', function (evento) {
    if (!listaSugerenciasConfig.contains(evento.target) && evento.target !== inputDireccionConfig) {
        ocultarSugerenciasConfig();
    }
});

function buscarSugerenciasConfig(texto) {
    var url = `https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=6&countrycodes=ar&q=${encodeURIComponent(texto)}`;

    fetch(url)
        .then(function (respuesta) { return respuesta.json(); })
        .then(function (datos) {
            mostrarSugerenciasConfig(datos);
        })
        .catch(function (error) {
            console.error('Error al buscar sugerencias:', error);
            ocultarSugerenciasConfig();
        });
}

function mostrarSugerenciasConfig(resultados) {
    listaSugerenciasConfig.innerHTML = '';

    if (!resultados || resultados.length === 0) {
        ocultarSugerenciasConfig();
        return;
    }

    resultados.forEach(function (lugar) {
        var item = document.createElement('li');
        item.textContent = lugar.display_name;

        item.addEventListener('click', function () {
            establecerUbicacionConfig(parseFloat(lugar.lat), parseFloat(lugar.lon), lugar.display_name);
            ocultarSugerenciasConfig();
            inputDireccionConfig.value = '';
        });

        listaSugerenciasConfig.appendChild(item);
    });

    listaSugerenciasConfig.hidden = false;
}

function ocultarSugerenciasConfig() {
    listaSugerenciasConfig.hidden = true;
    listaSugerenciasConfig.innerHTML = '';
}


btnGeoConfig.addEventListener('click', function () {
    if (!navigator.geolocation) {
        estadoGeoConfig.textContent = 'Tu navegador no soporta geolocalización.';
        return;
    }

    estadoGeoConfig.textContent = 'Obteniendo ubicación...';

    navigator.geolocation.getCurrentPosition(
        function (posicion) {
            var lat = posicion.coords.latitude;
            var lon = posicion.coords.longitude;

            estadoGeoConfig.textContent = 'Ubicación obtenida';
            establecerUbicacionConfig(lat, lon, null);
            buscarDireccionInversa(lat, lon);
        },
        function () {
            estadoGeoConfig.textContent = 'No se pudo obtener la ubicación. Revisá los permisos del navegador.';
        }
    );
});


btnGuardarConfig.addEventListener('click', function () {
    var lat = inputLatConfig.value;
    var lon = inputLonConfig.value;
    var direccion = inputDireccionTextoConfig.value;

    if (!lat || !lon) {
        estadoGuardarConfig.textContent = 'Elegí una ubicación antes de guardar.';
        estadoGuardarConfig.className = 'error';
        return;
    }

    btnGuardarConfig.disabled = true;
    estadoGuardarConfig.textContent = 'Guardando...';
    estadoGuardarConfig.className = '';

    var datos = new URLSearchParams();
    datos.append('lat', lat);
    datos.append('lon', lon);
    datos.append('direccion', direccion);

    fetch('index.php?slug=guardar_ubicacion', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: datos.toString()
    })
        .then(function (respuesta) { return respuesta.json(); })
        .then(function (resultado) {
            btnGuardarConfig.disabled = false;

            if (resultado.success) {
                estadoGuardarConfig.textContent = 'Ubicación guardada';
                estadoGuardarConfig.className = 'ok';
                actualizarTextoUbicacionActual();
            } else {
                estadoGuardarConfig.textContent = resultado.mensaje || 'No se pudo guardar.';
                estadoGuardarConfig.className = 'error';
            }
        })
        .catch(function (error) {
            console.error('Error al guardar ubicación:', error);
            btnGuardarConfig.disabled = false;
            estadoGuardarConfig.textContent = 'Ocurrió un error al guardar.';
            estadoGuardarConfig.className = 'error';
        });
});

//si no se llama esta funcion el mapa existe pero no se muestra
iniciarOActualizarMapaConfig();