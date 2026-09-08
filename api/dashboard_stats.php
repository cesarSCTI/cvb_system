<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requerirSesion('admin');

$stmt = db()->query('SELECT * FROM expedientes');
$todos = array_map('enriquecerExpediente', $stmt->fetchAll());

$total = count($todos);
$conteo = [
    'Entregado'     => 0,
    'En Cotización' => 0,
    'En Catastro'   => 0,
    'En Desarrollo' => 0,
    'Detenido'      => 0,
    'Pagado'        => 0,
];

$sumaEtapa1 = $sumaEtapa2 = $sumaEtapa3 = 0;
$nEtapa1 = $nEtapa2 = $nEtapa3 = 0;

foreach ($todos as $exp) {
    $estatus = $exp['estatus'];
    if (!array_key_exists($estatus, $conteo)) {
        $conteo[$estatus] = 0;
    }
    $conteo[$estatus]++;

    if ($exp['etapa1'] !== null) { $sumaEtapa1 += $exp['etapa1']; $nEtapa1++; }
    if ($exp['etapa2'] !== null) { $sumaEtapa2 += $exp['etapa2']; $nEtapa2++; }
    if ($exp['etapa3'] !== null) { $sumaEtapa3 += $exp['etapa3']; $nEtapa3++; }
}

jsonResponse([
    'total'      => $total,
    'entregados' => $conteo['Entregado'],
    'cotizacion' => $conteo['En Cotización'],
    'catastro'   => $conteo['En Catastro'],
    'desarrollo' => $conteo['En Desarrollo'],
    'detenidos'  => $conteo['Detenido'],
    'pagados'    => $conteo['Pagado'] ?? 0,
    'distribucion_estatus' => $conteo,
    'dias_promedio' => [
        'etapa1' => $nEtapa1 ? round($sumaEtapa1 / $nEtapa1, 1) : 0,
        'etapa2' => $nEtapa2 ? round($sumaEtapa2 / $nEtapa2, 1) : 0,
        'etapa3' => $nEtapa3 ? round($sumaEtapa3 / $nEtapa3, 1) : 0,
    ],
]);
