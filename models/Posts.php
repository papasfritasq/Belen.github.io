<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/DBAbstract.php";
require_once __DIR__ . '/Landing.php';

if (!function_exists('e')) {
    function e($valor)
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}


 
function carpetaDelEvento($evento)
{
    $carpeta = dirname($evento['imagen'] ?? '');
    return ($carpeta === '.' || $carpeta === '') ? null : $carpeta;
}
 
 
// nombres de archivo (solo el nombre, sin ruta) de las imágenes de una carpeta
function listarImagenesEvento($rutaCarpeta)
{
    $lista = glob($rutaCarpeta . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];
    sort($lista);
    return array_map('basename', $lista);
}
 
 
// valida lo que mandó el formulario (imágenes a quitar + nuevas) SIN tocar el disco
function validarCambioImagenes($rutaCarpeta, $postEliminar, $filesImagenes)
{
    $MIN_IMAGENES = 2;
    $MAX_IMAGENES = 6;
    $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $limiteBytes = 5 * 1024 * 1024; // 5 MB por imagen
 
    $existentes = listarImagenesEvento($rutaCarpeta);
 
    //solo aceptamos nombres que realmente existen en la carpeta del evento
    $eliminar = [];
    foreach ((array) $postEliminar as $nombre) {
        $nombre = basename((string) $nombre);
        if (in_array($nombre, $existentes, true)) {
            $eliminar[$nombre] = true;
        }
    }
    $eliminar = array_keys($eliminar);
 
    $nuevas = [];
    if (is_array($filesImagenes) && isset($filesImagenes['name']) && is_array($filesImagenes['name'])) {
        foreach ($filesImagenes['name'] as $i => $nombreOriginal) {
            if ($filesImagenes['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($filesImagenes['error'][$i] !== UPLOAD_ERR_OK) {
                return ['error' => 'Ocurrió un error al subir una de las imágenes.', 'eliminar' => [], 'nuevas' => []];
            }
 
            $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
 
            if (!in_array($ext, $permitidas, true)) {
                return ['error' => 'Formato de archivo no permitido. Formatos válidos: ' . implode(', ', $permitidas) . '.', 'eliminar' => [], 'nuevas' => []];
            }
            if ($filesImagenes['size'][$i] > $limiteBytes) {
                return ['error' => 'Cada imagen puede pesar como máximo 5 MB.', 'eliminar' => [], 'nuevas' => []];
            }
 
            $nuevas[] = ['tmp_name' => $filesImagenes['tmp_name'][$i], 'ext' => $ext];
        }
    }
 
    $total = count($existentes) - count($eliminar) + count($nuevas);
 
    if ($total < $MIN_IMAGENES || $total > $MAX_IMAGENES) {
        return ['error' => "El evento tiene que quedar con entre {$MIN_IMAGENES} y {$MAX_IMAGENES} imágenes.", 'eliminar' => [], 'nuevas' => []];
    }
 
    return ['error' => null, 'eliminar' => $eliminar, 'nuevas' => $nuevas];
}
 
 
// aplica en disco lo que validó validarCambioImagenes
// devuelve el nombre de la imagen que queda como portada (la primera), o null si no quedó ninguna
function aplicarCambioImagenes($rutaCarpeta, $eliminar, $nuevas)
{
    foreach ($eliminar as $nombre) {
        @unlink($rutaCarpeta . basename($nombre));
    }
 
    foreach ($nuevas as $n) {
        $nombreNuevo = 'img_' . str_replace('.', '', uniqid('', true)) . '.' . $n['ext'];
        move_uploaded_file($n['tmp_name'], $rutaCarpeta . $nombreNuevo);
    }
 
    $restantes = listarImagenesEvento($rutaCarpeta);
    return $restantes[0] ?? null;
}
 
function actualizarPortadaEvento($id_evento, $nuevaPortada)
{
    $db = dbConnect();
    $stmt = $db->prepare("UPDATE eventos SET imagen = ? WHERE id_evento = ?");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("si", $nuevaPortada, $id_evento);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}
/**
 *
 * @param int   $id_usuario
 * @param array $datos      Campos simples del evento (ver claves esperadas abajo)
 * @param array $horarios   Lista de ['hora' => 'HH:MM'|null, 'actividad' => string]
 * @param array $infoExtra  Lista de ['clave' => string, 'valor' => string]
 * @return bool
 */
function crearPost(
    $id_usuario,
    array $datos,
    array $horarios = [],
    array $infoExtra = []
) {

    $db = dbConnect();
    $db->begin_transaction();


    $sql = "INSERT INTO eventos
        (
            id_usuario,
            titulo,
            descripcion,
            categoria,
            fecha,
            hora,
            imagen,
            zona,
            localidad,
            lat,
            lon,
            contacto_email,
            contacto_telefono,
            contacto_instagram,
            contacto_web,
            info_apto,
            info_duracion,
            info_capacidad,
            info_accesibilidad,
            info_mascotas,
            adicion,
            fecha_publicacion
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        $db->rollback();
        return false;
    }

    $localidad = trim($datos['localidad'] ?? '');

    $types = "i" . str_repeat("s", 8) . "dd" . str_repeat("s", 10);

    $stmt->bind_param(
        $types,
        $id_usuario,
        $datos['titulo'],
        $datos['descripcion'],
        $datos['categoria'],
        $datos['fechaEvento'],
        $datos['horaEvento'],
        $datos['nombreArchivo'],
        $datos['zona'],
        $localidad,
        $datos['lat'],
        $datos['lon'],
        $datos['contacto_email'],
        $datos['contacto_telefono'],
        $datos['contacto_instagram'],
        $datos['contacto_web'],
        $datos['info_apto'],
        $datos['info_duracion'],
        $datos['info_capacidad'],
        $datos['info_accesibilidad'],
        $datos['info_mascotas'],
        $datos['adicion']
    );

    if (!$stmt->execute()) {
        $stmt->close();
        $db->rollback();
        return false;
    }

    $id_evento = $stmt->insert_id;
    $stmt->close();


    if (!empty($horarios)) {
        $sqlHorario = "INSERT INTO evento_horario (id_evento, hora, actividad, orden)
                        VALUES (?, ?, ?, ?)";
        $stmtHorario = $db->prepare($sqlHorario);

        if (!$stmtHorario) {
            $db->rollback();
            return false;
        }

        $orden = 0;
        foreach ($horarios as $fila) {
            $hora = $fila['hora'] !== '' ? $fila['hora'] : null;
            $actividad = $fila['actividad'];
            $stmtHorario->bind_param("issi", $id_evento, $hora, $actividad, $orden);

            if (!$stmtHorario->execute()) {
                $stmtHorario->close();
                $db->rollback();
                return false;
            }
            $orden++;
        }
        $stmtHorario->close();
    }


    if (!empty($infoExtra)) {
        $sqlInfoExtra = "INSERT INTO evento_info_extra (id_evento, clave, valor, orden)
                          VALUES (?, ?, ?, ?)";
        $stmtInfoExtra = $db->prepare($sqlInfoExtra);

        if (!$stmtInfoExtra) {
            $db->rollback();
            return false;
        }

        $orden = 0;
        foreach ($infoExtra as $fila) {
            $clave = $fila['clave'];
            $valor = $fila['valor'];
            $stmtInfoExtra->bind_param("issi", $id_evento, $clave, $valor, $orden);

            if (!$stmtInfoExtra->execute()) {
                $stmtInfoExtra->close();
                $db->rollback();
                return false;
            }
            $orden++;
        }
        $stmtInfoExtra->close();
    }


    $db->commit();

    return true;
}


/**
 * Trae un evento junto con su horario e información extra, para renderizar
 * la página de detalle (detalleEvento.html).
 *
 * @param int $id_evento
 * @return array|null
 */
function obtenerPostConDetalle($id_evento)
{
    $db = dbConnect();

    $sql = "SELECT * FROM eventos WHERE id_evento = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$evento) {
        return null;
    }

    $sqlHorario = "SELECT hora, actividad FROM evento_horario
                    WHERE id_evento = ? ORDER BY orden ASC";
    $stmt = $db->prepare($sqlHorario);
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $evento['horario'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $sqlInfo = "SELECT clave, valor FROM evento_info_extra
                 WHERE id_evento = ? ORDER BY orden ASC";
    $stmt = $db->prepare($sqlInfo);
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $evento['info_extra'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $evento;
}


/**
 * Calcula los valores escalares (no-HTML) que necesita la página de detalle:
 * categoría legible, fecha/hora formateada, precio y ruta del archivo.
 *
 * @param array $evento
 * @return array
 */
function prepararDatosEventoDetalle($evento)
{
    $categoriasDetalle = [
        'musica'      => 'Música',
        'deporte'     => 'Deporte',
        'feria'       => 'Ferias',
        'arte'        => 'Arte y cultura',
        'gastronomia' => 'Gastronomía',
        'tecnologia'  => 'Tecnología',
        'otro'        => 'Otro',
    ];

    $categoriaLegible = $categoriasDetalle[$evento['categoria']] ?? $evento['categoria'];

    $fechaFormateada = '';
    if (!empty($evento['fecha'])) {
        $ts = strtotime($evento['fecha']);
        $fechaFormateada = $ts ? date('d/m/Y', $ts) : $evento['fecha'];
    }
    $horaFormateada = $evento['hora'] !== null ? substr($evento['hora'], 0, 5) : '';
    $fechaHoraTexto = trim($fechaFormateada . ($horaFormateada !== '' ? ' · ' . $horaFormateada . ' hs' : ''));

    // Todavía no existen eventos privados/pagos: se muestra "a confirmar".
    $precioTexto = 'Gratis';
    $precioClase = 'event-price-tbd';

    $extensionesVideo = ['mp4', 'mov', 'webm', 'avi', 'mkv'];
    $extArchivo = strtolower(pathinfo($evento['imagen'] ?? '', PATHINFO_EXTENSION));
    $esVideo = in_array($extArchivo, $extensionesVideo, true);

    $rutaArchivo = !empty($evento['imagen']) ? 'uploads/' . $evento['imagen'] : '';

    return [
        'categoriaLegible' => $categoriaLegible,
        'fechaHoraTexto'   => $fechaHoraTexto,
        'precioTexto'      => $precioTexto,
        'precioClase'      => $precioClase,
        'rutaArchivo'      => $rutaArchivo,
        'esVideo'          => $esVideo,
    ];
}

/**
 * Devuelve el <p> de "Organiza ..." o '' si el evento no tiene organizador cargado.
 */
function renderOrganizadorHtml($evento)
{
    $organizador = $evento['contacto_instagram'] ?: ($evento['contacto_web'] ?: null);

    if (!$organizador) {
        return '';
    }

    return '<p class="detail-organizer">Organiza <span>' . e($organizador) . '</span></p>';
}

/**
 * Devuelve la sección completa de Line-up, o '' si el evento no tiene horario cargado.
 */
function renderLineupSectionHtml($evento)
{
    if (empty($evento['horario'])) {
        return '';
    }

    $items = '';
    foreach ($evento['horario'] as $fila) {
        $horaFila = $fila['hora'] !== null ? substr($fila['hora'], 0, 5) : '';
        $items .= '<li>';
        if ($horaFila !== '') {
            $items .= '<strong>' . e($horaFila) . '</strong> — ';
        }
        $items .= e($fila['actividad']) . '</li>';
    }

    return '
        <section class="detail-block reveal">
          <h2 class="section-title">Line-up</h2>
          <ul class="detail-list">' . $items . '</ul>
        </section>';
}

/**
 * Devuelve la card de contacto del organizador, o '' si no hay ningún dato de contacto.
 */
function renderContactoSectionHtml($evento)
{
    $items = '';

    if (!empty($evento['contacto_email'])) {
        $items .= '<li><a href="mailto:' . e($evento['contacto_email']) . '">' . e($evento['contacto_email']) . '</a></li>';
    }
    if (!empty($evento['contacto_telefono'])) {
        $telHref = preg_replace('/[^\d+]/', '', $evento['contacto_telefono']);
        $items .= '<li><a href="tel:' . e($telHref) . '">' . e($evento['contacto_telefono']) . '</a></li>';
    }
    if (!empty($evento['contacto_instagram'])) {
        $usuario = ltrim($evento['contacto_instagram'], '@');
        $items .= '<li><a href="https://instagram.com/' . e($usuario) . '" target="_blank" rel="noopener">' . e($evento['contacto_instagram']) . '</a></li>';
    }
    if (!empty($evento['contacto_web'])) {
        $items .= '<li><a href="' . e($evento['contacto_web']) . '" target="_blank" rel="noopener">' . e($evento['contacto_web']) . '</a></li>';
    }

    if ($items === '') {
        return '';
    }

    return '
        <section class="detail-card reveal">
          <h3 class="detail-card-title">Contacto del organizador</h3>
          <ul class="detail-contact">' . $items . '</ul>
        </section>';
}

/**
 * Devuelve la card de "Información útil" (apto, duración, capacidad, etc.
 * + info_extra), o '' si el evento no tiene ningún dato cargado.
 */
function renderInfoUtilSectionHtml($evento)
{
    $infoFacts = [
        'Apto'          => $evento['info_apto'] ?? null,
        'Duración'      => $evento['info_duracion'] ?? null,
        'Capacidad'     => $evento['info_capacidad'] ?? null,
        'Accesibilidad' => $evento['info_accesibilidad'] ?? null,
        'Mascotas'      => $evento['info_mascotas'] ?? null,
    ];

    $items = '';
    foreach ($infoFacts as $etiqueta => $valor) {
        if ($valor !== null && $valor !== '') {
            $items .= '<li><span>' . e($etiqueta) . '</span><strong>' . e($valor) . '</strong></li>';
        }
    }

    // La info extra son campos opcionales que el organizador puede sumar.
    if (!empty($evento['info_extra'])) {
        foreach ($evento['info_extra'] as $fila) {
            $items .= '<li><span>' . e($fila['clave']) . '</span><strong>' . e($fila['valor']) . '</strong></li>';
        }
    }

    if ($items === '') {
        return '';
    }

    return '
        <section class="detail-card reveal">
          <h3 class="detail-card-title">Información útil</h3>
          <ul class="detail-facts">' . $items . '</ul>
        </section>';
}

/**
 * Devuelve la card de "A tener en cuenta", o '' si el evento no tiene nota cargada.
 */
function renderNotaSectionHtml($evento)
{
    if (empty($evento['adicion'])) {
        return '';
    }

    return '
        <section class="detail-card detail-card-note reveal">
          <h3 class="detail-card-title">A tener en cuenta</h3>
          <p>' . nl2br(e($evento['adicion'])) . '</p>
        </section>';
}


/**
 * Eventos que el admin tiene que revisar: nuevos, reenviados y modificados.
 */
function getEventosPendientes() {

    $db = dbConnect();

    $sql = "SELECT e.*, u.email 
            FROM eventos e 
            JOIN users u ON e.id_usuario = u.id_usuario 
            WHERE e.estado IN ('pendiente','modificado') 
            ORDER BY e.fecha_publicacion DESC";

    $result = mysqli_query($db, $sql);

    if (!$result) {
        return [];
    }

    $eventos = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $eventos;
}

/**
 * Trae únicamente los eventos aprobados
 *
 * @return array
 */
function getEventosAprobados() {
    $db = dbConnect();

    $sql = "SELECT * FROM eventos WHERE estado = 'aprobado' ORDER BY fecha_publicacion DESC";

    $result = mysqli_query($db, $sql);

    if (!$result) {
        return [];
    }

    $eventos = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $eventos;
}


function cambiarEstadoEvento($id_evento, $nuevoEstado) {

    $db = dbConnect();

    $sql = "UPDATE eventos SET estado = ?, ultimo_cambio = NULL, version_base = NULL WHERE id_evento = ?";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("si", $nuevoEstado, $id_evento);

    $resultado = $stmt->execute();

    $stmt->close();

    return $resultado;
}


/**
 * Rechaza un evento SIN borrarlo: pasa a 'rechazado' y guarda el motivo.
 */
function rechazarEvento($id_evento, $motivo) {
    $db = dbConnect();
    $stmt = mysqli_prepare($db, "UPDATE eventos SET estado = 'rechazado', motivo_rechazo = ?, ultimo_cambio = NULL, version_base = NULL WHERE id_evento = ?");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, "si", $motivo, $id_evento);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function getEventosRechazadosDeUsuario($id_usuario) {
    $db = dbConnect();
    $stmt = mysqli_prepare($db, "SELECT id_evento, titulo, fecha, motivo_rechazo
                                 FROM eventos
                                 WHERE id_usuario = ? AND estado = 'rechazado'
                                 ORDER BY id_evento DESC");
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $id_usuario);
    mysqli_stmt_execute($stmt);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $filas;
}


/**
 * Borrado definitivo. Ya NO se usa para rechazar; queda para cuando el
 * anfitrión elige "Borrar" en su propio panel (eliminar_evento).
 */
function eliminarEventoPorCompleto($id_evento) {
    $db = dbConnect();

    $sql = "DELETE FROM eventos WHERE id_evento = ?";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $id_evento);

    $resultado = $stmt->execute();

    $stmt->close();

    return $resultado;
}



function getEventoById($id_evento) {
    $db = dbConnect();
    $sql = "SELECT * FROM eventos WHERE id_evento = ? LIMIT 1";
    $stmt = mysqli_prepare($db, $sql);
    $evento = null;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_evento);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $evento = $row;
        }
        mysqli_stmt_close($stmt);
    }
    return $evento;
}

function cancelarEvento($id_evento, $id_usuario, $motivo) {
    $db = dbConnect();

    $sql = "UPDATE eventos
            SET estado = 'cancelado',
                motivo_cancelacion = ?,
                fecha_cancelacion = NOW()
            WHERE id_evento = ?
              AND id_usuario = ?
              AND estado <> 'cancelado'";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sii", $motivo, $id_evento, $id_usuario);
    $ok = $stmt->execute();
    $filas = $stmt->affected_rows;
    $stmt->close();

    return $ok && $filas > 0;
}
function verificarYCrearTablaNotificaciones() {
    static $verificado = false;

    if ($verificado) {
        return;
    }

    $db = dbConnect();
    $sql = "CREATE TABLE IF NOT EXISTS notificaciones (
        id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        mensaje TEXT NOT NULL,
        leido TINYINT(1) DEFAULT 0,
        fechaRegistro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    @mysqli_query($db, $sql);

    $verificado = true;
}

function crearNotificacion($id_usuario, $mensaje, $link = null) {
    verificarYCrearTablaNotificaciones();
    $db = dbConnect();
    $sql = "INSERT INTO notificaciones (id_usuario, mensaje, link, fechaRegistro) VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($db, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iss", $id_usuario, $mensaje, $link);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function getNotificacionesUsuario($id_usuario) {
    $db = dbConnect();
    $sql = "SELECT * FROM notificaciones WHERE id_usuario = ? ORDER BY fechaRegistro DESC LIMIT 5";
    $stmt = mysqli_prepare($db, $sql);
    $notificaciones = [];
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_usuario);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $notificaciones[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    return $notificaciones;
}

function contarNotificacionesNoLeidas($id_usuario) {
    $db = dbConnect();
    $sql = "SELECT COUNT(*) as total FROM notificaciones WHERE id_usuario = ? AND leido = 0";
    $stmt = mysqli_prepare($db, $sql);
    $total = 0;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_usuario);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $total = intval($row['total']);
        }
        mysqli_stmt_close($stmt);
    }
    return $total;
}

function marcarNotificacionesComoLeidas($id_usuario) {
    verificarYCrearTablaNotificaciones();
    $db = dbConnect();
    $sql = "UPDATE notificaciones SET leido = 1 WHERE id_usuario = ? AND leido = 0";
    $stmt = mysqli_prepare($db, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_usuario);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Trae los eventos aprobados que tienen coordenadas cargadas,
 * para pintarlos en el mapa.
 *
 * @return array
 */
function getEventosParaMapa() {
    $db = dbConnect();

    $sql = "SELECT id_evento, titulo, categoria, zona, fecha, hora, imagen, lat, lon
            FROM eventos
            WHERE estado = 'aprobado'
              AND lat IS NOT NULL
              AND lon IS NOT NULL
            ORDER BY fecha ASC";

    $result = mysqli_query($db, $sql);

    if (!$result) {
        return [];
    }

    $eventos = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $eventos;
}


/**
 * Crea una notificación (con link opcional) para cada usuario con rol admin.
 */
function notificarAdmins($mensaje, $link = null) {
    $db = dbConnect();
    $res = mysqli_query($db, "SELECT id_usuario FROM users WHERE rol = 'admin'");
    if (!$res) return;
    while ($admin = mysqli_fetch_assoc($res)) {
        crearNotificacion((int) $admin['id_usuario'], $mensaje, $link);
    }
}

/**
 * Campos del evento que se comparan para detectar cambios.
 */
function camposComparablesEvento() {
    return [
        'titulo', 'descripcion', 'categoria', 'fecha', 'hora', 'zona', 'localidad', 'lat', 'lon',
        'contacto_email', 'contacto_telefono', 'contacto_instagram', 'contacto_web',
        'info_apto', 'info_duracion', 'info_capacidad', 'info_accesibilidad', 'info_mascotas', 'adicion',
    ];
}

/**
 * Llamar justo después de guardar la edición de un evento.
 *
 * Los cambios son ACUMULATIVOS: se guarda una "versión base" (la última que vio
 * el admin) y en cada edición se compara contra ella, no contra la edición
 * anterior. Así el admin ve todo lo que cambió desde su última revisión, hasta
 * que apruebe o rechace (ahí se borra la versión base).
 *
 *  - aprobado  -> pasa a 'modificado'
 *  - rechazado -> pasa a 'pendiente' (corrigió y reenvió) y se borra el motivo
 *  - pendiente / modificado -> se queda igual
 *
 * @param int   $id_evento
 * @param array $viejo  Datos del evento ANTES de editar (fila de la BD)
 * @param array $nuevo  Datos que acaba de guardar el anfitrión
 */
function registrarModificacionEvento($id_evento, array $viejo, array $nuevo) {
    $cambiosEstaEdicion = detectarCambiosEvento($viejo, $nuevo);
    if (empty($cambiosEstaEdicion)) return;

    $db = dbConnect();

    $stmt = mysqli_prepare($db, "SELECT estado, version_base FROM eventos WHERE id_evento = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_evento);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $estadoPrevio = $fila['estado'] ?? '';

    $base = !empty($fila['version_base']) ? json_decode($fila['version_base'], true) : null;
    if (!is_array($base)) {
        $base = array_intersect_key($viejo, array_flip(camposComparablesEvento()));
    }

    $cambiosAcumulados = detectarCambiosEvento($base, $nuevo);
    $textoAcumulado = $cambiosAcumulados ? implode(", ", $cambiosAcumulados) : null;
    $textoEstaEdicion = implode(", ", $cambiosEstaEdicion);
    $baseJson = json_encode($base, JSON_UNESCAPED_UNICODE);

    $stmt = mysqli_prepare($db, "UPDATE eventos
        SET fecha_modificacion = NOW(),
            ultimo_cambio = ?,
            version_base = ?,
            motivo_rechazo = IF(estado = 'rechazado', NULL, motivo_rechazo),
            estado = CASE
                WHEN estado = 'aprobado'  THEN 'modificado'
                WHEN estado = 'rechazado' THEN 'pendiente'
                ELSE estado
            END
        WHERE id_evento = ?");
    mysqli_stmt_bind_param($stmt, "ssi", $textoAcumulado, $baseJson, $id_evento);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($db, "SELECT e.titulo, u.display_name
        FROM eventos e JOIN users u ON u.id_usuario = e.id_usuario
        WHERE e.id_evento = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_evento);
    mysqli_stmt_execute($stmt);
    $ev = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$ev) return;

    $verbo = ($estadoPrevio === 'rechazado') ? "corrigió y reenvió" : "modificó";

    notificarAdmins(
        $ev['display_name'] . " " . $verbo . " el evento \"" . $ev['titulo'] . "\": " . $textoEstaEdicion . ".",
        "index.php?slug=panelAdmin#evento-" . (int) $id_evento
    );
}

function detectarCambiosDetallados(array $viejo, array $nuevo) {
    $etiquetas = [
        'titulo' => 'Título', 'descripcion' => 'Descripción', 'categoria' => 'Categoría',
        'fecha' => 'Fecha', 'hora' => 'Hora', 'zona' => 'Ubicación', 'localidad' => 'Localidad',
        'contacto_email' => 'Email', 'contacto_telefono' => 'Teléfono',
        'contacto_instagram' => 'Instagram', 'contacto_web' => 'Web',
        'info_apto' => 'Apto para', 'info_duracion' => 'Duración', 'info_capacidad' => 'Capacidad',
        'info_accesibilidad' => 'Accesibilidad', 'info_mascotas' => 'Mascotas', 'adicion' => 'Nota adicional',
    ];

    $filas = [];
    foreach ($etiquetas as $campo => $etiqueta) {
        $a = trim((string) ($viejo[$campo] ?? ''));
        $b = trim((string) ($nuevo[$campo] ?? ''));
        if ($campo === 'hora') { $a = substr($a, 0, 5); $b = substr($b, 0, 5); }
        if ($a === $b) continue;

        $filas[] = ['campo' => $campo, 'etiqueta' => $etiqueta, 'antes' => $a, 'ahora' => $b];
    }

    // Coordenadas del mapa
    $latA = $viejo['lat'] ?? null; $lonA = $viejo['lon'] ?? null;
    $latB = $nuevo['lat'] ?? null; $lonB = $nuevo['lon'] ?? null;
    if (abs((float) $latA - (float) $latB) > 0.00001 || abs((float) $lonA - (float) $lonB) > 0.00001) {
        $fmt = function ($lat, $lon) {
            return ($lat === null || $lat === '' || $lon === null || $lon === '')
                ? '' : round((float) $lat, 5) . ', ' . round((float) $lon, 5);
        };
        $filas[] = ['campo' => 'mapa', 'etiqueta' => 'Punto en el mapa',
                    'antes' => $fmt($latA, $lonA), 'ahora' => $fmt($latB, $lonB)];
    }

    return $filas;
}

function detectarCambiosEvento(array $viejo, array $nuevo) {
    $mostrarValores = ['fecha', 'hora', 'categoria', 'zona', 'localidad'];

    $cambios = [];
    foreach (detectarCambiosDetallados($viejo, $nuevo) as $f) {
        $cambios[] = in_array($f['campo'], $mostrarValores, true)
            ? "{$f['etiqueta']} ({$f['antes']} → {$f['ahora']})"
            : $f['etiqueta'];
    }
    return $cambios;
}

function getCambiosPendientesPorCampo(array $evento) {
    if (empty($evento['version_base'])) return [];

    $base = json_decode($evento['version_base'], true);
    if (!is_array($base)) return [];

    $mapa = [];
    foreach (detectarCambiosDetallados($base, $evento) as $f) {
        $mapa[$f['campo']] = $f;
    }
    return $mapa;
}

function marcarCambio($campo, $valorActual, array $cambios) {
    if (!isset($cambios[$campo])) {
        return e($valorActual);
    }
    $antes = $cambios[$campo]['antes'] !== '' ? e($cambios[$campo]['antes']) : '<em>(vacío)</em>';
    $ahora = $cambios[$campo]['ahora'] !== '' ? e($cambios[$campo]['ahora']) : '<em>(vacío)</em>';

    return '<span class="cambio-ahora">' . $ahora . '</span> '
         . '<del class="cambio-antes" title="Valor anterior">' . $antes . '</del>';
}

function renderCambiosEventoHtml(array $evento) {
    $cambios = getCambiosPendientesPorCampo($evento);
    if (empty($cambios)) return '';

    $items = '';
    foreach ($cambios as $f) {
        $antes = $f['antes'] !== '' ? e($f['antes']) : '<em>(vacío)</em>';
        $ahora = $f['ahora'] !== '' ? e($f['ahora']) : '<em>(vacío)</em>';
        $items .= '<li class="cambio-item">'
                . '<span class="cambio-label">' . e($f['etiqueta']) . '</span>'
                . '<span class="cambio-ahora">' . $ahora . '</span>'
                . '<del class="cambio-antes">' . $antes . '</del>'
                . '</li>';
    }

    return '<div class="cambios-box">'
         . '<strong> Cambios desde la última revisión</strong>'
         . '<ul class="cambios-lista"> ' . $items . '</ul></div>';
}

function usuarioPuedeVerCambiosEvento(array $evento) {
    $rol = $_SESSION['rol'] ?? '';
    $idSesion = (int) ($_SESSION['id_usuario'] ?? 0);

    return $rol === 'admin' || ($idSesion > 0 && $idSesion === (int) ($evento['id_usuario'] ?? 0));
}

function renderUbicacionDetalleHtml(array $evento, array $cambios = []) {
    $html = '<p class="detail-address"> '
          . e($evento['zona']) . '</p>';

    if (isset($cambios['zona'])) {
        $antes = $cambios['zona']['antes'] !== '' ? e($cambios['zona']['antes']) : '<em>(vacío)</em>';
        $html .= '<p class="detail-address-old"><span class="tag-anterior">Anterior</span> ' . $antes . '</p>';
    }

    if (isset($cambios['mapa'])) {
        $antes = $cambios['mapa']['antes'] !== '' ? e($cambios['mapa']['antes']) : '<em>(vacío)</em>';
        $html .= '<p class="detail-address-old"><span class="tag-anterior">Punto anterior en el mapa</span> ' . $antes . '</p>';
    }

    return $html;
}

function renderFechaHoraAnteriorHtml(array $evento, array $cambios = []) {
    if (!isset($cambios['fecha']) && !isset($cambios['hora'])) return '';

    $fechaAnt = isset($cambios['fecha']) ? $cambios['fecha']['antes'] : (string) ($evento['fecha'] ?? '');
    $horaAnt  = isset($cambios['hora'])  ? $cambios['hora']['antes']  : substr((string) ($evento['hora'] ?? ''), 0, 5);

    $fechaTxt = '';
    if ($fechaAnt !== '') {
        $ts = strtotime($fechaAnt);
        $fechaTxt = $ts ? date('d/m/Y', $ts) : $fechaAnt;
    }

    $texto = trim($fechaTxt . ($horaAnt !== '' ? ' · ' . $horaAnt . ' hs' : ''));
    $textoHtml = $texto !== '' ? e($texto) : '<em>(vacío)</em>';

    return '<span class="detail-old-inline"><span class="tag-anterior">Anterior</span> ' . $textoHtml . '</span>';
}

function renderOtrosCambiosDetalleHtml(array $cambios) {

}

function getUsuariosInteresadosEnEvento($id_evento) {
    $db = dbConnect();

    $sql = "SELECT id_usuario FROM favoritos WHERE id_evento = ? AND estado = 'agregado'
            UNION
            SELECT id_usuario FROM asistencias WHERE id_evento = ? AND estado = 'asiste'";

    $stmt = $db->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param("ii", $id_evento, $id_evento);
    $stmt->execute();
    $res = $stmt->get_result();

    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['id_usuario'];
    }
    $stmt->close();

    return $ids;
}

function resumenCambiosParaNotificacion(array $cambios) {
    $mostrarValores = ['fecha', 'hora', 'categoria'];
    $omitir = ['localidad'];

    $partes = [];
    foreach ($cambios as $campo => $f) {
        if (in_array($campo, $omitir, true)) continue;
        $partes[] = in_array($campo, $mostrarValores, true)
            ? "{$f['etiqueta']} ({$f['antes']} → {$f['ahora']})"
            : $f['etiqueta'];
    }
    return implode(", ", $partes);
}


function crearNotificacionConCambios($id_usuario, $mensaje, $id_evento, array $cambios) {
    verificarYCrearTablaNotificaciones();
    $db = dbConnect();

    $json = json_encode(['id_evento' => (int) $id_evento, 'cambios' => $cambios], JSON_UNESCAPED_UNICODE);

    $stmt = mysqli_prepare($db, "INSERT INTO notificaciones (id_usuario, mensaje, link, cambios_json, fechaRegistro)
                                 VALUES (?, ?, NULL, ?, NOW())");
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, "iss", $id_usuario, $mensaje, $json);
    $ok = mysqli_stmt_execute($stmt);
    $idNotif = mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok || !$idNotif) return false;

    $link = "index.php?slug=detalleEvento&id=" . (int) $id_evento . "&notif=" . (int) $idNotif;
    $stmt = mysqli_prepare($db, "UPDATE notificaciones SET link = ? WHERE id_notificacion = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $link, $idNotif);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    return true;
}

function notificarCambiosEventoAprobado(array $evento, array $cambios) {
    if (empty($cambios)) return;

    $resumen = resumenCambiosParaNotificacion($cambios);
    if ($resumen === '') return;

    $mensaje = 'El evento "' . ($evento['titulo'] ?? '') . '" tuvo cambios: ' . $resumen . '. Tocá para verlos.';

    foreach (getUsuariosInteresadosEnEvento((int) $evento['id_evento']) as $idCliente) {
        if ($idCliente === (int) $evento['id_usuario']) continue;
        crearNotificacionConCambios($idCliente, $mensaje, (int) $evento['id_evento'], $cambios);
    }
}

function notificarCancelacionEvento($id_evento, $motivo) {
    $evento = getEventoById((int) $id_evento);
    if (!$evento) return;

    $motivoCorto = mb_strimwidth(trim($motivo), 0, 150, '…');

    $mensaje = 'El evento "' . $evento['titulo'] . '" fue cancelado por el organizador. Motivo: ' . $motivoCorto;
    $link = "index.php?slug=detalleEvento&id=" . (int) $id_evento;
    foreach (getUsuariosInteresadosEnEvento((int) $id_evento) as $idCliente) {
        if ($idCliente === (int) $evento['id_usuario']) continue;
        crearNotificacion($idCliente, $mensaje, $link);
    }
}

function consumirCambiosNotificacion($id_notificacion, $id_usuario, $id_evento) {
    $db = dbConnect();

    $stmt = mysqli_prepare($db, "SELECT cambios_json FROM notificaciones
                                 WHERE id_notificacion = ? AND id_usuario = ? LIMIT 1");
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "ii", $id_notificacion, $id_usuario);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$fila || empty($fila['cambios_json'])) return [];

    $data = json_decode($fila['cambios_json'], true);
    if (!is_array($data) || (int) ($data['id_evento'] ?? 0) !== (int) $id_evento) return [];

    $stmt = mysqli_prepare($db, "UPDATE notificaciones SET cambios_json = NULL, leido = 1
                                 WHERE id_notificacion = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_notificacion);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    return is_array($data['cambios'] ?? null) ? $data['cambios'] : [];
}

function getSiguienteEventoPendiente($id_actual) {
    $ids = array_map(function ($ev) { return (int) $ev['id_evento']; }, getEventosPendientes());
    $id_actual = (int) $id_actual;

    $pos = array_search($id_actual, $ids, true);
    if ($pos !== false && isset($ids[$pos + 1])) {
        return $ids[$pos + 1];
    }

    foreach ($ids as $id) {
        if ($id !== $id_actual) return $id;
    }
    return null;
}

function renderPanelRevisionHtml(array $evento, array $anfitrion = [], $flash = null) {
    $idEvento = (int) $evento['id_evento'];

    if (($evento['estado'] ?? '') === 'modificado') {
        $estadoTxt = 'Modificado';  $estadoClase = 'estado-modificado';
    } elseif (!empty($evento['ultimo_cambio'])) {
        $estadoTxt = 'Con cambios'; $estadoClase = 'estado-cambios';
    } else {
        $estadoTxt = 'Nuevo';       $estadoClase = 'estado-nuevo';
    }

    $svg = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    $icoEscudo = '<svg ' . $svg . ' width="18" height="18"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path></svg>';
    $icoCheck  = '<svg ' . $svg . ' width="15" height="15"><path d="M20 6 9 17l-5-5"></path></svg>';
    $icoX      = '<svg ' . $svg . ' width="15" height="15"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>';

    $fmtFecha = function ($valor) {
        $ts = strtotime((string) $valor);
        return $ts ? date('d/m/Y · H:i', $ts) . ' hs' : '-';
    };

    $fila = function ($etiqueta, $valorHtml) {
        return '<li><span>' . $etiqueta . '</span><strong>' . $valorHtml . '</strong></li>';
    };

    $meta  = $fila('Anfitrión', e($anfitrion['display_name'] ?? '-'));
    if (!empty($anfitrion['email'])) {
        $meta .= $fila('Email', e($anfitrion['email']));
    }
    $meta .= $fila('Enviado', e($fmtFecha($evento['fecha_publicacion'] ?? '')));
    if (!empty($evento['fecha_modificacion'])) {
        $meta .= $fila('Última modificación', e($fmtFecha($evento['fecha_modificacion'])));
    }
    if (($evento['lat'] ?? '') !== '' && ($evento['lon'] ?? '') !== '' && $evento['lat'] !== null && $evento['lon'] !== null) {
        $meta .= $fila('Punto en el mapa', e(round((float) $evento['lat'], 5) . ', ' . round((float) $evento['lon'], 5)));
    }

    $cambios = renderCambiosEventoHtml($evento);
    if ($cambios === '') {
        $cambios = '<div class="admin-review-nuevo"><strong>Evento nuevo</strong><p>No hay una versión anterior para comparar.</p></div>';
    }

    $flashHtml = '';
    if (is_array($flash) && !empty($flash['msg'])) {
        $flashHtml = '<p class="admin-review-flash ' . (!empty($flash['ok']) ? 'ok' : 'error') . '">' . e($flash['msg']) . '</p>';
    }

    return '
    <section class="admin-review-bar" id="revision-admin">

      <header class="admin-review-head">
        <span class="admin-review-icon">' . $icoEscudo . '</span>
        <div class="admin-review-titulos">
          <h3 class="admin-review-title">Revisión del evento</h3>
          <p class="admin-review-sub">Revisá los datos antes de decidir.</p>
        </div>
        <span class="admin-review-estado ' . $estadoClase . '">' . $estadoTxt . '</span>
      </header>

      ' . $flashHtml . '

      <ul class="admin-review-meta">' . $meta . '</ul>

      ' . $cambios . '

      <div class="admin-review-actions">

        <form class="admin-review-approve" method="POST" action="index.php?slug=panelAdmin" onsubmit="return confirm(\'¿Aprobar y publicar este evento?\');">
          <input type="hidden" name="id_evento" value="' . $idEvento . '">
          <input type="hidden" name="accion" value="aprobar">
          <input type="hidden" name="siguiente" value="1">
          <button type="submit" class="btn-review-aprobar">' . $icoCheck . ' Aprobar y pasar al siguiente</button>
        </form>

        <form class="admin-review-reject" method="POST" action="index.php?slug=panelAdmin" autocomplete="off">
          <input type="hidden" name="id_evento" value="' . $idEvento . '">
          <input type="hidden" name="accion" value="rechazar">
          <input type="hidden" name="siguiente" value="1">
          <label class="admin-review-label" for="motivo-rechazo-' . $idEvento . '">Rechazar evento</label>
          <textarea id="motivo-rechazo-' . $idEvento . '" name="motivo" rows="3" placeholder="Motivo del rechazo (obligatorio)" autocomplete="off" required></textarea>
          <input type="password" name="admin_pass" placeholder="Tu contraseña de admin" autocomplete="new-password" required>
          <button type="submit" class="btn-review-rechazar">' . $icoX . ' Rechazar y pasar al siguiente</button>
        </form>

      </div>
    </section>';
}

?>