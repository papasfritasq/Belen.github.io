document.addEventListener("DOMContentLoaded", function () {
    var caja = document.querySelector(".chat-messages-box");
    var form = document.querySelector(".chat-input-wrapper");

    function iniciales(nombre) {
        var palabras = (nombre || "").trim().split(/\s+/).filter(Boolean);
        if (palabras.length === 0) return "?";
        var primera = palabras[0].charAt(0);
        var segunda = palabras.length > 1 ? palabras[1].charAt(0) : "";
        return (primera + segunda).toUpperCase();
    }

    function crearAvatar(nombre) {
        var avatar = document.createElement("span");
        avatar.className = "chat-avatar";
        avatar.setAttribute("aria-hidden", "true");
        avatar.textContent = iniciales(nombre);
        return avatar;
    }

    document.querySelectorAll(".chat-item-link").forEach(function (item) {
        if (item.querySelector(".chat-avatar")) return;
        var titulo = item.querySelector(".chat-item-info strong");
        item.insertBefore(crearAvatar(titulo ? titulo.textContent : ""), item.firstChild);
    });

    var infoDestinatario = document.querySelector(".chat-recipient-info");
    if (infoDestinatario && !infoDestinatario.parentNode.classList.contains("chat-recipient-wrap")) {
        var nombreDestinatario = infoDestinatario.querySelector("h3");
        var envoltorio = document.createElement("div");
        envoltorio.className = "chat-recipient-wrap";
        infoDestinatario.parentNode.insertBefore(envoltorio, infoDestinatario);
        envoltorio.appendChild(crearAvatar(nombreDestinatario ? nombreDestinatario.textContent : ""));
        envoltorio.appendChild(infoDestinatario);
    }

    var buscador = document.getElementById("buscar-conversacion");
    var lista = document.querySelector(".chat-list-items");

    if (buscador && lista) {
        var sinResultados = document.createElement("div");
        sinResultados.className = "chat-empty-list";
        sinResultados.textContent = "No encontramos conversaciones con ese nombre.";
        sinResultados.hidden = true;
        lista.appendChild(sinResultados);

        buscador.addEventListener("input", function () {
            var q = buscador.value.trim().toLowerCase();
            var visibles = 0;

            lista.querySelectorAll(".chat-item-link").forEach(function (item) {
                var coincide = q === "" || item.textContent.toLowerCase().indexOf(q) !== -1;
                item.style.display = coincide ? "" : "none";
                if (coincide) visibles++;
            });

            sinResultados.hidden = visibles > 0;
        });
    }

    function cerrarDesplegables() {
        document.querySelectorAll(".chat-select.open").forEach(function (el) {
            el.classList.remove("open");
            var t = el.querySelector(".chat-select-trigger");
            if (t) t.setAttribute("aria-expanded", "false");
        });
    }

    document.querySelectorAll("select.chat-filter-select").forEach(function (select) {
        if (!select.options.length) return;

        var wrapper = document.createElement("div");
        wrapper.className = "chat-select";

        var trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "chat-select-trigger";
        trigger.setAttribute("aria-haspopup", "listbox");
        trigger.setAttribute("aria-expanded", "false");
        trigger.textContent = select.options[Math.max(select.selectedIndex, 0)].text;

        var panel = document.createElement("div");
        panel.className = "chat-select-panel";
        panel.setAttribute("role", "listbox");

        Array.prototype.forEach.call(select.options, function (option, index) {
            var item = document.createElement("div");
            var activa = index === select.selectedIndex;
            item.className = "chat-select-option" + (activa ? " selected" : "");
            item.setAttribute("role", "option");
            item.setAttribute("aria-selected", activa ? "true" : "false");

            var label = document.createElement("span");
            label.textContent = option.text;
            var check = document.createElement("span");
            check.className = "chat-select-check";

            item.appendChild(label);
            item.appendChild(check);

            item.addEventListener("click", function (e) {
                e.stopPropagation();
                select.selectedIndex = index;
                trigger.textContent = option.text;
                panel.querySelectorAll(".chat-select-option").forEach(function (el) {
                    el.classList.remove("selected");
                    el.setAttribute("aria-selected", "false");
                });
                item.classList.add("selected");
                item.setAttribute("aria-selected", "true");
                cerrarDesplegables();
                select.dispatchEvent(new Event("change", { bubbles: true }));
            });

            panel.appendChild(item);
        });

        trigger.addEventListener("click", function (e) {
            e.stopPropagation();
            var estabaAbierto = wrapper.classList.contains("open");
            cerrarDesplegables();
            if (!estabaAbierto) {
                wrapper.classList.add("open");
                trigger.setAttribute("aria-expanded", "true");
            }
        });

        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        wrapper.appendChild(trigger);
        wrapper.appendChild(panel);
    });

    document.addEventListener("click", cerrarDesplegables);
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") cerrarDesplegables();
    });


    //al abrir el chat, mostrar directamente los últimos mensajes
    function irAbajo() {
        if (caja) caja.scrollTop = caja.scrollHeight;
    }
    irAbajo();

    if (!form || !caja) return;

    var input = form.querySelector("input[name='texto_mensaje']");
    var boton = form.querySelector(".btn-send-chat");

    function agregarMensaje(texto, fecha) {
        var fila = document.createElement("div");
        fila.className = "mensaje-fila mensaje-propio";

        var burbuja = document.createElement("div");
        burbuja.className = "mensaje-burbuja mensaje-propio";

        var p = document.createElement("p");
        p.className = "mensaje-texto";
        p.textContent = texto;

        var small = document.createElement("small");
        small.className = "mensaje-fecha";
        small.textContent = fecha;

        burbuja.appendChild(p);
        burbuja.appendChild(small);
        fila.appendChild(burbuja);
        caja.appendChild(fila);
        irAbajo();
    }

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        var texto = input.value.trim();
        if (texto === "") return;

        var datos = new FormData(form);
        datos.append("enviar_mensaje", "1"); //el botón no se incluye solo en FormData

        boton.disabled = true;

        fetch(form.action, {
            method: "POST",
            headers: { "X-Requested-With": "fetch" },
            body: datos
        })
            .then(function (response) {
                if (!response.ok) throw new Error("Error HTTP " + response.status);
                return response.json();
            })
            .then(function (data) {
                if (data.exito) {
                    agregarMensaje(data.texto, data.fecha);
                    input.value = "";
                } else {
                    mostrarToast(data.mensaje || "No se pudo enviar el mensaje.", "error");
                }
            })
            .catch(function (err) {
                console.error("Error al enviar el mensaje", err);
                mostrarToast("No se pudo enviar el mensaje. Probá de nuevo.", "error");
            })
            .finally(function () {
                boton.disabled = false;
                input.focus();
            });
    });
});