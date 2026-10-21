const inputArchivo = document.getElementById('input-archivo');
const previewBox = document.getElementById('preview-box');
const placeholder = document.getElementById('preview-placeholder');
const inputFecha = document.getElementById('fecha');
const formEvento = document.getElementById('form-evento');
const dropzone = document.getElementById('dropzone');
const contadorImagenes = document.getElementById('contador-imagenes');

if (inputFecha) {
    const hoy = new Date();
    const yyyy = hoy.getFullYear();
    const mm = String(hoy.getMonth() + 1).padStart(2, '0');
    const dd = String(hoy.getDate()).padStart(2, '0');
    const hoyStr = `${yyyy}-${mm}-${dd}`;

    inputFecha.min = hoyStr;

    //si venía con una fecha ya vencida la reseteamos para forzar a elegir una válida
    if (inputFecha.value && inputFecha.value < hoyStr) {
        inputFecha.value = '';
    }

    const inputHora = document.getElementById('hora');

    if (inputHora) {
        function actualizarMinHora() {
            if (inputFecha.value === hoyStr) {
                const hh = String(hoy.getHours()).padStart(2, '0');
                const min = String(hoy.getMinutes()).padStart(2, '0');
                const horaMin = `${hh}:${min}`;

                inputHora.min = horaMin;

                if (inputHora.value && inputHora.value < horaMin) {
                    inputHora.value = '';
                }
            } else {
                inputHora.removeAttribute('min');
            }
        }

        actualizarMinHora();
        inputFecha.addEventListener('change', actualizarMinHora);
    }
}

const campoTitulo = document.getElementById('titulo');
const campoDescripcion = document.getElementById('descripcion');
const campoHora = document.getElementById('hora');
const campoLocalidad = document.getElementById('localidad');
const campoPrecio = document.getElementById('precio');
const bloquePrecio = document.getElementById('campo-precio');

const prevFecha = document.getElementById('prev-fecha');
const prevHora = document.getElementById('prev-hora');
const prevTitulo = document.getElementById('prev-titulo');
const prevDesc = document.getElementById('prev-desc');
const prevLocalidad = document.getElementById('prev-localidad');
const prevPrecio = document.getElementById('prev-precio');

function formatearFecha(valor) {
    if (!valor) return 'Fecha';
    const partes = valor.split('-');
    if (partes.length !== 3) return 'Fecha';
    const f = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));
    return f.toLocaleDateString('es-AR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function actualizarPreview() {
    if (prevFecha && inputFecha) prevFecha.textContent = formatearFecha(inputFecha.value);
    if (prevHora && campoHora) prevHora.textContent = campoHora.value || 'Hora';

    if (prevTitulo && campoTitulo) {
        prevTitulo.textContent = campoTitulo.value.trim() || 'Título de tu evento';
    }
    if (prevDesc && campoDescripcion) {
        prevDesc.textContent = campoDescripcion.value.trim() || 'Acá vas a ver cómo se lee la descripción en Explorar.';
    }
    if (prevLocalidad && campoLocalidad) {
        prevLocalidad.textContent = campoLocalidad.value.trim() || 'Localidad';
    }
}

[campoTitulo, campoDescripcion, inputFecha, campoHora, campoLocalidad, campoPrecio].forEach(function (el) {
    if (!el) return;
    el.addEventListener('input', actualizarPreview);
    el.addEventListener('change', actualizarPreview);
});


setInterval(actualizarPreview, 600);

const MIN_IMAGENES = 2;
const MAX_IMAGENES = 6;

let imagenes = [];      
let indiceActual = 0;   

function sincronizarInput() {
    const dt = new DataTransfer();
    imagenes.forEach(function (archivo) {
        dt.items.add(archivo);
    });
    inputArchivo.files = dt.files;
}

function mostrarCarrusel() {
    previewBox.innerHTML = '';

    if (contadorImagenes) contadorImagenes.textContent = imagenes.length;

    if (imagenes.length === 0) {
        previewBox.appendChild(placeholder);
        return;
    }

    if (indiceActual >= imagenes.length) indiceActual = imagenes.length - 1;
    if (indiceActual < 0) indiceActual = 0;

    const carrusel = document.createElement('div');
    carrusel.className = 'carrusel';

    const img = document.createElement('img');
    img.alt = 'Imagen ' + (indiceActual + 1) + ' del evento';
    img.src = URL.createObjectURL(imagenes[indiceActual]);
    carrusel.appendChild(img);

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

    if (imagenes.length > 1) {
        const btnPrev = document.createElement('button');
        btnPrev.type = 'button';
        btnPrev.className = 'carrusel-flecha carrusel-prev';
        btnPrev.setAttribute('aria-label', 'Imagen anterior');
        btnPrev.textContent = '‹';
        btnPrev.addEventListener('click', function () {
            indiceActual = (indiceActual - 1 + imagenes.length) % imagenes.length;
            mostrarCarrusel();
        });

        const btnNext = document.createElement('button');
        btnNext.type = 'button';
        btnNext.className = 'carrusel-flecha carrusel-next';
        btnNext.setAttribute('aria-label', 'Imagen siguiente');
        btnNext.textContent = '›';
        btnNext.addEventListener('click', function () {
            indiceActual = (indiceActual + 1) % imagenes.length;
            mostrarCarrusel();
        });

        carrusel.appendChild(btnPrev);
        carrusel.appendChild(btnNext);
    }

    const contador = document.createElement('div');
    contador.className = 'carrusel-contador';
    contador.textContent = (indiceActual + 1) + ' / ' + imagenes.length;
    carrusel.appendChild(contador);

    // aviso si todavía no llega al mínimo
    if (imagenes.length < MIN_IMAGENES) {
        const aviso = document.createElement('p');
        aviso.className = 'carrusel-aviso';
        aviso.textContent = 'Tenés que subir al menos ' + MIN_IMAGENES + ' imágenes.';
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
        if (imagenes.length >= MAX_IMAGENES) {
            alert('Podés subir hasta ' + MAX_IMAGENES + ' imágenes.');
            return;
        }
        // evitar repetidas
        const repetida = imagenes.some(function (i) {
            return i.name === archivo.name && i.size === archivo.size;
        });
        if (!repetida) {
            imagenes.push(archivo);
        }
    });

    indiceActual = imagenes.length - 1; 
    sincronizarInput();
    mostrarCarrusel();
}

if (inputArchivo && previewBox) {
    inputArchivo.addEventListener('change', function () {
        agregarArchivos(Array.from(this.files));
    });
}

if (dropzone) {
    ['dragenter', 'dragover'].forEach(function (nombre) {
        dropzone.addEventListener(nombre, function (e) {
            e.preventDefault();
            dropzone.classList.add('arrastrando');
        });
    });

    ['dragleave', 'dragend'].forEach(function (nombre) {
        dropzone.addEventListener(nombre, function () {
            dropzone.classList.remove('arrastrando');
        });
    });

    dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropzone.classList.remove('arrastrando');
        if (e.dataTransfer && e.dataTransfer.files.length) {
            agregarArchivos(Array.from(e.dataTransfer.files));
        }
    });
}

if (formEvento) {
    formEvento.addEventListener('submit', function (e) {
        if (imagenes.length < MIN_IMAGENES) {
            e.preventDefault();
            alert('Tenés que subir al menos ' + MIN_IMAGENES + ' imágenes (máximo ' + MAX_IMAGENES + ').');
            if (dropzone) dropzone.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
}

function ajustarAlturaUbicacion() {
    inputUbicacion.style.height = 'auto';
    inputUbicacion.style.height = inputUbicacion.scrollHeight + 'px';
}

const btnGeo = document.getElementById('btn_ubi');
const inputUbicacion = document.getElementById('ubicacion');
const estadoUbicacion = document.getElementById('estado-ubicacion');

if (inputUbicacion) {
    inputUbicacion.addEventListener('input', ajustarAlturaUbicacion);
    ajustarAlturaUbicacion(); //por si ya viene con una dirección cargada (editar evento)
}

if (btnGeo) {
    btnGeo.addEventListener('click', function () {
        if (!navigator.geolocation) {
            estadoUbicacion.textContent = 'Tu navegador no soporta geolocalización.';
            return;
        }

        estadoUbicacion.textContent = 'Obteniendo ubicación...';

        navigator.geolocation.getCurrentPosition(
            function (posicion) {
                const lat = posicion.coords.latitude.toFixed(6);
                const lon = posicion.coords.longitude.toFixed(6);

                document.getElementById('lat').value = lat;
                document.getElementById('lon').value = lon;

                if (typeof centrarMapaEnUbicacion === 'function') {
                    centrarMapaEnUbicacion(parseFloat(lat), parseFloat(lon), '<b>Obteniendo dirección...</b>');
                }

                if (typeof obtenerDireccionDesdeCoordenadas === 'function') {
                    obtenerDireccionDesdeCoordenadas(lat, lon, function (direccion) {
                        inputUbicacion.value = direccion;
                        ajustarAlturaUbicacion();
                        estadoUbicacion.textContent = 'Ubicación obtenida ✔';

                        if (typeof marcadorActual !== 'undefined' && marcadorActual) {
                            marcadorActual.setPopupContent(`<b>Tu ubicación actual:</b><br>${direccion}`);
                            marcadorActual.openPopup();
                        }
                    });
                } else {
                    inputUbicacion.value = lat + ', ' + lon;
                    ajustarAlturaUbicacion();
                    estadoUbicacion.textContent = 'Ubicación obtenida ✔';
                }

                if (typeof actualizarLocalidad === 'function') {
                    actualizarLocalidad(lat, lon);
                }
            },
            function () {
                estadoUbicacion.textContent = 'No se pudo obtener la ubicación.';
            }
        );
    });
}

function agregarFila(idLista, idTemplate) {
    const lista = document.getElementById(idLista);
    const tpl = document.getElementById(idTemplate);
    lista.appendChild(tpl.content.firstElementChild.cloneNode(true));
}

function agregarHorario() {
    agregarFila('lista-horarios', 'tpl-horario');
}

function agregarInfoExtra() {
    agregarFila('lista-info-extra', 'tpl-info-extra');
}

function quitarFila(boton) {
    boton.closest('.fila-dinamica').remove();
}