<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido.'], 405);
}

$body = readJsonBody();
$tipo = $body['tipo'] ?? ''; // 'admin' | 'cliente'
$identificador = trim($body['identificador'] ?? ''); // email (admin) o username (cliente)
$password = $body['password'] ?? '';

if ($tipo === '' || $identificador === '' || $password === '') {
    jsonResponse(['error' => 'Faltan datos de acceso.'], 422);
}

if (!in_array($tipo, ['admin', 'cliente'], true)) {
    jsonResponse(['error' => 'Tipo de acceso inválido.'], 422);
}

$campo = $tipo === 'admin' ? 'email' : 'username';

$stmt = db()->prepare("SELECT * FROM usuarios WHERE {$campo} = ? AND rol = ? AND activo = 1 LIMIT 1");
$stmt->execute([$identificador, $tipo]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($password, $usuario['password'])) {
    jsonResponse(['error' => 'Usuario o contraseña incorrectos.'], 401);
}

crearSesionUsuario($usuario);

jsonResponse([
    'ok' => true,
    'usuario' => [
        'id'     => $usuario['id'],
        'nombre' => $usuario['nombre'],
        'rol'    => $usuario['rol'],
    ],
]);
