<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modules/usuarios/auth.php';
require_once __DIR__ . '/config/database.php';

// Verificar sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}


$rol = $_SESSION['usuario_rol'];
$usuario_id = $_SESSION['usuario_id'];
$db = Database::getConnection();
$mensaje = '';

if ($rol === 'repartidor') {
    header('Location: ' . BASE_URL . 'modules/pedidos/index.php');
    exit;
}

// Si es comercio, obtener su comercio_id
$comercio_id = null;
if ($rol === 'comercio') {
    $stmtCom = $db->prepare("SELECT id FROM comercios WHERE usuario_id = :uid LIMIT 1");
    $stmtCom->execute([':uid' => $usuario_id]);
    $comercio_id = $stmtCom->fetchColumn() ?: null;
}

// Procesar Acciones Rápidas desde el Widget "Últimos envíos"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_rapida'])) {
    $pedido_id = (int)$_POST['pedido_id'];
    $nuevo_estado = $_POST['accion_rapida'] === 'entregar' ? 'entregado' : 'cancelado';
    
    require_once __DIR__ . '/modules/pedidos/Pedido.php';
    if (Pedido::actualizarEstado($pedido_id, $nuevo_estado)) {
        $mensaje = "Pedido actualizado a " . strtoupper($nuevo_estado) . ".";
    }
}

// Filtro de periodo para las gráficas ('semana' o 'mes')
$periodo = $_GET['periodo'] ?? 'mes';
$diasFiltro = $periodo === 'semana' ? 7 : 30;
$fechaDesde = date('Y-m-d', strtotime("-$diasFiltro days"));

// Filtro SQL base según el rol
$whereRol = "";
$paramsRol = [];
if ($rol === 'comercio' && $comercio_id) {
    $whereRol = " AND p.comercio_id = :comercio_id ";
    $paramsRol[':comercio_id'] = $comercio_id;
} elseif ($rol === 'repartidor') {
    $stmtRep = $db->prepare("SELECT id FROM repartidores WHERE usuario_id = :uid LIMIT 1");
    $stmtRep->execute([':uid' => $usuario_id]);
    $rep_id = $stmtRep->fetchColumn() ?: 0;
    $whereRol = " AND p.repartidor_id = :rep_id ";
    $paramsRol[':rep_id'] = $rep_id;
}

$hoy = date('Y-m-d');

// =========================================================================
// 1. CONSULTAS PARA LAS 4 TARJETAS KPI (ESTILO STACKOVERFLOW)
// =========================================================================

// KPI 1: Pedidos Hoy
$sqlHoy = "SELECT COUNT(*) FROM pedidos p WHERE DATE(p.fecha_creacion) = :hoy $whereRol";
$stmt = $db->prepare($sqlHoy);
$stmt->execute(array_merge([':hoy' => $hoy], $paramsRol));
$pedidosHoy = (int)$stmt->fetchColumn();

// KPI 2: Total Entregado Hoy ($)
$sqlDineroHoy = "SELECT COALESCE(SUM(p.monto_mando + p.costo_envio), 0) 
                 FROM pedidos p 
                 WHERE p.estado = 'entregado' AND DATE(p.fecha_creacion) = :hoy $whereRol";
$stmt = $db->prepare($sqlDineroHoy);
$stmt->execute(array_merge([':hoy' => $hoy], $paramsRol));
$totalEntregadoHoy = (float)$stmt->fetchColumn();

// KPI 3: Pendientes por Entregar (pendiente, asignado, en_camino)
$sqlPendientes = "SELECT COUNT(*) FROM pedidos p 
                  WHERE p.estado IN ('pendiente', 'asignado', 'en_camino') $whereRol";
$stmt = $db->prepare($sqlPendientes);
$stmt->execute($paramsRol);
$pendientesEntregar = (int)$stmt->fetchColumn();

// KPI exclusivo para comercios: pedidos asignados o en camino
$enRutaComercio = 0;
if ($rol === 'comercio' && $comercio_id) {
    $stmtRutaCom = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE comercio_id = :cid AND estado IN ('asignado', 'en_camino')");
    $stmtRutaCom->execute([':cid' => (int)$comercio_id]);
    $enRutaComercio = (int)$stmtRutaCom->fetchColumn();
}

// KPI adicional para repartidores: efectivo COD de pedidos entregados hoy
$efectivoRendirHoy = 0.0;
if ($rol === 'repartidor') {
    $sqlEfectivoRendir = "SELECT COALESCE(SUM(p.monto_mando + p.costo_envio), 0)
                          FROM pedidos p
                          WHERE p.estado = 'entregado'
                            AND p.metodo_pago = 'efectivo'
                            AND DATE(p.fecha_creacion) = :hoy $whereRol";
    $stmt = $db->prepare($sqlEfectivoRendir);
    $stmt->execute(array_merge([':hoy' => $hoy], $paramsRol));
    $efectivoRendirHoy = (float)$stmt->fetchColumn();
}

// KPI 4 (Solo Admin): Tienda con mayor cantidad de pedidos en el mes
$topTienda = ['nombre' => 'Sin datos', 'total' => 0];
if ($rol === 'admin') {
    $sqlTopTienda = "SELECT c.nombre_comercial, COUNT(p.id) as total 
                     FROM pedidos p 
                     INNER JOIN comercios c ON p.comercio_id = c.id 
                     WHERE DATE(p.fecha_creacion) >= :desde 
                     GROUP BY c.id 
                     ORDER BY total DESC LIMIT 1";
    $stmt = $db->prepare($sqlTopTienda);
    $stmt->execute([':desde' => $fechaDesde]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $topTienda = ['nombre' => $row['nombre_comercial'], 'total' => (int)$row['total']];
    }
}

// =========================================================================
// 2. DATOS PARA GRÁFICAS DINÁMICAS
// =========================================================================

// Gráfica A (Solo Admin): Comparación de Compañías / Comercios con más envíos
$datosComercios = [];
if ($rol === 'admin') {
    $sqlComp = "SELECT c.nombre_comercial as label, COUNT(p.id) as total 
                FROM comercios c 
                LEFT JOIN pedidos p ON c.id = p.comercio_id AND DATE(p.fecha_creacion) >= :desde
                GROUP BY c.id 
                ORDER BY total DESC LIMIT 6";
    $stmt = $db->prepare($sqlComp);
    $stmt->execute([':desde' => $fechaDesde]);
    $datosComercios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Gráfica B (Admin y Comercio): Repartidores con mayores entregas
$datosRepartidores = [];
if (in_array($rol, ['admin', 'comercio'])) {
    $whereRepCom = ($rol === 'comercio' && $comercio_id) ? " WHERE r.comercio_id = :comercio_id " : "";
    $sqlRep = "SELECT u.nombre as label, COUNT(p.id) as total 
               FROM repartidores r 
               INNER JOIN usuarios u ON r.usuario_id = u.id 
               LEFT JOIN pedidos p ON r.id = p.repartidor_id AND p.estado = 'entregado' AND DATE(p.fecha_creacion) >= :desde
               $whereRepCom
               GROUP BY r.id 
               ORDER BY total DESC LIMIT 6";
    $stmt = $db->prepare($sqlRep);
    $paramsRep = [':desde' => $fechaDesde];
    if ($rol === 'comercio' && $comercio_id) {
        $paramsRep[':comercio_id'] = $comercio_id;
    }
    $stmt->execute($paramsRep);
    $datosRepartidores = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Gráfica C (Todos): Tipos / Categorías de Paquetes más enviados
$sqlPaquetes = "SELECT COALESCE(NULLIF(TRIM(p.descripcion_paquete), ''), 'Paquete estándar') as label, COUNT(p.id) as total 
                FROM pedidos p 
                WHERE DATE(p.fecha_creacion) >= :desde $whereRol
                GROUP BY label 
                ORDER BY total DESC LIMIT 5";
$stmt = $db->prepare($sqlPaquetes);
$stmt->execute(array_merge([':desde' => $fechaDesde], $paramsRol));
$datosPaquetes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================================================================
// 3. WIDGET DERECHO: ÚLTIMOS ENVÍOS
// =========================================================================
$sqlUltimos = "SELECT p.*, c.nombre_comercial, u.nombre as repartidor_nombre 
               FROM pedidos p 
               INNER JOIN comercios c ON p.comercio_id = c.id 
               LEFT JOIN repartidores r ON p.repartidor_id = r.id 
               LEFT JOIN usuarios u ON r.usuario_id = u.id 
               WHERE 1=1 $whereRol
               ORDER BY p.id DESC LIMIT 7";
$stmt = $db->prepare($sqlUltimos);
$stmt->execute($paramsRol);
$ultimosEnvios = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/layout/header.php';
?>

<!-- Chart.js CDN ligero para gráficas limpias -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Estilos específicos Flat UI StackOverflow para el Dashboard */
    .so-kpi-grid {  
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }

    .so-kpi-grid.rol-comercio,
    .so-kpi-grid.rol-repartidor {
        grid-template-columns: repeat(4, 1fr);
    }

    .so-kpi-box {
        background: #ffffff;
        border: 1px solid #d6d9dc;
        border-radius: 3px;
        padding: 16px;
        gap: 4px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .so-kpi-box.highlight-orange {
        border-top: 3px solid var(--so-orange);
    }

    .so-kpi-value {
        font-size: 1.65rem;
        font-weight: 700;
        color: #0c0d0e;
        line-height: 1.2;
        margin: 0;
    }

    .so-kpi-label {
        font-size: 0.78rem;
        color: #6a737c;
        font-weight: 500;
    }

    .so-kpi-sub {
        font-size: 0.72rem;
        color: #3b4045;
        background: #f1f2f3;
        padding: 2px 6px;
        border-radius: 3px;
        display: inline-block;
        margin-top: 8px;
        width: fit-content;
    }

    .so-dashboard-split {
        display: grid;
        grid-template-columns: 1.35fr 1fr;
        gap: 20px;
        align-items: start;
    }

    @media (max-width: 1050px) {
        .so-dashboard-split {
            grid-template-columns: 1fr;
        }
    }

    .so-panel {
        background: #ffffff;
        border: 1px solid #d6d9dc;
        border-radius: 3px;
        padding: 16px;
    }

    .so-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e3e6e8;
        padding-bottom: 10px;
        margin-bottom: 14px;
    }

    .so-panel-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: #232629;
        margin: 0;
    }

    /* Lista de Últimos Envíos estilo feed de preguntas SO */
    .so-shipment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #e3e6e8;
    }

    .so-shipment-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .so-tag {
        display: inline-block;
        padding: 2px 6px;
        font-size: 0.72rem;
        border-radius: 3px;
        background-color: #e1ecf4;
        color: #39739d;
        border: 1px solid #e1ecf4;
        font-weight: 500;
    }

    .so-tag-status-entregado { background-color: #def7ec; color: #03543f; border-color: #bcf0da; }
    .so-tag-status-cancelado { background-color: #fde8e8; color: #9b1c1c; border-color: #f8b4b4; }
    .so-tag-status-pendiente { background-color: #feecdc; color: #9a3412; border-color: #fcd9bd; }

    /* Botones cuadrados de acción */
    .so-sq-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }

    .btn-sq {
        width: 28px;
        height: 28px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #d6d9dc;
        background: #f8f9f9;
        color: #3b4045;
        border-radius: 3px;
        cursor: pointer;
        font-size: 0.85rem;
        text-decoration: none;
        transition: all 0.12s;
    }

    .btn-sq:hover {
        background: #e3e6e8;
        color: #0c0d0e;
    }

    .btn-sq-success:hover { background: #def7ec; border-color: #84e1bc; color: #03543f; }
    .btn-sq-danger:hover  { background: #fde8e8; border-color: #f8b4b4; color: #9b1c1c; }
    .btn-sq-map:hover     { background: #e1ecf4; border-color: #7aa7c7; color: #2c5877; }

    @media (max-width: 900px) {
    .so-kpi-grid,
    .so-kpi-grid.rol-comercio,
    .so-kpi-grid.rol-repartidor {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 560px) {
    .so-kpi-grid,
    .so-kpi-grid.rol-comercio,
    .so-kpi-grid.rol-repartidor {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Encabezado de Bienvenida -->
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h1 style="font-size: 1.45rem; font-weight: 600; color: #0c0d0e; margin: 0 0 4px 0;">Panel de Control</h1>
        <p style="font-size: 0.85rem; color: #6a737c; margin: 0;">Bienvenido al sistema de administración de envíos locales.</p>
    </div>

    <!-- Selector de Periodo para Estadísticas -->
    <div style="display: flex; border: 1px solid #9fa6ad; border-radius: 3px; overflow: hidden;">
        <a href="?periodo=semana" style="padding: 5px 12px; font-size: 0.78rem; text-decoration: none; <?= $periodo === 'semana' ? 'background:#e3e6e8; color:#0c0d0e; font-weight:600;' : 'background:#fff; color:#6a737c;' ?>">
            7 días
        </a>
        <a href="?periodo=mes" style="padding: 5px 12px; font-size: 0.78rem; text-decoration: none; border-left: 1px solid #9fa6ad; <?= $periodo === 'mes' ? 'background:#e3e6e8; color:#0c0d0e; font-weight:600;' : 'background:#fff; color:#6a737c;' ?>">
            30 días
        </a>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert-banner success" role="status">
        <div class="alert-banner-message">
            <?= Apariencia::icono('auditoria') ?>
            <span><?= htmlspecialchars($mensaje) ?></span>
        </div>
        <button type="button" class="alert-banner-dismiss" aria-label="Cerrar aviso" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- 1. TARJETAS INDICADORAS (KPIs ESTILO STACKOVERFLOW)                        -->
<!-- ========================================================================= -->
<div class="so-kpi-grid <?= $rol === 'comercio' ? 'rol-comercio' : ($rol === 'repartidor' ? 'rol-repartidor' : '') ?>">
    <div class="so-kpi-box highlight-orange">
        <span class="so-kpi-label">Pedidos hoy</span>
        <div class="so-kpi-value"><?= number_format($pedidosHoy) ?></div>
        <span class="so-kpi-sub">Órdenes registradas hoy</span>
    </div>

    <div class="so-kpi-box">
        <span class="so-kpi-label">Total entregado hoy</span>
        <div class="so-kpi-value" style="color: #2f6f44;">$<?= number_format($totalEntregadoHoy, 2) ?></div>
        <span class="so-kpi-sub">Recaudación completada</span>
    </div>

    <div class="so-kpi-box">
        <span class="so-kpi-label">Pendiente por entregar</span>
        <div class="so-kpi-value" style="color: #b45309;"><?= number_format($pendientesEntregar) ?></div>
        <span class="so-kpi-sub">En ruta o por asignar</span>
    </div>

    <?php if ($rol === 'admin'): ?>
        <div class="so-kpi-box">
            <span class="so-kpi-label">Tienda con mayor pedidos</span>
            <div class="so-kpi-value" style="font-size: 1.15rem; margin: 8px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($topTienda['nombre']) ?>">
                <?= htmlspecialchars($topTienda['nombre']) ?>
            </div>
            <span class="so-kpi-sub"><?= $topTienda['total'] ?> envíos (<?= $diasFiltro ?>d)</span>
        </div>
    <?php elseif ($rol === 'repartidor'): ?>
        <div class="so-kpi-box" style="border-top: 3px solid var(--so-orange);">
            <span class="so-kpi-label">Efectivo a Liquidar Hoy</span>
            <strong class="so-kpi-value">$<?= number_format($efectivoRendirHoy, 2) ?></strong>
            <span class="so-kpi-sub">Cobros COD entregados hoy</span>
        </div>
    <?php elseif ($rol === 'comercio'): ?>
        <div class="so-kpi-box">
            <span class="so-kpi-label">En Camino Ahora</span>
            <strong class="so-kpi-value"><?= $enRutaComercio ?></strong>
            <span class="so-kpi-sub">Despachos activos en ruta</span>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- 2. CONTENIDO PRINCIPAL: GRÁFICAS (IZQUIERDA) + ÚLTIMOS ENVÍOS (DERECHA)   -->
<!-- ========================================================================= -->
<div class="so-dashboard-split">
    
    <!-- COLUMNA IZQUIERDA: GRÁFICAS DINÁMICAS -->
    <div style="display: flex; flex-direction: column; gap: 18px;">
        
        <?php if ($rol === 'admin'): ?>
            <!-- Gráfica 1 (Admin): Comparación de Compañías / Comercios -->
            <div class="so-panel">
                <div class="so-panel-header">
                    <h3 class="so-panel-title">Comparativa de Comercios por Envíos (Últimos <?= $diasFiltro ?> días)</h3>
                    <span class="so-tag">Empresas</span>
                </div>
                <div style="height: 220px;">
                    <canvas id="chartComercios"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <?php if (in_array($rol, ['admin', 'comercio'])): ?>
            <!-- Gráfica 2 (Admin y Comercio): Repartidores con mayores entregas -->
            <div class="so-panel">
                <div class="so-panel-header">
                    <h3 class="so-panel-title">Repartidores con Mayores Entregas (Últimos <?= $diasFiltro ?> días)</h3>
                    <span class="so-tag">Rendimiento</span>
                </div>
                <div style="height: 210px;">
                    <canvas id="chartRepartidores"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <!-- Gráfica 3: Tipos / Categorías de Paquetes más entregados -->
        <div class="so-panel">
            <div class="so-panel-header">
                <h3 class="so-panel-title">Categorías / Paquetes Más Enviados</h3>
                <span class="so-tag">Volumen</span>
            </div>
            <div style="height: 200px;">
                <canvas id="chartPaquetes"></canvas>
            </div>
        </div>
    </div>

    <!-- COLUMNA DERECHA: ÚLTIMOS ENVÍOS | VER TODAS -> -->
    <div class="so-panel">
        <div class="so-panel-header">
            <h3 class="so-panel-title">Últimos envíos</h3>
            <a href="<?= BASE_URL ?>modules/pedidos/index.php" style="font-size: 0.8rem; color: #0074cc; text-decoration: none; font-weight: 500;">
                Ver todas &rarr;
            </a>
        </div>

        <?php if (empty($ultimosEnvios)): ?>
            <p style="text-align: center; color: #6a737c; font-size: 0.85rem; padding: 20px 0;">No hay envíos registrados recientemente.</p>
        <?php else: ?>
            <div>
                <?php foreach ($ultimosEnvios as $env): ?>
                    <?php
                    $totalCobrar = (float)$env['monto_mando'] + (float)$env['costo_envio'];
                    $statusClass = 'so-tag-status-pendiente';
                    if ($env['estado'] === 'entregado') $statusClass = 'so-tag-status-entregado';
                    if ($env['estado'] === 'cancelado') $statusClass = 'so-tag-status-cancelado';

                    // Coordenadas de prueba (Origen San Salvador -> Destino simulado o guardado)
                    $latDest = !empty($env['latitud']) ? $env['latitud'] : '13.701200';
                    $lngDest = !empty($env['longitud']) ? $env['longitud'] : '-89.224400';
                    $googleMapsUrl = "https://www.google.com/maps/dir/?api=1&origin=13.692940,-89.218190&destination={$latDest},{$lngDest}&travelmode=driving";
                    ?>
                    <div class="so-shipment-item">
                        <!-- Info Izquierda -->
                        <div style="min-width: 0; flex: 1;">
                            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px; flex-wrap: wrap;">
                                <code style="font-size: 0.75rem; background: #f1f2f3; padding: 1px 5px; border-radius: 3px;"><?= htmlspecialchars($env['codigo_seguimiento']) ?></code>
                                <span class="so-tag <?= $statusClass ?>"><?= htmlspecialchars(Apariencia::formatearEstado($env['estado'])) ?></span>
                                <span style="font-size: 0.82rem; font-weight: 700; color: #2f6f44;">$<?= number_format($totalCobrar, 2) ?></span>
                            </div>
                            
                            <div style="font-size: 0.85rem; font-weight: 600; color: #0c0d0e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($env['descripcion_paquete'] ?? 'Paquete estándar') ?>
                                <span style="font-weight: 400; color: #6a737c;">→ <?= htmlspecialchars($env['cliente_nombre']) ?></span>
                            </div>

                            <div style="font-size: 0.75rem; color: #6a737c; margin-top: 2px;">
                                De: <strong><?= htmlspecialchars($env['nombre_comercial']) ?></strong>
                                <?php if (!empty($env['repartidor_nombre'])): ?>
                                    · <?= Apariencia::icono('repartidores') ?> <?= htmlspecialchars($env['repartidor_nombre']) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 4 Botones Cuadrados de Acción Rápida -->
                        <div class="so-sq-actions">
                            <!-- 1. Botón Ver Detalle (Ojo) -->
                            <button type="button" class="btn-sq" title="Ver detalles del envío"
                                onclick='verDetalleEnvio(<?= json_encode([
                                    "codigo" => $env["codigo_seguimiento"],
                                    "comercio" => $env["nombre_comercial"],
                                    "cliente" => $env["cliente_nombre"],
                                    "telefono" => $env["cliente_telefono"],
                                    "direccion" => $env["cliente_direccion"],
                                    "paquete" => $env["descripcion_paquete"] ?? "Paquete estándar",
                                    "total" => number_format($totalCobrar, 2),
                                    "metodo" => strtoupper($env["metodo_pago"] ?? "EFECTIVO"),
                                    "estado" => strtoupper($env["estado"]),
                                    "repartidor" => $env["repartidor_nombre"] ?? "Sin asignar",
                                    "mapa" => $googleMapsUrl
                                ]) ?>)'>
                                <?= Apariencia::icono('detalle') ?>
                            </button>

                            <!-- 2. Marcar como Entregado -->
                            <?php if (!in_array($env['estado'], ['entregado', 'cancelado'])): ?>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Marcar el pedido <?= $env['codigo_seguimiento'] ?> como ENTREGADO?');">
                                    <input type="hidden" name="pedido_id" value="<?= $env['id'] ?>">
                                    <input type="hidden" name="accion_rapida" value="entregar">
                                    <button type="submit" class="btn-sq btn-sq-success" title="Marcar como Entregado"><?= Apariencia::icono('confirmar') ?></button>
                                </form>

                                <!-- 3. Cancelar Pedido -->
                                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Cancelar el pedido <?= $env['codigo_seguimiento'] ?>?');">
                                    <input type="hidden" name="pedido_id" value="<?= $env['id'] ?>">
                                    <input type="hidden" name="accion_rapida" value="cancelar">
                                    <button type="submit" class="btn-sq btn-sq-danger" title="Cancelar pedido"><?= Apariencia::icono('cancelar') ?></button>
                                </form>
                            <?php endif; ?>

                            <!-- 4. Ver Recorrido / Posición en Mapa en Vivo (Leaflet) -->
                            <button type="button" class="btn-sq btn-sq-map" title="Ver recorrido GPS en vivo"
                                onclick='abrirMapaRuta(<?= json_encode([
                                    "id"         => (int)$env["id"],
                                    "codigo"     => $env["codigo_seguimiento"],
                                    "cliente"    => $env["cliente_nombre"],
                                    "direccion"  => $env["cliente_direccion"],
                                    "repartidor" => $env["repartidor_nombre"] ?? "Sin asignar",
                                    "lat"        => (float)$latDest,
                                    "lng"        => (float)$lngDest
                                ]) ?>)'>
                                <?= Apariencia::icono('ubicacion') ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Detalle Rápido de Envío -->
<div class="modal-backdrop" id="modalDetalleEnvio">
    <div class="modal-content" style="max-width: 460px;">
        <div class="modal-header">
            <h3 id="detCodigo" style="font-size: 1rem;">Detalle del Envío</h3>
            <button class="modal-close" onclick="cerrarModalDetalle()">&times;</button>
        </div>
        <div class="modal-body" style="font-size: 0.88rem; line-height: 1.6;">
            <p style="margin: 4px 0;"><strong>Comercio Origen:</strong> <span id="detComercio"></span></p>
            <p style="margin: 4px 0;"><strong>Paquete / Producto:</strong> <span id="detPaquete"></span></p>
            <hr style="border: 0; border-top: 1px solid #e3e6e8; margin: 10px 0;">
            <p style="margin: 4px 0;"><strong>Cliente:</strong> <span id="detCliente"></span> (<span id="detTelefono"></span>)</p>
            <p style="margin: 4px 0;"><strong>Dirección:</strong> <span id="detDireccion"></span></p>
            <hr style="border: 0; border-top: 1px solid #e3e6e8; margin: 10px 0;">
            <p style="margin: 4px 0;"><strong>Repartidor:</strong> <span id="detRepartidor"></span></p>
            <p style="margin: 4px 0;"><strong>Monto a Cobrar:</strong> <span id="detTotal" style="color: #2f6f44; font-weight: bold;"></span> (<span id="detMetodo"></span>)</p>
            <p style="margin: 4px 0;"><strong>Estado Actual:</strong> <span id="detEstado" class="so-tag"></span></p>

            <div style="margin-top: 18px; display: flex; justify-content: space-between; gap: 10px;">
                <a id="detLinkMapa" href="#" target="_blank" class="btn" style="background: #e1ecf4; color: #39739d; border-color: #7aa7c7;">
                    <?= Apariencia::icono('ubicacion') ?> Abrir Ruta en Google Maps
                </a>
                <button type="button" class="btn btn-primary" onclick="cerrarModalDetalle()">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Lógica del Modal de Detalle
function verDetalleEnvio(data) {
    document.getElementById('detCodigo').innerText = 'Envío #' + data.codigo;
    document.getElementById('detComercio').innerText = data.comercio;
    document.getElementById('detPaquete').innerText = data.paquete;
    document.getElementById('detCliente').innerText = data.cliente;
    document.getElementById('detTelefono').innerText = data.telefono;
    document.getElementById('detDireccion').innerText = data.direccion;
    document.getElementById('detRepartidor').innerText = data.repartidor;
    document.getElementById('detTotal').innerText = '$' + data.total;
    document.getElementById('detMetodo').innerText = data.metodo;
    document.getElementById('detEstado').innerText = data.estado;
    document.getElementById('detLinkMapa').href = data.mapa;
    document.getElementById('modalDetalleEnvio').classList.add('show');
}

function cerrarModalDetalle() {
    document.getElementById('modalDetalleEnvio').classList.remove('show');
}

// =========================================================================
// CONFIGURACIÓN DE GRÁFICAS FLAT UI (CHART.JS)
// =========================================================================
Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
Chart.defaults.color = '#6a737c';

<?php if ($rol === 'admin'): ?>
const ctxComercios = document.getElementById('chartComercios');
if (ctxComercios) {
    new Chart(ctxComercios, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($datosComercios, 'label')) ?>,
            datasets: [{
                label: 'Envíos realizados',
                data: <?= json_encode(array_map('intval', array_column($datosComercios, 'total'))) ?>,
                backgroundColor: '#f48024',
                borderRadius: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f2f3' } },
                x: { grid: { display: false } }
            }
        }
    });
}
<?php endif; ?>

<?php if (in_array($rol, ['admin', 'comercio'])): ?>
const ctxRepartidores = document.getElementById('chartRepartidores');
if (ctxRepartidores) {
    new Chart(ctxRepartidores, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($datosRepartidores, 'label')) ?>,
            datasets: [{
                label: 'Entregas completadas',
                data: <?= json_encode(array_map('intval', array_column($datosRepartidores, 'total'))) ?>,
                backgroundColor: '#379fef',
                borderRadius: 2
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f2f3' } },
                y: { grid: { display: false } }
            }
        }
    });
}
<?php endif; ?>

const ctxPaquetes = document.getElementById('chartPaquetes');
if (ctxPaquetes) {
    new Chart(ctxPaquetes, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($datosPaquetes, 'label')) ?>,
            datasets: [{
                data: <?= json_encode(array_map('intval', array_column($datosPaquetes, 'total'))) ?>,
                backgroundColor: ['#f48024', '#379fef', '#48a868', '#9fa6ad', '#d9534f'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>