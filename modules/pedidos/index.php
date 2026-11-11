<?php
require_once __DIR__ . '/../../layout/header.php';
require_once __DIR__ . '/../usuarios/auth.php';
require_once __DIR__ . '/Pedido.php';
require_once __DIR__ . '/../comercios/Comercio.php';
require_once __DIR__ . '/../repartidores/Repartidor.php';

$rol = $_SESSION['usuario_rol'];
$usuario_id = $_SESSION['usuario_id'];
$mensaje = '';

$filtro = $_GET['filtro'] ?? 'todos';

// Procesar Asignación o Reasignación de Repartidor (Admin y Comercio)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'asignar') {
    Auth::verificarRol(['admin', 'comercio']);
    $pedido_id = (int)$_POST['pedido_id'];
    $repartidor_id = (int)$_POST['repartidor_id'];
    if (Pedido::asignarRepartidor($pedido_id, $repartidor_id)) {
        $mensaje = "Repartidor asignado/reasignado correctamente.";
    }
}

// Procesar Cambio de Estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_estado') {
    $pedido_id = (int)$_POST['pedido_id'];
    $nuevo_estado = $_POST['estado'];
    if (Pedido::actualizarEstado($pedido_id, $nuevo_estado)) {
        $mensaje = "Estado del pedido actualizado a " . strtoupper($nuevo_estado) . ".";
    }
}

// Procesar Nuevo Pedido desde Modal (Comercio / Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_pedido') {
    Auth::verificarRol(['admin', 'comercio']);
    
    $comercio_id = (int)$_POST['comercio_id'];
    $cliente = trim($_POST['cliente_nombre']);
    $direccion = trim($_POST['cliente_direccion']);
    $telefono = trim($_POST['cliente_telefono']);
    $monto = (float)$_POST['monto_mando'];
    $costo_envio = (float)$_POST['costo_envio'];

    if ($comercio_id <= 0) {
        $mensaje = "Error: El usuario no tiene un perfil de comercio registrado.";
    } else {
        if (Pedido::crear($comercio_id, $cliente, $direccion, $telefono, $monto, $costo_envio)) {
            $mensaje = "Pedido registrado con éxito.";
        }
    }
}

// Cargar pedidos según el rol
if ($rol === 'admin') {
    $todosPedidos = Pedido::listarTodos();
} elseif ($rol === 'comercio') {
    $todosPedidos = Pedido::listarPorComercio($usuario_id);
} else {
    $todosPedidos = Pedido::listarPorRepartidor($usuario_id);
}

// 1. Filtrar pedidos por pestaña activa
$pedidosFiltrados = array_filter($todosPedidos, function($p) use ($filtro) {
    if ($filtro === 'activos') {
        return in_array($p['estado'], ['pendiente', 'asignado', 'en_camino']);
    } elseif ($filtro === 'entregados') {
        return $p['estado'] === 'entregado';
    } elseif ($filtro === 'cancelados') {
        return $p['estado'] === 'cancelado';
    }
    return true;
});

// 2. Configuración y cálculo de la Paginación (10 pedidos por página)
$limitePorPagina = 10;
$paginaActual = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$totalPedidos = count($pedidosFiltrados);
$totalPaginas = max(1, (int)ceil($totalPedidos / $limitePorPagina));
$offset = ($paginaActual - 1) * $limitePorPagina;

// Extraer solo el bloque de pedidos correspondiente a la página actual
$pedidos = array_slice($pedidosFiltrados, $offset, $limitePorPagina);

// Cargar repartidores para asignación
$db = Database::getConnection();
$repartidoresLista = $db->query("SELECT r.id, u.nombre, r.estado_disponibilidad FROM repartidores r INNER JOIN usuarios u ON r.usuario_id = u.id")->fetchAll(PDO::FETCH_ASSOC);

$comercioActual = null;
if ($rol === 'comercio') {
    $stmtCom = $db->prepare("SELECT id FROM comercios WHERE usuario_id = :uid LIMIT 1");
    $stmtCom->execute([':uid' => $usuario_id]);
    $comercioActual = $stmtCom->fetch(PDO::FETCH_ASSOC);
}
?>

<!-- Encabezado de modules/pedidos/index.php -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h2>Control de Pedidos y Envíos</h2>
    <div style="display: flex; gap: 8px;">
        <button type="button" class="btn" onclick="window.location.reload()" style="display: inline-flex; align-items: center; gap: 5px;">
            🔄 Actualizar
        </button>
        <?php if (in_array($rol, ['admin', 'comercio'])): ?>
            <button type="button" class="btn btn-primary" onclick="abrirModalPedido()">+ Crear Pedido</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="padding: 10px; margin-bottom: 15px; border-radius: 3px; font-size: 0.9rem; background-color: #eef9f2; border: 1px solid #bcf0da; color: #0e9f6e;">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<!-- Tabs de Filtrado -->
<div class="tabs-container">
    <a href="?filtro=todos" class="tab-btn <?= $filtro === 'todos' ? 'active' : '' ?>">Todos los Pedidos</a>
    <a href="?filtro=activos" class="tab-btn <?= $filtro === 'activos' ? 'active' : '' ?>">Pedidos Activos</a>
    <a href="?filtro=entregados" class="tab-btn <?= $filtro === 'entregados' ? 'active' : '' ?>">Entregados</a>
    <a href="?filtro=cancelados" class="tab-btn <?= $filtro === 'cancelados' ? 'active' : '' ?>">Cancelados</a>
</div>

<!-- Tabla de Pedidos -->
<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Comercio</th>
                    <th>Cliente / Destino</th>
                    <th>Monto Total</th>
                    <th>Repartidor</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pedidos)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--so-gray-text);">No hay envíos en este filtro.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pedidos as $p): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($p['codigo_seguimiento']) ?></code></td>
                            <td><?= htmlspecialchars($p['nombre_comercial']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($p['cliente_nombre']) ?></strong><br>
                                <small style="color: var(--so-gray-text);"><?= htmlspecialchars($p['cliente_direccion']) ?> (Tel: <?= htmlspecialchars($p['cliente_telefono']) ?>)</small>
                            </td>
                            <td>
                                <strong>$<?= number_format($p['monto_mando'] + $p['costo_envio'], 2) ?></strong><br>
                                <small style="color: var(--so-gray-text);">Envío: $<?= number_format($p['costo_envio'], 2) ?></small>
                            </td>
                            <td>
                                <?= !empty($p['repartidor_nombre']) ? htmlspecialchars($p['repartidor_nombre']) : '<em style="color:#999;">Sin Asignar</em>' ?>
                            </td>
                            <td>
                                <?php
                                $badgeStyle = "background-color:#f3f4f6; color:#374151;";
                                switch ($p['estado']) {
                                    case 'pendiente': $badgeStyle = "background-color:#feecdc; color:#9a3412;"; break;
                                    case 'asignado': $badgeStyle = "background-color:#e1ecf4; color:#39739d;"; break;
                                    case 'en_camino': $badgeStyle = "background-color:#e0f2fe; color:#0369a1;"; break;
                                    case 'entregado': $badgeStyle = "background-color:#def7ec; color:#03543f;"; break;
                                    case 'cancelado': $badgeStyle = "background-color:#fde8e8; color:#9b1c1c;"; break;
                                }
                                ?>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 0.75rem; <?= $badgeStyle ?>">
                                    <?= strtoupper($p['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <!-- Asignar o Reasignar Repartidor -->
                                <?php if (in_array($rol, ['admin', 'comercio']) && !in_array($p['estado'], ['entregado', 'cancelado'])): ?>
                                    <form id="form-asignar-<?= $p['id'] ?>" action="" method="POST" style="display:inline-flex; gap: 5px;">
                                        <input type="hidden" name="action" value="asignar">
                                        <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                        <select name="repartidor_id" id="select-rep-<?= $p['id'] ?>" class="form-control" style="font-size:0.8rem; padding:2px 5px;" required>
                                            <option value=""><?= !empty($p['repartidor_id']) ? 'Cambiar repartidor...' : 'Asignar repartidor...' ?></option>
                                            <?php foreach ($repartidoresLista as $r): ?>
                                                <?php if ($r['id'] != $p['repartidor_id']): ?>
                                                    <option value="<?= $r['id'] ?>">
                                                        <?= htmlspecialchars($r['nombre']) ?> (<?= ucfirst($r['estado_disponibilidad']) ?>)
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" onclick="validarYEnviarAsignacion(<?= $p['id'] ?>, '<?= htmlspecialchars($p['repartidor_nombre'] ?? '') ?>')" class="btn" style="padding: 2px 6px; font-size:0.75rem;">
                                            <?= !empty($p['repartidor_id']) ? 'Cambiar' : 'Asignar' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Cambio de Estado -->
                                <?php if (in_array($rol, ['admin', 'repartidor']) && !in_array($p['estado'], ['entregado', 'cancelado'])): ?>
                                    <form action="" method="POST" style="display:inline-flex; gap: 5px; margin-top:3px;">
                                        <input type="hidden" name="action" value="cambiar_estado">
                                        <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                        <select name="estado" class="form-control" style="font-size:0.8rem; padding:2px 5px;">
                                            <?php if ($p['estado'] === 'asignado'): ?>
                                                <option value="en_camino">En Camino</option>
                                            <?php endif; ?>
                                            <option value="entregado">Entregado</option>
                                            <option value="cancelado">Cancelado</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary" style="padding: 2px 6px; font-size:0.75rem;">Actualizar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginador Flat UI -->
    <?php if ($totalPaginas > 1): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 12px; border-top: 1px solid var(--so-gray-border); flex-wrap: wrap; gap: 10px;">
            <small style="color: var(--so-gray-text);">
                Mostrando <?= count($pedidos) ?> de <?= $totalPedidos ?> pedidos (Página <?= $paginaActual ?> de <?= $totalPaginas ?>)
            </small>
            
            <div style="display: flex; gap: 4px;">
                <?php if ($paginaActual > 1): ?>
                    <a href="?filtro=<?= $filtro ?>&p=<?= $paginaActual - 1 ?>" class="btn" style="padding: 3px 8px; font-size: 0.8rem;">&laquo; Anterior</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <a href="?filtro=<?= $filtro ?>&p=<?= $i ?>" class="btn <?= $i === $paginaActual ? 'btn-primary' : '' ?>" style="padding: 3px 8px; font-size: 0.8rem;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($paginaActual < $totalPaginas): ?>
                    <a href="?filtro=<?= $filtro ?>&p=<?= $paginaActual + 1 ?>" class="btn" style="padding: 3px 8px; font-size: 0.8rem;">Siguiente &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Elegante de Confirmación de Reasignación -->
<div class="modal-backdrop" id="modalConfirmarReasignacion">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header" style="border-bottom: 1px solid #fee2e2;">
            <h3 style="color: #c2410c; display: flex; align-items: center; gap: 8px;">
                ⚠️ Advertencia de Reasignación
            </h3>
            <button class="modal-close" onclick="cerrarModalConfirmar()">&times;</button>
        </div>
        <div class="modal-body">
            <p id="textoModalConfirmar" style="font-size: 0.9rem; color: var(--so-black); line-height: 1.4; margin-bottom: 20px;"></p>
            
            <div style="text-align: right; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn" onclick="cerrarModalConfirmar()">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnAceptarReasignacion">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<script>
let formActivoProcesar = null;

function validarYEnviarAsignacion(pedidoId, repartidorActual) {
    const select = document.getElementById('select-rep-' + pedidoId);
    if (!select.value) {
        mostrarToast('Por favor, selecciona un repartidor antes de continuar.', 'warning', true);
        return;
    }

    formActivoProcesar = document.getElementById('form-asignar-' + pedidoId);

    if (repartidorActual && repartidorActual.trim() !== '') {
        const texto = `Este pedido se encuentra actualmente asignado a <strong>${repartidorActual}</strong>.<br><br>¿Deseas desasignarlo y transferir la orden al nuevo repartidor seleccionado?`;
        document.getElementById('textoModalConfirmar').innerHTML = texto;
        document.getElementById('modalConfirmarReasignacion').classList.add('show');
    } else {
        formActivoProcesar.submit();
    }
}

function cerrarModalConfirmar() {
    document.getElementById('modalConfirmarReasignacion').classList.remove('show');
    formActivoProcesar = null;
}

document.getElementById('btnAceptarReasignacion').addEventListener('click', function() {
    if (formActivoProcesar) {
        formActivoProcesar.submit();
    }
});
</script>

<!-- Modal Registrar Pedido -->
<?php if (in_array($rol, ['admin', 'comercio'])): ?>
<div class="modal-backdrop" id="modalPedido">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Registrar Orden de Envío</h3>
            <button class="modal-close" onclick="cerrarModalPedido()">&times;</button>
        </div>
        <div class="modal-body">
            <form action="" method="POST" novalidate>
                <input type="hidden" name="action" value="crear_pedido">

                <?php if ($rol === 'admin'): ?>
                    <div class="form-group">
                        <label for="comercio_id">Comercio Origen</label>
                        <select name="comercio_id" id="comercio_id" class="form-control" required>
                            <option value="">Seleccione un comercio...</option>
                            <?php
                            $comerciosAll = Comercio::listar();
                            foreach ($comerciosAll as $c) {
                                echo "<option value='{$c['id']}'>" . htmlspecialchars($c['nombre_comercial']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="comercio_id" value="<?= $comercioActual['id'] ?? 0 ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="cliente_nombre">Nombre del Cliente</label>
                    <input type="text" name="cliente_nombre" id="cliente_nombre" class="form-control" required placeholder="Ej. Ana Martínez">
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label for="cliente_telefono">Teléfono Cliente</label>
                        <input type="text" name="cliente_telefono" id="cliente_telefono" class="form-control" required placeholder="5555-8899">
                    </div>

                    <div class="form-group">
                        <label for="cliente_direccion">Dirección de Entrega</label>
                        <input type="text" name="cliente_direccion" id="cliente_direccion" class="form-control" required placeholder="Calle 4, Apt 2B">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label for="monto_mando">Valor del Pedido ($)</label>
                        <input type="number" step="0.01" name="monto_mando" id="monto_mando" class="form-control" required placeholder="25.50">
                    </div>

                    <div class="form-group">
                        <label for="costo_envio">Costo de Envío ($)</label>
                        <input type="number" step="0.01" name="costo_envio" id="costo_envio" class="form-control" required placeholder="3.00">
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn" onclick="cerrarModalPedido()" style="margin-right: 8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Orden de Delivery</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalPedido() {
    document.getElementById('modalPedido').classList.add('show');
}
function cerrarModalPedido() {
    document.getElementById('modalPedido').classList.remove('show');
}
document.getElementById('modalPedido').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalPedido();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>