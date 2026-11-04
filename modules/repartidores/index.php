<?php
// 1. Cargar dependencias y verificar permisos ANTES de imprimir HTML
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../usuarios/auth.php';
require_once __DIR__ . '/Repartidor.php';
require_once __DIR__ . '/../comercios/Comercio.php';

Auth::verificarRol(['admin', 'comercio']);

require_once __DIR__ . '/../../layout/header.php';

$rol = $_SESSION['usuario_rol'];
$usuario_id = $_SESSION['usuario_id'];
$comercio_id = null;

$db = Database::getConnection();
if ($rol === 'comercio') {
    $stmtCom = $db->prepare("SELECT id FROM comercios WHERE usuario_id = :uid LIMIT 1");
    $stmtCom->execute([':uid' => $usuario_id]);
    $comercio_id = $stmtCom->fetchColumn() ?: null;
}

// Cargar la lista de comercios solo si es Administrador
$comerciosLista = [];
if ($rol === 'admin') {
    $comerciosLista = Comercio::listar();
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $vehiculo = trim($_POST['vehiculo'] ?? '');
    $placa = trim($_POST['placa'] ?? '');

    // Si es Admin, tomar el comercio_id seleccionado del combobox (si se dejó vacío, queda null)
    if ($rol === 'admin') {
        $comercio_id_input = !empty($_POST['comercio_id']) ? (int)$_POST['comercio_id'] : null;
    } else {
        $comercio_id_input = $comercio_id;
    }

    $esBicicleta = strtolower($vehiculo) === 'bicicleta';

    if (!empty($nombre) && !empty($email) && !empty($password) && !empty($vehiculo) && ($esBicicleta || !empty($placa))) {
        $resultado = Repartidor::registrar($nombre, $email, $password, $vehiculo, $placa, $comercio_id_input);
        if ($resultado === true) {
            $mensaje = "Repartidor registrado con éxito.";
            $tipo_mensaje = "exito";
        } else {
            $mensaje = $resultado;
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "Por favor, completa todos los campos obligatorios.";
        $tipo_mensaje = "error";
    }
}

$repartidores = Repartidor::listar($comercio_id);
?>

<!-- Encabezado y Tabla quedan exactamente igual -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h2>Control de Repartidores</h2>
    <button type="button" class="btn btn-primary" onclick="abrirModalRepartidor()">+ Nuevo Repartidor</button>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="padding: 10px; margin-bottom: 15px; border-radius: 3px; font-size: 0.9rem; 
                background-color: <?= $tipo_mensaje === 'exito' ? '#eef9f2' : '#fdf2f2' ?>; 
                border: 1px solid <?= $tipo_mensaje === 'exito' ? '#bcf0da' : '#f8b4b4' ?>; 
                color: <?= $tipo_mensaje === 'exito' ? '#0e9f6e' : '#9b1c1c' ?>;">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<!-- Procesar Toggle de Estado (Activar / Desactivar) -->
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_estado_repartidor') {
    $r_id = (int)$_POST['repartidor_id'];
    $nuevo_st = (int)$_POST['nuevo_estado'];
    if (Repartidor::cambiarEstado($r_id, $nuevo_st)) {
        $mensaje = $nuevo_st === 1 ? "Repartidor activado correctamente." : "Repartidor desactivado correctamente.";
        $tipo_mensaje = "exito";
    }
    // Recargar lista actualizada
    $repartidores = Repartidor::listar($comercio_id);
}
?>

<!-- Tabla de Repartidores -->
<div class="card">
    <div class="table-responsive">
        <table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Contacto</th>
            <?php if ($rol === 'admin'): ?><th>Comercio Afiliado</th><?php endif; ?>
            <th>Vehículo</th>
            <th>Placa</th>
            <th>Disponibilidad</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($repartidores as $r): ?>
            <tr style="<?= $r['estado_usuario'] == 0 ? 'opacity: 0.65; background-color: #fafafa;' : '' ?>">
                <td>#<?= $r['id'] ?></td>
                <td>
                    <strong><?= htmlspecialchars($r['nombre']) ?></strong>
                    <?= $r['estado_usuario'] == 0 ? ' <small style="color:#d9534f;">(Inactivo)</small>' : '' ?>
                </td>
                <td><?= htmlspecialchars($r['email']) ?></td>
                
                <?php if ($rol === 'admin'): ?>
                    <td>
                        <?= !empty($r['nombre_comercial']) 
                            ? '<span style="font-weight:600; color:var(--so-orange);">' . htmlspecialchars($r['nombre_comercial']) . '</span>' 
                            : '<em style="color:#999;">General (Global)</em>' ?>
                    </td>
                <?php endif; ?>

                <td><?= htmlspecialchars($r['vehiculo']) ?></td>
                <td><code><?= htmlspecialchars($r['placa']) ?></code></td>
                <td>
                    <?php if ($r['estado_usuario'] == 0): ?>
                        <span style="padding: 3px 8px; border-radius: 3px; font-size: 0.75rem; background-color: #f3f4f6; color: #6b7280;">
                            N/A
                        </span>
                    <?php else: ?>
                        <?php
                        $badgeColor = $r['estado_disponibilidad'] === 'disponible' ? '#def7ec' : '#feecdc';
                        $textColor = $r['estado_disponibilidad'] === 'disponible' ? '#03543f' : '#9a3412';
                        ?>
                        <span style="padding: 3px 8px; border-radius: 3px; font-size: 0.75rem; background-color: <?= $badgeColor ?>; color: <?= $textColor ?>;">
                            <?= ucfirst($r['estado_disponibilidad']) ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <form action="" method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_estado_repartidor">
                        <input type="hidden" name="repartidor_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="nuevo_estado" value="<?= $r['estado_usuario'] == 1 ? '0' : '1' ?>">
                        <button type="submit" class="btn" style="padding: 2px 8px; font-size: 0.75rem; 
                                background-color: <?= $r['estado_usuario'] == 1 ? '#fde8e8' : '#def7ec' ?>; 
                                color: <?= $r['estado_usuario'] == 1 ? '#9b1c1c' : '#03543f' ?>; 
                                border-color: <?= $r['estado_usuario'] == 1 ? '#f8b4b4' : '#bcf0da' ?>;">
                            <?= $r['estado_usuario'] == 1 ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
    </div>
</div>

<!-- Modal Emergente: Registrar Repartidor -->
<div class="modal-backdrop" id="modalRepartidor">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Registrar Nuevo Repartidor</h3>
            <button class="modal-close" onclick="cerrarModalRepartidor()">&times;</button>
        </div>
        <div class="modal-body">
            <form action="" method="POST" novalidate>
                <input type="hidden" name="action" value="crear">

                <div class="form-group">
                    <label for="nombre">Nombre Completo</label>
                    <input type="text" name="nombre" id="nombre" class="form-control" required placeholder="Carlos Mendoza">
                </div>

                <!-- Campo Combobox visible ÚNICAMENTE para Administrador -->
                <?php if ($rol === 'admin'): ?>
                    <div class="form-group">
                        <label for="comercio_id">Comercio Afiliado (Opcional)</label>
                        <select name="comercio_id" id="comercio_id" class="form-control">
                            <option value="">General / Sin Asignar (Global)</option>
                            <?php foreach ($comerciosLista as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_comercial']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" name="email" id="email" class="form-control" required placeholder="carlos@delivery.com">
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" required placeholder="••••••••">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label for="vehiculo">Tipo de Vehículo</label>
                        <select name="vehiculo" id="vehiculo" class="form-control" required onchange="togglePlacaRequerida()">
                            <option value="Motocicleta">Motocicleta</option>
                            <option value="Bicicleta">Bicicleta</option>
                            <option value="Automóvil">Automóvil</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="placa" id="label-placa">Matrícula / Placa</label>
                        <input type="text" name="placa" id="placa" class="form-control" required placeholder="M-12345">
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn" onclick="cerrarModalRepartidor()" style="margin-right: 8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar Repartidor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalRepartidor() {
    document.getElementById('modalRepartidor').classList.add('show');
}
function cerrarModalRepartidor() {
    document.getElementById('modalRepartidor').classList.remove('show');
}
document.getElementById('modalRepartidor').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalRepartidor();
});

function togglePlacaRequerida() {
    const vehiculoSelect = document.getElementById('vehiculo');
    const placaInput = document.getElementById('placa');
    const labelPlaca = document.getElementById('label-placa');
    const asterisk = labelPlaca.querySelector('.required-asterisk');

    if (vehiculoSelect.value === 'Bicicleta') {
        placaInput.value = '';
        placaInput.removeAttribute('required');
        placaInput.disabled = true;
        placaInput.placeholder = 'No aplica para bicicletas';
        placaInput.classList.remove('input-error');
        if (asterisk) asterisk.style.display = 'none';
    } else {
        placaInput.disabled = false;
        placaInput.setAttribute('required', 'required');
        placaInput.placeholder = 'M-12345';
        if (asterisk) asterisk.style.display = 'inline';
    }
}
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>