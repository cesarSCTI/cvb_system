<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$usuario = requerirSesion();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $stmt = db()->query('SELECT id, nombre FROM notarias WHERE activo = 1 ORDER BY nombre');
    jsonResponse(['data' => $stmt->fetchAll()]);
}

if ($metodo === 'POST') {
    requerirSesion('admin');
    $b = readJsonBody();
    if (empty($b['nombre'])) {
        jsonResponse(['error' => "El campo 'nombre' es obligatorio."], 422);
    }
    $pdo = db();
    $pdo->prepare('INSERT INTO notarias (nombre) VALUES (?)')->execute([$b['nombre']]);
    jsonResponse(['ok' => true, 'id' => (int) $pdo->lastInsertId()], 201);
}

jsonResponse(['error' => 'Método no permitido.'], 405);
