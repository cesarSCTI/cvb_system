<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$sesion = requerirSesion();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET':
        obtenerPerfil((int) $sesion['id']);
        break;
    case 'PUT':
        actualizarPerfil((int) $sesion['id']);
        break;
    default:
        jsonResponse(['error' => 'Método no permitido.'], 405);
}

// ---------------------------------------------------------------------

function obtenerPerfil(int $id): void
{
    $stmt = db()->prepare('SELECT id, nombre, email, username, rol FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) {
        jsonResponse(['error' => 'Usuario no encontrado.'], 404);
    }
    jsonResponse(['data' => $u]);
}

/**
 * PUT /api/perfil.php
 * body: { nombre?, email?, username?, password?, password_actual? }
 * Para cambiar la contraseña se exige 'password_actual' correcta.
 */
function actualizarPerfil(int $id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $actual = $stmt->fetch();
    if (!$actual) {
        jsonResponse(['error' => 'Usuario no encontrado.'], 404);
    }

    $b = readJsonBody();
    $sets = [];
    $params = [];

    if (array_key_exists('nombre', $b)) {
        $nombre = trim((string) $b['nombre']);
        if ($nombre === '') {
            jsonResponse(['error' => 'El nombre no puede quedar vacío.'], 422);
        }
        if (mb_strlen($nombre) > 150) {
            jsonResponse(['error' => 'El nombre no puede exceder 150 caracteres.'], 422);
        }
        $sets[] = 'nombre = ?';
        $params[] = $nombre;
    }

    if (array_key_exists('email', $b)) {
        $email = trim((string) $b['email']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'El correo no tiene un formato válido.'], 422);
        }
        if ($email !== '') {
            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
            $dup->execute([$email, $id]);
            if ($dup->fetch()) {
                jsonResponse(['error' => 'Ese correo ya está en uso por otro usuario.'], 409);
            }
        }
        $sets[] = 'email = ?';
        $params[] = $email ?: null;
    }

    if (array_key_exists('username', $b)) {
        $username = trim((string) $b['username']);
        if ($username !== '') {
            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE username = ? AND id <> ?');
            $dup->execute([$username, $id]);
            if ($dup->fetch()) {
                jsonResponse(['error' => 'Ese nombre de usuario ya está en uso.'], 409);
            }
        }
        $sets[] = 'username = ?';
        $params[] = $username ?: null;
    }

    if (array_key_exists('password', $b) && (string) $b['password'] !== '') {
        $nueva = (string) $b['password'];
        $passwordActual = (string) ($b['password_actual'] ?? '');
        if (!password_verify($passwordActual, $actual['password'])) {
            jsonResponse(['error' => 'La contraseña actual no es correcta.'], 422);
        }
        if (mb_strlen($nueva) < 6) {
            jsonResponse(['error' => 'La nueva contraseña debe tener al menos 6 caracteres.'], 422);
        }
        $sets[] = 'password = ?';
        $params[] = password_hash($nueva, PASSWORD_BCRYPT);
    }

    if (!$sets) {
        jsonResponse(['error' => 'No se enviaron cambios.'], 422);
    }

    $params[] = $id;
    try {
        $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'No se pudo actualizar el perfil.'], 400);
    }

    // Refresca el nombre en la sesión para que la UI lo vea al instante.
    if (array_key_exists('nombre', $b)) {
        iniciarSesion();
        $_SESSION['usuario']['nombre'] = trim((string) $b['nombre']);
    }

    jsonResponse(['ok' => true]);
}
