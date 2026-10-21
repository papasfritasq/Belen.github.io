<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/DBAbstract.php";

function getEventos()
{
    $db = dbConnect();

    $sql = "SELECT e.*, u.display_name, u.nombre_usuario 
            FROM eventos e
            JOIN users u ON e.id_usuario = u.id_usuario
            WHERE e.estado = 'aprobado'
            ORDER BY e.fecha ASC, e.hora ASC";

    $result = mysqli_query($db, $sql);

    $eventos = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $eventos[] = $row;
        }
    }

    return $eventos;
}

function getEventoPorId($id)
{
    $db = dbConnect();

    $sql = "SELECT * 
            FROM eventos 
            WHERE id_evento = ?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $evento = $resultado->fetch_assoc();

    $stmt->close();

    return $evento;
}

function filtrarCategoria($categoria)
{
    $db = dbConnect();

    $sql = "SELECT e.*, u.display_name, u.nombre_usuario
            FROM eventos e
            JOIN users u ON e.id_usuario = u.id_usuario
            WHERE e.categoria = ?
            AND e.estado = 'aprobado'
            ORDER BY e.fecha ASC, e.hora ASC";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $categoria);
    $stmt->execute();

    $result = $stmt->get_result();
    $eventos = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    return $eventos;
}

function buscarEventos($buscar, $categoria = '')
{
    $db = dbConnect();

    $sql = "SELECT e.*, u.display_name, u.nombre_usuario
            FROM eventos e
            JOIN users u ON e.id_usuario = u.id_usuario
            WHERE e.estado = 'aprobado'
            AND (
                e.titulo LIKE ?
                OR e.descripcion LIKE ?
                OR e.zona LIKE ?
                OR u.display_name LIKE ?
                OR u.nombre_usuario LIKE ?
            )";

    if ($categoria !== '') {
        $sql .= " AND e.categoria = ?";
    }

    $sql .= " ORDER BY e.fecha ASC, e.hora ASC";

    $stmt = $db->prepare($sql);
    $buscarLike = "%" . $buscar . "%";

    if ($categoria !== '') {
        $stmt->bind_param(
            "ssssss",
            $buscarLike,
            $buscarLike,
            $buscarLike,
            $buscarLike,
            $buscarLike,
            $categoria
        );
    } else {
        $stmt->bind_param(
            "sssss",
            $buscarLike,
            $buscarLike,
            $buscarLike,
            $buscarLike,
            $buscarLike
        );
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $eventos = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    return $eventos;
}

function formatearFechaHora($fecha, $hora)
{
    if (!$fecha) {
        return '';
    }

    $meses = [
        '01' => 'ene',
        '02' => 'feb',
        '03' => 'mar',
        '04' => 'abr',
        '05' => 'may',
        '06' => 'jun',
        '07' => 'jul',
        '08' => 'ago',
        '09' => 'sep',
        '10' => 'oct',
        '11' => 'nov',
        '12' => 'dic',
    ];

    $ts = strtotime(
        trim($fecha . ' ' . ($hora ?? ''))
    );

    if ($ts === false) {
        return htmlspecialchars($fecha);
    }

    $dia = date('d', $ts);
    $mes = $meses[date('m', $ts)] ?? '';
    $h24 = (int) date('H', $ts);
    $min = date('i', $ts);
    $ampm = $h24 >= 12 ? 'p. m.' : 'a. m.';

    $h12 = $h24 % 12;
    if ($h12 === 0) {
        $h12 = 12;
    }

    $horaFmt = str_pad((string) $h12, 2, '0', STR_PAD_LEFT) . ':' . $min . ' ' . $ampm;

    return "{$dia}-{$mes}, {$horaFmt}";
}

function prepararDatosEvento($evento)
{
    $categoriaLabels = [
        'musica' => 'Música',
        'deporte' => 'Deportivo',
        'feria' => 'Feria',
        'arte' => 'Cultural',
        'gastronomia' => 'Gastronómico',
        'tecnologia' => 'Tecnología',
        'otro' => 'Otro',
    ];

    $esExterno = $evento['externo'] ?? false;

    $srcImagen = $esExterno
        ? htmlspecialchars($evento['imagen'] ?? '')
        : 'uploads/' . htmlspecialchars($evento['imagen'] ?? '');

    $categoriaKey = $evento['categoria'] ?? '';

    $categoriaLabel = $categoriaLabels[$categoriaKey]
        ?? ($categoriaKey !== '' ? ucfirst($categoriaKey) : 'Evento');

    $fechaHora = formatearFechaHora(
        $evento['fecha'] ?? '',
        $evento['hora'] ?? ''
    );

    $precio = $evento['precio'] ?? null;

    $esGratis = (
        $precio === null
        || $precio === ''
        || (float) $precio <= 0
    );

    $precioTexto = $esGratis
        ? 'Gratuito'
        : '$ ' . number_format((float) $precio, 0, ',', '.');

    $precioClase = $esGratis
        ? 'event-price free'
        : 'event-price';

    $username = htmlspecialchars($evento['nombre_usuario'] ?? '');

    $anfitrionNombre = !empty($evento['display_name'])
        ? $evento['display_name']
        : ($evento['nombre_usuario'] ?? '');

    $anfitrionNombre = htmlspecialchars($anfitrionNombre);

    $organizadorHtml = '';

    if ($anfitrionNombre !== '') {
        $usernameSpan = $username !== ''
            ? " <span class='anfitrion-username'>(@{$username})</span>"
            : '';

        $organizadorHtml = "<p class='event-organizer'>Por "
            . "<a class='anfitrion-link' href='index.php?slug=profile&username=" . urlencode($evento['nombre_usuario'] ?? '') . "'>"
            . "<strong>{$anfitrionNombre}</strong>"
            . "</a>"
            . $usernameSpan
            . "</p>";
    }

    $idEvento = htmlspecialchars($evento['id_evento'] ?? '');



$promedioValoracion =
    $evento['promedio_valoracion']
    ?? null;


$cantidadValoraciones =
    (int) (
        $evento['cantidad_valoraciones']
        ?? 0
    );



$estrellasHtml = '';




if ($cantidadValoraciones === 0) {

    for ($i = 1; $i <= 5; $i++) {

        $estrellasHtml .=
            "<i class='fa-regular fa-star estrella-vacia'></i>";
    }


    $rankingHtml = "
        <div class='evento-ranking evento-sin-valoraciones'>

            <span class='evento-estrellas'>
                {$estrellasHtml}
            </span>

            <span class='sin-valoraciones'>
                Sin valoraciones
            </span>

        </div>
    ";


} else {


    $promedioParaEstrellas =
        (int) round(
            (float) $promedioValoracion
        );


    for ($i = 1; $i <= 5; $i++) {

        if (
            $i <=
            $promedioParaEstrellas
        ) {

            $estrellasHtml .=
                "<i class='fa-solid fa-star estrella-activa'></i>";

        } else {

            $estrellasHtml .=
                "<i class='fa-regular fa-star estrella-vacia'></i>";
        }
    }



    $promedioTexto =
        number_format(
            (float) $promedioValoracion,
            1,
            ',',
            '.'
        );


    $rankingHtml = "
        <div class='evento-ranking'>

            <span class='evento-estrellas'>
                {$estrellasHtml}
            </span>

            <strong>
                {$promedioTexto}
            </strong>

            <span class='evento-cantidad-valoraciones'>
                ({$cantidadValoraciones})
            </span>

        </div>
    ";
}

    return [
        'idEvento' => $idEvento,
        'titulo' => htmlspecialchars($evento['titulo'] ?? ''),
        'descripcion' => htmlspecialchars($evento['descripcion'] ?? ''),
        'zona' => htmlspecialchars($evento['zona'] ?? ''),
        'srcImagen' => $srcImagen,
        'categoriaLabel' => $categoriaLabel,
        'fechaHora' => $fechaHora,
        'precioTexto' => $precioTexto,
        'precioClase' => $precioClase,
        'organizadorHtml' => $organizadorHtml,
        'rankingHtml' => $rankingHtml,
    ];
}

function mostrarEventos($eventos = null)
{
    if ($eventos === null) {
        $eventos = getEventos();
    }

    if (empty($eventos)) {
        return "
            <div class='no-eventos'>
                <h3>No se encontraron eventos</h3>
                <p>Probá con otra palabra o categoría.</p>
            </div>
        ";
    }

    $html = "";
    $esVisitante = isset($_SESSION['rol']) && $_SESSION['rol'] === 'visitante';
    $idUsuario = $_SESSION['id_usuario'] ?? null;

    $favoritosUsuario = [];
    $asistenciasUsuario = [];

    if ($esVisitante && $idUsuario) {
        require_once __DIR__ . '/Panel.php';
        if (function_exists('obtenerIdsFavoritosUsuario')) {
            $favoritosUsuario = obtenerIdsFavoritosUsuario($idUsuario);
        }
        if (function_exists('obtenerIdsAsistenciasUsuario')) {
            $asistenciasUsuario = obtenerIdsAsistenciasUsuario($idUsuario);
        }
    }

    foreach ($eventos as $evento) {
        $d = prepararDatosEvento($evento);

        $botonFavoritoHtml = '';
        if ($esVisitante) {
            $esFavorito = in_array((int)$d['idEvento'], $favoritosUsuario, true);
            $claseFavorito = $esFavorito ? 'favorito-activo' : '';

            $botonFavoritoHtml = "
                <button type='button' 
                        class='btn-favorito {$claseFavorito}' 
                        onclick='toggleFavorito(this, {$d['idEvento']})' 
                        title='Guardar como favorito'>
                    <svg viewBox='0 0 24 24'>
                        <path d='M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z'/>
                    </svg>
                </button>
            ";
        }

        $botonConfirmarHtml = '';
        if ($esVisitante) {

            if (eventoYaPaso($evento)) {

                $botonConfirmarHtml = "
                    <button type='button' class='btn-confirm btn-finalizado' disabled>
                        Evento finalizado
                    </button>
                ";

            } else {

                $asiste = in_array((int)$d['idEvento'], $asistenciasUsuario, true);
                $claseAsistencia = $asiste ? 'asistencia-confirmada' : '';
                $textoAsistencia = $asiste ? '✓ Asistencia confirmada' : '✓ Confirmar asistencia';

                $botonConfirmarHtml = "
                    <button type='button' 
                            class='btn-confirm {$claseAsistencia}' 
                            onclick='toggleAsistencia(this, {$d['idEvento']})'>
                        {$textoAsistencia}
                    </button>
                ";
            }
        }

        $html .= "
            <div class='evento event-card'>

                <div class='event-image'>
                    <img src='{$d['srcImagen']}' alt='Imagen del evento' loading='lazy'>

                    <span class='event-badge'>
                        {$d['categoriaLabel']}
                    </span>

                    {$botonFavoritoHtml}
                </div>

                <div class='event-body'>
                    <span class='event-date'>
                        <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>
                            <rect x='3' y='5' width='18' height='16' rx='2'></rect>
                            <path d='M16 3v4M8 3v4M3 10h18'></path>
                        </svg>
                        {$d['fechaHora']}
                    </span>

                    <h2 class='event-title'>{$d['titulo']}</h2>

                    {$d['rankingHtml']}
                    
                    {$d['organizadorHtml']}

                    <p class='event-description'>{$d['descripcion']}</p>

                    <span class='event-location'>
                        <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>
                            <path d='M12 22s7-7.58 7-12A7 7 0 0 0 5 10c0 4.42 7 12 7 12z'></path>
                            <circle cx='12' cy='10' r='2.5'></circle>
                        </svg>
                        {$d['zona']}
                    </span>
                </div>

                <div class='event-side'>
                    <div class='event-price-box'>
                        <span class='event-entrada-label'>Entrada</span>
                        <span class='{$d['precioClase']}'>{$d['precioTexto']}</span>
                    </div>

                    <div class='event-actions'>
                        <a class='btn-details' href='index.php?slug=detalleEvento&id={$d['idEvento']}'>
                            Ver detalles →
                        </a>

                        {$botonConfirmarHtml}
                    </div>
                </div>

            </div>
        ";
    }

    return $html;
}

function getLocalidadesDisponibles() {
    $db = dbConnect();
    $res = $db->query("SELECT DISTINCT localidad FROM eventos
                       WHERE estado = 'aprobado'
                         AND localidad IS NOT NULL AND localidad <> ''
                       ORDER BY localidad");
    $localidades = $res ? array_column($res->fetch_all(MYSQLI_ASSOC), 'localidad') : [];
    return $localidades;
}

function getEventosFiltrados($buscar = '', $categoria = '', $localidad = '', $fechaDesde = '', $fechaHasta = '')
{
    $db = dbConnect();

    $sql = "SELECT  e.*, u.display_name, u.nombre_usuario,
                AVG(v.estrellas) AS promedio_valoracion,
                COUNT(v.id_valoracion) AS cantidad_valoraciones
            FROM eventos e
            JOIN users u
                ON e.id_usuario = u.id_usuario
            LEFT JOIN valoraciones_eventos v
                ON e.id_evento = v.id_evento
            WHERE e.estado = 'aprobado'";

    $tipos = "";
    $params = [];

    if ($buscar !== '') {

        $sql .= " AND (
                    e.titulo LIKE ?
                    OR e.descripcion LIKE ?
                    OR e.zona LIKE ?
                    OR e.localidad LIKE ?
                    OR u.display_name LIKE ?
                    OR u.nombre_usuario LIKE ?
                  )";

        $like = "%" . $buscar . "%";

        for ($i = 0; $i < 6; $i++) {
            $params[] = $like;
        }

        $tipos .= "ssssss";
    }

    if ($categoria !== '') {

        $sql .= " AND e.categoria = ?";

        $params[] = $categoria;
        $tipos .= "s";
    }

    if ($localidad !== '') {

        $sql .= " AND e.localidad = ?";

        $params[] = $localidad;
        $tipos .= "s";
    }

    if ($fechaDesde !== '') {

        $sql .= " AND e.fecha >= ?";

        $params[] = $fechaDesde;
        $tipos .= "s";
    }

    if ($fechaHasta !== '') {

        $sql .= " AND e.fecha <= ?";

        $params[] = $fechaHasta;
        $tipos .= "s";
    }

    $sql .= " GROUP BY e.id_evento
              ORDER BY e.fecha ASC, e.hora ASC";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return [];
    }

    if ($params) {
        $stmt->bind_param($tipos, ...$params);
    }

    $stmt->execute();

    $eventos = $stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    return $eventos;
}


function splitEventosPorFecha($eventos)
{
    $hoy = date('Y-m-d');
    $actual = [];
    $pasados = [];
 
    foreach ($eventos as $evento) {
        $fecha = $evento['fecha'] ?? '';
 
        if ($fecha !== '' && $fecha < $hoy) {
            $pasados[] = $evento;
        } else {
            $actual[] = $evento;
        }
    }
 
    return [$actual, $pasados];
}
function eventoYaPaso($evento)
{
    $fecha = $evento['fecha'] ?? '';
    if ($fecha === '') {
        return false;
    }

    $hora = $evento['hora'] ?? '';
    if ($hora === '' || $hora === null) {
        $hora = '23:59:59';
    }

    $ts = strtotime($fecha . ' ' . $hora);
    return $ts !== false && $ts < time();
}
?>