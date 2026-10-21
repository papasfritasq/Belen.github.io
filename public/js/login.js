function togglePasswordVisibility() {
  const passwordInput = document.getElementById('txt_password');
  const eyeIcon = document.getElementById('eye-icon');
  const mostrar = passwordInput.type === 'password';

  passwordInput.type = mostrar ? 'text' : 'password';
  eyeIcon.classList.toggle('fa-eye', !mostrar);
  eyeIcon.classList.toggle('fa-eye-slash', mostrar);
}