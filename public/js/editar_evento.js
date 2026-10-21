const MIN_IMAGENES = 2;
const MAX_IMAGENES = 6;

const inputArchivo = document.getElementById('input-archivo');
const previewBox = document.getElementById('preview-box');
const placeholder = document.getElementById('preview-placeholder');
const formEvento = document.getElementById('form-evento');
const dropzone = document.getElementById('dropzone');
const contadorImagenes = document.getElementById('contador-imagenes');

const inputFecha = document.getElementById('fecha');
const campoHora = document.getElementById('hora');
const campoTitulo = document.getElementById('titulo');
const campoDescripcion = document.getElementById('descripcion');
const inputLat = document.getElementById('lat');
const inputLon = document.getElementById('lon');
const inputUbicacion = document.getElementById('ubicacion');
const inputLocalidad = document.getElementById('localidad');
const estadoUbicacion = document.getElementById('estado-ubicacion');


function formatearFecha(valor) {
    if (!valor) return 'Fecha';
    const p = valor.split('-');
    if (p.length !== 3) return 'Fecha';
    const f = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
    return f.toLocaleDateString('es-AR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function actualizarPreview() {
    document.getElementById('prev-fecha').textContent = formatearFecha(inputFecha.value);
    document.getElementById('prev-hora').textContent = campoHora.value || 'Hora';
    document.getElementById('prev-titulo').textContent = campoTitulo.value.trim() || 'Título de tu evento';
    document.getElementById('prev-desc').textContent =
        campoDescripcion.value.trim() || 'Acá vas a ver cómo se lee la descripción en Explorar.';
    document.getElementById('prev-localidad').textContent = inputLocalidad.value.trim() || 'Localidad';
}

[campoTitulo, campoDescripcion, inputFecha, campoHora, inputLocalidad].forEach(function (el) {
    el.addEventListener('input', actualizarPreview);
    el.addEventListener('change', actualizarPreview);
});
actualizarPreview(); // arranca con los datos ya cargados del evento



let imagenes = [];    
let indiceActual = 0;

function actualesVigentes() {
    return Array.from(document.querySelectorAll('.img-actual')).filter(function (el) {
        const chk = el.querySelector('input[type="checkbox"]');
        return !(chk && chk.checked);
    });
}

function totalImagenes() {
    return actualesVigentes().length + imagenes.length;
}

function sincronizarInput() {
    const dt = new DataTransfer();
    imagenes.forEach(function (a) { dt.items.add(a); });
    inputArchivo.files = dt.files;
}

function mostrarCarrusel() {
    previewBox.innerHTML = '';
    contadorImagenes.textContent = totalImagenes();

    document.querySelectorAll('.img-actual').forEach(function (el) {
        const chk = el.querySelector('input[type="checkbox"]');
        el.classList.toggle('quitada', !!(chk && chk.checked));
    });

    const fuentes = imagenes.map(function (f) {
        return { src: URL.createObjectURL(f), nueva: true };
    }).concat(actualesVigentes().map(function (el) {
        return { src: el.querySelector('img').src, nueva: false };
    }));

    if (fuentes.length === 0) {
        previewBox.appendChild(placeholder);
        return;
    }

    if (indiceActual >= fuentes.length) indiceActual = fuentes.length - 1;
    if (indiceActual < 0) indiceActual = 0;

    const carrusel = document.createElement('div');
    carrusel.className = 'carrusel';

    const img = document.createElement('img');
    img.alt = 'Imagen ' + (indiceActual + 1) + ' del evento';
    img.src = fuentes[indiceActual].src;
    carrusel.appendChild(img);

    if (fuentes[indiceActual].nueva) {
        const btnBorrar = document.createElement('button');
        btnBorrar.type = 'button';
        btnBorrar.className = 'carrusel-borrar';
        btnBorrar.textContent = 'Quitar';
        btnBorrar.addEventListener('click', function () {
            imagenes.splice(indiceActual, 1);
            sincronizarInput();
            mostrarCarrusel();
        });
        carrusel.appendChild(btnBorrar);
    }

    if (fuentes.length > 1) {
        const btnPrev = document.createElement('button');
        btnPrev.type = 'button';
        btnPrev.className = 'carrusel-flecha carrusel-prev';
        btnPrev.setAttribute('aria-label', 'Imagen anterior');
        btnPrev.textContent = '‹';
        btnPrev.addEventListener('click', function () {
            indiceActual = (indiceActual - 1 + fuentes.length) % fuentes.length;
            mostrarCarrusel();
        });

        const btnNext = document.createElement('button');
        btnNext.type = 'button';
        btnNext.className = 'carrusel-flecha carrusel-next';
        btnNext.setAttribute('aria-label', 'Imagen siguiente');
        btnNext.textContent = '›';
        btnNext.addEventListener('click', function () {
            indiceActual = (indiceActual + 1) % fuentes.length;
            mostrarCarrusel();
        });

        carrusel.appendChild(btnPrev);
        carrusel.appendChild(btnNext);
    }

    const contador = document.createElement('div');
    contador.className = 'carrusel-contador';
    contador.textContent = (indiceActual + 1) + ' / ' + fuentes.length;
    carrusel.appendChild(contador);

    if (totalImagenes() < MIN_IMAGENES) {
        const aviso = document.createElement('p');
        aviso.className = 'carrusel-aviso';
        aviso.textContent = 'Tenés que tener al menos ' + MIN_IMAGENES + ' imágenes.';
        carrusel.appendChild(aviso);
    }

    previewBox.appendChild(carrusel);
}

function agregarArchivos(lista) {
    lista.forEach(function (archivo) {
        if (!archivo.type.startsWith('image/')) {
            alert('"' + archivo.name + '" no es una imagen.');
            return;
        }
        if (totalImagenes() >= MAX_IMAGENES) {
            alert('Podés tener hasta ' + MAX_IMAGENES + ' imágenes.');
            return;
        }
        const repetida = imagenes.some(function (i) {
            return i.name === archivo.name && i.size === archivo.size;
        });
        if (!repetida) imagenes.push(archivo);
    });

    indiceActual = 0; // las nuevas van primero en el carrusel
    sincronizarInput();
    mostrarCarrusel();
}

inputArchivo.addEventListener('change', function () {
    agregarArchivos(Array.from(this.files));
});

document.querySelectorAll('.img-actual input[type="checkbox"]').forEach(function (chk) {
    chk.addEventListener('change', mostrarCarrusel);
});

['dragenter', 'dragover'].forEach(function (n) {
    dropzone.addEventListener(n, function (e) {
        e.preventDefault();
        dropzone.classList.add('arrastrando');
    });
});
['dragleave', 'dragend'].forEach(function (n) {
    dropzone.addEventListener(n, function () { dropzone.classList.remove('arrastrando'); });
});
dropzone.addEventListener('drop', function (e) {
    e.preventDefault();
    dropzone.classList.remove('arrastrando');
    if (e.dataTransfer && e.dataTransfer.files.length) {
        agregarArchivos(Array.from(e.dataTransfer.files));
    }
});

formEvento.addEventListener('submit', function (e) {
    if (totalImagenes() < MIN_IMAGENES) {
        e.preventDefault();
        alert('Tenés que tener al menos ' + MIN_IMAGENES + ' imágenes (máximo ' + MAX_IMAGENES + ').');
        dropzone.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

mostrarCarrusel();



const lat0 = parseFloat(inputLat.value);
const lon0 = parseFloat(inputLon.value);
const tieneCoords = !isNaN(lat0) && !isNaN(lon0);

// si el evento no tiene coordenadas, arranca en Posadas
const mapa = L.map('mi_mapa').setView(
    tieneCoords ? [lat0, lon0] : [-27.3671, -55.8961],
    tieneCoords ? 16 : 13
);

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(mapa);

let marcador = null;

function ponerMarcador(lat, lon) {
    inputLat.value = Number(lat).toFixed(6);
    inputLon.value = Number(lon).toFixed(6);

    if (marcador) {
        marcador.setLatLng([lat, lon]);
    } else {
        marcador = L.marker([lat, lon], { draggable: true }).addTo(mapa);
        marcador.on('dragend', function () {
            const p = marcador.getLatLng();
            ponerMarcador(p.lat, p.lng);
            actualizarDireccion(p.lat, p.lng);
        });
    }
}

if (tieneCoords) ponerMarcador(lat0, lon0);

function actualizarDireccion(lat, lon) {
    fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat=' + lat + '&lon=' + lon)
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.display_name) inputUbicacion.value = d.display_name;
            const a = d.address || {};
            const loc = a.city || a.town || a.village || a.municipality || a.county || '';
            if (loc) inputLocalidad.value = loc;
            actualizarPreview();
        })
        .catch(function () { });
}

mapa.on('click', function (e) {
    ponerMarcador(e.latlng.lat, e.latlng.lng);
    actualizarDireccion(e.latlng.lat, e.latlng.lng);
});

function buscarUbicacion() {
    const texto = document.getElementById('direccion').value.trim();
    if (texto === '') return;

    estadoUbicacion.textContent = 'Buscando...';

    fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(texto))
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.length === 0) {
                estadoUbicacion.textContent = 'No se encontró esa ubicación.';
                return;
            }
            const lat = parseFloat(res[0].lat);
            const lon = parseFloat(res[0].lon);
            mapa.setView([lat, lon], 16);
            ponerMarcador(lat, lon);
            actualizarDireccion(lat, lon);
            estadoUbicacion.textContent = 'Ubicación encontrada ✔';
        })
        .catch(function () {
            estadoUbicacion.textContent = 'Error al buscar la ubicación.';
        });
}

document.getElementById('btn-buscar').addEventListener('click', buscarUbicacion);
document.getElementById('direccion').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        buscarUbicacion();
    }
});

document.getElementById('btn_ubi').addEventListener('click', function () {
    if (!navigator.geolocation) {
        estadoUbicacion.textContent = 'Tu navegador no soporta geolocalización.';
        return;
    }

    estadoUbicacion.textContent = 'Obteniendo ubicación...';

    navigator.geolocation.getCurrentPosition(
        function (pos) {
            const lat = pos.coords.latitude;
            const lon = pos.coords.longitude;
            inputUbicacion.value = lat.toFixed(6) + ', ' + lon.toFixed(6);
            mapa.setView([lat, lon], 16);
            ponerMarcador(lat, lon);
            actualizarDireccion(lat, lon);
            estadoUbicacion.textContent = 'Ubicación obtenida ✔';
        },
        function () {
            estadoUbicacion.textContent = 'No se pudo obtener la ubicación.';
        }
    );
});



const ICONO_QUITAR = '<i class="fa-solid fa-xmark"></i>';

function agregarHorario() {
    const fila = document.createElement('div');
    fila.className = 'fila-dinamica fila-horario';
    fila.innerHTML =
        '<input type="time" name="horario_hora[]" placeholder="Hora" aria-label="Hora">' +
        '<input type="text" name="horario_actividad[]" placeholder="Ej: Cierre con invitados sorpresa" aria-label="Actividad">' +
        '<button type="button" class="btn-quitar-fila" onclick="quitarFila(this)" aria-label="Quitar horario">' + ICONO_QUITAR + '</button>';
    document.getElementById('lista-horarios').appendChild(fila);
}

function agregarInfoExtra() {
    const fila = document.createElement('div');
    fila.className = 'fila-dinamica';
    fila.innerHTML =
        '<input type="text" name="info_extra_clave[]" placeholder="Ej: Estacionamiento" aria-label="Dato">' +
        '<input type="text" name="info_extra_valor[]" placeholder="Ej: Gratuito en el predio" aria-label="Detalle">' +
        '<button type="button" class="btn-quitar-fila" onclick="quitarFila(this)" aria-label="Quitar dato">' + ICONO_QUITAR + '</button>';
    document.getElementById('lista-info-extra').appendChild(fila);
}

function quitarFila(boton) {
    boton.closest('.fila-dinamica').remove();
}