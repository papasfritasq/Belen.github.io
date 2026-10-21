<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Devuelve siempre la MISMA conexión durante todo el request (patrón singleton).
 * La primera llamada abre la conexión; las siguientes reusan la que ya está abierta.
 * No hace falta (ni conviene) cerrarla a mano en cada función de los modelos:
 * PHP la cierra sola al terminar el script.
 */
function dbConnect(){
    static $db = null;

    if ($db === null) {
        $db = mysqli_connect(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );

        if (!$db) {
            die("Error de conexión a MySQL: " . mysqli_connect_error());
        }

        mysqli_set_charset($db, "utf8mb4");
    }

    return $db;
}

/**
 * Helper para queries simples sin parámetros. OJO: no usar con datos
 * que vengan del usuario (GET/POST) sin sanitizar antes -- para eso
 * usar siempre mysqli_prepare + bind_param, como en el resto del código.
 */
function dbQuery($query){
    $db = dbConnect();

    $response = mysqli_query($db, $query);

    if (!$response) {
        die("Error SQL: " . mysqli_error($db));
    }

    return mysqli_fetch_all($response, MYSQLI_ASSOC);
}

?>