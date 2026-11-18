<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../usuarios/auth.php';
require_once __DIR__ . '/../../config/database.php';

Auth::verificarRol(['admin', 'comercio']);

$turno_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = Database::getConnection();

$sql = "SELECT 
            t.*,
            COALESCE(c.nombre_comercial, 'Caja General (Admin)') as tienda_nombre,
            ua.nombre as usuario_apertura,
            uc.nombre as usuario_cierre
        FROM turnos_caja t
        LEFT JOIN comercios c ON t.comercio_id = c.id
        LEFT JOIN usuarios ua ON t.usuario_apertura_id = ua.id
        LEFT JOIN usuarios uc ON t.usuario_cierre_id = uc.id
        WHERE t.id = :id LIMIT 1";
$stmt = $db->prepare($sql);
$stmt->execute([':id' => $turno_id]);
$turno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$turno) {
    ob_end_clean();
    die("Registro de caja no encontrado.");
}

// Consultar rendición de repartidores en ese turno
$whereCom = $turno['comercio_id'] ? " AND p.comercio_id = :cid " : "";
$params = [
    ':f_ap' => $turno['fecha_apertura'],
    ':f_ci' => $turno['fecha_cierre'] ?? date('Y-m-d H:i:s')
];
if ($turno['comercio_id']) {
    $params[':cid'] = $turno['comercio_id'];
}

$sqlRep = "SELECT 
                u.nombre as repartidor,
                COUNT(p.id) as entregas,
                SUM(CASE WHEN p.metodo_pago = 'efectivo' THEN (p.monto_mando + p.costo_envio) ELSE 0 END) as efectivo_rendir
           FROM pedidos p
           INNER JOIN repartidores r ON p.repartidor_id = r.id
           INNER JOIN usuarios u ON r.usuario_id = u.id
           WHERE p.estado = 'entregado'
             AND p.fecha_creacion BETWEEN :f_ap AND :f_ci
             $whereCom
           GROUP BY r.id";
$stmtR = $db->prepare($sqlRep);
$stmtR->execute($params);
$repartidores = $stmtR->fetchAll(PDO::FETCH_ASSOC);

$difFormat = $turno['diferencia'] >= 0
    ? '+$' . number_format($turno['diferencia'], 2)
    : '-$' . number_format(abs($turno['diferencia']), 2);

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre_Caja_#' . $turno['id'] . '</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 20px; color: #232629; font-size: 11px; }
        .header { border-bottom: 2px solid #f48024; padding-bottom: 10px; margin-bottom: 16px; }
        .box { border: 1px solid #d6d9dc; padding: 10px; margin-bottom: 14px; background: #ffffff; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e3e6e8; padding: 6px 8px; text-align: left; }
        th { background: #f8f9f9; font-weight: bold; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <strong style="font-size: 16px;">' . htmlspecialchars(SITE_NAME) . '</strong><br>
        <span>Comprobante de Cierre de Caja #' . $turno['id'] . ' · <strong>' . htmlspecialchars($turno['tienda_nombre']) . '</strong></span><br>
        <small style="color:#6a737c;">
            Apertura: ' . $turno['fecha_apertura'] . ' | 
            Cierre: ' . ($turno['fecha_cierre'] ?? 'En curso') . ' | 
            Responsable: <strong>' . htmlspecialchars($turno['usuario_cierre'] ?? $turno['usuario_apertura']) . '</strong>
        </small>
    </div>

    <div class="box">
        <strong>Resumen Financiero del Turno</strong>
        <table>
            <tbody>
                <tr><td>Saldo Inicial (Apertura)</td><td class="text-right"><strong>$' . number_format($turno['monto_inicial'], 2) . '</strong></td></tr>
                <tr><td>Ingresos en Efectivo (COD)</td><td class="text-right">+$' . number_format($turno['ingresos_efectivo'], 2) . '</td></tr>
                <tr><td>Ingresos Tarjeta</td><td class="text-right">$' . number_format($turno['ingresos_tarjeta'], 2) . '</td></tr>
                <tr><td>Ingresos Transferencia</td><td class="text-right">$' . number_format($turno['ingresos_transferencia'], 2) . '</td></tr>
                <tr><td>Crédito Pendiente</td><td class="text-right">$' . number_format($turno['credito_pendiente'], 2) . '</td></tr>
                <tr><td>Egresos (Envíos Gratis / Promociones)</td><td class="text-right" style="color:#c2410c;">-$' . number_format($turno['egresos_envios_gratis'], 2) . '</td></tr>
                <tr style="background:#f8f9f9;"><td><strong>Dinero Esperado en Caja</strong></td><td class="text-right"><strong>$' . number_format($turno['dinero_esperado'], 2) . '</strong></td></tr>
                <tr><td><strong>Dinero Contado Físico</strong></td><td class="text-right"><strong>$' . number_format($turno['dinero_contado'], 2) . '</strong></td></tr>
                <tr><td><strong>Diferencia</strong></td><td class="text-right"><strong>' . $difFormat . '</strong></td></tr>
            </tbody>
        </table>
    </div>

    <div class="box">
        <strong>Rendición de Repartidores en el Turno</strong>
        <table>
            <thead>
                <tr>
                    <th>Repartidor</th>
                    <th>Entregas</th>
                    <th class="text-right">Efectivo Rendido</th>
                </tr>
            </thead>
            <tbody>';

if (empty($repartidores)) {
    $html .= '<tr><td colspan="3">Sin entregas registradas en este turno.</td></tr>';
} else {
    foreach ($repartidores as $r) {
        $html .= '<tr>
            <td>' . htmlspecialchars($r['repartidor']) . '</td>
            <td>' . $r['entregas'] . '</td>
            <td class="text-right">$' . number_format($r['efectivo_rendir'], 2) . '</td>
        </tr>';
    }
}

$html .= '</tbody></table></div>';

if (!empty($turno['notas'])) {
    $html .= '<div class="box"><strong>Notas del Cierre:</strong><p style="margin: 5px 0 0 0;">' . nl2br(htmlspecialchars($turno['notas'])) . '</p></div>';
}

$html .= '</body></html>';

// Buscar Dompdf en vendor/autoload.php
$autoloadPaths = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php'
];
$autoloadEncontrado = null;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        $autoloadEncontrado = $path;
        break;
    }
}

ob_end_clean();

if ($autoloadEncontrado) {
    require_once $autoloadEncontrado;
    if (class_exists('\Dompdf\Dompdf')) {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $dompdf->stream("Cierre_Caja_{$turno['id']}.pdf", ["Attachment" => false]);
        exit;
    }
}

// Respaldo imprimible si Dompdf no está instalado en vendor/
echo $html . '<script>window.print();</script>';