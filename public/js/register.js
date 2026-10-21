function toggleDropdown() {
  const container = document.getElementById('accountTypeSelect');
  container.classList.toggle('open');
}

function selectRole(roleValue, displayText) {
  document.getElementById('txt_tipo_cuenta').value = roleValue;

  const selectedText = document.getElementById('selectedText');
  const triggerIcon = document.getElementById('triggerIcon');
  const trigger = document.getElementById('selectTrigger');
  const container = document.getElementById('accountTypeSelect');

  selectedText.innerText = displayText;
  selectedText.classList.add('selected');
  trigger.classList.add('active');
  triggerIcon.classList.add('active');

  if (roleValue === 'anfitrion') {
    triggerIcon.innerHTML = '<i class="fa-solid fa-ticket" aria-hidden="true"></i>';
  } else {
    triggerIcon.innerHTML = '<i class="fa-regular fa-user" aria-hidden="true"></i>';
  }

  document.querySelectorAll('.dropdown-option').forEach(opt => opt.classList.remove('selected'));
  document.getElementById(`opt-${roleValue}`).classList.add('selected');

  const notice = document.getElementById('organizerNotice');
  const hostFields = document.getElementById('host-fields');
  const usernameInput = document.getElementById('txt_username');
  const nameLabel = document.getElementById('name-label');

  if (roleValue === 'anfitrion') {
    notice.style.display = 'block';
    hostFields.classList.remove('hidden');
    hostFields.style.display = 'block';
    usernameInput.setAttribute('required', 'required');
    nameLabel.textContent = 'Nombre público o de organización';
  } else {
    notice.style.display = 'none';
    hostFields.classList.add('hidden');
    hostFields.style.display = 'none';
    usernameInput.removeAttribute('required');
    usernameInput.value = '';
    nameLabel.textContent = 'Nombre completo';
  }

  container.classList.remove('open');
}

document.addEventListener('click', function(e) {
  const container = document.getElementById('accountTypeSelect');
  if (container && !container.contains(e.target)) {
    container.classList.remove('open');
  }
});

function togglePasswordVisibility() {
  const passwordInput = document.getElementById('txt_password');
  const eyeIcon = document.getElementById('eye-icon');
  const mostrar = passwordInput.type === 'password';

  passwordInput.type = mostrar ? 'text' : 'password';
  eyeIcon.classList.toggle('fa-eye', !mostrar);
  eyeIcon.classList.toggle('fa-eye-slash', mostrar);
}

function validateForm() {
  const roleValue = document.getElementById('txt_tipo_cuenta').value;
  if (!roleValue) {
    mostrarToast('Elegí cómo vas a usar EventNow para continuar.', 'info');
    toggleDropdown();
    return false;
  }
  return true;
}