let formularioActual = null;
let filtroActual = { tipo: 'todos', valor: '' };

function mostrarToast(mensaje, tipo = 'exito') {
    const cont = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = 'toast toast-' + tipo;
    t.textContent = mensaje;
    cont.appendChild(t);
    setTimeout(() => t.classList.add('toast-salir'), 3000);
    setTimeout(() => t.remove(), 3400);
}

function solicitarPassword(form, mensaje) {
    formularioActual = form;
    document.getElementById('modalMensaje').innerText = mensaje;
    document.getElementById('adminPassInput').value = '';
    document.getElementById('modalPassword').style.display = 'flex';
    document.getElementById('adminPassInput').focus();
    return false;
}

function cerrarModal() {
    document.getElementById('modalPassword').style.display = 'none';
    formularioActual = null;
}

function enviarFormulario() {
    const pass = document.getElementById('adminPassInput').value.trim();
    if (pass === "") {
        mostrarToast('Por favor, ingresá la contraseña.', 'error');
        return;
    }
    if (formularioActual) {
        formularioActual.querySelector('input[name="admin_pass"]').value = pass;
        const formToSubmit = formularioActual;
        cerrarModal();
        enviarAjax(formToSubmit);
    }
}

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form.classList.contains('form-ajax') || e.defaultPrevented) return;
    e.preventDefault();
    enviarAjax(form);
});

async function enviarAjax(form) {
    const boton = form.querySelector('button[type="submit"]');
    if (boton) boton.disabled = true;

    try {
        const resp = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'fetch' }
        });
        const data = await resp.json();

        mostrarToast(data.msg, data.ok ? 'exito' : 'error');
        if (data.ok) aplicarCambio(form, data);
    } catch (err) {
        mostrarToast('No se pudo completar la acción. Probá de nuevo.', 'error');
    } finally {
        if (boton) boton.disabled = false;
    }
}

function aplicarCambio(form, data) {
    const fila = form.closest('tr');

    if (data.accion === 'aprobar' || data.accion === 'rechazar') {
        fila.classList.add('fila-saliendo');
        setTimeout(() => {
            fila.remove();
            actualizarPendientes();
        }, 300);
        return;
    }

    if (data.accion === 'toggle_desactivar') {
        const desactivado = data.estado === 'desactivado';
        const badge = fila.querySelector('.badge-estado');
        const btn = form.querySelector('button');

        fila.dataset.estado = data.estado;

        badge.textContent = data.estado;
        badge.style.background = desactivado ? 'var(--danger-soft)' : 'var(--success-soft)';
        badge.style.color = desactivado ? 'var(--danger)' : 'var(--success)';

        btn.textContent = desactivado ? 'Activar' : 'Desactivar';
        btn.style.cssText = desactivado ? 'background-color: var(--success); color: var(--primary-foreground);' : '';

        actualizarEstadisticas();
        aplicarFiltros(); // por si la fila ya no cumple el filtro activo
    }
}

function actualizarPendientes() {
    const tbody = document.getElementById('tbodyPendientes');
    const cantidad = tbody.querySelectorAll('tr.fila-pendiente').length;

    document.getElementById('contadorPendientes').textContent = cantidad + ' por revisar';

    if (cantidad === 0) {
        tbody.innerHTML = "<tr><td colspan='5' style='text-align:center;'>No hay eventos pendientes de revisión.</td></tr>";
    }

    actualizarEstadisticas();
}

function dosDigitos(n) {
    return String(n).padStart(2, '0');
}

function actualizarEstadisticas() {
    const filas = [...document.querySelectorAll('.fila-usuario')];
    const pendientes = document.querySelectorAll('#tbodyPendientes tr.fila-pendiente').length;

    const total = filas.length;
    const anfitriones = filas.filter(f => f.dataset.rol === 'anfitrion').length;
    const activas = filas.filter(f => f.dataset.estado !== 'desactivado').length;

    const poner = (id, valor) => {
        const el = document.getElementById(id);
        if (el) el.textContent = valor;
    };

    poner('statPendientes', dosDigitos(pendientes));
    poner('statUsuarios', dosDigitos(total));
    poner('statAnfitriones', dosDigitos(anfitriones));
    poner('statActivas', dosDigitos(activas));
    poner('contadorUsuarios', total + (total === 1 ? ' usuario' : ' usuarios'));
}

function aplicarFiltros() {
    const texto = document.getElementById('buscadorUsuarios').value.toLowerCase();
    let visibles = 0;
    let total = 0;

    document.querySelectorAll('.fila-usuario').forEach(fila => {
        const id = fila.cells[0].innerText.toLowerCase();
        const email = fila.cells[1].innerText.toLowerCase();
        const coincideTexto = id.includes(texto) || email.includes(texto);

        let coincideFiltro = true;
        if (filtroActual.tipo === 'rol') {
            coincideFiltro = fila.dataset.rol === filtroActual.valor;
        } else if (filtroActual.tipo === 'estado') {
            coincideFiltro = fila.dataset.estado === filtroActual.valor;
        }

        const mostrar = coincideTexto && coincideFiltro;
        fila.style.display = mostrar ? '' : 'none';
        total++;
        if (mostrar) visibles++;
    });

    const pie = document.getElementById('contadorFiltrados');
    if (pie) pie.textContent = 'Mostrando ' + visibles + ' de ' + total + ' usuarios';
}

function buscarUsuarioTexto() {
    aplicarFiltros();
}

function filtrarUsuarios(filtroTipo, valor, elemento) {
    const contenedor = elemento.closest('.filtros-roles-container');
    contenedor.querySelectorAll('.filtro-card').forEach(btn => btn.classList.remove('active'));
    elemento.classList.add('active');

    filtroActual = { tipo: filtroTipo, valor: valor };
    aplicarFiltros();
}

window.addEventListener('pageshow', () => {
    const buscador = document.getElementById('buscadorUsuarios');
    if (buscador) buscador.value = '';
});

actualizarEstadisticas();
aplicarFiltros();