function mostrarToast(mensaje, tipo = 'exito') {
    let cont = document.getElementById('toast-container');

    if (!cont) {
        cont = document.createElement('div');
        cont.id = 'toast-container';
        document.body.appendChild(cont);
    }

    const t = document.createElement('div');
    t.className = 'toast toast-' + tipo;
    t.textContent = mensaje;
    cont.appendChild(t);

    setTimeout(() => t.classList.add('toast-salir'), 3000);
    setTimeout(() => t.remove(), 2000);
}