<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$usuario = requerirSesion(); // cualquier rol, pero autenticado
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET':
        if (isset($_GET['id'])) {
            obtenerExpediente((int) $_GET['id'], $usuario);
        } else {
            listarExpedientes($usuario);
        }
        break;

    case 'POST':
        requerirSesion('admin');
        crearExpediente();
        break;

    case 'PUT':
        requerirSesion('admin');
        actualizarExpediente((int) ($_GET['id'] ?? 0));
        break;

    case 'DELETE':
        requerirSesion('admin');
        eliminarExpediente((int) ($_GET['id'] ?? 0));
        break;

    default:
        jsonResponse(['error' => 'Método no permitido.'], 405);
}

// ---------------------------------------------------------------------

function listarExpedientes(array $usuario): void
{
    $pdo = db();

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
    $q = trim($_GET['q'] ?? '');
    $estatusFiltro = trim($_GET['estatus'] ?? '');
    $municipioFiltro = trim($_GET['municipio'] ?? '');
    $notariaFiltro = isset($_GET['notaria_id']) ? (int) $_GET['notaria_id'] : null;

    $where = [];
    $params = [];

    // Regla de negocio clave: un cliente SOLO ve los expedientes de su notaría.
    if ($usuario['rol'] === 'cliente') {
        $where[] = 'e.notaria_id = ?';
        $params[] = $usuario['notaria_id'];
    } elseif ($notariaFiltro) {
        $where[] = 'e.notaria_id = ?';
        $params[] = $notariaFiltro;
    }

    if ($q !== '') {
        $where[] = '(e.folio LIKE ? OR e.municipio LIKE ? OR n.nombre LIKE ?)';
        $like = "%{$q}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($municipioFiltro !== '') {
        $where[] = 'e.municipio = ?';
        $params[] = $municipioFiltro;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT e.*, n.nombre AS notaria_nombre
            FROM expedientes e
            JOIN notarias n ON n.id = e.notaria_id
            {$whereSql}
            ORDER BY e.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $todos = $stmt->fetchAll();

    // Enriquecer (calcular etapas/estatus) y filtrar por estatus si aplica
    $enriquecidos = array_map('enriquecerExpediente', $todos);

    if ($estatusFiltro !== '') {
        $enriquecidos = array_values(array_filter(
            $enriquecidos,
            fn($e) => $e['estatus'] === $estatusFiltro
        ));
    }

    $total = count($enriquecidos);
    $offset = ($page - 1) * $perPage;
    $pagina = array_slice($enriquecidos, $offset, $perPage);

    jsonResponse([
        'data' => $pagina,
        'meta' => [
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $perPage,
            'total_paginas' => (int) ceil($total / $perPage),
        ],
    ]);
}

function obtenerExpediente(int $id, array $usuario): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT e.*, n.nombre AS notaria_nombre
         FROM expedientes e
         JOIN notarias n ON n.id = e.notaria_id
         WHERE e.id = ?"
    );
    $stmt->execute([$id]);
    $exp = $stmt->fetch();

    if (!$exp) {
        jsonResponse(['error' => 'Expediente no encontrado.'], 404);
    }

    if ($usuario['rol'] === 'cliente' && (int) $exp['notaria_id'] !== (int) $usuario['notaria_id']) {
        jsonResponse(['error' => 'No autorizado para ver este expediente.'], 403);
    }

    jsonResponse(['data' => enriquecerExpediente($exp)]);
}

function crearExpediente(): void
{
    $b = readJsonBody();

    $requeridos = ['folio', 'notaria_id', 'direccion'];
    foreach ($requeridos as $campo) {
        if (empty($b[$campo])) {
            jsonResponse(['error' => "El campo '{$campo}' es obligatorio."], 422);
        }
    }

    $pdo = db();
    $sql = "INSERT INTO expedientes
        (folio, notaria_id, nombre_cliente, tipo_inmueble, direccion, codigo_postal, municipio, estado,
         lat, lng, valor_referencia, valor_resultante,
         fecha_solicitud_valores, fecha_entrega_valores, solicitud_ingreso_check, fecha_solicitud_ingreso,
         fecha_ingreso_catastro, fecha_entrega_notaria, estatus_manual)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $b['folio'],
            $b['notaria_id'],
            $b['nombre_cliente'] ?? null,
            $b['tipo_inmueble'] ?? null,
            $b['direccion'],
            $b['codigo_postal'] ?? null,
            $b['municipio'] ?? null,
            $b['estado'] ?? null,
            $b['lat'] ?? null,
            $b['lng'] ?? null,
            $b['valor_referencia'] ?? null,
            $b['valor_resultante'] ?? null,
            $b['fecha_solicitud_valores'] ?? null,
            $b['fecha_entrega_valores'] ?? null,
            !empty($b['solicitud_ingreso_check']) ? 1 : 0,
            $b['fecha_solicitud_ingreso'] ?? null,
            $b['fecha_ingreso_catastro'] ?? null,
            $b['fecha_entrega_notaria'] ?? null,
            $b['estatus_manual'] ?? '',
        ]);
    } catch (PDOException $e) {
        $msg = $e->getCode() === '23000' ? 'Ya existe un expediente con ese folio.' : 'Error al guardar el expediente.';
        jsonResponse(['error' => $msg], 400);
    }

    jsonResponse(['ok' => true, 'id' => (int) $pdo->lastInsertId()], 201);
}

function actualizarExpediente(int $id): void
{
    if (!$id) {
        jsonResponse(['error' => 'ID inválido.'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM expedientes WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Expediente no encontrado.'], 404);
    }

    $b = readJsonBody();

    $campos = [
        'folio', 'notaria_id', 'nombre_cliente', 'tipo_inmueble', 'direccion', 'codigo_postal',
        'municipio', 'estado', 'lat', 'lng', 'valor_referencia', 'valor_resultante',
        'fecha_solicitud_valores', 'fecha_entrega_valores', 'solicitud_ingreso_check',
        'fecha_solicitud_ingreso', 'fecha_ingreso_catastro', 'fecha_entrega_notaria', 'estatus_manual',
    ];

    $sets = [];
    $params = [];
    foreach ($campos as $campo) {
        if (array_key_exists($campo, $b)) {
            $sets[] = "{$campo} = ?";
            $valor = $b[$campo];
            if ($campo === 'solicitud_ingreso_check') {
                $valor = !empty($valor) ? 1 : 0;
            }
            $params[] = $valor === '' ? null : $valor;
        }
    }

    if (!$sets) {
        jsonResponse(['error' => 'No se enviaron campos para actualizar.'], 422);
    }

    $params[] = $id;
    $sql = 'UPDATE expedientes SET ' . implode(', ', $sets) . ' WHERE id = ?';

    try {
        $pdo->prepare($sql)->execute($params);
    } catch (PDOException $e) {
        $msg = $e->getCode() === '23000' ? 'Ya existe un expediente con ese folio.' : 'Error al actualizar el expediente.';
        jsonResponse(['error' => $msg], 400);
    }

    jsonResponse(['ok' => true]);
}

function eliminarExpediente(int $id): void
{
    if (!$id) {
        jsonResponse(['error' => 'ID inválido.'], 422);
    }
    db()->prepare('DELETE FROM expedientes WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}
