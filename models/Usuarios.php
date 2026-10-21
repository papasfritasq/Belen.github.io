<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/DBAbstract.php";


function newUserAnfi($email, $nombre, $nombre_usuario, $pass, $rol)
{
    $db = dbConnect();

    $password = password_hash($pass, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users 
            (email, display_name, nombre_usuario, password, rol, fechaRegistro, estado)
            VALUES (?, ?, ?, ?, ?, NOW(), 'activo')";

    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        die("Error preparando consulta: " . mysqli_error($db));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $email,
        $nombre,
        $nombre_usuario,
        $password,
        $rol
    );

    if (!mysqli_stmt_execute($stmt)) {
        die("Error creando usuario: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    return true;
}


function newUserVisitante($email, $nombre, $pass, $rol)
{
    $db = dbConnect();

    $password = password_hash($pass, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users 
            (email, display_name, password, rol, fechaRegistro, estado)
            VALUES (?, ?, ?, ?, NOW(), 'activo')";

    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        die("Error preparando consulta: " . mysqli_error($db));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $email,
        $nombre,
        $password,
        $rol
    );

    if (!mysqli_stmt_execute($stmt)) {
        die("Error creando usuario: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    return true;
}


function getUserByEmail($email)
{
    $db = dbConnect();

    $sql = "SELECT id_usuario, email, display_name, nombre_usuario, password, rol, fechaRegistro, estado
            FROM users
            WHERE email = ?
            LIMIT 1";

    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        die("Error preparando consulta: " . mysqli_error($db));
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $usuario = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $usuario ?: false;
}


function getUserByUsername($nombre_usuario)
{
    $db = dbConnect();

    $sql = "SELECT id_usuario, email, display_name, nombre_usuario, password, rol, fechaRegistro, estado
            FROM users
            WHERE nombre_usuario = ?
            LIMIT 1";

    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        die("Error preparando consulta: " . mysqli_error($db));
    }

    mysqli_stmt_bind_param($stmt, "s", $nombre_usuario);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $usuario = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $usuario ?: false;
}

function getUserById($id)
{
    $db = dbConnect();

    $sql = "SELECT id_usuario, email, display_name, nombre_usuario, password, rol, fechaRegistro, estado
            FROM users
            WHERE id_usuario = ?
            LIMIT 1";

    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        die("Error preparando consulta: " . mysqli_error($db));
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $usuario = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $usuario ?: false;
}


function getUserByEmailOrUsername($dato)
{
    $db = dbConnect();

    $sql = "SELECT id_usuario, email, display_name, nombre_usuario, password, rol, fechaRegistro, estado
            FROM users
            WHERE email = ? OR nombre_usuario = ?
            LIMIT 1";

    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        die("Error preparando consulta: " . mysqli_error($db));
    }

    mysqli_stmt_bind_param($stmt, "ss", $dato, $dato);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $usuario = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $usuario ?: false;
}


function getTodosLosUsuarios() {
    $db = dbConnect();

    $sql = "SELECT id_usuario, email, rol, fechaRegistro, estado 
            FROM users 
            ORDER BY fechaRegistro DESC";

    $result = mysqli_query($db, $sql);

    if (!$result) {
        return [];
    }

    $usuarios = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $usuarios;
}


function actualizarEstadoUsuario($id_usuario, $nuevoEstado) {
    $db = dbConnect();

    $sql = "UPDATE users SET estado = ? WHERE id_usuario = ?";
    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "si", $nuevoEstado, $id_usuario);
    $resultado = mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    return $resultado;
}


function eliminarUsuario($id_usuario) {
    $db = dbConnect();

    $sql = "DELETE FROM users WHERE id_usuario = ?";
    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $id_usuario);
    $resultado = $stmt->execute();

    $stmt->close();

    return $resultado;
}

function guardarUbicacionUsuario($id_usuario, $lat, $lon, $direccion_texto) {
    $db = dbConnect();

    $sql = "UPDATE users SET lat = ?, lon = ?, direccion_texto = ? WHERE id_usuario = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) { return false; }

    $stmt->bind_param("ddsi", $lat, $lon, $direccion_texto, $id_usuario);
    $resultado = $stmt->execute();

    $stmt->close();

    return $resultado;
}


function getUbicacionUsuario($id_usuario) {
    $db = dbConnect();

    $sql = "SELECT lat, lon, direccion_texto FROM users WHERE id_usuario = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $ubicacion = $resultado->fetch_assoc();

    $stmt->close();

    return $ubicacion;
}
function getDatosAnfitrion($id_usuario) {
    $db = dbConnect();

    $sql = "SELECT display_name, nombre_usuario, email, rol FROM users WHERE id_usuario = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();

    $stmt->close();

    return $usuario;
}

function actualizarPasswordPorEmail($email, $passwordPlano)
{
    $db = dbConnect();

    $hash = password_hash($passwordPlano, PASSWORD_DEFAULT);

    $sql = "UPDATE users SET password = ? WHERE email = ?";
    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "ss", $hash, $email);
    $resultado = mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    return $resultado;
}

function desactivarCuentaPropia($id_usuario)
{
    $db = dbConnect();
    $stmt = mysqli_prepare($db, "UPDATE users SET estado = 'desactivado', fecha_baja = NOW() WHERE id_usuario = ?");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, "i", $id_usuario);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function getDatosBaja($email)
{
    $db = dbConnect();
    $sql = "SELECT id_usuario, password, estado, fecha_baja,
                   (fecha_baja IS NOT NULL AND fecha_baja >= DATE_SUB(NOW(), INTERVAL 15 DAY)) AS recuperable,
                   GREATEST(0, 15 - DATEDIFF(NOW(), fecha_baja)) AS dias_restantes
            FROM users WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: false;
}

function reactivarCuenta($id_usuario)
{
    $db = dbConnect();
    $stmt = mysqli_prepare($db, "UPDATE users SET estado = 'activo', fecha_baja = NULL WHERE id_usuario = ?");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, "i", $id_usuario);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function purgarCuentasVencidas()
{
    $db = dbConnect();
    return mysqli_query($db, "DELETE FROM users
        WHERE estado = 'desactivado' AND fecha_baja IS NOT NULL
        AND fecha_baja < DATE_SUB(NOW(), INTERVAL 15 DAY)");
}
?>