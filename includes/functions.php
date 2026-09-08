<?php
/**
 * Reglas de negocio del sistema CVB.
 * Estas funciones replican EXACTAMENTE las fórmulas del Excel de seguimiento
 * ("Dashboard_Seguimiento_Expedientes.xlsx", hoja SEGUIMIENTO) para que los
 * cálculos de etapas y estatus sean idénticos a los que ya usa el equipo.
 */

/**
 * Cuenta días hábiles (lunes a viernes) entre dos fechas, ambos extremos incluidos.
 * Equivalente a NETWORKDAYS() de Excel (sin considerar días festivos).
 */
function networkDays(?string $start, ?string $end): ?int
{
    if (!$start || !$end) {
        return null;
    }

    try {
        $d1 = new DateTime($start);
        $d2 = new DateTime($end);
    } catch (Exception $e) {
        return null;
    }

    if ($d1 > $d2) {
        [$d1, $d2] = [$d2, $d1];
    }

    $days = 0;
    $cur = clone $d1;
    while ($cur <= $d2) {
        $dow = (int) $cur->format('N'); // 1=lunes ... 7=domingo
        if ($dow < 6) {
            $days++;
        }
        $cur->modify('+1 day');
    }

    return $days;
}

/** Diferencia en días calendario (valor absoluto) entre dos fechas. */
function calendarDaysDiff(?string $start, ?string $end): ?int
{
    if (!$start || !$end) {
        return null;
    }
    try {
        $d1 = new DateTime($start);
        $d2 = new DateTime($end);
    } catch (Exception $e) {
        return null;
    }
    return abs((int) $d1->diff($d2)->days);
}

/**
 * Calcula las 4 etapas de tiempo de respuesta de un expediente.
 * Etapa 1: días calendario, Solicitud -> Entrega de Valores (mínimo 1 día)
 * Etapa 2: días hábiles, Solicitud de Ingreso -> Ingreso a Catastro
 * Etapa 3: días hábiles, Ingreso a Catastro -> Entrega a Notaría
 * Etapa 4: Total = Etapa 2 + Etapa 3
 */
function calcularEtapas(array $exp): array
{
    $etapa1 = null;
    if ($exp['fecha_solicitud_valores'] && $exp['fecha_entrega_valores']) {
        $etapa1 = max(1, calendarDaysDiff($exp['fecha_solicitud_valores'], $exp['fecha_entrega_valores']));
    }

    $etapa2 = null;
    if ($exp['fecha_solicitud_ingreso'] && $exp['fecha_ingreso_catastro']) {
        $nd = networkDays($exp['fecha_solicitud_ingreso'], $exp['fecha_ingreso_catastro']);
        $etapa2 = $nd !== null ? $nd - 1 : null;
    }

    $etapa3 = null;
    if ($exp['fecha_ingreso_catastro'] && $exp['fecha_entrega_notaria']) {
        $nd = networkDays($exp['fecha_ingreso_catastro'], $exp['fecha_entrega_notaria']);
        $etapa3 = $nd !== null ? $nd - 1 : null;
    }

    $etapa4 = null;
    if ($etapa2 !== null || $etapa3 !== null) {
        $etapa4 = ($etapa2 ?? 0) + ($etapa3 ?? 0);
    }

    return [
        'etapa1' => $etapa1,
        'etapa2' => $etapa2,
        'etapa3' => $etapa3,
        'etapa4' => $etapa4,
    ];
}

/**
 * Calcula el estatus consolidado de un expediente siguiendo el mismo
 * orden de prioridad que la columna ESTATUS_CONSOLIDADO del Excel:
 * 1) Entregado (si hay fecha de entrega a notaría)
 * 2) Estatus manual (Pagado, Detenido, etc.) si fue capturado
 * 3) En Catastro (si hay fecha de ingreso a catastro)
 * 4) En Desarrollo (si se marcó la solicitud de ingreso)
 * 5) En Cotización (caso base)
 */
function calcularEstatus(array $exp): string
{
    if (!empty($exp['fecha_entrega_notaria'])) {
        return 'Entregado';
    }
    if (!empty($exp['estatus_manual'])) {
        return $exp['estatus_manual'];
    }
    if (!empty($exp['fecha_ingreso_catastro'])) {
        return 'En Catastro';
    }
    if (!empty($exp['solicitud_ingreso_check'])) {
        return 'En Desarrollo';
    }
    return 'En Cotización';
}

/** Clase CSS de badge para un estatus dado (usado por el frontend). */
function estatusBadgeClass(string $estatus): string
{
    $map = [
        'Entregado'     => 'badge-entregado',
        'En Catastro'   => 'badge-catastro',
        'En Desarrollo' => 'badge-desarrollo',
        'En Cotización' => 'badge-cotizacion',
        'Detenido'      => 'badge-detenido',
        'Pagado'        => 'badge-pagado',
    ];
    return $map[$estatus] ?? 'badge-default';
}

/**
 * Enriquecer un registro de expediente con los campos calculados
 * (etapas y estatus) para ser devuelto por la API.
 */
function enriquecerExpediente(array $exp): array
{
    $etapas = calcularEtapas($exp);
    $estatus = calcularEstatus($exp);

    $exp['etapa1'] = $etapas['etapa1'];
    $exp['etapa2'] = $etapas['etapa2'];
    $exp['etapa3'] = $etapas['etapa3'];
    $exp['etapa4'] = $etapas['etapa4'];
    $exp['estatus'] = $estatus;
    $exp['estatus_badge'] = estatusBadgeClass($estatus);

    // Progreso del avalúo (0-3) para la barra visual de 4 pasos
    $pasoIndex = 0; // En cotización
    if ($estatus === 'En Desarrollo') $pasoIndex = 1;
    if ($estatus === 'En Catastro') $pasoIndex = 2;
    if ($estatus === 'Entregado' || $estatus === 'Pagado') $pasoIndex = 3;
    $exp['paso_progreso'] = $pasoIndex;

    return $exp;
}

/** Respuesta JSON estándar + salida. */
function jsonResponse($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Lee y decodifica el body JSON de la petición. */
function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
