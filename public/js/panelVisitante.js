function mostrarContenido(nombre, opcion) {

    var contenidos = document.getElementsByClassName("contenido");
    var opciones = document.getElementsByClassName("opcion");
    var i;

    for (i = 0; i < contenidos.length; i++) {
        contenidos[i].classList.remove("activo");
    }

    for (i = 0; i < opciones.length; i++) {
        opciones[i].classList.remove("activa");
    }

    var contenido = document.getElementById(nombre);

    if (contenido) {
        contenido.classList.add("activo");
    }

    opcion.classList.add("activa");

    // Si abrimos el calendario, mostramos el mes seleccionado.
    if (nombre === "calendario") {
        mostrarMesSeleccionado();
    }
}


// El calendario comienza siempre en el mes actual.
var fechaCalendario = new Date();
var mesCalendario = fechaCalendario.getMonth();
var anioCalendario = fechaCalendario.getFullYear();

function mostrarMesSeleccionado() {

    var meses = document.getElementsByClassName("mes-calendario");
    var i;

    for (i = 0; i < meses.length; i++) {
        meses[i].style.display = "none";
    }

    // Mostramos solamente el mes elegido.
    var idMes = "mes-" + anioCalendario + "-" + (mesCalendario + 1);
    var mesSeleccionado = document.getElementById(idMes);

    if (mesSeleccionado) {
        mesSeleccionado.style.display = "block";
    }

    // Actualizamos los SELECT.
    var selectMes = document.getElementById("select-mes-calendario");
    if (selectMes) {
        selectMes.value = mesCalendario + 1;
    }

    var selectAnio = document.getElementById("select-anio-calendario");
    if (selectAnio) {
        selectAnio.value = anioCalendario;
    }
}

function cambiarMes(cantidad) {

    mesCalendario = mesCalendario + cantidad;

    if (mesCalendario > 11) {
        mesCalendario = 0;
        anioCalendario = anioCalendario + 1;
    }

    if (mesCalendario < 0) {
        mesCalendario = 11;
        anioCalendario = anioCalendario - 1;
    }

    mostrarMesSeleccionado();
}

function seleccionarMes() {

    var select = document.getElementById("select-mes-calendario");

    if (!select) {
        return;
    }

    mesCalendario = parseInt(select.value) - 1;
    mostrarMesSeleccionado();
}

function seleccionarAnio() {

    var select = document.getElementById("select-anio-calendario");

    if (!select) {
        return;
    }

    anioCalendario = parseInt(select.value);
    mostrarMesSeleccionado();
}

function irAlMesActual() {

    var ahora = new Date();

    mesCalendario = ahora.getMonth();
    anioCalendario = ahora.getFullYear();

    mostrarMesSeleccionado();

    // Volvemos suavemente al encabezado del calendario.
    var calendario = document.getElementById("calendario");

    if (calendario) {
        calendario.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });
    }
}


function cancelarAsistenciaCalendario(evento, idEvento) {

    evento.preventDefault();
    evento.stopPropagation();

    if (!confirm("¿Seguro que querés cancelar tu asistencia a este evento?")) {
        return;
    }

    var eventoCalendario = document.getElementById("evento-calendario-" + idEvento);

    if (!eventoCalendario) {
        alert("No se encontró el evento en el calendario.");
        return;
    }

    eventoCalendario.classList.add("eliminando");

    fetch("index.php?slug=confirmar", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id_evento: idEvento })
    })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {

            if (data.exito && data.estado === "cancelado") {

                eventoCalendario.remove();

                var tarjeta = document.getElementById("evento-card-" + idEvento);
                if (tarjeta) {
                    tarjeta.remove();
                }

                actualizarFavoritos();

            } else {

                eventoCalendario.classList.remove("eliminando");
                alert(data.mensaje || "No se pudo cancelar la asistencia.");

            }
        })
        .catch(function (error) {
            console.error("Error al cancelar asistencia desde calendario:", error);
            eventoCalendario.classList.remove("eliminando");
            alert("Ocurrió un error en la solicitud.");
        });
}



function cancelarAsistenciaPanel(btn, idEvento) {

    if (!confirm("¿Seguro que querés cancelar tu asistencia?")) {
        return;
    }

    fetch("index.php?slug=confirmar", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id_evento: idEvento })
    })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {

            if (data.exito && data.estado === "cancelado") {
                var tarjeta = document.getElementById("evento-card-" + idEvento);
                if (tarjeta) {
                    tarjeta.remove();
                }
                actualizarFavoritos();
            } else {
                alert(data.mensaje || "No se pudo cancelar la asistencia.");
            }
        })
        .catch(function (error) {
            console.error("Error al cancelar asistencia:", error);
            alert("Ocurrió un error en la solicitud.");
        });
}



function quitarFavoritoPanel(btn, idEvento) {

    if (!confirm("¿Seguro que querés quitar el evento de favoritos?")) {
        return;
    }

    fetch("index.php?slug=toggleFavorito", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id_evento: idEvento })
    })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {

            if (data.exito && data.estado === "removido") {
                var tarjeta = document.getElementById("evento-card-" + idEvento);
                if (tarjeta) {
                    tarjeta.remove();
                }
                actualizarFavoritos(true);
            } else {
                alert(data.mensaje || "No se pudo quitar de favoritos.");
            }
        })
        .catch(function (error) {
            console.error("Error al quitar favorito:", error);
            alert("Ocurrió un error en la solicitud.");
        });
}



function actualizarFavoritos(favoritoQuitado) {

    var tarjetas = document.querySelectorAll("#favoritos .eventos .evento");
    var visibles = tarjetas.length;

    var contadorResultados = document.getElementById("contador-resultados");
    if (contadorResultados) {
        contadorResultados.textContent = visibles;
    }

    var contadorTotal = document.getElementById("contador-total");
    var total = contadorTotal ? (parseInt(contadorTotal.textContent, 10) || 0) : 0;

    if (contadorTotal && favoritoQuitado) {
        total = Math.max(0, total - 1);
        contadorTotal.textContent = total;
    }

    var mensaje = document.getElementById("sin-resultados-favoritos");
    if (!mensaje) {
        return;
    }

    if (visibles > 0) {
        mensaje.hidden = true;
        return;
    }

    var titulo = document.getElementById("sin-resultados-titulo");
    var detalle = document.getElementById("sin-resultados-texto");

    if (total === 0) {
        titulo.textContent = "Todavía no tenés favoritos";
        detalle.textContent = "Guardá eventos con el corazón y van a aparecer acá.";
    } else {
        titulo.textContent = "Sin resultados";
        detalle.textContent = "Ningún favorito coincide con tus filtros.";
    }

    mensaje.hidden = false;
}


document.addEventListener("DOMContentLoaded", function () {
    mostrarMesSeleccionado();
});