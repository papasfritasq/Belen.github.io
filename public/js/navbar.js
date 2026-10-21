function toggleNotificaciones(wrapper) {
    wrapper.classList.toggle("active");

    if (wrapper.classList.contains("active")) {
        marcarNotificacionesVistas();
    }
}

function marcarNotificacionesVistas() {
    var badge = document.querySelector(".badge-count");
    if (badge) {
        badge.style.display = "none";
    }

    fetch('index.php?slug=notificacionesLeidas', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    }).catch(function (error) {
        console.error('No se pudieron marcar las notificaciones como leídas:', error);
    });
}



document.addEventListener("click", function (evento) {

    var notifWrapper = document.querySelector(".notification-wrapper");
    if (notifWrapper && !notifWrapper.contains(evento.target)) {
        notifWrapper.classList.remove("active");
    }

    var menuUsuario = document.querySelector(".user-menu-wrapper");
    if (menuUsuario && !menuUsuario.contains(evento.target)) {
        menuUsuario.classList.remove("active");
    }

    var navbarAbierto = document.querySelector(".navbar.mobile-open");
    if (navbarAbierto && !navbarAbierto.contains(evento.target)) {
        navbarAbierto.classList.remove("mobile-open");
        var btn = navbarAbierto.querySelector(".hamburger-btn");
        if (btn) btn.setAttribute("aria-expanded", "false");
    }

});


function toggleMobileMenu(btn) {
    var navbar = btn.closest(".navbar");
    var isOpen = navbar.classList.toggle("mobile-open");
    btn.setAttribute("aria-expanded", isOpen);
}

document.addEventListener('click', function (e) {
    var salir = e.target.closest('.dropdown-user-logout');
    if (!salir || e.defaultPrevented) return;

    [localStorage, sessionStorage].forEach(function (almacen) {
        Object.keys(almacen).forEach(function (clave) {
            if (clave.toLowerCase().indexOf('bp') === 0) {
                almacen.removeItem(clave);
            }
        });
    });
});

function abrirModalEliminar() {
    var modal = document.getElementById('modal-eliminar');
    if (modal) modal.hidden = false;
}

function cerrarModalEliminar() {
    var modal = document.getElementById('modal-eliminar');
    if (modal) modal.hidden = true;
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') cerrarModalEliminar();
});