<?php
// 1. Cargar dependencias y verificar permisos ANTES de cualquier salida
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../usuarios/auth.php';
require_once __DIR__ . '/../../config/database.php';

// PERMISOS: Ahora Admin y Comercio pueden ingresar
Auth::verificarRol(['admin', 'comercio']);

require_once __DIR__ . '/../../layout/header.php';

$rol = $_SESSION['usuario_rol'];
$usuario_id = $_SESSION['usuario_id'];

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

$db = Database::getConnection();

// Si el usuario es comercio, identificamos su ID de comercio
$comercio_id = null;
if ($rol === 'comercio') {
    $stmtCom = $db->prepare("SELECT id FROM comercios WHERE usuario_id = :uid LIMIT 1");
    $stmtCom->execute([':uid' => $usuario_id]);
    $comercio_id = $stmtCom->fetchColumn() ?: null;
}

// 2. Consulta SQL Dinámica con filtro de comercio
if ($rol === 'comercio' && $comercio_id) {
    // Para Comercio: filtra solo los envíos entregados de sus repartidores
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
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h2>Liquidaciones y Cierre Diario <?= $rol === 'comercio' ? '(Mis Repartidores)' : '' ?></h2>
    
    <a href="exportar_pdf.php?fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
        📄 Exportar Reporte
    </a>
</div>

<!-- Filtros de Fecha -->
<div class="card" style="margin-bottom: 20px;">
    <form action="" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0;">
            <label for="fecha_inicio">Desde:</label>
            <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio) ?>">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="fecha_fin">Hasta:</label>
            <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>">
        </div>

        <button type="submit" class="btn">Filtrar Cierre</button>
        <a href="index.php" class="btn" style="background-color:#f3f4f6; color:#374151;">Hoy</a>
    </form>
</div>

<!-- Tarjetas Resumen -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="text-align: center; border-left: 4px solid var(--so-orange);">
        <small style="color: var(--so-gray-text);">Total Envíos Entregados</small>
        <h3 style="font-size: 1.5rem; margin-top: 5px;"><?= $totalEnvios ?></h3>
    </div>
    
    <div class="card" style="text-align: center; border-left: 4px solid #0e9f6e;">
        <small style="color: var(--so-gray-text);">Monto Total Recaudado</small>
        <h3 style="font-size: 1.5rem; margin-top: 5px; color: #0e9f6e;">$<?= number_format($totalRecaudado, 2) ?></h3>
    </div>

    <div class="card" style="text-align: center; border-left: 4px solid #3f83f8;">
        <small style="color: var(--so-gray-text);">Pago Comisiones Repartidor</small>
        <h3 style="font-size: 1.5rem; margin-top: 5px; color: #1c64f2;">$<?= number_format($totalComisiones, 2) ?></h3>
    </div>
</div>

<!-- Tabla de Liquidaciones -->
<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Repartidor</th>
                    <th>Envíos Entregados</th>
                    <th>Monto Recaudado Total</th>
                    <th>Pago Comisión</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($liquidaciones)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--so-gray-text);">No existen órdenes entregadas registradas en las fechas seleccionadas.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($liquidaciones as $l): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($l['fecha']) ?></code></td>
                            <td>
                                <strong><?= htmlspecialchars($l['repartidor_nombre']) ?></strong>
                                <small style="color: var(--so-gray-text);"> (<?= htmlspecialchars($l['vehiculo']) ?>)</small>
                            </td>
                            <td><strong><?= $l['envios_entregados'] ?></strong> pedidos</td>
                            <td><strong>$<?= number_format($l['monto_recaudado'], 2) ?></strong></td>
                            <td style="color: #0e9f6e; font-weight: bold;">$<?= number_format($l['pago_comision'], 2) ?></td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 0.75rem; background-color: #def7ec; color: #03543f;">
                                    LIQUIDADO
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>