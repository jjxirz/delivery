<?php
require_once __DIR__ . '/../../layout/header.php';
require_once __DIR__ . '/../usuarios/auth.php';
require_once __DIR__ . '/Liquidacion.php';
require_once __DIR__ . '/../repartidores/Repartidor.php';

Auth::verificarRol(['admin']);

$mensaje = '';
$tipo_mensaje = '';

// Parámetros de filtro
$fecha_hoy = date('Y-m-d');
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? $fecha_hoy;

// Procesar Liquidación Manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'liquidar') {
    $repartidor_id = (int)$_POST['repartidor_id'];
    $fecha_liquidar = $_POST['fecha_liquidar'];

    if (!empty($repartidor_id) && !empty($fecha_liquidar)) {
        $res = Liquidacion::procesarLiquidacion($repartidor_id, $fecha_liquidar);
        if ($res === true) {
            $mensaje = "Liquidación procesada correctamente.";
            $tipo_mensaje = "exito";
        } else {
            $mensaje = $res;
            $tipo_mensaje = "error";
        }
    }
}

$repartidores = Repartidor::listar();
$liquidaciones = Liquidacion::listarLiquidaciones($fecha_inicio, $fecha_fin);

// Totales acumulados para métricas rápidas
$total_recaudado_acumulado = array_sum(array_column($liquidaciones, 'monto_recaudado'));
$total_comisiones_acumulado = array_sum(array_column($liquidaciones, 'pago_comision'));
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h2>Control de Liquidaciones y Reportes</h2>
    <button onclick="window.print();" class="btn btn-primary">🖨️ Imprimir / Exportar Reporte</button>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="padding: 10px; margin-bottom: 15px; border-radius: 3px; font-size: 0.9rem; 
                background-color: <?= $tipo_mensaje === 'exito' ? '#eef9f2' : '#fdf2f2' ?>; 
                border: 1px solid <?= $tipo_mensaje === 'exito' ? '#bcf0da' : '#f8b4b4' ?>; 
                color: <?= $tipo_mensaje === 'exito' ? '#0e9f6e' : '#9b1c1c' ?>;">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<!-- Tarjetas Métricas Rápidas -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="margin-bottom: 0;">
        <span style="font-size: 0.8rem; color: var(--so-gray-text);">Total Recaudado (Filtro)</span>
        <h3 style="font-size: 1.5rem; color: #03543f;">$<?= number_format($total_recaudado_acumulado, 2) ?></h3>
    </div>
    <div class="card" style="margin-bottom: 0;">
        <span style="font-size: 0.8rem; color: var(--so-gray-text);">Comisiones Pagadas</span>
        <h3 style="font-size: 1.5rem; color: var(--so-orange);">$<?= number_format($total_comisiones_acumulado, 2) ?></h3>
    </div>
</div>

<!-- Formulario para Calcular Cierre Diario -->
<div class="card" style="margin-bottom: 25px;">
    <h3>Generar Cierre de Caja Diario por Repartidor</h3>
    <form action="" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; margin-top: 15px;">
        <input type="hidden" name="action" value="liquidar">

        <div class="form-group" style="margin-bottom: 0;">
            <label for="repartidor_id">Seleccionar Repartidor</label>
            <select name="repartidor_id" id="repartidor_id" class="form-control" required>
                <option value="">Seleccione...</option>
                <?php foreach ($repartidores as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?> (<?= $r['vehiculo'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="fecha_liquidar">Fecha de Jornada</label>
            <input type="date" name="fecha_liquidar" id="fecha_liquidar" class="form-control" value="<?= $fecha_hoy ?>" required>
        </div>

        <div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Procesar Liquidación</button>
        </div>
    </form>
</div>

<!-- Filtro de Reporte y Tabla Historica -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
        <h3>Historial de Liquidaciones</h3>
        <form action="" method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>" class="form-control" style="font-size: 0.85rem;">
            <span style="font-size: 0.85rem; color: var(--so-gray-text);">a</span>
            <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>" class="form-control" style="font-size: 0.85rem;">
            <button type="submit" class="btn" style="padding: 7px 12px;">Filtrar</button>
        </form>
    </div>

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
                        <td colspan="6" style="text-align: center; color: var(--so-gray-text);">No hay registros de liquidación en el rango seleccionado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($liquidaciones as $l): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($l['fecha']) ?></code></td>
                            <td><strong><?= htmlspecialchars($l['repartidor_nombre']) ?></strong> (<?= htmlspecialchars($l['vehiculo']) ?>)</td>
                            <td><?= $l['total_envios'] ?> pedidos</td>
                            <td>$<?= number_format($l['monto_recaudado'], 2) ?></td>
                            <td><strong style="color: var(--so-orange);">$<?= number_format($l['pago_comision'], 2) ?></strong></td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 0.75rem; background-color: #def7ec; color: #03543f;">
                                    <?= strtoupper($l['estado_pago']) ?>
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