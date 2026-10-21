function enhanceSelect(select) {
  if (select.parentNode.classList.contains("cs-select")) return;

  var wrapper = document.createElement("div");
  wrapper.className = "cs-select";

  var trigger = document.createElement("button");
  trigger.type = "button";
  trigger.className = "cs-trigger";
  trigger.textContent = select.options[select.selectedIndex].text;

  var panel = document.createElement("div");
  panel.className = "cs-panel";

  for (var i = 0; i < select.options.length; i++) {
    crearOpcion(i);
  }

  function crearOpcion(indice) {
    var item = document.createElement("div");
    item.className = "cs-option";
    if (indice === select.selectedIndex) item.classList.add("selected");

    item.innerHTML = "<span>" + select.options[indice].text + "</span><span class='cs-check'></span>";

    item.addEventListener("click", function (e) {
      e.stopPropagation();
      select.selectedIndex = indice;
      select.dispatchEvent(new Event("change", { bubbles: true }));
      trigger.textContent = select.options[indice].text;

      var todas = panel.querySelectorAll(".cs-option");
      for (var j = 0; j < todas.length; j++) todas[j].classList.remove("selected");
      item.classList.add("selected");
      wrapper.classList.remove("open");
    });

    panel.appendChild(item);
  }

  trigger.addEventListener("click", function (e) {
    e.stopPropagation();
    var estabaAbierto = wrapper.classList.contains("open");
    cerrarTodos();
    if (!estabaAbierto) wrapper.classList.add("open");
  });

  select.parentNode.insertBefore(wrapper, select);
  select.style.display = "none"; // se oculta el nativo, queda solo el personalizado
  wrapper.appendChild(select);
  wrapper.appendChild(trigger);
  wrapper.appendChild(panel);
}

function cerrarTodos() {
  var abiertos = document.querySelectorAll(".cs-select.open");
  for (var i = 0; i < abiertos.length; i++) abiertos[i].classList.remove("open");
}

document.addEventListener("click", cerrarTodos);

function iniciarFiltros(form) {
  var destino = form.dataset.ajaxDestino;
  var temporizador;

  function filtrar() {
    var url = "index.php?" + new URLSearchParams(new FormData(form)).toString();

    fetch(url)
      .then(function (respuesta) { return respuesta.text(); })
      .then(function (html) {
        var pagina = new DOMParser().parseFromString(html, "text/html");
        var nuevo = pagina.querySelector(destino);
        var actual = document.querySelector(destino);
        if (nuevo && actual) actual.innerHTML = nuevo.innerHTML;
        history.replaceState(null, "", url);
      });
  }

  form.addEventListener("change", filtrar);

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    filtrar();
  });

  var buscador = form.querySelector(".search-input");
  if (buscador) {
    buscador.addEventListener("input", function () {
      clearTimeout(temporizador);
      temporizador = setTimeout(filtrar, 300);
    });
  }
}

document.addEventListener("DOMContentLoaded", function () {
  var selects = document.querySelectorAll("select.filter-control");
  for (var i = 0; i < selects.length; i++) enhanceSelect(selects[i]);

  var formularios = document.querySelectorAll("form[data-ajax-destino]");
  for (var j = 0; j < formularios.length; j++) iniciarFiltros(formularios[j]);
});