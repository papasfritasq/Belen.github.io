

function toggleFavorito(btn, idEvento, silencioso) {
  fetch('index.php?slug=toggleFavorito', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ id_evento: idEvento })
  })
    .then(async response => {
      if (!response.ok) {
        const text = await response.text();
        throw new Error(`Error HTTP ${response.status}: ${text}`);
      }
      return response.json();
    })
    .then(data => {
      if (data.exito) {
        if (data.estado === 'agregado') {
          btn.classList.add('favorito-activo');
          if (!silencioso) mostrarToast('Agregado a favoritos');
        } else {
          btn.classList.remove('favorito-activo');
          if (!silencioso) mostrarToast('Quitado de favoritos', 'info');
        }
      } else {
        mostrarToast(data.mensaje || 'No se pudo guardar el favorito.', 'error');
      }
    })
    .catch(error => {
      console.error('Detalle del error:', error);
      mostrarToast('No se pudo guardar el favorito. Probá de nuevo.', 'error');
    });
}

function toggleAsistencia(boton, idEvento) {
  fetch('index.php?slug=confirmar', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ id_evento: idEvento })
  })
    .then(async response => {
      if (!response.ok) {
        const text = await response.text();
        throw new Error(`Error HTTP ${response.status}: ${text}`);
      }
      return response.json();
    })
    .then(data => {
      if (data.exito) {
        if (data.estado === 'asiste') {
          boton.classList.add('asistencia-confirmada');
          boton.innerText = '✓ Asistencia confirmada';
          mostrarToast('Asistencia confirmada');
        } else {
          boton.classList.remove('asistencia-confirmada');
          boton.innerText = '✓ Confirmar asistencia';
          mostrarToast('Asistencia cancelada', 'info');
        }
      } else {
        mostrarToast(data.mensaje || 'Ocurrió un error al procesar la solicitud.', 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      mostrarToast('No se pudo procesar la solicitud. Probá de nuevo.', 'error');
    });
}
document.addEventListener("DOMContentLoaded", function () {

  var mesActual = document.getElementById("mes-actual");
  if (mesActual) {
    var meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
      "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    var hoy = new Date();
    mesActual.textContent = " Nueva edición de " + meses[hoy.getMonth()] + " " + hoy.getFullYear();
  }

  var selectCategoria = document.getElementById("select-categoria");
  if (selectCategoria && selectCategoria.dataset.seleccionada) {
    selectCategoria.value = selectCategoria.dataset.seleccionada;
  }

  var selectTemporalidad = document.getElementById("select-temporalidad");
  if (selectTemporalidad && selectTemporalidad.dataset.seleccionada) {
    selectTemporalidad.value = selectTemporalidad.dataset.seleccionada;
  }

  var datos = document.getElementById("landing-datos");
  var selectLocalidad = document.getElementById("select-localidad");

  if (datos && selectLocalidad) {
    var localidades = [];
    try {
      localidades = JSON.parse(datos.dataset.localidades || "[]");
    } catch (err) {
      console.error("No se pudieron leer las localidades", err);
    }

    var seleccionada = selectLocalidad.dataset.seleccionada || "";

    localidades.forEach(function (localidad) {
      var option = document.createElement("option");
      option.value = localidad;
      option.textContent = localidad;
      if (localidad === seleccionada) {
        option.selected = true;
      }
      selectLocalidad.appendChild(option);
    });

  }

  var inputDesde = document.getElementById("fecha-desde");
  var inputHasta = document.getElementById("fecha-hasta");

  if (inputDesde && inputHasta) {
    function actualizarMinHasta() {
      if (inputDesde.value) {
        inputHasta.min = inputDesde.value;
        // si "hasta" quedó antes de "desde", la limpiamos
        if (inputHasta.value && inputHasta.value < inputDesde.value) {
          inputHasta.value = "";
        }
      } else {
        inputHasta.removeAttribute("min");
      }
    }

    actualizarMinHasta();
    inputDesde.addEventListener("change", actualizarMinHasta);
  }

  var toast = document.getElementById("location-toast");
  if (toast && toast.dataset.visible === "1") {
    toast.style.display = "";

    var cerrar = function () {
      toast.classList.add("is-hiding");
      setTimeout(function () { toast.remove(); }, 300);
    };

    var botonCerrar = document.getElementById("location-toast-close");
    if (botonCerrar) {
      botonCerrar.addEventListener("click", cerrar);
    }

    setTimeout(cerrar, 9000);
  }

  var formFiltros = document.getElementById("form-filtros");
  var inputPagina = document.getElementById("input-pagina");
  var pedidoActual = 0;

  function filtrarEventos() {
    var params = new URLSearchParams(new FormData(formFiltros));
    var url = "index.php?" + params.toString();
    var numeroPedido = ++pedidoActual;

    fetch(url)
      .then(function (response) { return response.text(); })
      .then(function (html) {
        // si mientras tanto se hizo otro pedido más nuevo, ignoramos este
        if (numeroPedido !== pedidoActual) return;

        var doc = new DOMParser().parseFromString(html, "text/html");

        var nuevaGrilla = doc.querySelector(".events-grid");
        var grilla = document.querySelector(".events-grid");
        if (nuevaGrilla && grilla) grilla.innerHTML = nuevaGrilla.innerHTML;

        var nuevoContador = doc.querySelector(".event-counter");
        var contador = document.querySelector(".event-counter");
        if (nuevoContador && contador) contador.innerHTML = nuevoContador.innerHTML;

        var nuevasPaginaciones = doc.querySelectorAll(".paginacion");
        document.querySelectorAll(".paginacion").forEach(function (el, i) {
          if (nuevasPaginaciones[i]) el.innerHTML = nuevasPaginaciones[i].innerHTML;
        });

        history.replaceState(null, "", url);
      })
      .catch(function (err) {
        console.error("No se pudo filtrar", err);
      });
  }

  // Cualquier cambio de filtro, orden o búsqueda vuelve a la página 1
  function filtrarDesdeCero() {
    if (inputPagina) inputPagina.value = 1;
    filtrarEventos();
  }

  if (formFiltros) {
    formFiltros.addEventListener("change", filtrarDesdeCero);

    formFiltros.addEventListener("submit", function (e) {
      e.preventDefault();
      filtrarDesdeCero();
    });

    var selectOrden = document.getElementById("select-orden");
    if (selectOrden) {
      selectOrden.addEventListener("change", filtrarDesdeCero);
    }

    var inputBuscar = formFiltros.querySelector(".search-input");
    var temporizadorBusqueda;

    if (inputBuscar) {
      inputBuscar.addEventListener("input", function () {
        clearTimeout(temporizadorBusqueda);
        temporizadorBusqueda = setTimeout(filtrarDesdeCero, 300);
      });
    }

    document.addEventListener("click", function (e) {
      var boton = e.target.closest(".paginacion a[data-pagina]");
      if (!boton) return;

      e.preventDefault();
      if (inputPagina) inputPagina.value = boton.dataset.pagina;
      filtrarEventos();

      var seccion = document.querySelector(".events-section");
      if (seccion) seccion.scrollIntoView({ behavior: "smooth" });
    });
  }

});

var fechaCalendario = new Date();

var mesCalendario = fechaCalendario.getMonth();


var anioCalendario = fechaCalendario.getFullYear();

function MesActual() {


  var ahora =
    new Date();


  mesCalendario =
    ahora.getMonth();


  anioCalendario =
    ahora.getFullYear();


  mostrarMesSeleccionado();

}