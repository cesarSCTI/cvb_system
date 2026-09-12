<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$usuario = requerirSesion();
$metodo  = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET':
        listarNotarias($usuario);
        break;

    case 'POST':
        requerirSesion('admin');
        crearNotaria();
        break;

    case 'PUT':
        requerirSesion('admin');
        actualizarNotaria((int) ($_GET['id'] ?? 0));
        break;

    case 'DELETE':
        requerirSesion('admin');
        eliminarNotaria((int) ($_GET['id'] ?? 0));
        break;

    default:
        jsonResponse(['error' => 'Método no permitido.'], 405);
}

// ---------------------------------------------------------------------

/** Usuario de acceso (rol cliente) ligado a una notaría, o null. */
function usuarioDeNotaria(int $notariaId): ?array
{
    $stmt = db()->prepare(
        "SELECT id, username FROM usuarios WHERE notaria_id = ? AND rol = 'cliente' ORDER BY id LIMIT 1"
    );
    $stmt->execute([$notariaId]);
    return $stmt->fetch() ?: null;
}

function validarEmail(?string $email): void
{
    if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'El correo no tiene un formato válido.'], 422);
    }
}

/**
 * GET /api/notarias.php            -> solo notarías activas (id, nombre)
 * GET /api/notarias.php?todas=1    -> (admin) todas, con contacto, estado, conteos y usuario de acceso
 */
function listarNotarias(array $usuario): void
{
    $pdo = db();
    $verTodas = !empty($_GET['todas']) && $usuario['rol'] === 'admin';

    if ($verTodas) {
        $sql = "SELECT n.id, n.nombre, n.email, n.telefono, n.activo,
                       (SELECT COUNT(*) FROM expedientes e WHERE e.notaria_id = n.id) AS n_expedientes,
                       (SELECT COUNT(*) FROM usuarios   u WHERE u.notaria_id = n.id) AS n_usuarios,
                       (SELECT u2.username FROM usuarios u2
                          WHERE u2.notaria_id = n.id AND u2.rol = 'cliente'
                          ORDER BY u2.id LIMIT 1) AS username
                FROM notarias n
                ORDER BY n.activo DESC, n.nombre";
        $rows = $pdo->query($sql)->fetchAll();
        foreach ($rows as &$r) {
            $r['id']            = (int) $r['id'];
            $r['activo']        = (int) $r['activo'];
            $r['n_expedientes'] = (int) $r['n_expedientes'];
            $r['n_usuarios']    = (int) $r['n_usuarios'];
        }
        jsonResponse(['data' => $rows]);
    }

    $stmt = $pdo->query('SELECT id, nombre FROM notarias WHERE activo = 1 ORDER BY nombre');
    jsonResponse(['data' => $stmt->fetchAll()]);
}

/**
 * POST /api/notarias.php
 * body: { nombre*, email?, telefono?, username?, password? }
 * Si se envían username + password, se crea también el usuario de acceso al portal cliente.
 */
function crearNotaria(): void
{
    $b = readJsonBody();

    $nombre   = trim($b['nombre'] ?? '');
    $email    = trim($b['email'] ?? '');
    $telefono = trim($b['telefono'] ?? '');
    $username = trim($b['username'] ?? '');
    $password = (string) ($b['password'] ?? '');

    if ($nombre === '') {
        jsonResponse(['error' => "El campo 'nombre' es obligatorio."], 422);
    }
    if (mb_strlen($nombre) > 150) {
        jsonResponse(['error' => 'El nombre no puede exceder 150 caracteres.'], 422);
    }
    validarEmail($email);

    if (($username === '') !== ($password === '')) {
        jsonResponse(['error' => 'Para crear el acceso al portal debes indicar usuario y contraseña.'], 422);
    }
    if ($password !== '' && mb_strlen($password) < 6) {
        jsonResponse(['error' => 'La contraseña debe tener al menos 6 caracteres.'], 422);
    }

    $pdo = db();

    $dup = $pdo->prepare('SELECT id FROM notarias WHERE LOWER(nombre) = LOWER(?)');
    $dup->execute([$nombre]);
    if ($dup->fetch()) {
        jsonResponse(['error' => 'Ya existe una notaría con ese nombre.'], 409);
    }

    if ($username !== '') {
        $du = $pdo->prepare('SELECT id FROM usuarios WHERE username = ?');
        $du->execute([$username]);
        if ($du->fetch()) {
            jsonResponse(['error' => 'Ese nombre de usuario ya está en uso.'], 409);
        }
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('INSERT INTO notarias (nombre, email, telefono) VALUES (?, ?, ?)')
            ->execute([$nombre, $email ?: null, $telefono ?: null]);
        $notariaId = (int) $pdo->lastInsertId();

        if ($username !== '') {
            $pdo->prepare(
                "INSERT INTO usuarios (nombre, email, username, password, rol, notaria_id)
                 VALUES (?, ?, ?, ?, 'cliente', ?)"
            )->execute([
                $nombre,
                $email ?: null,
                $username,
                password_hash($password, PASSWORD_BCRYPT),
                $notariaId,
            ]);
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'No se pudo crear la notaría.'], 400);
    }

    jsonResponse(['ok' => true, 'id' => $notariaId], 201);
}

/**
 * PUT /api/notarias.php?id=N
 * body: { nombre?, email?, telefono?, activo?, username?, password? }
 * - nombre/email/telefono/activo: se actualizan sobre la notaría.
 * - password (y opcionalmente username): cambia la contraseña del usuario de acceso.
 *   Si la notaría aún no tiene usuario de acceso, se requiere username + password para crearlo.
 */
function actualizarNotaria(int $id): void
{
    if (!$id) {
        jsonResponse(['error' => 'ID inválido.'], 422);
    }

    $pdo  = db();
    $stmt = $pdo->prepare('SELECT id FROM notarias WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Notaría no encontrada.'], 404);
    }

    $b = readJsonBody();

    // -------- Campos de la notaría --------
    $sets = [];
    $params = [];

    if (array_key_exists('nombre', $b)) {
        $nombre = trim((string) $b['nombre']);
        if ($nombre === '') {
            jsonResponse(['error' => "El campo 'nombre' no puede quedar vacío."], 422);
        }
        if (mb_strlen($nombre) > 150) {
            jsonResponse(['error' => 'El nombre no puede exceder 150 caracteres.'], 422);
        }
        $dup = $pdo->prepare('SELECT id FROM notarias WHERE LOWER(nombre) = LOWER(?) AND id <> ?');
        $dup->execute([$nombre, $id]);
        if ($dup->fetch()) {
            jsonResponse(['error' => 'Ya existe otra notaría con ese nombre.'], 409);
        }
        $sets[] = 'nombre = ?';
        $params[] = $nombre;
    }

    if (array_key_exists('email', $b)) {
        $email = trim((string) $b['email']);
        validarEmail($email);
        $sets[] = 'email = ?';
        $params[] = $email ?: null;
    }

    if (array_key_exists('telefono', $b)) {
        $telefono = trim((string) $b['telefono']);
        $sets[] = 'telefono = ?';
        $params[] = $telefono ?: null;
    }

    if (array_key_exists('activo', $b)) {
        $sets[] = 'activo = ?';
        $params[] = !empty($b['activo']) ? 1 : 0;
    }

    // -------- Usuario de acceso / contraseña --------
    $nuevaPassword = array_key_exists('password', $b) ? (string) $b['password'] : null;
    $nuevoUsername = array_key_exists('username', $b) ? trim((string) $b['username']) : null;
    $tocaAcceso = ($nuevaPassword !== null && $nuevaPassword !== '') || ($nuevoUsername !== null && $nuevoUsername !== '');

    if (!$sets && !$tocaAcceso) {
        jsonResponse(['error' => 'No se enviaron campos para actualizar.'], 422);
    }

    if ($nuevaPassword !== null && $nuevaPassword !== '' && mb_strlen($nuevaPassword) < 6) {
        jsonResponse(['error' => 'La contraseña debe tener al menos 6 caracteres.'], 422);
    }

    $acceso = usuarioDeNotaria($id);

    if ($tocaAcceso) {
        if ($acceso === null) {
            // Aún no hay usuario de acceso: hay que crearlo -> se necesita usuario y contraseña.
            if ($nuevoUsername === null || $nuevoUsername === '' || $nuevaPassword === null || $nuevaPassword === '') {
                jsonResponse(['error' => 'Esta notaría no tiene usuario de acceso. Indica usuario y contraseña para crearlo.'], 422);
            }
        }
        if ($nuevoUsername !== null && $nuevoUsername !== '') {
            $du = $pdo->prepare('SELECT id FROM usuarios WHERE username = ? AND id <> ?');
            $du->execute([$nuevoUsername, $acceso['id'] ?? 0]);
            if ($du->fetch()) {
                jsonResponse(['error' => 'Ese nombre de usuario ya está en uso.'], 409);
            }
        }
    }

    try {
        $pdo->beginTransaction();

        if ($sets) {
            $p = $params;
            $p[] = $id;
            $pdo->prepare('UPDATE notarias SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($p);
        }

        if ($tocaAcceso) {
            if ($acceso === null) {
                $nombreRef = $pdo->query('SELECT nombre FROM notarias WHERE id = ' . $id)->fetchColumn();
                $pdo->prepare(
                    "INSERT INTO usuarios (nombre, username, password, rol, notaria_id)
                     VALUES (?, ?, ?, 'cliente', ?)"
                )->execute([
                    $nombreRef,
                    $nuevoUsername,
                    password_hash($nuevaPassword, PASSWORD_BCRYPT),
                    $id,
                ]);
            } else {
                $uSets = [];
                $uParams = [];
                if ($nuevoUsername !== null && $nuevoUsername !== '') {
                    $uSets[] = 'username = ?';
                    $uParams[] = $nuevoUsername;
                }
                if ($nuevaPassword !== null && $nuevaPassword !== '') {
                    $uSets[] = 'password = ?';
                    $uParams[] = password_hash($nuevaPassword, PASSWORD_BCRYPT);
                }
                if ($uSets) {
                    $uParams[] = $acceso['id'];
                    $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $uSets) . ' WHERE id = ?')->execute($uParams);
                }
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'No se pudo actualizar la notaría.'], 400);
    }

    jsonResponse(['ok' => true]);
}

/**
 * DELETE /api/notarias.php?id=N
 * Borra la notaría SOLO si no tiene expedientes ni usuarios ligados.
 * Si los tiene, responde 409 con los conteos: el frontend debe ofrecer
 * "desactivar" (PUT activo=0) en lugar de borrar.
 */
function eliminarNotaria(int $id): void
{
    if (!$id) {
        jsonResponse(['error' => 'ID inválido.'], 422);
    }

    $pdo  = db();
    $stmt = $pdo->prepare('SELECT id FROM notarias WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Notaría no encontrada.'], 404);
    }

    $exp = $pdo->prepare('SELECT COUNT(*) FROM expedientes WHERE notaria_id = ?');
    $exp->execute([$id]);
    $nExp = (int) $exp->fetchColumn();

    $usr = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE notaria_id = ?');
    $usr->execute([$id]);
    $nUsr = (int) $usr->fetchColumn();

    if ($nExp > 0 || $nUsr > 0) {
        jsonResponse([
            'error'       => 'No se puede eliminar: la notaría tiene registros ligados. Desactívala en su lugar.',
            'expedientes' => $nExp,
            'usuarios'    => $nUsr,
        ], 409);
    }

    $pdo->prepare('DELETE FROM notarias WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}
