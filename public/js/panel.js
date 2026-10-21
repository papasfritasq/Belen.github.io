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

});


function toggleMobileMenu(btn) {
    const navbar = btn.closest('.navbar');
    const isOpen = navbar.classList.toggle('mobile-open');
    btn.setAttribute('aria-expanded', isOpen);
}

document.addEventListener('click', function (e) {
    const navbar = document.querySelector('.navbar.mobile-open');
    if (navbar && !navbar.contains(e.target)) {
        navbar.classList.remove('mobile-open');
        const btn = navbar.querySelector('.hamburger-btn');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }
});
document.addEventListener("DOMContentLoaded", function () {
 
    var resultados = document.getElementById("resultados");
    var contador = document.getElementById("contador-eventos");
 
    if (!resultados || !contador) {
        return;
    }
 
    function actualizarContador() {
        var cantidad = resultados.querySelectorAll(".evento").length;
 
        contador.textContent =
            (cantidad < 10 ? "0" : "") + cantidad +
            (cantidad === 1 ? " evento" : " eventos");
    }
 
    actualizarContador();
 
    new MutationObserver(actualizarContador).observe(resultados, {
        childList: true,
        subtree: true
    });
});