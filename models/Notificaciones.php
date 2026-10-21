<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/DBAbstract.php";

function cargarNavbarComun($tpl, $id_usuario, $rolUsuario) {
    $usuario = getUserById($id_usuario);

    $notificaciones = getNotificacionesUsuario($id_usuario);
    $cantNoLeidas = contarNotificacionesNoLeidas($id_usuario);

    $htmlNotificaciones = "";
    if (empty($notificaciones)) {
        $htmlNotificaciones = "<li><div><p>No tenés notificaciones nuevas.</p></div></li>";
    } else {
        foreach ($notificaciones as $n) {
            $esNueva = isset($n['leido']) && (int) $n['leido'] === 0;
            $claseLi = $esNueva ? 'notif-nueva' : '';
            $puntoHtml = $esNueva ? "<span class='punto-nuevo' aria-label='No leída'></span>" : "";

            $contenido = "<div>
                <p>" . htmlspecialchars($n['mensaje']) . "</p>
                <small>" . htmlspecialchars($n['fechaRegistro']) . "</small>
            </div>";

            if (!empty($n['link'])) {
                $contenido = "<a href='" . htmlspecialchars($n['link'], ENT_QUOTES, 'UTF-8') . "' style='color:inherit; text-decoration:none; display:block;'>{$contenido}</a>";
            }

            $htmlNotificaciones .= "<li class='{$claseLi}'>{$puntoHtml}{$contenido}</li>";
        }
    }

    $perfilSlug = 'panel';
    if ($rolUsuario === 'visitante') {
        $perfilSlug = 'panelVisitante';
    } elseif ($rolUsuario === 'admin') {
        $perfilSlug = 'panelAdmin';
    }

        $rolClase = '';
    if ($rolUsuario === 'visitante') {
        $rolClase = 'es-visitante';
    } elseif ($rolUsuario === 'anfitrion') {
        $rolClase = 'es-anfitrion';
    } elseif ($rolUsuario === 'admin') {
        $rolClase = 'es-admin';
    }

    $tpl = setVarTPL("USUARIO_NOMBRE", htmlspecialchars($usuario['display_name'] ?? '', ENT_QUOTES, 'UTF-8'), $tpl);
    $tpl = setVarTPL("PERFIL_SLUG", htmlspecialchars($perfilSlug, ENT_QUOTES, 'UTF-8'), $tpl);
    $tpl = setVarTPL("ROL_CLASE", $rolClase, $tpl);
    $tpl = setVarTPL("BADGE_NOTIFICACIONES", $cantNoLeidas > 0 ? $cantNoLeidas : '', $tpl);
    $tpl = setVarTPL("BADGE_CLASE", $cantNoLeidas > 0 ? '' : 'oculto', $tpl);
    $tpl = setVarTPL("LISTA_NOTIFICACIONES", $htmlNotificaciones, $tpl);

    return $tpl;
}