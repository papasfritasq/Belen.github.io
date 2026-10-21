function initReveal() {
  var elementos = document.querySelectorAll(".reveal");
  if (!elementos.length) return;

  var observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("in-view");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 }
  );

  elementos.forEach(function (el) {
    observer.observe(el);
  });
}

document.addEventListener("DOMContentLoaded", initReveal);


document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('mapa_detalle');
  if (!el) return;

  var zona = el.dataset.zona.trim();
  var map = L.map('mapa_detalle', { scrollWheelZoom: false });

  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);

  var coordMatch = zona.match(/^(-?\d+(\.\d+)?)\s*,\s*(-?\d+(\.\d+)?)$/);

  if (coordMatch) {
    var lat = parseFloat(coordMatch[1]);
    var lon = parseFloat(coordMatch[3]);
    map.setView([lat, lon], 16);
    L.marker([lat, lon]).addTo(map);
  } else {
    var url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(zona)}`;
    fetch(url)
      .then(r => r.json())
      .then(data => {
        if (data.length > 0) {
          var lat = parseFloat(data[0].lat);
          var lon = parseFloat(data[0].lon);
          map.setView([lat, lon], 16);
          L.marker([lat, lon]).addTo(map).bindPopup(zona);
        } else {
          map.setView([-34.6037, -58.3816], 11);
          el.insertAdjacentHTML('afterend', '<p style="font-size:13px;color:#888;">No se pudo ubicar la dirección en el mapa.</p>');
        }
      })
      .catch(() => {
        map.setView([-34.6037, -58.3816], 11);
      });
  }
});


const btnConfirmar = document.getElementById('btn-confirmar');

if (btnConfirmar) {
  btnConfirmar.addEventListener('click', async (e) => {
    e.preventDefault(); // evita que navegue al JSON

    const texto = btnConfirmar.querySelector('.confirm-text');

    try {
      const res = await fetch(btnConfirmar.href);
      const data = await res.json();

      if (!data.exito) {
        alert(data.mensaje || 'No se pudo actualizar la asistencia.');
        return;
      }

      if (data.estado === 'asiste') {
        texto.textContent = 'Asistencia confirmada ✓';
        btnConfirmar.classList.add('confirmado');
      } else {
        texto.textContent = 'Confirmar asistencia';
        btnConfirmar.classList.remove('confirmado');
      }
    } catch (err) {
      //si no hay sesión, index.php devuelve HTML y el JSON falla
      window.location.href = 'index.php?slug=login';
    }
  });
}

document.addEventListener("DOMContentLoaded", function () {

  var contenedorQR = document.getElementById("qr-evento");
  if (!contenedorQR) return;

  var idEvento = contenedorQR.getAttribute("data-id");
  if (!idEvento) return;

  if (typeof QRCode === "undefined") {
    console.error("QRCode.js no está cargado.");
    return;
  }

  var urlEvento =
    window.location.origin +
    window.location.pathname +
    "?slug=detalleEvento&id=" +
    idEvento;

  new QRCode(contenedorQR, {
    text: urlEvento,
    width: 180,
    height: 180,
    correctLevel: QRCode.CorrectLevel.H
  });

});


document.addEventListener("DOMContentLoaded", function () {

  var botonPDF = document.getElementById("btn-descargar-pdf");
  if (!botonPDF) return;

  botonPDF.addEventListener("click", function () {
    generarPDF(botonPDF);
  });

});

// aplica varios estilos de una vez
function estilos(el, obj) {
  Object.keys(obj).forEach(function (k) {
    el.style[k] = obj[k];
  });
  return el;
}

function generarPDF(botonPDF) {

  if (typeof html2canvas === "undefined") {
    alert("No se pudo cargar html2canvas.");
    return;
  }

  if (
    typeof window.jspdf === "undefined" ||
    typeof window.jspdf.jsPDF === "undefined"
  ) {
    alert("No se pudo cargar jsPDF.");
    return;
  }

  var eventoOriginal = document.getElementById("evento-pdf");

  if (!eventoOriginal) {
    alert("No se encontró el evento.");
    return;
  }

  botonPDF.disabled = true;
  botonPDF.dataset.html = botonPDF.innerHTML;
  botonPDF.textContent = "Generando PDF...";

  var titulo = document.querySelector(".detail-title");
  var organizador = document.querySelector(
    ".detail-hero-info .event-organizer, .detail-hero-info .evento-anfitrion"
  );
  var categoria = document.querySelector(".event-badge");
  var meta = document.querySelectorAll(".detail-meta li");
  var imagen = document.querySelector(".detail-hero-image img");

  var pdfContainer = document.createElement("div");

  pdfContainer.className = "detail-page";

  estilos(pdfContainer, {
    position: "absolute",
    left: "-100000px",
    top: "0",
    width: "900px",
    background: "#ffffff",
    padding: "25px",
    boxSizing: "border-box",
    fontFamily: "Arial, sans-serif",
    color: "#1A1A1A"
  });


  var cabecera = document.createElement("div");

  estilos(cabecera, {
    display: "flex",
    gap: "30px",
    marginBottom: "20px",
    paddingBottom: "15px",
    borderBottom: "1px solid #dddddd"
  });

  if (imagen && imagen.src) {
    var imagenPDF = document.createElement("img");
    imagenPDF.src = imagen.src;

    estilos(imagenPDF, {
      width: "320px",
      height: "220px",
      objectFit: "cover",
      borderRadius: "12px"
    });

    cabecera.appendChild(imagenPDF);
  }

  var datos = document.createElement("div");
  datos.style.flex = "1";

  if (categoria) {
    var categoriaPDF = document.createElement("div");
    categoriaPDF.textContent = categoria.textContent.trim();

    estilos(categoriaPDF, {
      fontSize: "13px",
      fontWeight: "bold",
      textTransform: "uppercase",
      marginBottom: "12px",
      color: "#A0522D"
    });

    datos.appendChild(categoriaPDF);
  }

  if (titulo) {
    var tituloPDF = document.createElement("h1");
    tituloPDF.textContent = titulo.textContent.trim();

    estilos(tituloPDF, {
      fontFamily: "Georgia, serif",
      fontSize: "30px",
      margin: "0 0 15px 0",
      lineHeight: "1.2"
    });

    datos.appendChild(tituloPDF);
  }

  if (organizador) {
    var organizadorPDF = document.createElement("div");
    organizadorPDF.innerHTML = organizador.innerHTML;
    organizadorPDF.style.marginBottom = "15px";
    datos.appendChild(organizadorPDF);
  }

  if (meta.length > 0) {
    var informacionPDF = document.createElement("div");

    estilos(informacionPDF, {
      lineHeight: "1.8",
      fontSize: "14px"
    });

    if (meta[0]) {
      var fechaPDF = document.createElement("div");
      fechaPDF.textContent = meta[0].textContent.trim();
      informacionPDF.appendChild(fechaPDF);
    }

    if (meta[1]) {
      var ubicacionPDF = document.createElement("div");
      ubicacionPDF.textContent = meta[1].textContent.trim();
      informacionPDF.appendChild(ubicacionPDF);
    }

    datos.appendChild(informacionPDF);
  }

  cabecera.appendChild(datos);
  pdfContainer.appendChild(cabecera);


  var contenidoGrid = eventoOriginal.querySelector(".detail-grid");

  if (contenidoGrid) {

    var contenidoClonado = contenidoGrid.cloneNode(true);

    var mapaPDF = contenidoClonado.querySelector("#mapa_detalle");
    if (mapaPDF) mapaPDF.remove();

    contenidoClonado.querySelectorAll("[data-no-pdf]").forEach(function (elemento) {
      if (elemento.classList.contains("detail-share")) return;
      elemento.remove();
    });

    contenidoClonado.querySelectorAll(".detail-confirm, #btn-descargar-pdf").forEach(function (elemento) {
      elemento.remove();
    });

    var qrClon = contenidoClonado.querySelector("#qr-evento");
    var qrImgOriginal = document.querySelector("#qr-evento img");

    if (qrClon && qrImgOriginal && qrImgOriginal.src) {
      qrClon.removeAttribute("id");
      qrClon.innerHTML = "";

      var qrCopia = document.createElement("img");
      qrCopia.src = qrImgOriginal.src;

      estilos(qrCopia, {
        display: "block",
        width: "140px",
        height: "140px"
      });

      qrClon.appendChild(qrCopia);
    }

    contenidoClonado.querySelectorAll("*").forEach(function (elemento) {

      var tag = elemento.tagName.toLowerCase();

      if (
        tag === "img" ||
        tag === "i" ||
        tag === "path" ||
        tag === "circle" ||
        tag === "canvas"
      ) {
        return;
      }

      estilos(elemento, {
        height: "auto",
        minHeight: "0",
        maxHeight: "none",
        overflow: "visible"
      });

      if (elemento.classList.contains("reveal")) {
        elemento.style.opacity = "1";
        elemento.style.transform = "none";
      }

    });

    contenidoClonado.querySelectorAll(".detail-card, .detail-block").forEach(function (bloque) {
      bloque.style.marginTop = "0";
      bloque.style.marginBottom = "16px";
    });

    estilos(contenidoClonado, {
      display: "block",
      width: "100%",
      margin: "0"
    });

    pdfContainer.appendChild(contenidoClonado);
  }

  document.body.appendChild(pdfContainer);

  esperarImagenes(pdfContainer, function () {
    crearPDFDesdeContenedor(pdfContainer, botonPDF, eventoOriginal);
  });

}

function esperarImagenes(contenedor, callback) {

  var imagenes = contenedor.querySelectorAll("img");

  if (!imagenes.length) {
    callback();
    return;
  }

  var cargadas = 0;
  var terminado = false;

  function listo() {
    cargadas++;
    if (!terminado && cargadas === imagenes.length) {
      terminado = true;
      callback();
    }
  }

  imagenes.forEach(function (imagen) {
    if (imagen.complete) {
      listo();
    } else {
      imagen.addEventListener("load", listo);
      imagen.addEventListener("error", listo);
    }
  });

}

// genera PDF 

function crearPDFDesdeContenedor(pdfContainer, botonPDF, eventoOriginal) {

  html2canvas(pdfContainer, {
    scale: 2,
    useCORS: true,
    allowTaint: false,
    backgroundColor: "#ffffff",
    scrollX: 0,
    scrollY: 0
  })

  .then(function (canvas) {

    var imagen = canvas.toDataURL("image/jpeg", 0.95);

    var jsPDF = window.jspdf.jsPDF;
    var pdf = new jsPDF("p", "mm", "a4");

    var anchoPagina = 210;
    var altoPagina = 297;
    var margen = 10;

    var anchoContenido = anchoPagina - (margen * 2);
    var altoDisponible = altoPagina - (margen * 2);
    var altoContenido = canvas.height * anchoContenido / canvas.width;

    if (altoContenido > altoDisponible) {
      var escala = altoDisponible / altoContenido;
      altoContenido = altoDisponible;
      anchoContenido = anchoContenido * escala;
    }

    var posicionX = (anchoPagina - anchoContenido) / 2;

    pdf.addImage(imagen, "JPEG", posicionX, margen, anchoContenido, altoContenido);

    var idEvento = eventoOriginal.getAttribute("data-id");

    pdf.save("evento-" + idEvento + ".pdf");

    pdfContainer.remove();

    botonPDF.disabled = false;
    botonPDF.innerHTML = botonPDF.dataset.html || "Descargar evento en PDF";

  })

  .catch(function (error) {

    console.error("Error generando PDF:", error);

    pdfContainer.remove();

    botonPDF.disabled = false;
    botonPDF.innerHTML = botonPDF.dataset.html || "Descargar evento en PDF";

    alert("Ocurrió un error al generar el PDF.");

  });

}

document.addEventListener('DOMContentLoaded', function () {
  var carousel = document.getElementById('carousel-evento');
  if (!carousel) return;

  var track = carousel.querySelector('.carousel-track');
  var slides = carousel.querySelectorAll('.carousel-slide');
  var btnPrev = carousel.querySelector('.carousel-prev');
  var btnNext = carousel.querySelector('.carousel-next');
  var dotsBox = carousel.querySelector('.carousel-dots');
  var contador = document.querySelector('#carousel-count b');
  if (slides.length < 2) {
    btnPrev.remove();
    btnNext.remove();
    dotsBox.remove();
    return;
  }

  var actual = 0;

  slides.forEach(function (_, i) {
    var dot = document.createElement('button');
    dot.type = 'button';
    dot.className = 'carousel-dot';
    dot.setAttribute('aria-label', 'Ir a la imagen ' + (i + 1));
    dot.addEventListener('click', function () { ir(i); });
    dotsBox.appendChild(dot);
  });

  var dots = dotsBox.querySelectorAll('.carousel-dot');

  function ir(n) {
  actual = (n + slides.length) % slides.length;
  track.style.transform = 'translateX(-' + (actual * 100) + '%)';
  dots.forEach(function (d, i) {
    d.classList.toggle('active', i === actual);
  });
  if (contador) contador.textContent = (actual + 1) + ' / ' + slides.length;
  }

  btnPrev.addEventListener('click', function () { ir(actual - 1); });
  btnNext.addEventListener('click', function () { ir(actual + 1); });

  var inicioX = 0;
  carousel.addEventListener('touchstart', function (e) {
    inicioX = e.touches[0].clientX;
  }, { passive: true });
  carousel.addEventListener('touchend', function (e) {
    var diff = e.changedTouches[0].clientX - inicioX;
    if (Math.abs(diff) > 40) ir(diff < 0 ? actual + 1 : actual - 1);
  });

  ir(0);
});


document.addEventListener('DOMContentLoaded', function () {


  var notice = document.getElementById('notice');
  var noticeTimer;

  function notify(text) {
    if (!notice) return;
    notice.textContent = text;
    notice.hidden = false;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(function () { notice.hidden = true; }, 3500);
  }


  function tituloEvento() {
    var t = document.querySelector('.detail-title');
    return t ? t.textContent.trim() : document.title;
  }

  function copiarFallback() {
    var ta = document.createElement('textarea');
    ta.value = location.href;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) {}
    ta.remove();
    return ok;
  }

  function copyLink() {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(location.href).then(
        function () { notify('Enlace copiado'); },
        function () { notify(copiarFallback() ? 'Enlace copiado' : 'No se pudo copiar el enlace. Copialo desde la barra de direcciones.'); }
      );
    } else {
      notify(copiarFallback() ? 'Enlace copiado' : 'No se pudo copiar el enlace. Copialo desde la barra de direcciones.');
    }
  }

  var btnCopiar = document.getElementById('btn-copiar-enlace');
  var btnWsp = document.getElementById('btn-whatsapp');
  var btnShare = document.getElementById('btn-compartir');

  if (btnCopiar) btnCopiar.addEventListener('click', copyLink);

  if (btnWsp) btnWsp.addEventListener('click', function () {
    window.open(
      'https://wa.me/?text=' + encodeURIComponent(tituloEvento() + ' ' + location.href),
      '_blank',
      'noopener,noreferrer'
    );
  });

  if (btnShare) btnShare.addEventListener('click', function () {
    if (navigator.share) {
      navigator.share({ title: tituloEvento(), url: location.href }).catch(function () {});
    } else {
      copyLink();
    }
  });



  var list = document.getElementById('comment-list');
  var draft = document.getElementById('comment-draft');
  var publish = document.getElementById('comment-publish');
  var sortSelect = document.getElementById('comment-sort');
  var countEl = document.getElementById('comment-count');
  var meAvatar = document.getElementById('comment-me');
  if (!list || !draft || !publish || !sortSelect) return;

  var comments = [];

  var liked = new Set();
  var replying = null; // { id, to }

  function esc(v) {
    return String(v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function nombreUsuario() {
    var el = document.querySelector('.user-profile span');
    var t = el ? el.textContent.trim() : '';
    return t || 'Vos';
  }

  function iniciales(nombre) {
    var p = nombre.split(/\s+/).filter(Boolean);
    return ((p[0] ? p[0][0] : 'V') + (p[1] ? p[1][0] : '')).toUpperCase();
  }

  var yo = nombreUsuario();
  if (meAvatar) meAvatar.textContent = iniciales(yo);

  function avatarHTML(a, tone) {
    var cls = a.organizer ? 'org' : (tone || '');
    return '<div class="cmt-avatar ' + cls + '">' + esc(a.initials) + '</div>';
  }

  function headHTML(a, tone) {
    return '<div class="cmt-top">' + avatarHTML(a, tone) +
      '<div><div class="cmt-author">' + esc(a.author) +
      (a.organizer ? '<span class="cmt-badge">Organizador</span>' : '') +
      '</div><div class="cmt-time">' + esc(a.time) + '</div></div></div>';
  }

  function commentHTML(c) {
    var likes = c.likes + (liked.has(c.id) ? 1 : 0);
    var isLiked = liked.has(c.id);

    var answers = c.answers.length
      ? '<div class="cmt-replies">' + c.answers.map(function (a) {
          return '<div class="cmt-reply">' +
            '<div class="cmt-reply-label"><i class="fa-solid fa-reply" aria-hidden="true"></i> En respuesta a <strong>' + esc(a.to) + '</strong></div>' +
            headHTML(a, c.tone) +
            '<p class="cmt-text">' + esc(a.text) + '</p>' +
            '<div class="cmt-actions"><button type="button" data-action="reply" data-id="' + c.id + '" data-to="' + esc(a.author) + '">' +
            '<i class="fa-regular fa-comment" aria-hidden="true"></i> Responder</button></div>' +
            '</div>';
        }).join('') + '</div>'
      : '';

    var composer = (replying && replying.id === c.id)
      ? '<div class="cmt-composer cmt-reply-composer">' +
          '<div class="cmt-reply-label">Respondiendo a <strong>' + esc(replying.to) + '</strong>' +
          '<button type="button" class="cmt-cancel" data-action="cancel" aria-label="Cancelar respuesta">×</button></div>' +
          '<textarea id="reply-draft" aria-label="Respuesta a ' + esc(replying.to) + '" placeholder="Escribí tu respuesta…"></textarea>' +
          '<div class="cmt-composer-bottom"><span>Tu respuesta queda en esta conversación.</span>' +
          '<button type="button" data-action="send-reply" disabled>Responder</button></div>' +
        '</div>'
      : '';

    return '<article class="cmt-item" id="comment-' + c.id + '">' +
      headHTML(c, c.tone) +
      '<p class="cmt-text">' + esc(c.text) + '</p>' +
      '<div class="cmt-actions">' +
        '<button type="button" data-action="like" data-id="' + c.id + '" aria-pressed="' + isLiked + '" aria-label="Me gusta el comentario de ' + esc(c.author) + '">' +
          '<i class="' + (isLiked ? 'fa-solid' : 'fa-regular') + ' fa-thumbs-up" aria-hidden="true"></i> ' + likes + '</button>' +
        '<button type="button" data-action="reply" data-id="' + c.id + '" data-to="' + esc(c.author) + '">' +
          '<i class="fa-regular fa-comment" aria-hidden="true"></i> Responder</button>' +
        (c.answers.length
          ? '<span class="cmt-time">' + c.answers.length + ' ' + (c.answers.length === 1 ? 'respuesta' : 'respuestas') + '</span>'
          : '') +
      '</div>' +
      answers +
      composer +
    '</article>';
  }

  function render() {
    var prev = document.getElementById('reply-draft');
    var prevText = prev ? prev.value : '';

    var sort = sortSelect.value;
    var ordered = comments.slice().sort(function (a, b) {
      if (sort === 'popular') {
        return (b.likes + (liked.has(b.id) ? 1 : 0)) - (a.likes + (liked.has(a.id) ? 1 : 0));
      }
      if (sort === 'oldest') return a.id - b.id;
      return b.id - a.id;
    });

    list.innerHTML = ordered.length
      ? ordered.map(commentHTML).join('')
      : '<p class="cmt-empty">Todavía no hay comentarios. ¡Escribí el primero!</p>';

    if (countEl) {
      countEl.textContent = comments.reduce(function (n, c) { return n + 1 + c.answers.length; }, 0);
    }

    if (replying) {
      var t = document.getElementById('reply-draft');
      if (t) {
        t.value = prevText;
        var send = list.querySelector('[data-action="send-reply"]');
        if (send) send.disabled = !prevText.trim();
      }
    }
  }

  draft.addEventListener('input', function () {
    publish.disabled = !draft.value.trim();
  });

  publish.addEventListener('click', function () {
    var text = draft.value.trim();
    if (!text) return;
    comments.push({
      id: Date.now(),
      author: yo,
      initials: iniciales(yo),
      tone: '',
      text: text,
      time: 'Ahora',
      likes: 0,
      answers: []
    });
    draft.value = '';
    publish.disabled = true;
    render();
  });

  sortSelect.addEventListener('change', render);

  list.addEventListener('input', function (e) {
    if (e.target.id === 'reply-draft') {
      var send = list.querySelector('[data-action="send-reply"]');
      if (send) send.disabled = !e.target.value.trim();
    }
  });

  list.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-action]');
    if (!b) return;

    var id = Number(b.dataset.id);
    var action = b.dataset.action;

    if (action === 'like') {
      if (liked.has(id)) liked.delete(id); else liked.add(id);
      render();
    }

    if (action === 'reply') {
      var old = document.getElementById('reply-draft');
      if (old) old.value = ''; 
      replying = { id: id, to: b.dataset.to };
      render();
      var nuevo = document.getElementById('reply-draft');
      if (nuevo) nuevo.focus();
    }

    if (action === 'cancel') {
      replying = null;
      render();
    }

    if (action === 'send-reply') {
      var area = document.getElementById('reply-draft');
      var text = area ? area.value.trim() : '';
      var c = comments.find(function (x) { return replying && x.id === replying.id; });
      if (!text || !c) return;
      c.answers.push({
        id: Date.now(),
        author: yo,
        initials: iniciales(yo),
        text: text,
        to: replying.to,
        time: 'Ahora'
      });
      replying = null;
      render();
    }
  });

  render();
});