<?php

/*
| Funciones NUEVAS solo para la página de perfil del anfitrión.
| No reemplazan ninguna función existente.
| Usa funciones que ya tenés: getEventosAprobados() y prepararDatosEventoDetalle() (Posts.php),
| eventoYaPaso() (Landing.php) y fechaCortaEvento() (Panel.php).
*/

require_once __DIR__ . '/DBAbstract.php';


/**
 * Promedio y cantidad de valoraciones de TODOS los eventos de un anfitrión.
 */
function obtenerValoracionAnfitrion($idAnfitrion)
{
    $db = dbConnect();

    $sql = "SELECT COUNT(v.id_valoracion) AS cantidad,
                   COALESCE(AVG(v.estrellas), 0) AS promedio
            FROM valoraciones_eventos v
            INNER JOIN eventos e ON e.id_evento = v.id_evento
            WHERE e.id_usuario = ?";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return ['cantidad' => 0, 'promedio' => 0];
    }

    $stmt->bind_param("i", $idAnfitrion);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'cantidad' => (int) ($fila['cantidad'] ?? 0),
        'promedio' => round((float) ($fila['promedio'] ?? 0), 1),
    ];
}


/**
 * Eventos aprobados de un anfitrión, ordenados por fecha ascendente.
 */
function getEventosDeAnfitrion($idAnfitrion)
{
    $propios = array_filter(
        getEventosAprobados(),
        function ($evento) use ($idAnfitrion) {
            return (int) $evento['id_usuario'] === (int) $idAnfitrion;
        }
    );

    usort($propios, function ($a, $b) {
        return strcmp(
            ($a['fecha'] ?? '') . ' ' . ($a['hora'] ?? ''),
            ($b['fecha'] ?? '') . ' ' . ($b['hora'] ?? '')
        );
    });

    return array_values($propios);
}


/**
 * Tarjetas de eventos del perfil.
 */
function generarHtmlEventosPerfil($eventos)
{
    if (empty($eventos)) {
        return "<p class='perfil-vacio'>Este anfitrión todavía no publicó eventos.</p>";
    }

    $html = '';

    foreach ($eventos as $evento) {

        $datos = prepararDatosEventoDetalle($evento);

        $idEvento = (int) $evento['id_evento'];
        $titulo   = e($evento['titulo'] ?? '');
        $desc     = e($evento['descripcion'] ?? '');
        $categoria = e($datos['categoriaLegible']);
        $imagen   = e($datos['rutaArchivo']);

        $lugar = trim((string) ($evento['localidad'] ?? ''));
        if ($lugar === '') {
            $lugar = (string) ($evento['zona'] ?? '');
        }
        $lugar = e($lugar);

        $hora = !empty($evento['hora']) ? ' · ' . substr($evento['hora'], 0, 5) . ' h' : '';
        $fechaTxt = e(fechaCortaEvento($evento['fecha'] ?? '') . $hora);

        $ts = strtotime(($evento['fecha'] ?? '') . ' ' . ($evento['hora'] ?? '00:00'));

        $yaPaso = eventoYaPaso($evento);
        $chipTxt = $yaPaso ? 'Finalizado' : 'Próximo';
        $chipClase = $yaPaso ? 'chip-finalizado' : 'chip-proximo';

        $html .= "
            <a class='perfil-card' href='index.php?slug=detalleEvento&id={$idEvento}' data-fecha='{$ts}'>

                <div class='perfil-card-img'>
                    <img src='{$imagen}' alt='{$titulo}' loading='lazy'>
                    <span class='perfil-card-cat'>{$categoria}</span>
                </div>

                <div class='perfil-card-body'>
                    <span class='perfil-chip {$chipClase}'>{$chipTxt}</span>
                    <h3 class='perfil-card-title'>{$titulo}</h3>
                    <p class='perfil-card-desc'>{$desc}</p>

                    <ul class='perfil-card-meta'>
                        <li><i class='fa-regular fa-calendar' aria-hidden='true'></i> {$fechaTxt}</li>
                        <li><i class='fa-solid fa-location-dot' aria-hidden='true'></i> {$lugar}</li>
                    </ul>
                </div>

            </a>
        ";
    }

    return $html;
}