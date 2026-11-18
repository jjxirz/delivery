<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../usuarios/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/apariencia.php';
require_once __DIR__ . '/Pedido.php';

Auth::verificarRol(['admin', 'comercio', 'repartidor']);

$rol = $_SESSION['usuario_rol'];
$usuario_id = (int)$_SESSION['usuario_id'];
$db = Database::getConnection();
$mensaje = '';
$tipo_mensaje = 'success';
$pedidoCreado = false;

// Identificar comercio si aplica
$comercio_id = null;
if ($rol === 'comercio') {
    $stmtCom = $db->prepare("SELECT id FROM comercios WHERE usuario_id = :uid LIMIT 1");
    $stmtCom->execute([':uid' => $usuario_id]);
    $comercio_id = $stmtCom->fetchColumn() ?: null;
}

// =========================================================================
// 1. PROCESAMIENTO DE ACCIONES POST
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $accion = $_POST['action'];

    // A. Crear Nuevo Envío (Admin / Comercio)
    if ($accion === 'crear_pedido' && in_array($rol, ['admin', 'comercio'], true)) {
        $cid = ($rol === 'comercio') ? $comercio_id : (int)($_POST['comercio_id'] ?? 0);
        $telSoloNumeros = preg_replace('/\D/', '', $_POST['cliente_telefono'] ?? '');

        if ($cid <= 0) {
            $mensaje = "Debes seleccionar un comercio válido.";
            $tipo_mensaje = 'error';
        } elseif (strlen($telSoloNumeros) !== 8) {
            $mensaje = "El teléfono del cliente debe tener 8 dígitos en formato 0000-0000.";
            $tipo_mensaje = 'error';
        } else {
            $telefonoFormateado = substr($telSoloNumeros, 0, 4) . '-' . substr($telSoloNumeros, 4, 4);
            $datos = [
                'comercio_id'         => $cid,
                'repartidor_id'       => !empty($_POST['repartidor_id']) ? (int)$_POST['repartidor_id'] : null,
                'descripcion_paquete' => trim($_POST['descripcion_paquete'] ?? 'Paquete estándar'),
                'cliente_nombre'      => trim($_POST['cliente_nombre'] ?? ''),
                'cliente_telefono'    => $telefonoFormateado,
                'cliente_direccion'   => trim($_POST['cliente_direccion'] ?? ''),
                'monto_mando'         => (float)($_POST['monto_mando'] ?? 0),
                'costo_envio'         => (float)($_POST['costo_envio'] ?? 0),
                'es_envio_gratis'     => isset($_POST['es_envio_gratis']) ? 1 : 0,
                'metodo_pago'         => $_POST['metodo_pago'] ?? 'por_definir',
                'nota_pago'           => trim($_POST['nota_pago'] ?? '')
            ];

            if (Pedido::crear($datos)) {
                $mensaje = "Envío creado y registrado correctamente.";
                $pedidoCreado = true;
            } else {
                $mensaje = "Error al crear el envío. Verifica que el repartidor seleccionado esté activo.";
                $tipo_mensaje = 'error';
            }
        }
    }

    // B. Cambiar Estado Rápido (Repartidor / Comercio / Admin)
    if ($accion === 'cambiar_estado') {
        $pedido_id    = (int)($_POST['pedido_id'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] ?? '';
        $permitidos   = ['pendiente', 'asignado', 'en_camino', 'entregado', 'cancelado'];

        if ($pedido_id > 0 && in_array($nuevo_estado, $permitidos, true)) {
            if (Pedido::actualizarEstado($pedido_id, $nuevo_estado)) {
                $mensaje = "Estado actualizado a: " . Apariencia::formatearEstado($nuevo_estado) . ".";
            } else {
                $mensaje = "No se pudo actualizar el estado del pedido.";
                $tipo_mensaje = 'error';
            }
        }
    }

    // C. Reasignar / Asignar Repartidor (Admin / Comercio)
    if ($accion === 'reasignar_repartidor' && in_array($rol, ['admin', 'comercio'], true)) {
        $pedido_id     = (int)($_POST['pedido_id'] ?? 0);
        $repartidor_id = (int)($_POST['repartidor_id'] ?? 0);

        if (Pedido::asignarRepartidor($pedido_id, $repartidor_id)) {
            Apariencia::registrarBitacora('Reasignación de repartidor', 'pedidos', $pedido_id, "Asignado al repartidor ID #$repartidor_id");
            $mensaje = "Repartidor asignado / reasignado correctamente.";
        } else {
            $mensaje = "No se pudo reasignar el repartidor. Verifica que el envío no esté finalizado y que el repartidor esté activo.";
            $tipo_mensaje = 'error';
        }
    }

    // D. Definir / Cambiar Método de Pago
    if ($accion === 'actualizar_metodo_pago') {
        $pedido_id   = (int)($_POST['pedido_id'] ?? 0);
        $metodo_pago = $_POST['metodo_pago'] ?? 'por_definir';
        $validos     = ['por_definir', 'efectivo', 'tarjeta', 'transferencia', 'credito'];

        if ($pedido_id > 0 && in_array($metodo_pago, $validos, true)) {
            $stmtUpdPago = $db->prepare("UPDATE pedidos SET metodo_pago = :mp WHERE id = :id");
            if ($stmtUpdPago->execute([':mp' => $metodo_pago, ':id' => $pedido_id])) {
                $mensaje = "Método de pago actualizado a " . strtoupper($metodo_pago) . ".";
            }
        }
    }

    // E. Guardar / Actualizar Nota de Pago
    if ($accion === 'guardar_nota_pago') {
        $pedido_id = (int)($_POST['pedido_id'] ?? 0);
        $nota_pago = trim($_POST['nota_pago'] ?? '');

        if ($pedido_id > 0) {
            $stmtUpdNota = $db->prepare("UPDATE pedidos SET nota_pago = :nota WHERE id = :id");
            if ($stmtUpdNota->execute([':nota' => $nota_pago, ':id' => $pedido_id])) {
                $mensaje = "Nota de aclaración guardada correctamente.";
            }
        }
    }

    // F. Edición Segura de Estado con Contraseña y Bitácora (Admin / Comercio)
    if ($accion === 'editar_estado_seguro' && in_array($rol, ['admin', 'comercio'], true)) {
        $pedido_id    = (int)($_POST['pedido_id'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] ?? '';
        $password     = $_POST['password_confirmacion'] ?? '';
        $motivo       = trim($_POST['motivo_cambio'] ?? '');

        $stmtUser = $db->prepare("SELECT password FROM usuarios WHERE id = :uid LIMIT 1");
        $stmtUser->execute([':uid' => $usuario_id]);
        $hashActual = $stmtUser->fetchColumn();

        if (!$hashActual || !password_verify($password, $hashActual)) {
            $mensaje = "Contraseña incorrecta. No se realizaron cambios en el estado del pedido.";
            $tipo_mensaje = 'error';
        } elseif ($motivo === '') {
            $mensaje = "Debes escribir el motivo de la modificación para la bitácora de auditoría.";
            $tipo_mensaje = 'error';
        } else {
            $stmtOld = $db->prepare("SELECT codigo_seguimiento, estado FROM pedidos WHERE id = :id LIMIT 1");
            $stmtOld->execute([':id' => $pedido_id]);
            $pedOld = $stmtOld->fetch(PDO::FETCH_ASSOC);

            if ($pedOld && Pedido::actualizarEstado($pedido_id, $nuevo_estado)) {
                // Si pasa de entregado a otro estado, revertir ingreso automático en movimientos_caja
                if ($pedOld['estado'] === 'entregado' && $nuevo_estado !== 'entregado') {
                    $db->prepare("DELETE FROM movimientos_caja WHERE pedido_id = :pid AND tipo = 'ingreso'")
                       ->execute([':pid' => $pedido_id]);
                }

                $detalleLog = "Pedido {$pedOld['codigo_seguimiento']}: de '{$pedOld['estado']}' a '{$nuevo_estado}'. Motivo: {$motivo}";
                Apariencia::registrarBitacora('Cambio de estado protegido', 'pedidos', $pedido_id, $detalleLog);
                $mensaje = "Estado actualizado y registrado en la bitácora de auditoría.";
            }
        }
    }
}

// =========================================================================
// 2. CONSULTAS DE PEDIDOS, COMERCIOS Y REPARTIDORES ACTIVOS
// =========================================================================
$pedidos = Pedido::listar($rol, $usuario_id);

$comerciosList = ($rol === 'admin')
    ? $db->query("SELECT id, nombre_comercial FROM comercios ORDER BY nombre_comercial ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$repartidoresList = [];
if (in_array($rol, ['admin', 'comercio'], true)) {
    $sqlReps = ($rol === 'comercio' && $comercio_id)
        ? "SELECT r.id, u.nombre, r.vehiculo, r.estado_disponibilidad
           FROM repartidores r INNER JOIN usuarios u ON r.usuario_id = u.id
           WHERE r.comercio_id = :cid AND r.estado_disponibilidad != 'inactivo'
           ORDER BY u.nombre ASC"
        : "SELECT r.id, u.nombre, r.vehiculo, r.estado_disponibilidad
           FROM repartidores r INNER JOIN usuarios u ON r.usuario_id = u.id
           WHERE r.estado_disponibilidad != 'inactivo'
           ORDER BY u.nombre ASC";
    $stmtReps = $db->prepare($sqlReps);
    $stmtReps->execute(($rol === 'comercio' && $comercio_id) ? [':cid' => $comercio_id] : []);
    $repartidoresList = $stmtReps->fetchAll(PDO::FETCH_ASSOC);
}

$clientesFrecuentes = [];
if ($rol === 'admin' || ($rol === 'comercio' && $comercio_id)) {
    $sqlClientes = "SELECT cliente_nombre, cliente_telefono, MAX(cliente_direccion) AS cliente_direccion
                    FROM pedidos";
    $paramsClientes = [];
    if ($rol === 'comercio') {
        $sqlClientes .= " WHERE comercio_id = :cid";
        $paramsClientes[':cid'] = (int)$comercio_id;
    }
    $sqlClientes .= " GROUP BY cliente_nombre, cliente_telefono ORDER BY MAX(id) DESC LIMIT 100";
    $stmtClientes = $db->prepare($sqlClientes);
    $stmtClientes->execute($paramsClientes);
    $clientesFrecuentes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../../layout/header.php';
?>

<style>
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
    .btn-mini-icon {
        width: 26px;
        height: 26px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #babfc4;
        background: #ffffff;
        color: #3b4045;
        border-radius: var(--ui-radius, 3px);
        cursor: pointer;
        font-size: 0.78rem;
    }
    .btn-mini-icon:hover {
        background: #f1f2f3;
        border-color: var(--so-orange);
    }
    .btn-mini-icon.has-note {
        border-color: var(--so-orange);
        background: color-mix(in srgb, var(--so-orange) 12%, #ffffff);
        font-weight: 700;
    }
    .quick-fee-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-top: 6px;
    }
    .fee-pill-btn {
        padding: 3px 8px;
        font-size: 0.75rem;
        font-weight: 700;
        border-radius: var(--ui-radius, 3px);
        border: 1px solid #babfc4;
        background: #f8f9f9;
        color: #232629;
        cursor: pointer;
        transition: all 0.12s;
    }
    .fee-pill-btn:hover,
    .fee-pill-btn.active {
        background: color-mix(in srgb, var(--so-orange) 15%, #ffffff);
        border-color: var(--so-orange);
        color: #0c0d0e;
    }
    .so-input-popover-wrap { position: relative; }
    .quick-values-popover {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid var(--so-gray-border, #d6d9dc);
        border-radius: var(--ui-radius, 4px);
        padding: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        z-index: 50;
        flex-wrap: wrap;
        gap: 5px;
    }
    .quick-values-popover.show { display: flex; }
    .quick-values-popover .fee-pill-btn {
        padding: 3px 9px;
        font-size: 0.76rem;
    }
    .autocomplete-wrapper { position: relative; }
    .autocomplete-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 20;
        display: none;
        max-height: 190px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #9fa6ad;
        border-top: 0;
        border-radius: 0 0 4px 4px;
        box-shadow: 0 4px 10px rgba(0,0,0,.12);
    }
    .autocomplete-item {
        display: block;
        width: 100%;
        padding: 8px 10px;
        border: 0;
        border-bottom: 1px solid #f1f2f3;
        background: #fff;
        text-align: left;
        cursor: pointer;
        font-size: .82rem;
    }
    .autocomplete-item:hover,
    .autocomplete-item:focus { background: #f8f9f9; }
</style>

<?php if ($rol === 'repartidor'): ?>
    <!-- ===================================================================== -->
    <!-- VISTA MÓVIL SIMPLE EXCLUSIVA PARA REPARTIDOR                          -->
    <!-- ===================================================================== -->
    <style>
        .rep-mobile-wrapper {
            max-width: 620px;
            margin: 0 auto;
        }
        .rep-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            gap: 10px;
        }
        .rep-card {
            background: #ffffff;
            border: 1px solid var(--so-gray-border, #d6d9dc);
            border-radius: var(--ui-radius, 6px);
            padding: 14px;
            margin-bottom: 14px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .rep-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 8px;
            margin-bottom: 10px;
            border-bottom: 1px solid #e3e6e8;
        }
        .rep-badge-estado {
            padding: 3px 9px;
            border-radius: var(--ui-radius, 4px);
            font-size: 0.76rem;
            font-weight: 700;
            background: color-mix(in srgb, var(--so-orange) 14%, #ffffff);
            color: #0c0d0e;
            border: 1px solid color-mix(in srgb, var(--so-orange) 40%, #d6d9dc);
        }
        .rep-info-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #232629;
            line-height: 1.35;
        }
        .rep-cobro-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: color-mix(in srgb, var(--so-orange) 7%, #f8f9f9);
            border: 1px solid color-mix(in srgb, var(--so-orange) 25%, #e3e6e8);
            border-radius: var(--ui-radius, 4px);
            padding: 10px 12px;
            margin: 10px 0;
            gap: 8px;
            flex-wrap: wrap;
        }
        .rep-nota-box {
            font-size: 0.82rem;
            color: #3b4045;
            background: #f8f9f9;
            border: 1px dashed #babfc4;
            border-radius: 4px;
            padding: 7px 10px;
            margin-bottom: 10px;
        }
        .rep-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 10px;
        }
        .rep-btn-touch {
            min-height: 42px;
            font-size: 0.88rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border-radius: var(--ui-radius, 4px);
            cursor: pointer;
            border: 1px solid #babfc4;
            background: #f8f9f9;
            color: #0c0d0e;
            width: 100%;
        }
        .rep-btn-touch.primary-touch {
            background: var(--so-orange);
            border-color: var(--so-orange);
            color: #ffffff;
        }
        .rep-btn-touch.danger-touch {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #b91c1c;
        }
    </style>

    <div class="rep-mobile-wrapper">
        <div class="rep-header-bar">
            <div>
                <strong style="font-size: 0.95rem; color: #0c0d0e;">Entregas asignadas hoy</strong>
                <div style="font-size: 0.78rem; color: var(--so-gray-text);"><?= count($pedidos) ?> envío(s) en tu jornada</div>
            </div>
            <button type="button" class="btn" style="height: 34px; font-size: 0.8rem;" onclick="window.location.reload()">
                <?= Apariencia::icono('reasignar') ?> Actualizar
            </button>
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

        <?php if (empty($pedidos)): ?>
            <div class="card" style="text-align: center; padding: 32px 16px; color: var(--so-gray-text);">
                No tienes entregas asignadas para el día de hoy.
            </div>
        <?php else: ?>
            <?php foreach ($pedidos as $p): ?>
                <?php
                $totalCobrar  = (float)$p['monto_mando'] + (!empty($p['es_envio_gratis']) ? 0 : (float)$p['costo_envio']);
                $metodoActual = $p['metodo_pago'] ?? 'por_definir';
                $tieneNota    = !empty(trim((string)($p['nota_pago'] ?? '')));
                $enCurso      = !in_array($p['estado'], ['entregado', 'cancelado'], true);
                ?>
                <div class="rep-card">
                    <div class="rep-card-top">
                        <div>
                            <strong style="font-size: 0.98rem; font-family: monospace;"><?= htmlspecialchars($p['codigo_seguimiento']) ?></strong>
                            <div style="font-size: 0.78rem; color: #6a737c;"><?= htmlspecialchars($p['nombre_comercial']) ?></div>
                        </div>
                        <span class="rep-badge-estado"><?= htmlspecialchars(Apariencia::formatearEstado($p['estado'])) ?></span>
                    </div>

                    <div class="rep-info-row">
                        <span style="margin-top: 2px;"><?= Apariencia::icono('pedidos') ?></span>
                        <div><strong><?= htmlspecialchars($p['descripcion_paquete'] ?? 'Paquete estándar') ?></strong></div>
                    </div>

                    <div class="rep-info-row">
                        <span style="margin-top: 2px;"><?= Apariencia::icono('usuario') ?></span>
                        <div>
                            <span><?= htmlspecialchars($p['cliente_nombre']) ?></span>
                            <?php if (!empty($p['cliente_telefono'])): ?>
                                · <a href="tel:<?= htmlspecialchars(preg_replace('/\D/', '', $p['cliente_telefono'])) ?>" style="font-weight: 700; color: #0c0d0e; text-decoration: underline;">
                                    <?= htmlspecialchars($p['cliente_telefono']) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="rep-info-row">
                        <span style="margin-top: 2px;"><?= Apariencia::icono('ubicacion') ?></span>
                        <div><?= htmlspecialchars($p['cliente_direccion']) ?></div>
                    </div>

                    <div class="rep-cobro-box">
                        <div>
                            <small style="display: block; color: #525960; font-size: 0.74rem;">Total a cobrar</small>
                            <strong style="font-size: 1.25rem; color: #0c0d0e;">$<?= number_format($totalCobrar, 2) ?></strong>
                        </div>

                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 0.78rem; font-weight: 700; padding: 4px 8px; border-radius: 3px; background: #ffffff; border: 1px solid #babfc4;">
                                <?= $metodoActual === 'por_definir' ? 'Por definir' : strtoupper($metodoActual) ?>
                            </span>

                            <?php if ($metodoActual === 'por_definir'): ?>
                                <button type="button" class="btn-mini-icon" style="width: 34px; height: 34px;" title="Definir método de pago"
                                    onclick='abrirModalPago(<?= json_encode([
                                        "id"     => (int)$p["id"],
                                        "codigo" => $p["codigo_seguimiento"],
                                        "metodo" => $metodoActual
                                    ]) ?>)'>
                                    <?= Apariencia::icono('editar') ?>
                                </button>
                            <?php endif; ?>

                            <button type="button" class="btn-mini-icon <?= $tieneNota ? 'has-note' : '' ?>" style="width: 34px; height: 34px;" title="Comentario / Nota de pago"
                                onclick='abrirModalNotaPago(<?= json_encode([
                                    "id"     => (int)$p["id"],
                                    "codigo" => $p["codigo_seguimiento"],
                                    "nota"   => $p["nota_pago"] ?? ""
                                ]) ?>)'>
                                <?= Apariencia::icono('comentario') ?>
                            </button>
                        </div>
                    </div>

                    <?php if ($tieneNota): ?>
                        <div class="rep-nota-box">
                            <strong>Nota:</strong> <?= htmlspecialchars($p['nota_pago']) ?>
                        </div>
                    <?php endif; ?>

                    <button type="button" class="rep-btn-touch"
                        onclick='abrirMapaRuta(<?= json_encode([
                            "id"         => (int)$p["id"],
                            "codigo"     => $p["codigo_seguimiento"],
                            "cliente"    => $p["cliente_nombre"],
                            "direccion"  => $p["cliente_direccion"],
                            "repartidor" => $p["repartidor_nombre"] ?? "En ruta",
                            "lat"        => (float)($p["latitud"] ?: 13.701200),
                            "lng"        => (float)($p["longitud"] ?: -89.224400)
                        ]) ?>)'>
                        <?= Apariencia::icono('ruta') ?> Ver Mapa y Ubicación
                    </button>

                    <?php if ($enCurso): ?>
                        <?php if ($p['estado'] === 'asignado'): ?>
                            <form method="POST" style="margin: 8px 0 0 0;">
                                <input type="hidden" name="action" value="cambiar_estado">
                                <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="nuevo_estado" value="en_camino">
                                <button type="submit" class="rep-btn-touch primary-touch">
                                    <?= Apariencia::icono('repartidores') ?> Iniciar Ruta (En Camino)
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="rep-actions-grid">
                                <form method="POST" style="margin: 0;" onsubmit="return abrirConfirmacionFormulario(event, this, 'entregado', '<?= htmlspecialchars($p['codigo_seguimiento'], ENT_QUOTES) ?>')">
                                    <input type="hidden" name="action" value="cambiar_estado">
                                    <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="entregado">
                                    <button type="submit" class="rep-btn-touch primary-touch">
                                        Marcar Entregado
                                    </button>
                                </form>

                                <form method="POST" style="margin: 0;" onsubmit="return abrirConfirmacionFormulario(event, this, 'cancelado', '<?= htmlspecialchars($p['codigo_seguimiento'], ENT_QUOTES) ?>')">
                                    <input type="hidden" name="action" value="cambiar_estado">
                                    <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="cancelado">
                                    <button type="submit" class="rep-btn-touch danger-touch">
                                        Cancelar Envío
                                    </button>
                                </form>
                            </div>

                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ===================================================================== -->
    <!-- VISTA DE GESTIÓN PARA ADMINISTRADOR Y TIENDA (COMERCIO)               -->
    <!-- ===================================================================== -->
    <style>
        .cobro-cell-box {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 4px;
            min-width: 135px;
        }
        .cobro-monto-top {
            font-size: 0.94rem;
            font-weight: 700;
            color: #0c0d0e;
            line-height: 1.2;
        }
        .cobro-controls-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }
        .cobro-icons-group {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        .badge-pago {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.72rem;
            font-weight: 700;
            background: #f1f2f3;
            color: #232629;
        }
        .badge-pago-pendiente {
            background: color-mix(in srgb, var(--so-orange) 15%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--so-orange) 40%, #d6d9dc);
        }
        .action-bar-cell {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-icon-sq {
            width: 30px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #babfc4;
            background-color: #f8f9f9;
            color: #3b4045;
            border-radius: var(--ui-radius, 3px);
            cursor: pointer;
            transition: all 0.12s;
        }
        .btn-icon-sq:hover {
            background-color: #e3e6e8;
            color: #0c0d0e;
            border-color: var(--so-orange);
        }
        .select-action-uniform {
            height: 30px;
            padding: 0 6px;
            font-size: 0.78rem;
            border: 1px solid #babfc4;
            border-radius: var(--ui-radius, 3px);
            background: #ffffff;
        }
    </style>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="margin: 0 0 4px 0;">Pedidos y Envíos</h2>
            <p style="margin: 0; font-size: 0.85rem; color: var(--so-gray-text);">Creación de órdenes, seguimiento GPS en vivo, asignación de repartidores y auditoría.</p>
        </div>

        <button type="button" class="btn btn-primary" onclick="document.getElementById('modalNuevoPedido').classList.add('show')">
            + Nuevo Envío
        </button>
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

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Comercio</th>
                        <th>Paquete / Cliente</th>
                        <th>Dirección</th>
                        <th>Repartidor</th>
                        <th>Cobro y Pago</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px; color: var(--so-gray-text);">
                                No hay pedidos registrados todavía.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $p): ?>
                            <?php
                            $total        = (float)$p['monto_mando'] + (!empty($p['es_envio_gratis']) ? 0 : (float)$p['costo_envio']);
                            $metodoActual = $p['metodo_pago'] ?? 'por_definir';
                            $tieneNota    = !empty(trim((string)($p['nota_pago'] ?? '')));
                            $finalizado   = in_array($p['estado'], ['entregado', 'cancelado'], true);
                            ?>
                            <tr>
                                <td><code><strong><?= htmlspecialchars($p['codigo_seguimiento']) ?></strong></code></td>
                                <td><strong><?= htmlspecialchars($p['nombre_comercial']) ?></strong></td>
                                <td>
                                    <div style="font-weight: 600; font-size: 0.86rem;"><?= htmlspecialchars($p['descripcion_paquete'] ?? 'Paquete') ?></div>
                                    <small style="color: #525960;">
                                        <?= htmlspecialchars($p['cliente_nombre']) ?> (<?= htmlspecialchars($p['cliente_telefono']) ?>)
                                    </small>
                                </td>
                                <td><small><?= htmlspecialchars($p['cliente_direccion']) ?></small></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="font-size: 0.84rem;">
                                            <?= !empty($p['repartidor_nombre']) ? htmlspecialchars($p['repartidor_nombre']) : '<span style="color:#6a737c;">Sin asignar</span>' ?>
                                        </span>
                                        <?php if (!$finalizado): ?>
                                            <button type="button" class="btn-mini-icon" title="Asignar / Reasignar repartidor"
                                                onclick='abrirModalReasignar(<?= json_encode([
                                                    "id" => (int)$p["id"],
                                                    "codigo" => $p["codigo_seguimiento"],
                                                    "repartidor_id" => (int)($p["repartidor_id"] ?? 0)
                                                ]) ?>)'>
                                                <?= Apariencia::icono('reasignar') ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="cobro-cell-box">
                                        <div class="cobro-monto-top">$<?= number_format($total, 2) ?></div>
                                        <div class="cobro-controls-bottom">
                                            <?php if ($metodoActual === 'por_definir'): ?>
                                                <span class="badge-pago badge-pago-pendiente">Por definir</span>
                                            <?php else: ?>
                                                <span class="badge-pago"><?= strtoupper($metodoActual) ?></span>
                                            <?php endif; ?>

                                            <div class="cobro-icons-group">
                                                <?php if ($metodoActual === 'por_definir' || $rol === 'admin'): ?>
                                                    <button type="button" class="btn-mini-icon"
                                                        title="<?= $metodoActual === 'por_definir' ? 'Definir método de pago' : 'Cambiar método de pago' ?>"
                                                        onclick='abrirModalPago(<?= json_encode([
                                                            "id" => (int)$p["id"],
                                                            "codigo" => $p["codigo_seguimiento"],
                                                            "metodo" => $metodoActual
                                                        ]) ?>)'>
                                                        <?= Apariencia::icono('editar') ?>
                                                    </button>
                                                <?php endif; ?>

                                                <button type="button" class="btn-mini-icon <?= $tieneNota ? 'has-note' : '' ?>"
                                                    title="<?= $tieneNota ? 'Ver o editar nota de pago (!)' : 'Agregar nota de aclaración (+)' ?>"
                                                    onclick='abrirModalNotaPago(<?= json_encode([
                                                        "id" => (int)$p["id"],
                                                        "codigo" => $p["codigo_seguimiento"],
                                                        "nota" => $p["nota_pago"] ?? ""
                                                    ]) ?>)'>
                                                    <?= Apariencia::icono('comentario') ?><?= $tieneNota ? '!' : '+' ?>
                                                </button>
                                            </div>
                                        </div>

                                        <?php if (!empty($p['es_envio_gratis'])): ?>
                                            <small style="color: #6a737c; font-size: 0.71rem;">Envío gratis (-$<?= number_format($p['costo_envio'], 2) ?>)</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="padding: 3px 7px; border-radius: 3px; font-size: 0.75rem; font-weight: 600; background: #f1f2f3; display: inline-block;">
                                        <?= htmlspecialchars(Apariencia::formatearEstado($p['estado'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-bar-cell">
                                        <button type="button" class="btn-icon-sq" title="Ver recorrido GPS en vivo"
                                            onclick='abrirMapaRuta(<?= json_encode([
                                                "id" => (int)$p["id"],
                                                "codigo" => $p["codigo_seguimiento"],
                                                "cliente" => $p["cliente_nombre"],
                                                "direccion" => $p["cliente_direccion"],
                                                "repartidor" => $p["repartidor_nombre"] ?? "Pendiente de asignar",
                                                "lat" => (float)($p["latitud"] ?: 13.701200),
                                                "lng" => (float)($p["longitud"] ?: -89.224400)
                                            ]) ?>)'>
                                            <?= Apariencia::icono('ruta') ?>
                                        </button>

                                        <button type="button" class="btn-icon-sq" title="Ver detalles o cambiar estado con contraseña"
                                            onclick='abrirModalDetallePedido(<?= json_encode([
                                                "id" => (int)$p["id"],
                                                "codigo" => $p["codigo_seguimiento"],
                                                "comercio" => $p["nombre_comercial"],
                                                "paquete" => $p["descripcion_paquete"] ?? "Paquete estándar",
                                                "cliente" => $p["cliente_nombre"],
                                                "telefono" => $p["cliente_telefono"],
                                                "direccion" => $p["cliente_direccion"],
                                                "repartidor" => $p["repartidor_nombre"] ?? "Sin asignar",
                                                "estado" => $p["estado"],
                                                "estado_texto" => Apariencia::formatearEstado($p["estado"]),
                                                "total" => number_format($total, 2),
                                                "metodo" => strtoupper($metodoActual)
                                            ]) ?>)'>
                                            <?= Apariencia::icono('detalle') ?>
                                        </button>

                                        <?php if (!$finalizado): ?>
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="action" value="cambiar_estado">
                                                <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                                <select name="nuevo_estado"
                                                        onchange="confirmarCambioEstadoSelect(this, '<?= htmlspecialchars($p['codigo_seguimiento'], ENT_QUOTES) ?>')"
                                                        class="select-action-uniform"
                                                        title="Avanzar estado rápido">
                                                    <option value="">Estado...</option>
                                                    <option value="en_camino">En Camino</option>
                                                    <option value="entregado">Entregado</option>
                                                    <option value="cancelado">Cancelado</option>
                                                </select>
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

    <!-- MODAL: NUEVO ENVÍO -->
    <div class="modal-backdrop" id="modalNuevoPedido">
        <div class="modal-content" style="max-width: 560px; max-height: 92vh; overflow-y: auto;">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h3 style="margin: 0;">Registrar Nuevo Envío</h3>
                    <span id="badgeBorradorCache" style="display: none; font-size: 0.72rem; font-weight: 600; padding: 2px 7px; border-radius: 3px; background: color-mix(in srgb, var(--so-orange) 15%, #ffffff); color: #0c0d0e; border: 1px solid color-mix(in srgb, var(--so-orange) 40%, #d6d9dc);">Borrador guardado</span>
                </div>
                <button class="modal-close" onclick="document.getElementById('modalNuevoPedido').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="formNuevoPedido" onsubmit="marcarEnvioEnProceso()">
                    <input type="hidden" name="action" value="crear_pedido">

                    <?php if ($rol === 'admin'): ?>
                        <div class="form-group">
                            <label for="comercio_id">Comercio / Tienda Origen</label>
                            <div class="so-input-group">
                                <span class="so-input-group-prepend"><?= Apariencia::icono('comercios') ?></span>
                                <select name="comercio_id" id="comercio_id" class="form-control cache-pedido-field" required>
                                    <option value="">Selecciona una tienda...</option>
                                    <?php foreach ($comerciosList as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_comercial']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group so-input-popover-wrap">
                        <label for="descripcion_paquete">Categoría / Descripción del Paquete</label>
                        <div class="so-input-group">
                            <span class="so-input-group-prepend"><?= Apariencia::icono('pedidos') ?></span>
                            <input type="text" name="descripcion_paquete" id="descripcion_paquete" class="form-control cache-pedido-field" required autocomplete="off" placeholder="Escribe o haz clic para elegir categoría..." onfocus="mostrarPopover('popoverPaquete')">
                        </div>
                        <div class="quick-values-popover" id="popoverPaquete">
                            <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('descripcion_paquete', 'Comida / Restaurante', 'popoverPaquete')">Comida / Restaurante</button>
                            <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('descripcion_paquete', 'Paquetería / E-commerce', 'popoverPaquete')">Paquetería / E-commerce</button>
                            <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('descripcion_paquete', 'Documentos / Sobre', 'popoverPaquete')">Documentos / Sobre</button>
                            <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('descripcion_paquete', 'Farmacia / Salud', 'popoverPaquete')">Farmacia / Salud</button>
                            <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('descripcion_paquete', 'Ropa / Calzado', 'popoverPaquete')">Ropa / Calzado</button>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1.35fr 1fr; gap: 12px;">
                        <div class="form-group autocomplete-wrapper">
                            <label for="cliente_nombre">Nombre del Cliente</label>
                            <div class="so-input-group">
                                <span class="so-input-group-prepend"><?= Apariencia::icono('usuario') ?></span>
                                <input type="text" name="cliente_nombre" id="cliente_nombre" class="form-control cache-pedido-field" autocomplete="off" required placeholder="Ej. Juan Pérez...">
                            </div>
                            <div id="listaSugerenciasClientes" class="autocomplete-list" role="listbox"></div>
                        </div>

                        <div class="form-group">
                            <label for="cliente_telefono">Teléfono</label>
                            <div class="so-input-group">
                                <span class="so-input-group-prepend"><?= Apariencia::icono('telefono') ?></span>
                                <input type="text" name="cliente_telefono" id="cliente_telefono" class="form-control cache-pedido-field" required
                                       maxlength="9" pattern="\d{4}-\d{4}" title="Formato requerido: 0000-0000"
                                       placeholder="7000-0000" oninput="formatearTelefonoGuion(this)">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="cliente_direccion">Dirección Exacta de Entrega</label>
                        <div class="so-input-group">
                            <span class="so-input-group-prepend"><?= Apariencia::icono('ubicacion') ?></span>
                            <input type="text" name="cliente_direccion" id="cliente_direccion" class="form-control cache-pedido-field" required placeholder="Colonia, calle, número de casa o referencia...">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group so-input-popover-wrap">
                            <label for="monto_mando">Monto Producto / Pedido</label>
                            <div class="so-input-group">
                                <span class="so-input-group-prepend">$</span>
                                <input type="number" step="any" min="0" name="monto_mando" id="monto_mando" class="form-control cache-pedido-field" required
                                       value="0" placeholder="0"
                                       autocomplete="off" onfocus="limpiarSiEsCero(this); mostrarPopover('popoverMonto')" onblur="restaurarSiVacio(this, '0')">
                            </div>
                            <div class="quick-values-popover" id="popoverMonto">
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('monto_mando', '5', 'popoverMonto')">$5</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('monto_mando', '10', 'popoverMonto')">$10</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('monto_mando', '15', 'popoverMonto')">$15</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('monto_mando', '20', 'popoverMonto')">$20</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('monto_mando', '25', 'popoverMonto')">$25</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('monto_mando', '30', 'popoverMonto')">$30</button>
                            </div>
                        </div>

                        <div class="form-group so-input-popover-wrap">
                            <label for="costo_envio">Tarifa de Envío</label>
                            <div class="so-input-group">
                                <span class="so-input-group-prepend">$</span>
                                <input type="number" step="any" min="0" name="costo_envio" id="costo_envio" class="form-control cache-pedido-field" required
                                       value="1.00" placeholder="1.00"
                                       autocomplete="off" onfocus="seleccionarTodoAlEnfocar(this); mostrarPopover('popoverEnvio')" onblur="restaurarSiVacio(this, '1.00')">
                            </div>
                            <div class="quick-values-popover" id="popoverEnvio">
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('costo_envio', '1.00', 'popoverEnvio')">$1.00</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('costo_envio', '1.50', 'popoverEnvio')">$1.50</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('costo_envio', '2.00', 'popoverEnvio')">$2.00</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('costo_envio', '2.50', 'popoverEnvio')">$2.50</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('costo_envio', '3.00', 'popoverEnvio')">$3.00</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('costo_envio', '3.50', 'popoverEnvio')">$3.50</button>
                                <button type="button" class="fee-pill-btn" onmousedown="event.preventDefault(); aplicarValor('costo_envio', '5.00', 'popoverEnvio')">$5.00</button>
                            </div>
                        </div>
                    </div>


                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label for="metodo_pago">Método de Pago</label>
                            <select name="metodo_pago" id="metodo_pago" class="form-control cache-pedido-field">
                                <option value="por_definir">Por definir (al entregar)</option>
                                <option value="efectivo">Efectivo (COD)</option>
                                <option value="tarjeta">Tarjeta (POS)</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="credito">Crédito</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="repartidor_id">Asignar Repartidor (Opcional)</label>
                            <select name="repartidor_id" id="repartidor_id" class="form-control cache-pedido-field">
                                <option value="">Auto-asignar / Pendiente</option>
                                <?php foreach ($repartidoresList as $rep): ?>
                                    <option value="<?= $rep['id'] ?>">
                                        <?= htmlspecialchars($rep['nombre']) ?> (<?= ucfirst(str_replace('_', ' ', $rep['estado_disponibilidad'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="es_envio_gratis" id="es_envio_gratis" value="1" class="cache-pedido-check">
                            <span>Envío Gratis / Cortesía <small>(si se marca, la tienda cubre $<span id="envioGratisMonto">1.00</span>)</small></span>
                        </label>
                    </div>

                    <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="btn" onclick="limpiarFormularioNuevoPedido()">
                            <?= Apariencia::icono('eliminar') ?> Limpiar
                        </button>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn" onclick="document.getElementById('modalNuevoPedido').classList.remove('show')">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar Envío</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: REASIGNAR REPARTIDOR -->
    <div class="modal-backdrop" id="modalReasignarRepartidor">
        <div class="modal-content" style="max-width: 410px;">
            <div class="modal-header">
                <h3 id="tituloReasignar">Asignar Repartidor</h3>
                <button class="modal-close" onclick="document.getElementById('modalReasignarRepartidor').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="reasignar_repartidor">
                    <input type="hidden" name="pedido_id" id="reasignarPedidoId">

                    <div class="form-group">
                        <label for="reasignarSelectRep">Repartidor Activo</label>
                        <select name="repartidor_id" id="reasignarSelectRep" class="form-control" required>
                            <option value="">Selecciona un repartidor...</option>
                            <?php foreach ($repartidoresList as $rep): ?>
                                <option value="<?= $rep['id'] ?>"><?= htmlspecialchars($rep['nombre']) ?> (<?= htmlspecialchars($rep['vehiculo']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="text-align: right; margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" class="btn" onclick="document.getElementById('modalReasignarRepartidor').classList.remove('show')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar Asignación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: DETALLE Y CAMBIO DE ESTADO PROTEGIDO POR CONTRASEÑA -->
    <div class="modal-backdrop" id="modalDetallePedido">
        <div class="modal-content" style="max-width: 500px; max-height: 92vh; overflow-y: auto;">
            <div class="modal-header">
                <h3 id="detalleTitulo">Detalle del Pedido</h3>
                <button class="modal-close" onclick="document.getElementById('modalDetallePedido').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="background: #f8f9f9; border: 1px solid #d6d9dc; border-radius: 3px; padding: 10px 12px; margin-bottom: 14px; font-size: 0.86rem; line-height: 1.55;">
                    <div><strong>Comercio:</strong> <span id="detComercio"></span></div>
                    <div><strong>Paquete:</strong> <span id="detPaquete"></span></div>
                    <div><strong>Cliente:</strong> <span id="detCliente"></span> · <span id="detTelefono"></span></div>
                    <div><strong>Dirección:</strong> <span id="detDireccion"></span></div>
                    <div><strong>Repartidor:</strong> <span id="detRepartidor"></span></div>
                    <div><strong>Cobro Total:</strong> $<span id="detTotal"></span> (<span id="detMetodo"></span>)</div>
                    <div><strong>Estado Actual:</strong> <strong id="detEstadoActual"></strong></div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="editar_estado_seguro">
                    <input type="hidden" name="pedido_id" id="detPedidoId">

                    <strong style="display: block; font-size: 0.85rem; margin-bottom: 8px;">Modificar Estado (Requiere Contraseña y Bitácora)</strong>

                    <div class="form-group">
                        <label for="detNuevoEstado">Nuevo Estado</label>
                        <select name="nuevo_estado" id="detNuevoEstado" class="form-control" required>
                            <option value="pendiente">Pendiente</option>
                            <option value="asignado">Asignado</option>
                            <option value="en_camino">En Camino</option>
                            <option value="entregado">Entregado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="detMotivo">Motivo del Cambio (Auditado)</label>
                        <input type="text" name="motivo_cambio" id="detMotivo" class="form-control" required placeholder="Ej. Corrección de estado marcado por error...">
                    </div>

                    <div class="form-group">
                        <label for="detPassword">Tu Contraseña de Usuario</label>
                        <input type="password" name="password_confirmacion" id="detPassword" class="form-control" required placeholder="Ingresa tu contraseña para autorizar">
                    </div>

                    <div style="text-align: right; margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" class="btn" onclick="document.getElementById('modalDetallePedido').classList.remove('show')">Cerrar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambio Auditado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- MODALES COMPARTIDOS (MÉTODO DE PAGO Y NOTA DE PAGO - TODOS LOS ROLES)     -->
<!-- ========================================================================= -->
<div class="modal-backdrop" id="modalMetodoPago">
    <div class="modal-content" style="max-width: 390px;">
        <div class="modal-header">
            <h3 id="tituloModalPago">Método de Pago</h3>
            <button class="modal-close" onclick="document.getElementById('modalMetodoPago').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="actualizar_metodo_pago">
                <input type="hidden" name="pedido_id" id="pagoPedidoId">

                <div class="form-group">
                    <label for="pagoSelectMetodo">Selecciona cómo pagó el cliente</label>
                    <select name="metodo_pago" id="pagoSelectMetodo" class="form-control" required>
                        <option value="efectivo">Efectivo (COD)</option>
                        <option value="tarjeta">Tarjeta (POS)</option>
                        <option value="transferencia">Transferencia Bancaria</option>
                        <option value="credito">Crédito</option>
                    </select>
                </div>

                <div style="text-align: right; margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn" onclick="document.getElementById('modalMetodoPago').classList.remove('show')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="modalNotaPago">
    <div class="modal-content" style="max-width: 410px;">
        <div class="modal-header">
            <h3 id="tituloModalNota">Nota de Pago / Aclaración</h3>
            <button class="modal-close" onclick="document.getElementById('modalNotaPago').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="guardar_nota_pago">
                <input type="hidden" name="pedido_id" id="notaPedidoId">

                <div class="form-group">
                    <label for="notaTextoInput">Comentario o referencia de cobro</label>
                    <textarea name="nota_pago" id="notaTextoInput" rows="3" class="form-control" placeholder="Ej. Pagó con billete de $20, transferencia ref #1234..."></textarea>
                </div>

                <div style="text-align: right; margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn" onclick="document.getElementById('modalNotaPago').classList.remove('show')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Nota</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const clientesFrecuentes = <?= json_encode($clientesFrecuentes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const CACHE_KEY_NUEVO_PEDIDO = 'gpeedl_borrador_nuevo_pedido_v1';

function formatearTelefonoGuion(input) {
    let nums = input.value.replace(/\D/g, '').substring(0, 8);
    input.value = nums.length > 4 ? nums.substring(0, 4) + '-' + nums.substring(4) : nums;
}

function seleccionarTodoAlEnfocar(input) {
    const valor = parseFloat(input.value);
    if (input.value.trim() === '' || valor === 0) {
        input.value = '';
    } else {
        input.select();
    }
}

function seleccionarMonto(inputId, valor) {
    const input = document.getElementById(inputId);
    if (input) {
        input.value = Number(valor).toString();
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function mostrarPopover(popoverId) {
    cerrarTodosLosPopovers();
    document.getElementById(popoverId)?.classList.add('show');
}

function cerrarTodosLosPopovers() {
    document.querySelectorAll('.quick-values-popover.show').forEach(popover => popover.classList.remove('show'));
}

function actualizarEtiquetaEnvioGratis() {
    const tarifa = Number.parseFloat(document.getElementById('costo_envio')?.value ?? '');
    const etiqueta = document.getElementById('envioGratisMonto');
    if (etiqueta) etiqueta.textContent = Number.isFinite(tarifa) ? tarifa.toFixed(2) : '0.00';
}

function aplicarValor(inputId, valor, popoverId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.value = valor;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        guardarBorradorPedido();
    }
    if (popoverId) document.getElementById(popoverId)?.classList.remove('show');
}

document.addEventListener('click', event => {
    if (!event.target.closest('.so-input-popover-wrap')) cerrarTodosLosPopovers();
});

function guardarBorradorPedido() {
    const form = document.getElementById('formNuevoPedido');
    if (!form) return;

    const datos = {};
    let tieneContenido = false;
    form.querySelectorAll('.cache-pedido-field').forEach(input => {
        datos[input.id] = input.value;
        if (['monto_mando', 'costo_envio', 'metodo_pago'].includes(input.id)) {
            if (input.id === 'monto_mando' && Number.parseFloat(input.value) > 0) tieneContenido = true;
            if (input.id === 'costo_envio' && input.value.trim() !== '' && Number.parseFloat(input.value) !== 1) tieneContenido = true;
            if (input.id === 'metodo_pago' && input.value !== 'por_definir') tieneContenido = true;
        } else if (input.value.trim() !== '') {
            tieneContenido = true;
        }
    });

    const envioGratis = document.getElementById('es_envio_gratis');
    if (envioGratis) {
        datos.es_envio_gratis = envioGratis.checked;
        if (envioGratis.checked) tieneContenido = true;
    }

    try {
        if (tieneContenido) {
            localStorage.setItem(CACHE_KEY_NUEVO_PEDIDO, JSON.stringify(datos));
            const badge = document.getElementById('badgeBorradorCache');
            if (badge) badge.style.display = 'inline-block';
        } else {
            localStorage.removeItem(CACHE_KEY_NUEVO_PEDIDO);
            const badge = document.getElementById('badgeBorradorCache');
            if (badge) badge.style.display = 'none';
        }
    } catch (error) {
        console.warn('No se pudo guardar el borrador local del pedido.', error);
    }
}

function restaurarBorradorPedido() {
    try {
        const raw = localStorage.getItem(CACHE_KEY_NUEVO_PEDIDO);
        if (!raw) return false;
        const datos = JSON.parse(raw);
        Object.entries(datos).forEach(([id, valor]) => {
            const campo = document.getElementById(id);
            if (!campo) return;
            if (campo.type === 'checkbox') campo.checked = Boolean(valor);
            else campo.value = valor;
        });
        const badge = document.getElementById('badgeBorradorCache');
        if (badge) badge.style.display = 'inline-block';
        return true;
    } catch (error) {
        try { localStorage.removeItem(CACHE_KEY_NUEVO_PEDIDO); } catch (storageError) {}
        return false;
    }
}

function limpiarFormularioNuevoPedido() {
    document.getElementById('formNuevoPedido')?.reset();
    const monto = document.getElementById('monto_mando');
    const envio = document.getElementById('costo_envio');
    if (monto) monto.value = '0';
    if (envio) envio.value = '1.00';
    actualizarEtiquetaEnvioGratis();
    try { localStorage.removeItem(CACHE_KEY_NUEVO_PEDIDO); } catch (error) {}
    cerrarTodosLosPopovers();
    const badge = document.getElementById('badgeBorradorCache');
    if (badge) badge.style.display = 'none';
}

function marcarEnvioEnProceso() {
    guardarBorradorPedido();
}

const inputClienteNombre = document.getElementById('cliente_nombre');
const listaSugerenciasClientes = document.getElementById('listaSugerenciasClientes');
if (inputClienteNombre && listaSugerenciasClientes) {
    inputClienteNombre.addEventListener('input', () => {
        const consulta = inputClienteNombre.value.trim().toLocaleLowerCase('es');
        listaSugerenciasClientes.replaceChildren();
        if (consulta.length < 2) {
            listaSugerenciasClientes.style.display = 'none';
            return;
        }

        const coincidencias = clientesFrecuentes.filter(cliente =>
            String(cliente.cliente_nombre || '').toLocaleLowerCase('es').includes(consulta) ||
            String(cliente.cliente_telefono || '').includes(consulta)
        ).slice(0, 8);

        coincidencias.forEach(cliente => {
            const opcion = document.createElement('button');
            opcion.type = 'button';
            opcion.className = 'autocomplete-item';
            const nombre = document.createElement('strong');
            nombre.textContent = cliente.cliente_nombre || '';
            const telefono = document.createElement('span');
            telefono.textContent = ` · ${cliente.cliente_telefono || ''}`;
            const direccion = document.createElement('small');
            direccion.style.cssText = 'display:block;color:#6a737c;margin-top:3px;';
            direccion.textContent = cliente.cliente_direccion || '';
            opcion.append(nombre, telefono, direccion);
            opcion.addEventListener('click', () => {
                inputClienteNombre.value = cliente.cliente_nombre || '';
                const telefonoInput = document.getElementById('cliente_telefono');
                const direccionInput = document.getElementById('cliente_direccion');
                if (telefonoInput) {
                    telefonoInput.value = cliente.cliente_telefono || '';
                    formatearTelefonoGuion(telefonoInput);
                    telefonoInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (direccionInput) {
                    direccionInput.value = cliente.cliente_direccion || '';
                    direccionInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                inputClienteNombre.dispatchEvent(new Event('input', { bubbles: true }));
                listaSugerenciasClientes.style.display = 'none';
            });
            listaSugerenciasClientes.appendChild(opcion);
        });
        listaSugerenciasClientes.style.display = coincidencias.length ? 'block' : 'none';
    });

    document.addEventListener('click', event => {
        if (!event.target.closest('.autocomplete-wrapper')) {
            listaSugerenciasClientes.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formNuevoPedido');
    if (!form) return;

    form.querySelectorAll('.cache-pedido-field').forEach(campo => {
        campo.addEventListener('input', guardarBorradorPedido);
        campo.addEventListener('change', guardarBorradorPedido);
    });
    document.getElementById('costo_envio')?.addEventListener('input', actualizarEtiquetaEnvioGratis);
    document.getElementById('costo_envio')?.addEventListener('change', actualizarEtiquetaEnvioGratis);
    document.getElementById('es_envio_gratis')?.addEventListener('change', guardarBorradorPedido);

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'crear_pedido'): ?>
        <?php if ($pedidoCreado): ?>
            limpiarFormularioNuevoPedido();
        <?php else: ?>
            restaurarBorradorPedido();
            actualizarEtiquetaEnvioGratis();
            document.getElementById('modalNuevoPedido')?.classList.add('show');
        <?php endif; ?>
    <?php else: ?>
        restaurarBorradorPedido();
        actualizarEtiquetaEnvioGratis();
    <?php endif; ?>
});

function abrirModalPago(info) {
    document.getElementById('tituloModalPago').innerText = 'Pago Pedido #' + info.codigo;
    document.getElementById('pagoPedidoId').value = info.id;
    const sel = document.getElementById('pagoSelectMetodo');
    if (info.metodo && info.metodo !== 'por_definir') {
        sel.value = info.metodo;
    }
    document.getElementById('modalMetodoPago').classList.add('show');
}

function abrirModalNotaPago(info) {
    document.getElementById('tituloModalNota').innerText = 'Nota Pedido #' + info.codigo;
    document.getElementById('notaPedidoId').value = info.id;
    document.getElementById('notaTextoInput').value = info.nota || '';
    document.getElementById('modalNotaPago').classList.add('show');
}

function abrirModalReasignar(info) {
    document.getElementById('tituloReasignar').innerText = 'Repartidor · #' + info.codigo;
    document.getElementById('reasignarPedidoId').value = info.id;
    document.getElementById('reasignarSelectRep').value = info.repartidor_id || '';
    document.getElementById('modalReasignarRepartidor').classList.add('show');
}

function abrirModalDetallePedido(info) {
    document.getElementById('detalleTitulo').innerText = 'Detalle Pedido #' + info.codigo;
    document.getElementById('detPedidoId').value = info.id;
    document.getElementById('detComercio').innerText = info.comercio;
    document.getElementById('detPaquete').innerText = info.paquete;
    document.getElementById('detCliente').innerText = info.cliente;
    document.getElementById('detTelefono').innerText = info.telefono;
    document.getElementById('detDireccion').innerText = info.direccion;
    document.getElementById('detRepartidor').innerText = info.repartidor;
    document.getElementById('detTotal').innerText = info.total;
    document.getElementById('detMetodo').innerText = info.metodo;
    document.getElementById('detEstadoActual').innerText = info.estado_texto;
    document.getElementById('detNuevoEstado').value = info.estado;
    document.getElementById('detMotivo').value = '';
    document.getElementById('detPassword').value = '';
    document.getElementById('modalDetallePedido').classList.add('show');
}

function limpiarSiEsCero(input) {
    const val = parseFloat(input.value);
    if (input.value.trim() === '' || val === 0) {
        input.value = '';
    }
}

function restaurarSiVacio(input, valorDefecto) {
    if (input.value.trim() === '' || isNaN(parseFloat(input.value))) {
        input.value = valorDefecto;
    }
    if (input.id === 'costo_envio') actualizarEtiquetaEnvioGratis();
    guardarBorradorPedido();
}

let formularioEstadoPendiente = null;
let selectEstadoPendiente = null;

function confirmarCambioEstadoSelect(selectEl, codigoPedido) {
    const estado = selectEl.value;
    if (!estado) return;

    // Si es 'en_camino', avanza directo sin bloquear
    if (estado !== 'entregado' && estado !== 'cancelado') {
        selectEl.form.submit();
        return;
    }

    formularioEstadoPendiente = selectEl.form;
    selectEstadoPendiente = selectEl;
    mostrarModalConfirmacionEstado(estado, codigoPedido);
}

function abrirConfirmacionFormulario(e, formEl, estado, codigoPedido) {
    e.preventDefault();
    formularioEstadoPendiente = formEl;
    selectEstadoPendiente = null;
    mostrarModalConfirmacionEstado(estado, codigoPedido);
    return false;
}

function mostrarModalConfirmacionEstado(estado, codigoPedido) {
    const titulo = document.getElementById('tituloConfirmarEstado');
    const texto  = document.getElementById('textoConfirmarEstado');
    const banner = document.getElementById('bannerAvisoEstado');
    const btnOk  = document.getElementById('btnEjecutarConfirmacion');

    if (estado === 'entregado') {
        titulo.innerText = 'Confirmar Entrega · #' + codigoPedido;
        texto.innerText  = '¿Confirmas que el pedido #' + codigoPedido + ' fue ENTREGADO y cobrado correctamente?';
        banner.className = 'alert-banner success';
        btnOk.innerText  = 'Sí, Marcar Entregado';
        btnOk.style.backgroundColor = '';
        btnOk.style.borderColor = '';
    } else {
        titulo.innerText = 'Confirmar Cancelación · #' + codigoPedido;
        texto.innerText  = '¿Seguro que deseas CANCELAR el pedido #' + codigoPedido + '?';
        banner.className = 'alert-banner error';
        btnOk.innerText  = 'Sí, Cancelar Pedido';
        btnOk.style.backgroundColor = '#b91c1c';
        btnOk.style.borderColor = '#b91c1c';
    }

    document.getElementById('modalConfirmarEstado').classList.add('show');
}

function cancelarConfirmacionEstado() {
    document.getElementById('modalConfirmarEstado').classList.remove('show');
    if (selectEstadoPendiente) {
        selectEstadoPendiente.value = '';
    }
    formularioEstadoPendiente = null;
    selectEstadoPendiente = null;
}

function ejecutarCambioEstadoConfirmado() {
    if (formularioEstadoPendiente) {
        formularioEstadoPendiente.submit();
    }
}

</script>

<!-- MODAL: CONFIRMACIÓN DE ENTREGA O CANCELACIÓN -->
<div class="modal-backdrop" id="modalConfirmarEstado">
    <div class="modal-content" style="max-width: 410px;">
        <div class="modal-header">
            <h3 id="tituloConfirmarEstado" style="margin: 0;">Confirmar Cambio de Estado</h3>
            <button type="button" class="modal-close" onclick="cancelarConfirmacionEstado()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="bannerAvisoEstado" class="alert-banner warning" style="margin-bottom: 12px;">
                <span id="textoConfirmarEstado"></span>
            </div>
            <p style="font-size: 0.84rem; color: #525960; margin: 0 0 16px 0;">
                Esta acción finalizará el envío activo y quedará registrada en el historial del turno.
            </p>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn" onclick="cancelarConfirmacionEstado()">Volver</button>
                <button type="button" id="btnEjecutarConfirmacion" class="btn btn-primary" onclick="ejecutarCambioEstadoConfirmado()">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>