
(function () {

  var IMG_BASE_PATH = "uploads/"; 
  var DETALLE_SLUG = "index.php?slug=detalleEvento&id="; 

  var CAT_META = {
    musica:      { label: "Música",       color: "#a8462e" },
    deporte:     { label: "Deportes",     color: "#33628c" },
    feria:       { label: "Ferias",       color: "#3f7a55" },
    arte:        { label: "Arte",         color: "#7a4d8f" },
    gastronomia: { label: "Gastronomía",  color: "#b3722f" },
    tecnologia:  { label: "Tecnología",   color: "#4a6fa5" },
    otro:        { label: "Otro",         color: "#766e63" }
  };

  function catMeta(cat) {
    return CAT_META[cat] || { label: cat || "Otro", color: "#a8462e" };
  }

  if (typeof EVENTOS === "undefined" || !Array.isArray(EVENTOS)) {
    console.error("mapa_eventos.js: no se encontró la variable EVENTOS.");
    return;
  }

  var eventos = EVENTOS.filter(function (e) {
    return typeof e.lat === "number" && typeof e.lon === "number" && !isNaN(e.lat) && !isNaN(e.lon);
  });

  var userPos = { lat: USER_LAT, lng: USER_LON };

  var filterCat = "all";
  var markers = {};

  function distanceKm(a, b) {
    var R = 6371, rad = Math.PI / 180;
    var dLat = (b.lat - a.lat) * rad;
    var dLng = (b.lng - a.lng) * rad;
    var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(a.lat * rad) * Math.cos(b.lat * rad) *
      Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.sqrt(h));
  }

  function visibleEvents() {
    return eventos.filter(function (e) {
      return filterCat === "all" || e.categoria === filterCat;
    });
  }

  var map = L.map("mapa-eventos", { scrollWheelZoom: true }).setView([userPos.lat, userPos.lng], 13);

  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);

  function makePin(color, isUser) {
    return L.divIcon({
      className: "mapa-pin-wrap" + (isUser ? " is-user" : ""),
      html: '<div class="mapa-pin" style="--pin:' + color + '"></div>',
      iconSize: [26, 34],
      iconAnchor: [13, 32],
      popupAnchor: [0, -32]
    });
  }

  var marcadorUsuario = L.marker([userPos.lat, userPos.lng], {
    icon: makePin("#221d18", true),
    zIndexOffset: 500,
    interactive: false
  }).addTo(map).bindTooltip("Vos", { permanent: true, direction: "top", offset: [0, -34], className: "vos-tooltip" });

  eventos.forEach(function (e) {
    var meta = catMeta(e.categoria);
    var marker = L.marker([e.lat, e.lon], { icon: makePin(meta.color) }).addTo(map);

    var imgHtml = e.imagen
      ? '<img src="' + IMG_BASE_PATH + e.imagen + '" alt="">'
      : '';

    marker.bindPopup(
      '<div class="mapa-popup">' +
        imgHtml +
        '<span class="mapa-popup-cat" style="--pin:' + meta.color + '">' + meta.label + '</span>' +
        '<h3 class="mapa-popup-title">' + e.titulo + '</h3>' +
        '<p class="mapa-popup-meta">' + (e.fecha || '') + (e.hora ? ' · ' + e.hora : '') + '</p>' +
        '<p class="mapa-popup-meta">' + (e.zona || '') + '</p>' +
        '<a class="mapa-popup-link" href="' + DETALLE_SLUG + e.id + '">Ver detalle →</a>' +
      '</div>'
    );
    marker.on("click", function () {
      setActiveCard(e.id);
    });
    markers[e.id] = marker;
  });

  if (eventos.length) {
    map.fitBounds(L.latLngBounds(eventos.map(function (e) { return [e.lat, e.lon]; }).concat([[userPos.lat, userPos.lng]])).pad(0.15));
  }

  var chipsWrap = document.querySelector("[data-chips]");
  var categoriasPresentes = [];
  eventos.forEach(function (e) {
    if (e.categoria && categoriasPresentes.indexOf(e.categoria) === -1) {
      categoriasPresentes.push(e.categoria);
    }
  });

  function renderChips() {
    if (!chipsWrap) return;
    var html = '<button type="button" class="mapa-chip' + (filterCat === "all" ? " active" : "") + '" data-cat="all">Todas</button>';
    categoriasPresentes.forEach(function (cat) {
      var meta = catMeta(cat);
      html += '<button type="button" class="mapa-chip' + (filterCat === cat ? " active" : "") + '" data-cat="' + cat + '">' + meta.label + '</button>';
    });
    chipsWrap.innerHTML = html;

    chipsWrap.querySelectorAll(".mapa-chip").forEach(function (chip) {
      chip.addEventListener("click", function () {
        filterCat = chip.getAttribute("data-cat");
        renderChips();
        applyFilters();
      });
    });
  }

  var listEl = document.querySelector("[data-list]");
  var countEl = document.querySelector("[data-count]");

  function setActiveCard(id) {
    if (!listEl) return;
    listEl.querySelectorAll(".mapa-card").forEach(function (el) {
      el.classList.toggle("active", el.getAttribute("data-id") === String(id));
    });
    var card = listEl.querySelector('.mapa-card[data-id="' + id + '"]');
    if (card) card.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function renderList() {
    if (!listEl) return;

    var visible = visibleEvents().slice().sort(function (a, b) {
      return distanceKm(userPos, { lat: a.lat, lng: a.lon }) - distanceKm(userPos, { lat: b.lat, lng: b.lon });
    });

    if (countEl) countEl.textContent = visible.length;

    if (!visible.length) {
      listEl.innerHTML =
        '<div class="mapa-empty"><strong>No hay eventos con esos filtros</strong><span>Probá con otra categoría.</span></div>';
      return;
    }

    listEl.innerHTML = visible.map(function (e) {
      var meta = catMeta(e.categoria);
      var km = distanceKm(userPos, { lat: e.lat, lng: e.lon }).toFixed(1).replace(".", ",");
      return (
        '<article class="mapa-card" data-id="' + e.id + '" tabindex="0">' +
          '<div class="mapa-card-top"><span class="mapa-card-cat" style="--pin:' + meta.color + '">' + meta.label + '</span>' +
          '<span class="mapa-card-dist">a ' + km + ' km</span></div>' +
          '<h3 class="mapa-card-title">' + e.titulo + '</h3>' +
          '<p class="mapa-card-meta">' + (e.fecha || '') + (e.hora ? ' · ' + e.hora : '') + '</p>' +
          '<p class="mapa-card-zone">' + (e.zona || '') + '</p>' +
          '<div class="mapa-card-foot"><a href="' + DETALLE_SLUG + e.id + '" class="mapa-card-link">Ver detalle →</a></div>' +
        '</article>'
      );
    }).join("");

    listEl.querySelectorAll(".mapa-card").forEach(function (card) {
      var id = Number(card.getAttribute("data-id"));
      function focus() {
        var ev = eventos.filter(function (x) { return x.id === id; })[0];
        if (!ev) return;
        map.flyTo([ev.lat, ev.lon], Math.max(map.getZoom(), 14), { duration: 0.6 });
        markers[id].openPopup();
        setActiveCard(id);
      }
      card.addEventListener("click", focus);
      card.addEventListener("keydown", function (ev2) {
        if (ev2.key === "Enter" || ev2.key === " ") { ev2.preventDefault(); focus(); }
      });
    });
  }

  function applyFilters() {
    var visibleIds = visibleEvents().map(function (e) { return e.id; });
    eventos.forEach(function (e) {
      if (visibleIds.indexOf(e.id) === -1) {
        map.removeLayer(markers[e.id]);
      } else if (!map.hasLayer(markers[e.id])) {
        markers[e.id].addTo(map);
      }
    });
    renderList();
    var visible = visibleEvents();
    if (visible.length) {
      map.flyToBounds(L.latLngBounds(visible.map(function (e) { return [e.lat, e.lon]; })).pad(0.15), { duration: 0.6 });
    }
  }
//cerca mio
  var locateBtn = document.querySelector("[data-locate]");
  var locateLabel = document.querySelector("[data-locate-label]");
  var hintEl = document.querySelector("[data-hint]");

  var savedPos = { lat: USER_LAT, lng: USER_LON };

  function colocarMarcadorUsuario() {
    if (marcadorUsuario) map.removeLayer(marcadorUsuario);
    marcadorUsuario = L.marker([userPos.lat, userPos.lng], {
      icon: makePin("#221d18", true),
      zIndexOffset: 500,
      interactive: false
    }).addTo(map).bindTooltip("Vos", { permanent: true, direction: "top", offset: [0, -34], className: "vos-tooltip" });
  }

  if (locateBtn) {
    locateBtn.addEventListener("click", function () {
      if (isNaN(savedPos.lat) || isNaN(savedPos.lng)) {
        if (hintEl) hintEl.textContent = "Todavía no configuraste tu ubicación. Podés hacerlo desde Configuración.";
        return;
      }
      userPos = { lat: savedPos.lat, lng: savedPos.lng };
      map.setView([userPos.lat, userPos.lng], 13);
      colocarMarcadorUsuario();
      renderList();
      if (hintEl) hintEl.textContent = "Listo: la lista quedó ordenada desde tu ubicación guardada.";
    });
  }

  renderChips();
  renderList();

})();