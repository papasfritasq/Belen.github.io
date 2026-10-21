<?php

require_once __DIR__ . '/DBAbstract.php';


function obtenerIdsFavoritosUsuario($id_usuario) {

    $db = dbConnect();

    $sql = "SELECT id_evento
            FROM favoritos
            WHERE id_usuario = ?
            AND estado = 'agregado'";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();

    $res = $stmt->get_result();
    $ids = [];

    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['id_evento'];
    }

    $stmt->close();

    return $ids;
}


function obtenerIdsAsistenciasUsuario($id_usuario) {

    $db = dbConnect();

    $sql = "SELECT id_evento
            FROM asistencias
            WHERE id_usuario = ?
            AND estado = 'asiste'";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();

    $res = $stmt->get_result();
    $ids = [];

    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['id_evento'];
    }

    $stmt->close();

    return $ids;
}


function toggleFavoritoUsuario($id_usuario, $id_evento) {

    $db = dbConnect();

    $sql = "SELECT estado
            FROM favoritos
            WHERE id_usuario = ?
            AND id_evento = ?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("ii", $id_usuario, $id_evento);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($fila) {

        $nuevoEstado = ($fila['estado'] === 'agregado') ? 'removido' : 'agregado';

        $sqlUpdate = "UPDATE favoritos
                      SET estado = ?,
                          fecha_guardado = NOW()
                      WHERE id_usuario = ?
                      AND id_evento = ?";

        $stmtUp = $db->prepare($sqlUpdate);
        $stmtUp->bind_param("sii", $nuevoEstado, $id_usuario, $id_evento);
        $res = $stmtUp->execute();
        $stmtUp->close();

        return [
            'estado' => $nuevoEstado,
            'exito' => $res
        ];

    } else {

        $sqlInsert = "INSERT INTO favoritos
                      (id_usuario, id_evento, estado, fecha_guardado)
                      VALUES (?, ?, 'agregado', NOW())";

        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bind_param("ii", $id_usuario, $id_evento);
        $res = $stmtInsert->execute();
        $stmtInsert->close();

        return [
            'estado' => 'agregado',
            'exito' => $res
        ];
    }
}


function toggleAsistenciaUsuario($id_usuario, $id_evento) {

    $db = dbConnect();

    $sql = "SELECT estado
            FROM asistencias
            WHERE id_usuario = ?
            AND id_evento = ?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("ii", $id_usuario, $id_evento);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($fila) {

        $nuevoEstado = ($fila['estado'] === 'asiste') ? 'cancelado' : 'asiste';

        $sqlUpdate = "UPDATE asistencias
                      SET estado = ?,
                          fecha_confirmacion = NOW()
                      WHERE id_usuario = ?
                      AND id_evento = ?";

        $stmtUp = $db->prepare($sqlUpdate);
        $stmtUp->bind_param("sii", $nuevoEstado, $id_usuario, $id_evento);
        $res = $stmtUp->execute();
        $stmtUp->close();

        return [
            'estado' => $nuevoEstado,
            'exito' => $res
        ];

    } else {

        $sqlInsert = "INSERT INTO asistencias
                      (id_usuario, id_evento, fecha_confirmacion, estado)
                      VALUES (?, ?, NOW(), 'asiste')";

        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bind_param("ii", $id_usuario, $id_evento);
        $res = $stmtInsert->execute();
        $stmtInsert->close();

        return [
            'estado' => 'asiste',
            'exito' => $res
        ];
    }
}


function getFavoritosVisitante($id_usuario) {

    $db = dbConnect();

    $sql = "SELECT e.*,
                   (SELECT AVG(v.estrellas)
                      FROM valoraciones_eventos v
                     WHERE v.id_evento = e.id_evento) AS promedio_valoracion,
                   (SELECT COUNT(*)
                      FROM valoraciones_eventos v2
                     WHERE v2.id_evento = e.id_evento) AS cantidad_valoraciones
            FROM eventos e
            INNER JOIN favoritos f
                ON e.id_evento = f.id_evento
            WHERE f.id_usuario = ?
            AND f.estado = 'agregado'
            AND e.estado = 'aprobado'";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();

    $eventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $eventos;
}

function normalizarTextoFiltro($texto) {

    $texto = mb_strtolower((string) $texto, 'UTF-8');

    return strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'
    ]);
}

function normalizarFechaFiltro($fecha) {

    if ($fecha === '') {
        return '';
    }

    $obj = DateTime::createFromFormat('Y-m-d', $fecha);

    return ($obj && $obj->format('Y-m-d') === $fecha) ? $fecha : '';
}

function zonaDeEventoFavorito($evento) {

    return trim((string) ($evento['localidad'] ?? $evento['zona'] ?? ''));
}

function obtenerZonasFavoritos($eventos) {

    $zonas = [];

    foreach ($eventos as $evento) {

        $zona = zonaDeEventoFavorito($evento);

        if ($zona !== '') {
            $zonas[$zona] = $zona;
        }
    }

    ksort($zonas);

    return $zonas;
}

function armarOpcionesSelect($opciones, $seleccionado) {

    $html = '';

    foreach ($opciones as $valor => $etiqueta) {

        $selected = ((string) $valor === (string) $seleccionado) ? ' selected' : '';

        $html .= "<option value='"
            . htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') . "'{$selected}>"
            . htmlspecialchars((string) $etiqueta, ENT_QUOTES, 'UTF-8')
            . "</option>";
    }

    return $html;
}

function filtrarFavoritosVisitante($eventos, $filtros) {

    $buscar       = normalizarTextoFiltro($filtros['buscar'] ?? '');
    $categoria    = $filtros['categoria'] ?? '';
    $localidad    = $filtros['localidad'] ?? '';
    $ranking      = $filtros['ranking'] ?? '';
    $fechaDesde   = $filtros['fecha_desde'] ?? '';
    $fechaHasta   = $filtros['fecha_hasta'] ?? '';
    $temporalidad = $filtros['temporalidad'] ?? 'todos';

    $puntuacionMinima = ($ranking !== '') ? (float) $ranking : null;
    $hoy = date('Y-m-d');

    $resultado = [];

    foreach ($eventos as $evento) {

        $fecha = substr((string) ($evento['fecha'] ?? ''), 0, 10);

        if ($buscar !== '') {

            $texto = normalizarTextoFiltro(implode(' ', [
                $evento['titulo'] ?? '',
                $evento['descripcion'] ?? '',
                $evento['categoria'] ?? '',
                zonaDeEventoFavorito($evento)
            ]));

            if (mb_strpos($texto, $buscar) === false) {
                continue;
            }
        }

        if ($categoria !== '' && ($evento['categoria'] ?? '') !== $categoria) {
            continue;
        }

        if ($localidad !== '' && zonaDeEventoFavorito($evento) !== $localidad) {
            continue;
        }

        if ($puntuacionMinima !== null) {

            $promedio = $evento['promedio_valoracion'] ?? null;

            if ($promedio === null || (float) $promedio < $puntuacionMinima) {
                continue;
            }
        }

        if ($fechaDesde !== '' && ($fecha === '' || $fecha < $fechaDesde)) {
            continue;
        }

        if ($fechaHasta !== '' && ($fecha === '' || $fecha > $fechaHasta)) {
            continue;
        }

        if ($temporalidad === 'vigentes' && $fecha < $hoy) {
            continue;
        }

        if ($temporalidad === 'pasados' && $fecha >= $hoy) {
            continue;
        }

        $resultado[] = $evento;
    }

    return $resultado;
}


function getAsistireVisitante($id_usuario) {

    $db = dbConnect();

    $sql = "SELECT e.*
            FROM eventos e
            INNER JOIN asistencias a
                ON e.id_evento = a.id_evento
            WHERE a.id_usuario = ?
            AND a.estado = 'asiste'
            AND e.estado = 'aprobado'
            ORDER BY e.fecha ASC, e.hora ASC";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();

    $eventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $eventos;
}

function fechaCortaEvento($fecha)
{
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $ts = strtotime(substr($fecha ?? '', 0, 10));

    if (!$ts) return '';

    return (int) date('j', $ts) . ' de ' . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}


function generarHtmlTarjetasEventos($eventos, $tipoSeccion = 'general') {

    if (empty($eventos)) {
        return "<p class='sin-eventos'>No hay eventos guardados en esta sección.</p>";
    }

    $html = "";

    foreach ($eventos as $evento) {

        $imagen = !empty($evento['imagen']) ? $evento['imagen'] : 'default.jpg';
        $idEvento = (int) $evento['id_evento'];
        $btnAccion = "";

        if ($tipoSeccion === 'favoritos') {

            $btnAccion = "
                <button
                    type='button'
                    class='btn-quitar-fav'
                    onclick='quitarFavoritoPanel(this, {$idEvento})'
                >
                    <i class='fa-solid fa-heart-crack'></i>
                    Quitar de favoritos
                </button>
            ";

        } elseif ($tipoSeccion === 'asistire') {

            $btnAccion = "
                <button
                    type='button'
                    class='btn-cancelar-asistencia'
                    onclick='cancelarAsistenciaPanel(this, {$idEvento})'
                >
                    <i class='fa-solid fa-calendar-xmark'></i>
                    Cancelar asistencia
                </button>
            ";
        }

        $titulo       = htmlspecialchars($evento['titulo'] ?? '', ENT_QUOTES, 'UTF-8');
        $descripcion  = htmlspecialchars($evento['descripcion'] ?? '', ENT_QUOTES, 'UTF-8');
        $categoria    = htmlspecialchars($evento['categoria'] ?? '', ENT_QUOTES, 'UTF-8');
        $fecha        = htmlspecialchars($evento['fecha'] ?? '', ENT_QUOTES, 'UTF-8');
        $hora         = htmlspecialchars($evento['hora'] ?? '', ENT_QUOTES, 'UTF-8');
        $zona         = htmlspecialchars($evento['zona'] ?? '', ENT_QUOTES, 'UTF-8');
        $imagenSegura = htmlspecialchars($imagen, ENT_QUOTES, 'UTF-8');

        $html .= "
            <div class='evento' id='evento-card-{$idEvento}'>

                <img src='uploads/{$imagenSegura}' alt='Imagen del evento'>

                <div class='evento-contenido'>

                    <h2>{$titulo}</h2>

                    <p>{$descripcion}</p>

                    <p><strong>Categoría:</strong> {$categoria}</p>

                    <p><strong>Fecha:</strong> {$fecha}</p>

                    <p><strong>Hora:</strong> {$hora}</p>

                    <p><strong>Ubicación:</strong> {$zona}</p>

                </div>

                <div class='botones-evento'>

                    <a
                        href='index.php?slug=detalleEvento&id={$idEvento}'
                        class='btn-detalles'
                    >
                        Ver detalle
                    </a>

                    {$btnAccion}

                </div>

            </div>
        ";
    }

    return $html;
}


function generarCalendarioAnual($eventos) {

    $meses = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre'
    ];

    $diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    $añoActual = (int) date('Y');
    $mesActual = (int) date('n');
    $hoy = date('Y-m-d');

    // Años disponibles alrededor del año actual
    $añoInicial = $añoActual - 10;
    $añoFinal   = $añoActual + 10;

    $eventosPorFecha = [];

    foreach ($eventos as $evento) {

        if (empty($evento['fecha'])) {
            continue;
        }

        $eventosPorFecha[$evento['fecha']][] = $evento;
    }

    $html = "";

    for ($año = $añoInicial; $año <= $añoFinal; $año++) {

        $html .= "<div class='anio-calendario' id='anio-{$año}'><div class='anio-meses'>";

        for ($mes = 1; $mes <= 12; $mes++) {

            $fechaInicio = new DateTime(
                $año . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-01'
            );

            $cantidadDias = (int) $fechaInicio->format('t');

            $primerDiaSemana = (int) $fechaInicio->format('N');

            $nombreMes = $meses[$mes];
            $idMes = "mes-{$año}-{$mes}";

            $html .= "
                <section class='mes-calendario' id='{$idMes}'>

                    <div class='mes-calendario-header'>
                        <h3>{$nombreMes} {$año}</h3>
                    </div>

                    <div class='dias-semana'>
            ";

            foreach ($diasSemana as $diaSemana) {
                $html .= "<div class='dia-semana'>{$diaSemana}</div>";
            }

            $html .= "</div><div class='dias-calendario'>";

            // Espacios antes del día 1
            for ($espacio = 1; $espacio < $primerDiaSemana; $espacio++) {
                $html .= "<div class='dia-calendario dia-vacio'></div>";
            }

            // Días del mes
            for ($dia = 1; $dia <= $cantidadDias; $dia++) {

                $fechaCompleta =
                    $año . '-'
                    . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-'
                    . str_pad($dia, 2, '0', STR_PAD_LEFT);

                $claseDia = 'dia-calendario';

                if ($fechaCompleta === $hoy) {
                    $claseDia .= ' dia-hoy';
                }

                $html .= "
                    <div class='{$claseDia}'>
                        <div class='numero-dia'>{$dia}</div>
                ";

                if (isset($eventosPorFecha[$fechaCompleta])) {

                    $html .= "<div class='eventos-dia'>";

                    foreach ($eventosPorFecha[$fechaCompleta] as $evento) {

                        $idEvento = (int) $evento['id_evento'];

                        $titulo = htmlspecialchars($evento['titulo'] ?? '', ENT_QUOTES, 'UTF-8');

                        // Pasado, de hoy o futuro
                        if ($fechaCompleta < $hoy) {
                            $claseEvento = 'evento-calendario pasado';
                        } elseif ($fechaCompleta === $hoy) {
                            $claseEvento = 'evento-calendario hoy';
                        } else {
                            $claseEvento = 'evento-calendario futuro';
                        }

                        $idEventoCalendario = 'evento-calendario-' . $idEvento;

                        $html .= "
                            <div
                                class='{$claseEvento}'
                                id='{$idEventoCalendario}'
                                title='{$titulo}'
                            >

                                <a
                                    href='index.php?slug=detalleEvento&id={$idEvento}'
                                    class='evento-calendario-enlace'
                                >
                                    <i class='fa-solid fa-calendar-check'></i>
                                    <span>{$titulo}</span>
                                </a>

                                <button
                                    type='button'
                                    class='btn-cancelar-calendario'
                                    onclick='cancelarAsistenciaCalendario(event, {$idEvento})'
                                    title='Cancelar asistencia'
                                >
                                    <i class='fa-solid fa-xmark'></i>
                                </button>

                            </div>
                        ";
                    }

                    $html .= "</div>";
                }

                $html .= "</div>";
            }

            $html .= "</div></section>";
        }

        $html .= "</div></div>";
    }

    return $html;
}



function usuarioAsistioEvento($id_usuario, $id_evento)
{
    $db = dbConnect();

    $sql = "SELECT id_evento
            FROM asistencias
            WHERE id_usuario = ?
            AND id_evento = ?
            AND estado = 'asiste'
            LIMIT 1";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ii", $id_usuario, $id_evento);
    $stmt->execute();

    $asistio = $stmt->get_result()->num_rows > 0;

    $stmt->close();

    if (!$asistio) {
        return false;
    }

    $sqlEvento = "SELECT fecha, hora
                  FROM eventos
                  WHERE id_evento = ?
                  LIMIT 1";

    $stmtEvento = $db->prepare($sqlEvento);

    if (!$stmtEvento) {
        return false;
    }

    $stmtEvento->bind_param("i", $id_evento);
    $stmtEvento->execute();

    $evento = $stmtEvento->get_result()->fetch_assoc();

    $stmtEvento->close();

    if (!$evento) {
        return false;
    }

    $fechaEvento = $evento['fecha'];

    if (!empty($evento['hora'])) {
        $fechaFinEvento = $fechaEvento . ' ' . $evento['hora'];
    } else {
        $fechaFinEvento = $fechaEvento . ' 23:59:59';
    }

    $timestampFin = strtotime($fechaFinEvento);

    if (!$timestampFin || time() < $timestampFin) {
        return false;
    }

    return true;
}


function puedeValorarEvento($id_usuario, $id_evento)
{
    return usuarioAsistioEvento($id_usuario, $id_evento);
}



function obtenerValoracionUsuario($id_usuario, $id_evento)
{
    $db = dbConnect();

    $sql = "SELECT *
            FROM valoraciones_eventos
            WHERE id_usuario = ?
            AND id_evento = ?
            LIMIT 1";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ii", $id_usuario, $id_evento);
    $stmt->execute();

    $valoracion = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $valoracion ?: null;
}


function guardarValoracionEvento($id_usuario, $id_evento, $estrellas, $comentario)
{
    $db = dbConnect();

    $estrellas = (int) $estrellas;

    if ($estrellas < 1 || $estrellas > 5) {
        return false;
    }

    if (!usuarioAsistioEvento($id_usuario, $id_evento)) {
        return false;
    }

    $sqlEvento = "SELECT id_evento
                  FROM eventos
                  WHERE id_evento = ?
                  LIMIT 1";

    $stmtEvento = $db->prepare($sqlEvento);

    if (!$stmtEvento) {
        return false;
    }

    $stmtEvento->bind_param("i", $id_evento);
    $stmtEvento->execute();

    $eventoExiste = $stmtEvento->get_result()->num_rows > 0;

    $stmtEvento->close();

    if (!$eventoExiste) {
        return false;
    }

    $comentario = trim($comentario);

    $sqlExiste = "SELECT id_valoracion
                  FROM valoraciones_eventos
                  WHERE id_usuario = ?
                  AND id_evento = ?
                  LIMIT 1";

    $stmtExiste = $db->prepare($sqlExiste);

    if (!$stmtExiste) {
        return false;
    }

    $stmtExiste->bind_param("ii", $id_usuario, $id_evento);
    $stmtExiste->execute();

    $valoracionExistente = $stmtExiste->get_result()->fetch_assoc();

    $stmtExiste->close();

    if ($valoracionExistente) {

        $sqlUpdate = "UPDATE valoraciones_eventos
                      SET estrellas = ?,
                          comentario = ?,
                          fecha_modificacion = NOW()
                      WHERE id_valoracion = ?";

        $stmtUpdate = $db->prepare($sqlUpdate);

        if (!$stmtUpdate) {
            return false;
        }

        $idValoracion = (int) $valoracionExistente['id_valoracion'];

        $stmtUpdate->bind_param("isi", $estrellas, $comentario, $idValoracion);

        $resultado = $stmtUpdate->execute();

        $stmtUpdate->close();

        return $resultado;
    }

    $sqlInsert = "INSERT INTO valoraciones_eventos
                  (id_evento, id_usuario, estrellas, comentario, fecha_valoracion)
                  VALUES (?, ?, ?, ?, NOW())";

    $stmtInsert = $db->prepare($sqlInsert);

    if (!$stmtInsert) {
        return false;
    }

    $stmtInsert->bind_param("iiis", $id_evento, $id_usuario, $estrellas, $comentario);

    $resultado = $stmtInsert->execute();

    $stmtInsert->close();

    return $resultado;
}


function obtenerResumenValoraciones($id_evento)
{
    $db = dbConnect();

    $sql = "SELECT
                COUNT(*) AS cantidad,
                COALESCE(AVG(estrellas), 0) AS promedio
            FROM valoraciones_eventos
            WHERE id_evento = ?";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return [
            'cantidad' => 0,
            'promedio' => 0
        ];
    }

    $stmt->bind_param("i", $id_evento);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return [
        'cantidad' => (int) ($fila['cantidad'] ?? 0),
        'promedio' => round((float) ($fila['promedio'] ?? 0), 1)
    ];
}

function obtenerValoracionesEvento($id_evento)
{
    $db = dbConnect();

    $sql = "SELECT
                v.id_valoracion,
                v.id_usuario,
                v.estrellas,
                v.comentario,
                v.fecha_valoracion,
                v.fecha_modificacion,
                u.display_name,
                u.nombre_usuario
            FROM valoraciones_eventos v
            INNER JOIN users u
                ON u.id_usuario = v.id_usuario
            WHERE v.id_evento = ?
            ORDER BY v.fecha_valoracion DESC";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $id_evento);
    $stmt->execute();

    $valoraciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    return $valoraciones;
}


function generarEstrellasValoracion($estrellas)
{
    $estrellas = (int) $estrellas;

    $html = '';

    for ($i = 1; $i <= 5; $i++) {

        if ($i <= $estrellas) {
            $html .= '<i class="fa-solid fa-star estrella-activa"></i>';
        } else {
            $html .= '<i class="fa-regular fa-star estrella-vacia"></i>';
        }
    }

    return $html;
}


function esOrganizadorDelEvento($id_usuario, $id_evento)
{
    $db = dbConnect();

    $sql = "SELECT id_evento
            FROM eventos
            WHERE id_evento = ?
            AND id_usuario = ?
            LIMIT 1";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ii", $id_evento, $id_usuario);
    $stmt->execute();

    $esOrganizador = $stmt->get_result()->num_rows > 0;

    $stmt->close();

    return $esOrganizador;
}


function obtenerRespuestaValoracion($id_valoracion)
{
    $db = dbConnect();

    $sql = "SELECT
                r.id_respuesta,
                r.id_valoracion,
                r.id_usuario,
                r.respuesta,
                r.fecha_respuesta,
                r.fecha_modificacion,
                u.display_name,
                u.nombre_usuario
            FROM respuestas_valoraciones r
            INNER JOIN users u
                ON u.id_usuario = r.id_usuario
            WHERE r.id_valoracion = ?
            LIMIT 1";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $id_valoracion);
    $stmt->execute();

    $respuesta = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $respuesta ?: null;
}


function guardarRespuestaValoracion($id_usuario, $id_evento, $id_valoracion, $respuesta)
{
    $db = dbConnect();

    $id_usuario    = (int) $id_usuario;
    $id_evento     = (int) $id_evento;
    $id_valoracion = (int) $id_valoracion;

    $respuesta = trim($respuesta);

    if ($respuesta === '') {
        return false;
    }

    if (strlen($respuesta) > 1000) {
        return false;
    }

    if (!esOrganizadorDelEvento($id_usuario, $id_evento)) {
        return false;
    }

    $sqlValoracion = "SELECT id_valoracion
                      FROM valoraciones_eventos
                      WHERE id_valoracion = ?
                      AND id_evento = ?
                      LIMIT 1";

    $stmtValoracion = $db->prepare($sqlValoracion);

    if (!$stmtValoracion) {
        return false;
    }

    $stmtValoracion->bind_param("ii", $id_valoracion, $id_evento);
    $stmtValoracion->execute();

    $existeValoracion = $stmtValoracion->get_result()->num_rows > 0;

    $stmtValoracion->close();

    if (!$existeValoracion) {
        return false;
    }

    $sqlExiste = "SELECT id_respuesta, id_usuario
                  FROM respuestas_valoraciones
                  WHERE id_valoracion = ?
                  LIMIT 1";

    $stmtExiste = $db->prepare($sqlExiste);

    if (!$stmtExiste) {
        return false;
    }

    $stmtExiste->bind_param("i", $id_valoracion);
    $stmtExiste->execute();

    $respuestaExistente = $stmtExiste->get_result()->fetch_assoc();

    $stmtExiste->close();

    if ($respuestaExistente) {

        if ((int) $respuestaExistente['id_usuario'] !== $id_usuario) {
            return false;
        }

        $idRespuesta = (int) $respuestaExistente['id_respuesta'];

        $sqlUpdate = "UPDATE respuestas_valoraciones
                      SET respuesta = ?,
                          fecha_modificacion = NOW()
                      WHERE id_respuesta = ?";

        $stmtUpdate = $db->prepare($sqlUpdate);

        if (!$stmtUpdate) {
            return false;
        }

        $stmtUpdate->bind_param("si", $respuesta, $idRespuesta);

        $resultado = $stmtUpdate->execute();

        $stmtUpdate->close();

        return $resultado;
    }

    $sqlInsert = "INSERT INTO respuestas_valoraciones
                  (id_valoracion, id_usuario, respuesta, fecha_respuesta)
                  VALUES (?, ?, ?, NOW())";

    $stmtInsert = $db->prepare($sqlInsert);

    if (!$stmtInsert) {
        return false;
    }

    $stmtInsert->bind_param("iis", $id_valoracion, $id_usuario, $respuesta);

    $resultado = $stmtInsert->execute();

    $stmtInsert->close();

    return $resultado;
}


function valH($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function valIniciales($nombre)
{
    $partes = preg_split('/\s+/', trim((string) $nombre), -1, PREG_SPLIT_NO_EMPTY);
    $a = isset($partes[0]) ? mb_substr($partes[0], 0, 1) : 'U';
    $b = isset($partes[1]) ? mb_substr($partes[1], 0, 1) : '';

    return mb_strtoupper($a . $b);
}

function valFecha($fecha)
{
    $ts = strtotime((string) $fecha);

    return $ts ? date('d/m/Y', $ts) : (string) $fecha;
}

function valEditado($creada, $modificada)
{
    $tsCreada     = strtotime((string) $creada);
    $tsModificada = strtotime((string) $modificada);

    if (!$tsModificada || $tsModificada <= 0) {
        return '';
    }

    return (!$tsCreada || $tsModificada > $tsCreada) ? ' · Editado' : '';
}

function generarHtmlAvisoValoracion($icono, $titulo, $texto)
{
    return "
        <div class='cmt-locked'>
            <span class='cmt-locked-icon'><i class='fa-regular {$icono}'></i></span>
            <div>
                <strong>{$titulo}</strong>
                <p>{$texto}</p>
            </div>
        </div>
    ";
}



function generarHtmlValoracionesEvento($id_evento, $id_usuario = 0)
{
    $valoraciones = obtenerValoracionesEvento($id_evento);

    if (empty($valoraciones)) {
        return '';
    }

    $html = "<div class='cmt-list'>";

    foreach ($valoraciones as $valoracion) {

        $idValoracion = (int) $valoracion['id_valoracion'];
        $nombre       = valH($valoracion['display_name'] ?: 'Usuario');
        $iniciales    = valH(valIniciales($valoracion['display_name'] ?: 'Usuario'));
        $fecha        = valH(valFecha($valoracion['fecha_valoracion'] ?? ''));
        $editado      = valEditado($valoracion['fecha_valoracion'] ?? '', $valoracion['fecha_modificacion'] ?? '');
        $comentario   = valH($valoracion['comentario'] ?? '');
        $cantEstrellas = (int) $valoracion['estrellas'];
        $estrellas    = generarEstrellasValoracion($cantEstrellas);

        $html .= "
            <article class='cmt-item' id='valoracion-{$idValoracion}'>

                <div class='cmt-top'>
                    <div class='cmt-avatar'>{$iniciales}</div>
                    <div>
                        <div class='cmt-author'>{$nombre}</div>
                        <div class='cmt-time'>{$fecha}{$editado}</div>
                    </div>
                </div>

                <div class='cmt-stars' aria-label='{$cantEstrellas} de 5 estrellas'>{$estrellas}</div>
        ";

        if ($comentario !== '') {
            $html .= "<p class='cmt-text'>{$comentario}</p>";
        }


        $respuesta = obtenerRespuestaValoracion($idValoracion);

        if ($respuesta) {

            $nombreOrg    = valH($respuesta['display_name'] ?: 'Organizador');
            $inicialesOrg = valH(valIniciales($respuesta['display_name'] ?: 'Organizador'));
            $textoResp    = valH($respuesta['respuesta'] ?? '');
            $fechaResp    = valH(valFecha($respuesta['fecha_respuesta'] ?? ''));
            $editadoResp  = valEditado($respuesta['fecha_respuesta'] ?? '', $respuesta['fecha_modificacion'] ?? '');

            $html .= "
                <div class='cmt-replies'>
                    <div class='cmt-reply'>

                        <div class='cmt-reply-label'>
                            <i class='fa-solid fa-reply' aria-hidden='true'></i>
                            En respuesta a <strong>{$nombre}</strong>
                        </div>

                        <div class='cmt-top'>
                            <div class='cmt-avatar org'>{$inicialesOrg}</div>
                            <div>
                                <div class='cmt-author'>{$nombreOrg}<span class='cmt-badge'>Anfitrión</span></div>
                                <div class='cmt-time'>{$fechaResp}{$editadoResp}</div>
                            </div>
                        </div>

                        <p class='cmt-text'>{$textoResp}</p>

                    </div>
                </div>
            ";
        }


        $html .= generarHtmlFormularioRespuestaValoracion(
            $id_usuario,
            $id_evento,
            $idValoracion
        );

        $html .= "</article>";
    }

    $html .= "</div>";

    return $html;
}



function generarHtmlFormularioValoracion($id_usuario, $id_evento, $yaPaso = true)
{
    //organizador no se valora a sí mismo
    if (esOrganizadorDelEvento($id_usuario, $id_evento)) {
        return '';
    }

    //el evento todavía no terminó
    if (!$yaPaso) {
        return generarHtmlAvisoValoracion(
            'fa-clock',
            'Las opiniones se abren cuando termina el evento',
            'Una vez que haya terminado se va a poder comentar. Si tenés dudas, contactá al anfitrión.'
        );
    }

    //ya terminó, pero esta persona no confirmó asistencia
    if (!puedeValorarEvento($id_usuario, $id_evento)) {
        return generarHtmlAvisoValoracion(
            'fa-circle-check',
            'Solo valoran quienes asistieron',
            'Para dejar tu opinión tenés que haber confirmado tu asistencia a este evento.'
        );
    }

    $valoracion = obtenerValoracionUsuario($id_usuario, $id_evento);

    $estrellasSeleccionadas = (int) ($valoracion['estrellas'] ?? 0);
    $comentario  = valH($valoracion['comentario'] ?? '');
    $idEvento    = (int) $id_evento;
    $titulo      = $valoracion ? 'Tu valoración' : '¿Qué te pareció este evento?';
    $textoBoton  = $valoracion ? 'Actualizar valoración' : 'Publicar valoración';

    $html = "
        <form class='cmt-composer cmt-rating-form' method='POST' action='index.php?slug=detalleEvento&id={$idEvento}#comentarios'>

            <div class='cmt-form-title'>{$titulo}</div>

            <input type='hidden' name='accion' value='guardar_valoracion'>
            <input type='hidden' name='id_evento' value='{$idEvento}'>

            <div class='selector-estrellas'>
                <label>Tu puntuación</label>
                <div class='estrellas-input'>
    ";

    for ($i = 5; $i >= 1; $i--) {

        $checked = ($i === $estrellasSeleccionadas) ? ' checked' : '';

        $html .= "
                    <input type='radio' id='estrella-{$i}' name='estrellas' value='{$i}'{$checked} required>
                    <label for='estrella-{$i}' title='{$i} estrellas'><i class='fa-solid fa-star'></i></label>
        ";
    }

    $html .= "
                </div>
            </div>

            <div class='cmt-composer-top'>
                <textarea
                    id='comentario-valoracion'
                    name='comentario'
                    maxlength='310'
                    aria-label='Comentario'
                    placeholder='Contanos qué te pareció (opcional)…'
                >{$comentario}</textarea>
            </div>

            <div class='cmt-composer-bottom'>
                <span>Una buena conversación empieza con respeto.</span>
                <button type='submit'><i class='fa-solid fa-star' aria-hidden='true'></i> {$textoBoton}</button>
            </div>

        </form>
    ";

    return $html;
}



function generarHtmlFormularioRespuestaValoracion($id_usuario, $id_evento, $id_valoracion)
{
    if (!esOrganizadorDelEvento($id_usuario, $id_evento)) {
        return '';
    }

    $respuesta      = obtenerRespuestaValoracion($id_valoracion);
    $textoRespuesta = valH($respuesta['respuesta'] ?? '');
    $botonTexto     = $respuesta ? 'Actualizar respuesta' : 'Responder';
    $titulo         = $respuesta ? 'Tu respuesta' : 'Responder como anfitrión';
    $idEvento       = (int) $id_evento;
    $idValoracion   = (int) $id_valoracion;

    return "
        <form class='cmt-composer cmt-reply-composer' method='POST' action='index.php?slug=detalleEvento&id={$idEvento}#valoracion-{$idValoracion}'>

            <div class='cmt-reply-label'><i class='fa-solid fa-reply' aria-hidden='true'></i> {$titulo}</div>

            <input type='hidden' name='accion' value='guardar_respuesta'>
            <input type='hidden' name='id_evento' value='{$idEvento}'>
            <input type='hidden' name='id_valoracion' value='{$idValoracion}'>

            <textarea
                name='respuesta'
                maxlength='1000'
                aria-label='Respuesta'
                placeholder='Escribí una respuesta para el asistente…'
                required
            >{$textoRespuesta}</textarea>

            <div class='cmt-composer-bottom'>
                <span>Tu respuesta queda visible para todos.</span>
                <button type='submit'><i class='fa-solid fa-comment' aria-hidden='true'></i> {$botonTexto}</button>
            </div>

        </form>
    ";
}



function generarHtmlSeccionValoraciones($id_evento, $id_usuario, $yaPaso, $mensajesHtml = '')
{
    $resumen  = obtenerResumenValoraciones($id_evento);
    $cantidad = (int) $resumen['cantidad'];
    $promedio = number_format($resumen['promedio'], 1, ',', '.');

    $resumenHtml = '';

    if ($cantidad > 0) {
        $resumenHtml = "
            <div class='cmt-rating-summary'>
                <strong>{$promedio}</strong>
                <span class='cmt-stars'>" . generarEstrellasValoracion(round($resumen['promedio'])) . "</span>
            </div>
        ";
    }

    $formulario = generarHtmlFormularioValoracion($id_usuario, $id_evento, $yaPaso);
    $lista      = generarHtmlValoracionesEvento($id_evento, $id_usuario);

    if ($lista === '' && $yaPaso) {
        $lista = "<p class='cmt-empty'>Todavía nadie calificó este evento. ¡Sé el primero!</p>";
    }

    return "
        <section class='cmt-section' id='comentarios' data-no-pdf>

            <div class='cmt-header'>
                <div class='cmt-title'>
                    <i class='fa-regular fa-comment' aria-hidden='true'></i>
                    <h2>Opiniones</h2>
                    <span class='cmt-count'>{$cantidad}</span>
                </div>
                {$resumenHtml}
            </div>

            {$mensajesHtml}
            {$formulario}
            {$lista}

        </section>
    ";
}