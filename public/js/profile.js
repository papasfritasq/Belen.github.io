

document.addEventListener('DOMContentLoaded', function () {

  var select = document.getElementById('perfil-orden');
  var grilla = document.getElementById('perfil-grid');

  if (!select || !grilla) return;

  function ordenar() {
    var tarjetas = Array.prototype.slice.call(grilla.querySelectorAll('.perfil-card'));
    var descendente = select.value === 'desc';

    tarjetas.sort(function (a, b) {
      var diferencia = Number(a.dataset.fecha) - Number(b.dataset.fecha);
      return descendente ? -diferencia : diferencia;
    });

    tarjetas.forEach(function (tarjeta) {
      grilla.appendChild(tarjeta);
    });
  }

  select.addEventListener('change', ordenar);
});