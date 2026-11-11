<?php
// Evitar que salidas de texto previas corrompan el archivo PDF
ob_start();

ini_set('memory_limit', '256M');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../usuarios/auth.php';
require_once __DIR__ . '/../../config/database.php';

// 1. PERMISOS: Permitir acceso a Admin y Comercio
Auth::verificarRol(['admin', 'comercio']);

$autoloader = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloader)) {
    die("Error: No se encontró el autoloader de Composer en $autoloader. Por favor ejecuta 'composer require dompdf/dompdf' en la raíz del proyecto.");
}

require_once $autoloader;

use Dompdf\Dompdf;
use Dompdf\Options;

$rol = $_SESSION['usuario_rol'];
$usuario_id = $_SESSION['usuario_id'];

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

$db = Database::getConnection();

// 2. Obtener comercio_id si el usuario es Comercio
$comercio_id = null;
if ($rol === 'comercio') {
    $stmtCom = $db->prepare("SELECT id FROM comercios WHERE usuario_id = :uid LIMIT 1");
    $stmtCom->execute([':uid' => $usuario_id]);
    $comercio_id = $stmtCom->fetchColumn() ?: null;
}

// 3. Consulta SQL Adaptada según el Rol
if ($rol === 'comercio' && $comercio_id) {
    // Para Comercio: filtra únicamente los envíos entregados de sus repartidores
    $sql = "SELECT 
                DATE(p.fecha_creacion) as fecha,
                u.nombre as repartidor_nombre,
                r.vehiculo,
                COUNT(p.id) as envios_entregados,
                SUM(p.monto_mando + p.costo_envio) as monto_recaudado,
                SUM(p.costo_envio) as pago_comision
            FROM pedidos p
            INNER JOIN repartidores r ON p.repartidor_id = r.id
            INNER JOIN usuarios u ON r.usuario_id = u.id
            WHERE p.estado = 'entregado'
              AND r.comercio_id = :comercio_id
              AND DATE(p.fecha_creacion) BETWEEN :f_inicio AND :f_fin
            GROUP BY DATE(p.fecha_creacion), r.id
            ORDER BY fecha DESC, u.nombre ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':comercio_id' => $comercio_id,
        ':f_inicio' => $fecha_inicio,
        ':f_fin' => $fecha_fin
    ]);
} else {
    // Para Admin: liquida a todos los repartidores
    $sql = "SELECT 
                DATE(p.fecha_creacion) as fecha,
                u.nombre as repartidor_nombre,
                r.vehiculo,
                COUNT(p.id) as envios_entregados,
                SUM(p.monto_mando + p.costo_envio) as monto_recaudado,
                SUM(p.costo_envio) as pago_comision
            FROM pedidos p
            INNER JOIN repartidores r ON p.repartidor_id = r.id
            INNER JOIN usuarios u ON r.usuario_id = u.id
            WHERE p.estado = 'entregado'
              AND DATE(p.fecha_creacion) BETWEEN :f_inicio AND :f_fin
            GROUP BY DATE(p.fecha_creacion), r.id
            ORDER BY fecha DESC, u.nombre ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute([':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin]);
}

$liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales acumulados
$totalEnvios = 0;
$totalRecaudado = 0;
$totalComisiones = 0;

foreach ($liquidaciones as $l) {
    $totalEnvios += $l['envios_entregados'];
    $totalRecaudado += $l['monto_recaudado'];
    $totalComisiones += $l['pago_comision'];
}

// Plantilla HTML del PDF
$subtituloRol = $rol === 'comercio' ? '(Mis Repartidores)' : '';

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Liquidación - ' . SITE_NAME . '</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #333; margin: 15px; }
        .header { border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; margin-bottom: 15px; }
        .title { font-size: 18px; font-weight: bold; color: #2c3e50; }
        .subtitle { font-size: 11px; color: #7f8c8d; margin-top: 3px; }
        .summary-table { width: 100%; margin-bottom: 15px; border-spacing: 5px; }
        .summary-card { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 10px; text-align: center; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th { background-color: #34495e; color: #ffffff; text-align: left; padding: 8px; font-size: 11px; }
        table.data-table td { border-bottom: 1px solid #eeeeee; padding: 8px; font-size: 11px; }
        .text-right { text-align: right; }
        .badge { background-color: #e8f5e9; color: #2e7d32; padding: 2px 6px; border-radius: 3px; font-weight: bold; font-size: 10px; }
        .footer { margin-top: 25px; text-align: center; font-size: 10px; color: #95a5a6; border-top: 1px solid #eeeeee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">' . SITE_NAME . '</div>
        <div class="subtitle">Reporte General de Liquidaciones y Cierre Diario ' . $subtituloRol . '</div>
        <div class="subtitle">Período: <strong>' . $fecha_inicio . '</strong> al <strong>' . $fecha_fin . '</strong></div>
    </div>

    <table class="summary-table">
        <tr>
            <td class="summary-card"><strong>Envíos Entregados:</strong><br>' . $totalEnvios . '</td>
            <td class="summary-card"><strong>Recaudado Total:</strong><br>$' . number_format($totalRecaudado, 2) . '</td>
            <td class="summary-card"><strong>Comisiones Repartidores:</strong><br>$' . number_format($totalComisiones, 2) . '</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Repartidor / Vehículo</th>
                <th class="text-right">Entregados</th>
                <th class="text-right">Recaudado</th>
                <th class="text-right">Comisión</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>';

if (empty($liquidaciones)) {
    $html .= '<tr><td colspan="6" style="text-align:center; padding:20px; color:#777;">No existen envíos entregados en el rango seleccionado.</td></tr>';
} else {
    foreach ($liquidaciones as $row) {
        $html .= '<tr>
            <td>' . $row['fecha'] . '</td>
            <td><strong>' . htmlspecialchars($row['repartidor_nombre']) . '</strong> (' . htmlspecialchars($row['vehiculo']) . ')</td>
            <td class="text-right">' . $row['envios_entregados'] . ' pedidos</td>
            <td class="text-right">$' . number_format($row['monto_recaudado'], 2) . '</td>
            <td class="text-right">$' . number_format($row['pago_comision'], 2) . '</td>
            <td><span class="badge">LIQUIDADO</span></td>
        </tr>';
    }
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        Documento generado por ' . SITE_NAME . ' el ' . date('Y-m-d H:i:s') . '
    </div>
</body>
</html>';

// 4. Limpiar cualquier salida residual del búfer de PHP para que Dompdf no falle
if (ob_get_length()) {
    ob_end_clean();
}

// Configuración y Renderizado de Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Forzar visualización directa/descarga limpia del PDF
$dompdf->stream("Liquidacion_" . $fecha_inicio . "_" . $fecha_fin . ".pdf", ["Attachment" => false]);
exit;