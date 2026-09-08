<?php
require_once __DIR__ . '/functions.php';

function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/** Guarda los datos del usuario autenticado en sesión. */
function crearSesionUsuario(array $usuario): void
{
    iniciarSesion();
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'         => $usuario['id'],
        'nombre'     => $usuario['nombre'],
        'rol'        => $usuario['rol'],
        'notaria_id' => $usuario['notaria_id'],
    ];
}

function usuarioActual(): ?array
{
    iniciarSesion();
    return $_SESSION['usuario'] ?? null;
}

function cerrarSesion(): void
{
    iniciarSesion();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 42000, $params['path']);
    }
    session_destroy();
}

/**
 * Exige que exista una sesión activa. Si $rol se especifica, exige además
 * que el usuario tenga ese rol ('admin' o 'cliente'). Si no se cumple,
 * responde 401/403 en JSON y termina la ejecución.
 */
function requerirSesion(?string $rol = null): array
{
    $usuario = usuarioActual();
    if (!$usuario) {
        jsonResponse(['error' => 'No autenticado.'], 401);
    }
    if ($rol !== null && $usuario['rol'] !== $rol) {
        jsonResponse(['error' => 'No autorizado.'], 403);
    }
    return $usuario;
}
