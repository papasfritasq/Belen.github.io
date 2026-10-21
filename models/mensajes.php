<?php
require_once __DIR__ . "/DBAbstract.php";

/**
 * Busca o crea un chat.
 * - Con $id_evento: chat ligado a ese evento (cliente + anfitrión).
 * - Con $id_evento === null: chat directo entre dos usuarios (desde el perfil).
 *   En ese caso se busca en ambos sentidos para no duplicar la conversación.
 */
function obtenerOCrearChat($id_evento, $id_cliente, $id_anfitrion) {
    $db = dbConnect();

    if ($id_evento === null) {
        $sql = "SELECT id_chat, id_cliente, id_anfitrion, estado_cliente, estado_anfitrion
                FROM chats
                WHERE id_evento IS NULL
                  AND ((id_cliente = ? AND id_anfitrion = ?) OR (id_cliente = ? AND id_anfitrion = ?))
                LIMIT 1";
        $stmt = mysqli_prepare($db, $sql);
        mysqli_stmt_bind_param($stmt, "iiii", $id_cliente, $id_anfitrion, $id_anfitrion, $id_cliente);
    } else {
        $sql = "SELECT id_chat, id_cliente, id_anfitrion, estado_cliente, estado_anfitrion
                FROM chats WHERE id_evento = ? AND id_cliente = ? AND id_anfitrion = ?";
        $stmt = mysqli_prepare($db, $sql);
        mysqli_stmt_bind_param($stmt, "iii", $id_evento, $id_cliente, $id_anfitrion);
    }
    mysqli_stmt_execute($stmt);
    $chat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($chat) {
        $id_chat = (int) $chat['id_chat'];
        $yo = (int) $_SESSION['id_usuario'];

        $reactivar = ($chat['estado_cliente'] === 'eliminado' && (int) $chat['id_cliente'] === $yo)
                  || ($chat['estado_anfitrion'] === 'eliminado' && (int) $chat['id_anfitrion'] === $yo);

        if ($reactivar) {
            $stmtUp = mysqli_prepare($db, "UPDATE chats SET
                    estado_cliente   = IF(id_cliente = ?, 'activo', estado_cliente),
                    estado_anfitrion = IF(id_anfitrion = ?, 'activo', estado_anfitrion)
                WHERE id_chat = ?");
            mysqli_stmt_bind_param($stmtUp, "iii", $yo, $yo, $id_chat);
            mysqli_stmt_execute($stmtUp);
            mysqli_stmt_close($stmtUp);
        }
        return $id_chat;
    }

    $sqlIns = "INSERT INTO chats (id_evento, id_cliente, id_anfitrion, estado_cliente, estado_anfitrion)
               VALUES (?, ?, ?, 'activo', 'activo')";
    $stmtIns = mysqli_prepare($db, $sqlIns);
    mysqli_stmt_bind_param($stmtIns, "iii", $id_evento, $id_cliente, $id_anfitrion);
    mysqli_stmt_execute($stmtIns);
    $id_chat = mysqli_insert_id($db);
    mysqli_stmt_close($stmtIns);
    return $id_chat;
}

function enviarMensaje($id_chat, $id_remitente, $texto) {
    $db = dbConnect();
    $sql = "INSERT INTO mensajes (id_chat, id_remitente, mensaje, leido) VALUES (?, ?, ?, 0)";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "iis", $id_chat, $id_remitente, $texto);
    $exito = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($exito) {
        notificarMensaje($id_chat, $id_remitente);
    }
    return $exito;
}

/**
 * Trae los chats del usuario, con la cantidad de mensajes no leídos
 * de CADA chat (para mostrar el badge estilo WhatsApp en la lista).
 * LEFT JOIN con eventos para no perder los chats directos (sin evento).
 */
function obtenerChatsUsuario($id_usuario) {
    $db = dbConnect();
    $sql = "SELECT c.*, e.titulo AS nombre_evento,
                   u.display_name AS nombre_otro, u.id_usuario AS id_otro,
                   (SELECT COUNT(*) FROM mensajes m
                    WHERE m.id_chat = c.id_chat
                      AND m.id_remitente != ?
                      AND m.leido = 0) AS no_leidos,
                   (SELECT MAX(m2.fecha_envio) FROM mensajes m2
                    WHERE m2.id_chat = c.id_chat) AS ultimo_mensaje_fecha
            FROM chats c
            LEFT JOIN eventos e ON c.id_evento = e.id_evento
            JOIN users u ON u.id_usuario = IF(c.id_cliente = ?, c.id_anfitrion, c.id_cliente)
            WHERE (c.id_cliente = ? AND c.estado_cliente = 'activo')
               OR (c.id_anfitrion = ? AND c.estado_anfitrion = 'activo')
            ORDER BY COALESCE(
                         (SELECT MAX(m3.fecha_envio) FROM mensajes m3 WHERE m3.id_chat = c.id_chat),
                         c.fecha_creacion
                     ) DESC,
                     c.id_chat DESC";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "iiii", $id_usuario, $id_usuario, $id_usuario, $id_usuario);
    mysqli_stmt_execute($stmt);
    $chats = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $chats;
}

function obtenerMensajesChat($id_chat) {
    $db = dbConnect();
    $sql = "SELECT * FROM mensajes WHERE id_chat = ? ORDER BY fecha_envio ASC";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_chat);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $mensajes = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $mensajes;
}

/**
 * Cantidad total de mensajes no leídos del usuario (todos sus chats activos).
 * Usada para el badge numérico en el panel (tipo WhatsApp).
 */
function contarMensajesNoLeidos($id_usuario, $rol) {
    $db = dbConnect();
    if ($rol === 'anfitrion' || $rol === 'admin') {
        $sql = "SELECT COUNT(m.id_mensaje) AS total FROM mensajes m 
                JOIN chats c ON m.id_chat = c.id_chat 
                WHERE c.id_anfitrion = ? AND c.estado_anfitrion = 'activo' AND m.id_remitente != ? AND m.leido = 0";
    } else {
        $sql = "SELECT COUNT(m.id_mensaje) AS total FROM mensajes m 
                JOIN chats c ON m.id_chat = c.id_chat 
                WHERE c.id_cliente = ? AND c.estado_cliente = 'activo' AND m.id_remitente != ? AND m.leido = 0";
    }
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id_usuario, $id_usuario);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return (int) ($row['total'] ?? 0);
}

function tieneMensajesNoLeidos($id_usuario, $rol) {
    return contarMensajesNoLeidos($id_usuario, $rol) > 0;
}

function marcarMensajesComoLeidos($id_chat, $id_usuario) {
    $db = dbConnect();
    $sql = "UPDATE mensajes SET leido = 1 WHERE id_chat = ? AND id_remitente != ?";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id_chat, $id_usuario);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $link = "index.php?slug=mensajes&chat=" . (int) $id_chat;
    $stmt = mysqli_prepare($db, "UPDATE notificaciones SET leido = 1 WHERE id_usuario = ? AND link = ?");
    mysqli_stmt_bind_param($stmt, "is", $id_usuario, $link);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function eliminarChatParaMi($id_chat, $id_usuario) {
    $db = dbConnect();
    $sql = "UPDATE chats SET
                estado_cliente   = IF(id_cliente = ?, 'eliminado', estado_cliente),
                estado_anfitrion = IF(id_anfitrion = ?, 'eliminado', estado_anfitrion)
            WHERE id_chat = ? AND (id_cliente = ? OR id_anfitrion = ?)";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "iiiii", $id_usuario, $id_usuario, $id_chat, $id_usuario, $id_usuario);
    $res = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $res;
}

function usuarioPerteneceAChat($id_chat, $id_usuario) {
    $db = dbConnect();
    $stmt = mysqli_prepare($db, "SELECT 1 FROM chats WHERE id_chat = ? AND (id_cliente = ? OR id_anfitrion = ?)");
    mysqli_stmt_bind_param($stmt, "iii", $id_chat, $id_usuario, $id_usuario);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
    mysqli_stmt_close($stmt);
    return $ok;
}

function usuarioExiste($id_usuario) {
    $db = dbConnect();
    $stmt = mysqli_prepare($db, "SELECT 1 FROM users WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_usuario);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
    mysqli_stmt_close($stmt);
    return $ok;
}

function obtenerAnfitrionDeEvento($id_evento) {
    $db = dbConnect();
    $stmt = mysqli_prepare($db, "SELECT id_usuario FROM eventos WHERE id_evento = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_evento);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ? (int) $row['id_usuario'] : null;
}

function notificarMensaje($id_chat, $id_remitente) {
    $db = dbConnect();

    $sql = "SELECT c.id_cliente, c.id_anfitrion, e.titulo, u.display_name
            FROM chats c
            LEFT JOIN eventos e ON e.id_evento = c.id_evento
            JOIN users u ON u.id_usuario = ?
            WHERE c.id_chat = ?";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id_remitente, $id_chat);
    mysqli_stmt_execute($stmt);
    $chat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$chat) return;

    $esCliente = ((int) $chat['id_cliente'] === (int) $id_remitente);
    $destinatario = $esCliente ? (int) $chat['id_anfitrion'] : (int) $chat['id_cliente'];

    if ($chat['titulo'] === null) {
        $texto = $esCliente
            ? $chat['display_name'] . " te mandó un mensaje."
            : $chat['display_name'] . " te contestó.";
    } elseif ($esCliente) {
        $texto = $chat['display_name'] . " te mandó un mensaje del evento \"" . $chat['titulo'] . "\".";
    } else {
        $texto = $chat['display_name'] . " del evento \"" . $chat['titulo'] . "\" te contestó.";
    }

    $link = "index.php?slug=mensajes&chat=" . (int) $id_chat;

    $stmt = mysqli_prepare($db, "SELECT id_notificacion FROM notificaciones WHERE id_usuario = ? AND link = ? AND leido = 0 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "is", $destinatario, $link);
    mysqli_stmt_execute($stmt);
    $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($existente) {
        $stmt = mysqli_prepare($db, "UPDATE notificaciones SET mensaje = ?, fechaRegistro = NOW() WHERE id_notificacion = ?");
        mysqli_stmt_bind_param($stmt, "si", $texto, $existente['id_notificacion']);
    } else {
        $stmt = mysqli_prepare($db, "INSERT INTO notificaciones (id_usuario, mensaje, link, leido, fechaRegistro) VALUES (?, ?, ?, 0, NOW())");
        mysqli_stmt_bind_param($stmt, "iss", $destinatario, $texto, $link);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}