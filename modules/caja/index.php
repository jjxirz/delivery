<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../usuarios/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/apariencia.php';

Auth::verificarRol(['admin', 'comercio']);

$rol = $_SESSION['usuario_rol'];
$usuario_id = (int)$_SESSION['usuario_id'];
$db = Database::getConnection();
$mensaje = '';
$tipo_mensaje = 'success';

// Identificar comercio si aplica
$comercio_id = null;
if ($rol === 'comercio') {
    $stmtCom = $db->prepare("SELECT id FROM comercios WHERE usuario_id = :uid LIMIT 1");
    $stmtCom->execute([':uid' => $usuario_id]);
    $comercio_id = $stmtCom->fetchColumn() ?: null;
}

// =========================================================================
// 1. PROCESAR ACCIONES (ABRIR, REGISTRAR EGRESO, CERRAR, ELIMINAR)
// =========================================================================

// A. Abrir Caja
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'abrir_caja') {
    $monto_inicial = (float)($_POST['monto_inicial'] ?? 0);
    $cid = ($rol === 'comercio') ? $comercio_id : (!empty($_POST['comercio_id']) ? (int)$_POST['comercio_id'] : null);

    if ($monto_inicial < 0) {
        $mensaje = "El monto inicial no puede ser negativo.";
        $tipo_mensaje = 'error';
    } else {
        $sqlCheck = $cid
            ? "SELECT COUNT(*) FROM turnos_caja WHERE comercio_id = :cid AND estado = 'abierta'"
            : "SELECT COUNT(*) FROM turnos_caja WHERE comercio_id IS NULL AND estado = 'abierta'";
        $stmtCheck = $db->prepare($sqlCheck);
        $stmtCheck->execute($cid ? [':cid' => $cid] : []);

        if ((int)$stmtCheck->fetchColumn() > 0) {
            $mensaje = "Ya existe un turno de caja abierto para esta tienda/área.";
            $tipo_mensaje = 'error';
        } else {
            $stmtIns = $db->prepare("INSERT INTO turnos_caja (comercio_id, usuario_apertura_id, monto_inicial, estado, fecha_apertura) 
                                     VALUES (:cid, :uid, :inicial, 'abierta', NOW())");
            if ($stmtIns->execute([':cid' => $cid, ':uid' => $usuario_id, ':inicial' => $monto_inicial])) {
                $mensaje = "Caja abierta correctamente con $" . number_format($monto_inicial, 2) . ".";
            }
        }
    }
}

// B. Registrar Egreso Detallado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_egreso') {
    $turno_id = (int)($_POST['turno_id'] ?? 0);
    $concepto = trim($_POST['concepto'] ?? '');
    $monto    = (float)($_POST['monto'] ?? 0);

    if ($turno_id <= 0 || $concepto === '' || $monto <= 0) {
        $mensaje = "Debes indicar un concepto válido y un monto mayor a $0.00.";
        $tipo_mensaje = 'error';
    } else {
        $stmtEg = $db->prepare("INSERT INTO movimientos_caja (turno_id, usuario_id, tipo, concepto, monto, creado_en) 
                                VALUES (:tid, :uid, 'egreso', :con, :mon, NOW())");
        if ($stmtEg->execute([':tid' => $turno_id, ':uid' => $usuario_id, ':con' => $concepto, ':mon' => $monto])) {
            $mensaje = "Egreso registrado: " . htmlspecialchars($concepto) . " (-$" . number_format($monto, 2) . ").";
        }
    }
}

// C. Cerrar Caja y Liquidar Turno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cerrar_caja') {
    $turno_id       = (int)$_POST['turno_id'];
    $ing_efectivo   = (float)$_POST['ingresos_efectivo'];
    $ing_tarjeta    = (float)$_POST['ingresos_tarjeta'];
    $ing_transf     = (float)$_POST['ingresos_transferencia'];
    $cred_pendiente = (float)$_POST['credito_pendiente'];
    $egresos_tot    = (float)$_POST['egresos_totales'];
    $esperado       = (float)$_POST['dinero_esperado'];
    $contado        = (float)$_POST['dinero_contado'];
    $diferencia     = $contado - $esperado;
    $notas          = trim($_POST['notas'] ?? '');

    if ($contado < 0) {
        $mensaje = "El dinero contado físico no puede ser negativo.";
        $tipo_mensaje = 'error';
    } else {
        $stmtCierre = $db->prepare("UPDATE turnos_caja SET 
                                        usuario_cierre_id = :uid,
                                        ingresos_efectivo = :ef,
                                        ingresos_tarjeta = :ta,
                                        ingresos_transferencia = :tr,
                                        credito_pendiente = :cr,
                                        egresos_envios_gratis = :eg,
                                        dinero_esperado = :esp,
                                        dinero_contado = :con,
                                        diferencia = :dif,
                                        notas = :notas,
                                        estado = 'cerrada',
                                        fecha_cierre = NOW()
                                    WHERE id = :id AND estado = 'abierta'");
        if ($stmtCierre->execute([
            ':uid'   => $usuario_id,
            ':ef'    => $ing_efectivo,
            ':ta'    => $ing_tarjeta,
            ':tr'    => $ing_transf,
            ':cr'    => $cred_pendiente,
            ':eg'    => $egresos_tot,
            ':esp'   => $esperado,
            ':con'   => $contado,
            ':dif'   => $diferencia,
            ':notas' => $notas,
            ':id'    => $turno_id
        ])) {
            $mensaje = "Liquidación y cierre de caja guardados correctamente.";
        }
    }
}

// D. Eliminar Registro de Historial (Solo Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_corte' && $rol === 'admin') {
    $turno_id = (int)$_POST['turno_id'];
    $db->prepare("DELETE FROM turnos_caja WHERE id = :id")->execute([':id' => $turno_id]);
    $mensaje = "Registro eliminado del historial.";
}

// =========================================================================
// 2. CONSULTAR CAJA ABIERTA, PEDIDOS DEL TURNO Y MOVIMIENTOS DETALLADOS
// =========================================================================
$whereCaja = ($rol === 'comercio' && $comercio_id) ? " WHERE t.comercio_id = :cid " : " WHERE 1=1 ";
$paramsCaja = ($rol === 'comercio' && $comercio_id) ? [':cid' => $comercio_id] : [];

$stmtAbierta = $db->prepare("SELECT t.*, 
                                    COALESCE(c.nombre_comercial, 'Caja General (Admin)') as tienda_nombre,
                                    ua.nombre as usuario_apertura_nombre
                             FROM turnos_caja t 
                             LEFT JOIN comercios c ON t.comercio_id = c.id 
                             LEFT JOIN usuarios ua ON t.usuario_apertura_id = ua.id
                             $whereCaja AND t.estado = 'abierta' 
                             ORDER BY t.id DESC LIMIT 1");
$stmtAbierta->execute($paramsCaja);
$cajaAbierta = $stmtAbierta->fetch(PDO::FETCH_ASSOC);

$ingEfectivo = 0.0;
$ingTarjeta = 0.0;
$ingTransf = 0.0;
$credPendiente = 0.0;
$egresosGratis = 0.0;
$egresosManuales = 0.0;
$listaMovimientosTurno = [];
$listaEgresosTurno = [];
$dineroEsperado = 0.0;
$totalLiquidadoGeneral = 0.0;

if ($cajaAbierta) {
    $inicioTurno = $cajaAbierta['fecha_apertura'];
    $filtroCom = !empty($cajaAbierta['comercio_id']) ? " AND comercio_id = " . (int)$cajaAbierta['comercio_id'] : "";

    $sqlPed = "SELECT metodo_pago, es_envio_gratis, 
                      SUM(monto_mando + costo_envio) as total_cobrado, 
                      SUM(costo_envio) as total_envio
               FROM pedidos
               WHERE estado = 'entregado' AND fecha_creacion >= :inicio $filtroCom
               GROUP BY metodo_pago, es_envio_gratis";
    $stmtPed = $db->prepare($sqlPed);
    $stmtPed->execute([':inicio' => $inicioTurno]);

    foreach ($stmtPed->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $tot = (float)$fila['total_cobrado'];
        if ($fila['metodo_pago'] === 'efectivo') {
            $ingEfectivo += $tot;
        } elseif ($fila['metodo_pago'] === 'tarjeta') {
            $ingTarjeta += $tot;
        } elseif ($fila['metodo_pago'] === 'transferencia') {
            $ingTransf += $tot;
        } elseif ($fila['metodo_pago'] === 'credito') {
            $credPendiente += $tot;
        }

        if ((int)$fila['es_envio_gratis'] === 1) {
            $egresosGratis += (float)$fila['total_envio'];
        }
    }

    // Consultar movimientos (ingresos automáticos y egresos manuales) de este turno
    $stmtMov = $db->prepare("SELECT m.*, u.nombre as usuario_nombre 
                             FROM movimientos_caja m 
                             INNER JOIN usuarios u ON m.usuario_id = u.id 
                             WHERE m.turno_id = :tid ORDER BY m.id DESC");
    $stmtMov->execute([':tid' => $cajaAbierta['id']]);
    $listaMovimientosTurno = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

    foreach ($listaMovimientosTurno as $mov) {
        if ($mov['tipo'] === 'egreso') {
            $egresosManuales += (float)$mov['monto'];
            $listaEgresosTurno[] = $mov;
        }
    }

    $totalEgresos = $egresosGratis + $egresosManuales;
    $dineroEsperado = (float)$cajaAbierta['monto_inicial'] + $ingEfectivo - $totalEgresos;
    $totalLiquidadoGeneral = $ingEfectivo + $ingTarjeta + $ingTransf;
}

// Historial completo con nombres de apertura/cierre y desglose de pagos
$stmtHist = $db->prepare("SELECT t.*, 
                                 COALESCE(c.nombre_comercial, 'Caja General (Admin)') as tienda_nombre,
                                 ua.nombre as nombre_apertura,
                                 uc.nombre as nombre_cierre
                          FROM turnos_caja t 
                          LEFT JOIN comercios c ON t.comercio_id = c.id 
                          LEFT JOIN usuarios ua ON t.usuario_apertura_id = ua.id
                          LEFT JOIN usuarios uc ON t.usuario_cierre_id = uc.id
                          $whereCaja 
                          ORDER BY t.id DESC LIMIT 30");
$stmtHist->execute($paramsCaja);
$historialCajas = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

$comerciosList = ($rol === 'admin') ? $db->query("SELECT id, nombre_comercial FROM comercios")->fetchAll(PDO::FETCH_ASSOC) : [];

require_once __DIR__ . '/../../layout/header.php';
?>

<style>
    /* Botones de Turno: Verde para Abrir (sin icono) y Rojo con letras negras para Cerrar */
    .btn-caja-abrir {
        background-color: #16a34a !important;
        border: 1px solid #15803d !important;
        color: #ffffff !important;
        font-weight: 700;
        padding: 8px 18px;
        font-size: 0.9rem;
        border-radius: var(--ui-radius, 3px);
        cursor: pointer;
    }
    .btn-caja-abrir:hover { background-color: #15803d !important; }

    .btn-caja-cerrar {
        background-color: #ef4444 !important;
        border: 1px solid #dc2626 !important;
        color: #0c0d0e !important;
        font-weight: 700;
        padding: 8px 18px;
        font-size: 0.9rem;
        border-radius: var(--ui-radius, 3px);
        cursor: pointer;
    }
    .btn-caja-cerrar:hover { background-color: #dc2626 !important; }

    /* Tarjetas KPI adaptadas a la paleta elegida */
    .kpi-caja-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
        gap: 12px;
    }
    .kpi-caja-box {
        padding: 12px;
        background: #f8f9f9;
        border: 1px solid var(--so-gray-border, #d6d9dc);
        border-radius: var(--ui-radius, 3px);
    }
    .kpi-caja-box.accent-tint {
        background: color-mix(in srgb, var(--so-orange) 9%, #ffffff);
        border-color: color-mix(in srgb, var(--so-orange) 45%, #d6d9dc);
    }
    .kpi-caja-box.subtle-tint {
        background: color-mix(in srgb, var(--so-orange) 4%, #f8f9f9);
    }

    /* Botones de acción en historial */
    .caja-actions-wrap {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-caja-icon {
        width: 30px;
        height: 30px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--ui-radius, 3px);
        text-decoration: none;
        cursor: pointer;
        border: 1px solid #babfc4;
        background-color: #f8f9f9;
        color: #232629;
    }
    .btn-caja-icon:hover { background-color: #e3e6e8; }
    .btn-caja-icon.del-btn { color: #b91c1c; }
    .btn-caja-icon.del-btn:hover {
        background-color: #fee2e2;
        border-color: #fca5a5;
    }

    .so-input-group {
        display: flex;
        align-items: stretch;
        width: 100%;
    }
    .so-input-group-prepend {
        display: flex;
        align-items: center;
        padding: 0 10px;
        font-size: 0.88rem;
        font-weight: 700;
        color: #525960;
        background-color: #f1f2f3;
        border: 1px solid #babfc4;
        border-right: none;
        border-radius: var(--ui-radius, 3px) 0 0 var(--ui-radius, 3px);
    }
    .so-input-group .form-control {
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
        flex: 1;
    }

    /* Secciones del modal de cierre */
    .cierre-section-card {
        background: color-mix(in srgb, var(--so-orange) 6%, #ffffff);
        border: 1px solid color-mix(in srgb, var(--so-orange) 28%, #d6d9dc);
        border-radius: var(--ui-radius, 3px);
        padding: 10px 12px;
        margin-bottom: 12px;
    }
    .cierre-section-title {
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #3b4045;
        margin-bottom: 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid color-mix(in srgb, var(--so-orange) 22%, #e3e6e8);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .cierre-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 3px 0;
        font-size: 0.85rem;
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h2 style="margin: 0 0 4px 0;">Liquidaciones y Control de Caja</h2>
        <p style="margin: 0; font-size: 0.85rem; color: var(--so-gray-text);">Apertura de turno, liquidación por método de pago, egresos operativos y arqueo final.</p>
    </div>

    <div style="display: flex; gap: 8px; align-items: center;">
        <?php if (!$cajaAbierta): ?>
            <button type="button" class="btn-caja-abrir" onclick="document.getElementById('modalAbrirCaja').classList.add('show')">
                Abrir Caja
            </button>
        <?php else: ?>
            <button type="button" class="btn" onclick="document.getElementById('modalEgresoCaja').classList.add('show')">
                <?= Apariencia::icono('egreso') ?> Registrar Egreso
            </button>
            <button type="button" class="btn-caja-cerrar" onclick="document.getElementById('modalCerrarCaja').classList.add('show')">
                Cerrar Caja
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert-banner <?= in_array($tipo_mensaje ?? '', ['error', 'danger'], true) ? 'error' : (in_array($tipo_mensaje ?? '', ['warning', 'advertencia'], true) ? 'warning' : 'success') ?>" role="alert">
        <div class="alert-banner-message">
            <?= Apariencia::icono(in_array($tipo_mensaje ?? '', ['error', 'danger'], true) ? 'eliminar' : (in_array($tipo_mensaje ?? '', ['warning', 'advertencia'], true) ? 'reloj' : 'auditoria')) ?>
            <span><?= htmlspecialchars($mensaje) ?></span>
        </div>
        <button type="button" class="alert-banner-dismiss" aria-label="Cerrar aviso" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>

<?php if ($cajaAbierta): ?>
    <?php
    $esHoy = date('Y-m-d', strtotime($cajaAbierta['fecha_apertura'])) === date('Y-m-d');
    $etiquetaFecha = $esHoy
        ? 'Caja abierta hoy a las ' . date('H:i', strtotime($cajaAbierta['fecha_apertura']))
        : 'Caja abierta el ' . date('d/m/Y \a \l\a\s H:i', strtotime($cajaAbierta['fecha_apertura']));
    ?>
    <!-- RESUMEN DEL TURNO ACTIVO (SIN LÍNEA IZQUIERDA, PALETA UNIFICADA) -->
    <div class="card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; padding-bottom: 10px; border-bottom: 1px solid #e3e6e8;">
            <div>
                <span style="display: inline-block; padding: 5px 12px; font-size: 0.85rem; font-weight: 800; letter-spacing: 0.4px;
                             border-radius: var(--ui-radius, 3px); color: #0c0d0e;
                             border: 2px solid var(--so-orange);
                             background: linear-gradient(135deg, color-mix(in srgb, var(--so-orange) 14%, transparent), transparent);">
                    TURNO ACTIVO
                </span>
                <strong style="margin-left: 8px; font-size: 1rem;"><?= htmlspecialchars($cajaAbierta['tienda_nombre']) ?></strong>
                <div style="margin-top: 6px; font-size: 0.84rem; color: #3b4045;">
                    <?= $etiquetaFecha ?> con <strong>$<?= number_format((float)$cajaAbierta['monto_inicial'], 2) ?></strong> · Abierta por: <strong><?= htmlspecialchars($cajaAbierta['usuario_apertura_nombre'] ?? 'Usuario') ?></strong>
                </div>
            </div>
            <div style="text-align: right;">
                <small style="color: var(--so-gray-text); display: block;">Total Liquidado Ventas</small>
                <strong style="font-size: 1.2rem; color: #0c0d0e;">$<?= number_format($totalLiquidadoGeneral, 2) ?></strong>
            </div>
        </div>

        <div class="kpi-caja-grid">
            <div class="kpi-caja-box">
                <small style="color: #6a737c; display: block;">Efectivo (COD)</small>
                <strong style="font-size: 1.15rem;">+$<?= number_format($ingEfectivo, 2) ?></strong>
            </div>
            <div class="kpi-caja-box subtle-tint">
                <small style="color: #6a737c; display: block;">Tarjeta (POS)</small>
                <strong style="font-size: 1.15rem;">$<?= number_format($ingTarjeta, 2) ?></strong>
            </div>
            <div class="kpi-caja-box subtle-tint">
                <small style="color: #6a737c; display: block;">Transferencia</small>
                <strong style="font-size: 1.15rem;">$<?= number_format($ingTransf, 2) ?></strong>
            </div>
            <div class="kpi-caja-box">
                <small style="color: #6a737c; display: block;">Egresos y Cortesías</small>
                <strong style="font-size: 1.15rem;">-$<?= number_format($egresosGratis + $egresosManuales, 2) ?></strong>
            </div>
            <div class="kpi-caja-box accent-tint">
                <small style="color: #3b4045; display: block; font-weight: 600;">Efectivo Esperado en Gaveta</small>
                <strong style="font-size: 1.22rem; color: #0c0d0e;">$<?= number_format($dineroEsperado, 2) ?></strong>
            </div>
        </div>

        <?php if (!empty($listaMovimientosTurno)): ?>
            <div style="margin-top: 15px; padding-top: 12px; border-top: 1px dashed #d6d9dc;">
                <strong style="font-size: 0.85rem; display: block; margin-bottom: 8px;">Movimientos del Turno (Ingresos automáticos por entrega y Egresos):</strong>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Concepto / Detalle</th>
                                <th>Responsable</th>
                                <th style="text-align: right;">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listaMovimientosTurno as $mov): ?>
                                <?php $esIngreso = ($mov['tipo'] === 'ingreso'); ?>
                                <tr>
                                    <td><small><?= date('H:i', strtotime($mov['creado_en'])) ?></small></td>
                                    <td>
                                        <span style="font-size: 0.72rem; font-weight: 700; padding: 2px 6px; border-radius: 3px;
                                                     background: <?= $esIngreso ? 'color-mix(in srgb, var(--so-orange) 12%, #ffffff)' : '#f1f2f3' ?>;">
                                            <?= $esIngreso ? 'INGRESO' : 'EGRESO' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($mov['concepto']) ?></td>
                                    <td><small><?= htmlspecialchars($mov['usuario_nombre']) ?></small></td>
                                    <td style="text-align: right; font-weight: 600; color: <?= $esIngreso ? '#0c0d0e' : '#dc2626' ?>;">
                                        <?= $esIngreso ? '+' : '-' ?>$<?= number_format((float)$mov['monto'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- HISTORIAL UNIFICADO DE LIQUIDACIONES Y CORTES DE CAJA -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
        <h3 style="font-size: 1rem; margin: 0;">Historial de Liquidaciones y Cortes de Caja</h3>
        
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <div class="so-input-group" style="width: 170px;">
                <span class="so-input-group-prepend"><?= Apariencia::icono('reloj') ?></span>
                <input type="date" id="filtroFechaCaja" class="form-control" style="height: 30px; font-size: 0.8rem;" onchange="filtrarHistorialCaja()" title="Filtrar por fecha">
            </div>
            <div class="so-input-group" style="width: 215px;">
                <span class="so-input-group-prepend"><?= Apariencia::icono('usuario') ?></span>
                <input type="text" id="filtroRespCaja" class="form-control" style="height: 30px; font-size: 0.8rem;" placeholder="Buscar responsable o tienda..." onkeyup="filtrarHistorialCaja()">
            </div>
            <button type="button" class="btn" style="height: 30px; padding: 0 10px; font-size: 0.78rem;" onclick="limpiarFiltrosCaja()">Limpiar</button>
        </div>
    </div>

    <div class="table-responsive">
        <table id="tablaHistorialCaja">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Comercio / Área</th>
                    <th>Responsables</th>
                    <th>Ventas / Entregas</th>
                    <th>Egresos</th>
                    <th>Esperado</th>
                    <th>Cierre</th>
                    <th>Diferencia</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historialCajas)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--so-gray-text); padding: 20px;">
                            No hay liquidaciones registradas todavía.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($historialCajas as $h): ?>
                        <?php
                        $mEfectivo  = (float)($h['ingresos_efectivo'] ?? 0);
                        $mTarjeta   = (float)($h['ingresos_tarjeta'] ?? 0);
                        $mTransf    = (float)($h['ingresos_transferencia'] ?? 0);
                        $mVentas    = $mEfectivo + $mTarjeta + $mTransf;
                        $mEgresos   = (float)($h['egresos_envios_gratis'] ?? 0);
                        $mEsperado  = (float)($h['dinero_esperado'] ?? 0);
                        $mContado   = (float)($h['dinero_contado'] ?? 0);
                        $dif        = (float)($h['diferencia'] ?? 0);
                        $cerrada    = ($h['estado'] === 'cerrada');
                        $fechaIso   = date('Y-m-d', strtotime($h['fecha_apertura']));
                        $textoBusq  = strtolower(($h['tienda_nombre'] ?? '') . ' ' . ($h['nombre_apertura'] ?? '') . ' ' . ($h['nombre_cierre'] ?? ''));
                        ?>
                        <tr data-fecha="<?= $fechaIso ?>" data-busqueda="<?= htmlspecialchars($textoBusq) ?>">
                            <td>
                                <small><strong>Ap:</strong> <?= date('d/m/Y H:i', strtotime($h['fecha_apertura'])) ?></small><br>
                                <small style="color: var(--so-gray-text);"><strong>Ci:</strong> <?= !empty($h['fecha_cierre']) ? date('d/m/Y H:i', strtotime($h['fecha_cierre'])) : 'En curso' ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($h['tienda_nombre']) ?></strong><br>
                                <span style="padding: 1px 6px; border-radius: 3px; font-size: 0.7rem; font-weight: 700;
                                             background: <?= !$cerrada ? 'color-mix(in srgb, var(--so-orange) 15%, #fff)' : '#f1f2f3' ?>;
                                             color: #232629;">
                                    <?= strtoupper($h['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <small><strong>Abrió:</strong> <?= htmlspecialchars($h['nombre_apertura'] ?? '—') ?></small><br>
                                <small><strong>Cerró:</strong> <?= htmlspecialchars($h['nombre_cierre'] ?? ($cerrada ? '—' : 'Pendiente')) ?></small>
                            </td>
                            <td>
                                <strong>$<?= number_format($mVentas, 2) ?></strong>
                            </td>
                            <!-- EGRESOS -->
                            <td>
                                <?= $cerrada ? '<strong>-$' . number_format($mEgresos, 2) . '</strong>' : '<span style="color:#6a737c;">—</span>' ?>
                            </td>
                            <!-- COLUMNA INDEPENDIENTE: ESPERADO -->
                            <td>
                                <?= $cerrada ? '$' . number_format($mEsperado, 2) : '<span style="color:#6a737c;">—</span>' ?>
                            </td>
                            <!-- COLUMNA INDEPENDIENTE: CIERRE (CONTEO REAL) -->
                            <td>
                                <?= $cerrada ? '<strong>$' . number_format($mContado, 2) . '</strong>' : '<span style="color:#6a737c;">—</span>' ?>
                            </td>
                            <td>
                                <?php if ($cerrada): ?>
                                    <?php if (abs($dif) < 0.005): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: #0c0d0e;">
                                            <?= Apariencia::icono('auditoria') ?> $0.00
                                        </span>
                                    <?php elseif ($dif > 0): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: #0c0d0e;">
                                            ▲ +$<?= number_format($dif, 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: #dc2626;">
                                            ▼ -$<?= number_format(abs($dif), 2) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #6a737c;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="caja-actions-wrap">
                                    <a href="exportar_pdf.php?id=<?= $h['id'] ?>" target="_blank" class="btn-caja-icon" title="Comprobante PDF">
                                        <?= Apariencia::icono('pdf') ?>
                                    </a>

                                    <?php if ($rol === 'admin'): ?>
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Eliminar permanentemente este registro?');">
                                            <input type="hidden" name="action" value="eliminar_corte">
                                            <input type="hidden" name="turno_id" value="<?= $h['id'] ?>">
                                            <button type="submit" class="btn-caja-icon del-btn" title="Eliminar registro">
                                                <?= Apariencia::icono('eliminar') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL 1: ABRIR CAJA -->
<div class="modal-backdrop" id="modalAbrirCaja">
    <div class="modal-content" style="max-width: 410px;">
        <div class="modal-header">
            <h3>Abrir Turno de Caja</h3>
            <button class="modal-close" onclick="document.getElementById('modalAbrirCaja').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="abrir_caja">
                <?php if ($rol === 'admin'): ?>
                    <div class="form-group">
                        <label for="comercio_id">Comercio / Caja</label>
                        <select name="comercio_id" id="comercio_id" class="form-control">
                            <option value="">Caja General (Admin)</option>
                            <?php foreach ($comerciosList as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_comercial']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="monto_inicial">Fondo Inicial en Efectivo</label>
                    <div class="so-input-group">
                        <span class="so-input-group-prepend">$</span>
                        <input type="number" step="0.01" min="0" name="monto_inicial" id="monto_inicial" class="form-control" required value="0.00">
                    </div>
                </div>

                <div style="text-align: right; margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn" onclick="document.getElementById('modalAbrirCaja').classList.remove('show')">Cancelar</button>
                    <button type="submit" class="btn-caja-abrir">Confirmar Apertura</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($cajaAbierta): ?>
    <!-- MODAL 2: REGISTRAR EGRESO DETALLADO -->
    <div class="modal-backdrop" id="modalEgresoCaja">
        <div class="modal-content" style="max-width: 430px;">
            <div class="modal-header">
                <h3>Registrar Egreso / Salida</h3>
                <button class="modal-close" onclick="document.getElementById('modalEgresoCaja').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="registrar_egreso">
                    <input type="hidden" name="turno_id" value="<?= $cajaAbierta['id'] ?>">

                    <div class="form-group">
                        <label for="concepto">Detalle / Motivo del Egreso</label>
                        <input type="text" name="concepto" id="concepto" class="form-control" required placeholder="Ej. Combustible repartidor, vuelto entregado, insumo...">
                    </div>

                    <div class="form-group">
                        <label for="monto_egreso">Monto</label>
                        <div class="so-input-group">
                            <span class="so-input-group-prepend">$</span>
                            <input type="number" step="0.01" min="0.01" name="monto" id="monto_egreso" class="form-control" required placeholder="0.00">
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" class="btn" onclick="document.getElementById('modalEgresoCaja').classList.remove('show')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Egreso</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 3: CERRAR CAJA POR SECCIONES -->
    <div class="modal-backdrop" id="modalCerrarCaja">
        <div class="modal-content" style="max-width: 510px; max-height: 92vh; overflow-y: auto;">
            <div class="modal-header">
                <h3 style="margin: 0;">Cierre y Liquidación de Caja</h3>
                <button class="modal-close" onclick="document.getElementById('modalCerrarCaja').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="cierre-section-card">
                    <div class="cierre-section-title"><?= Apariencia::icono('reloj') ?> 1. Información del Turno</div>
                    <div class="cierre-line">
                        <span>Área / Comercio:</span>
                        <strong><?= htmlspecialchars($cajaAbierta['tienda_nombre']) ?></strong>
                    </div>
                    <div class="cierre-line">
                        <span>Apertura:</span>
                        <span><?= date('d/m/Y H:i', strtotime($cajaAbierta['fecha_apertura'])) ?> · <strong><?= htmlspecialchars($cajaAbierta['usuario_apertura_nombre'] ?? 'Usuario') ?></strong></span>
                    </div>
                    <div class="cierre-line">
                        <span>Fondo Inicial en Gaveta:</span>
                        <strong>$<?= number_format((float)$cajaAbierta['monto_inicial'], 2) ?></strong>
                    </div>
                </div>

                <div class="cierre-section-card">
                    <div class="cierre-section-title"><?= Apariencia::icono('caja') ?> 2. Ingresos y Liquidación de Ventas</div>
                    <div class="cierre-line">
                        <span><?= Apariencia::icono('caja') ?> Cobros en Efectivo (COD):</span>
                        <strong>+$<?= number_format($ingEfectivo, 2) ?></strong>
                    </div>
                    <div class="cierre-line">
                        <span><?= Apariencia::icono('tarjeta') ?> Cobros con Tarjeta (POS):</span>
                        <strong>$<?= number_format($ingTarjeta, 2) ?></strong>
                    </div>
                    <div class="cierre-line">
                        <span><?= Apariencia::icono('banco') ?> Cobros por Transferencia:</span>
                        <strong>$<?= number_format($ingTransf, 2) ?></strong>
                    </div>
                    <div class="cierre-line">
                        <span><?= Apariencia::icono('reloj') ?> Crédito Pendiente:</span>
                        <span>$<?= number_format($credPendiente, 2) ?></span>
                    </div>
                </div>

                <div class="cierre-section-card">
                    <div class="cierre-section-title"><?= Apariencia::icono('egreso') ?> 3. Deducciones y Salidas de Caja</div>
                    <div class="cierre-line">
                        <span>Egresos Operativos Registrados (<?= count($listaEgresosTurno) ?>):</span>
                        <strong>-$<?= number_format($egresosManuales, 2) ?></strong>
                    </div>
                    <div class="cierre-line">
                        <span>Envíos Gratis / Cortesía de Tienda:</span>
                        <strong>-$<?= number_format($egresosGratis, 2) ?></strong>
                    </div>
                    <div class="cierre-line" style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #babfc4; font-size: 0.92rem;">
                        <span><strong>Efectivo Esperado en Gaveta:</strong></span>
                        <strong>$<?= number_format($dineroEsperado, 2) ?></strong>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="cerrar_caja">
                    <input type="hidden" name="turno_id" value="<?= $cajaAbierta['id'] ?>">
                    <input type="hidden" name="ingresos_efectivo" value="<?= number_format($ingEfectivo, 2, '.', '') ?>">
                    <input type="hidden" name="ingresos_tarjeta" value="<?= number_format($ingTarjeta, 2, '.', '') ?>">
                    <input type="hidden" name="ingresos_transferencia" value="<?= number_format($ingTransf, 2, '.', '') ?>">
                    <input type="hidden" name="credito_pendiente" value="<?= number_format($credPendiente, 2, '.', '') ?>">
                    <input type="hidden" name="egresos_totales" value="<?= number_format($egresosGratis + $egresosManuales, 2, '.', '') ?>">
                    <input type="hidden" name="dinero_esperado" id="inputEsperadoCierre" value="<?= number_format($dineroEsperado, 2, '.', '') ?>">

                    <div class="cierre-section-card" style="margin-bottom: 0;">
                        <div class="cierre-section-title"><?= Apariencia::icono('auditoria') ?> 4. Conteo Físico y Observaciones</div>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="dinero_contado">Dinero Real Contado Físico</label>
                            <div class="so-input-group">
                                <span class="so-input-group-prepend">$</span>
                                <input type="number" step="0.01" min="0" name="dinero_contado" id="dinero_contado" class="form-control" required 
                                       value="<?= number_format($dineroEsperado, 2, '.', '') ?>" oninput="calcularDiferenciaCierre()">
                            </div>
                            <small id="textoDiferenciaLive" style="display: block; margin-top: 4px; font-weight: 600; color: #525960;">
                                Cuadre exacto ($0.00)
                            </small>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="notas">Observaciones / Notas del Cierre</label>
                            <textarea name="notas" id="notas" rows="2" class="form-control" placeholder="Ej. Detalle de billetes, justificación de diferencia o vales..."></textarea>
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" class="btn" onclick="document.getElementById('modalCerrarCaja').classList.remove('show')">Cancelar</button>
                        <button type="submit" class="btn-caja-cerrar">Confirmar Cierre y Liquidar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function calcularDiferenciaCierre() {
    const esperado = parseFloat(document.getElementById('inputEsperadoCierre').value) || 0;
    const contado = parseFloat(document.getElementById('dinero_contado').value) || 0;
    const dif = contado - esperado;
    const label = document.getElementById('textoDiferenciaLive');

    if (Math.abs(dif) < 0.005) {
        label.innerText = 'Cuadre exacto ($0.00)';
        label.style.color = '#525960';
    } else if (dif > 0) {
        label.innerText = 'Sobrante en gaveta: +$' + dif.toFixed(2);
        label.style.color = '#0c0d0e';
    } else {
        label.innerText = 'Faltante en gaveta: -$' + Math.abs(dif).toFixed(2);
        label.style.color = '#dc2626';
    }
}

function filtrarHistorialCaja() {
    const fecha = document.getElementById('filtroFechaCaja').value;
    const texto = document.getElementById('filtroRespCaja').value.toLowerCase().trim();
    const filas = document.querySelectorAll('#tablaHistorialCaja tbody tr[data-fecha]');

    filas.forEach(tr => {
        const coincideFecha = !fecha || tr.dataset.fecha === fecha;
        const coincideTexto = !texto || tr.dataset.busqueda.includes(texto);
        tr.style.display = (coincideFecha && coincideTexto) ? '' : 'none';
    });
}

function limpiarFiltrosCaja() {
    document.getElementById('filtroFechaCaja').value = '';
    document.getElementById('filtroRespCaja').value = '';
    filtrarHistorialCaja();
}
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>