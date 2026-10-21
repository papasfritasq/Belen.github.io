(function () {
  var pass = document.getElementById('txt_pass');
  var pass2 = document.getElementById('txt_pass2');
  var form = document.getElementById('form-nueva-contra');
  var reglaLargo = document.getElementById('regla-largo');
  var reglaIgual = document.getElementById('regla-igual');
  var msgError = document.getElementById('msg__error');

  document.querySelectorAll('.toggle-pass').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.target);
      var visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
      btn.firstElementChild.className = visible ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
    });
  });

  function revisar() {
    var largoOk = pass.value.length >= 8;
    var igualOk = pass2.value !== '' && pass.value === pass2.value;
    reglaLargo.classList.toggle('cumple', largoOk);
    reglaIgual.classList.toggle('cumple', igualOk);
    return largoOk && igualOk;
  }

  pass.addEventListener('input', revisar);
  pass2.addEventListener('input', revisar);

  form.addEventListener('submit', function (e) {
    if (!revisar()) {
      e.preventDefault();
      msgError.textContent = pass.value.length < 8
        ? 'La contraseña debe tener al menos 8 caracteres.'
        : 'Las contraseñas no coinciden.';
    }
  });
})();