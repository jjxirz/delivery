<?php
// Validar sesión y permisos antes de cualquier salida HTML
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../usuarios/auth.php';
require_once __DIR__ . '/Comercio.php';

Auth::verificarRol(['admin']);

// Cargar el encabezado visual
require_once __DIR__ . '/../../layout/header.php';

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $nombre = trim($_POST['nombre_comercial'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($nombre) && !empty($telefono) && !empty($direccion) && !empty($email) && !empty($password)) {
        $resultado = Comercio::registrar($nombre, $telefono, $direccion, $email, $password);
        if ($resultado === true) {
            $mensaje = "Comercio registrado con éxito.";
            $tipo_mensaje = "exito";
        } else {
            $mensaje = $resultado;
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "Todos los campos son obligatorios.";
        $tipo_mensaje = "error";
    }
}

$comercios = Comercio::listar();
?>

<!-- Encabezado -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h2>Gestión de Comercios Afiliados</h2>
    <button type="button" class="btn btn-primary" onclick="abrirModalComercio()">+ Nuevo Comercio</button>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="padding: 10px; margin-bottom: 15px; border-radius: 3px; font-size: 0.9rem; 
                background-color: <?= $tipo_mensaje === 'exito' ? '#eef9f2' : '#fdf2f2' ?>; 
                border: 1px solid <?= $tipo_mensaje === 'exito' ? '#bcf0da' : '#f8b4b4' ?>; 
                color: <?= $tipo_mensaje === 'exito' ? '#0e9f6e' : '#9b1c1c' ?>;">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<!-- Tabla de Comercios -->
<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre Comercial</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Dirección</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($comercios)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--so-gray-text);">No hay comercios registrados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($comercios as $c): ?>
                        <tr>
                            <td>#<?= $c['id'] ?></td>
                            <td><strong><?= htmlspecialchars($c['nombre_comercial']) ?></strong></td>
                            <td><?= htmlspecialchars($c['telefono']) ?></td>
                            <td><?= htmlspecialchars($c['email']) ?></td>
                            <td><?= htmlspecialchars($c['direccion']) ?></td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 0.75rem; 
                                             background-color: <?= $c['estado'] ? '#e1ecf4' : '#f3f4f6' ?>; 
                                             color: <?= $c['estado'] ? '#39739d' : '#6b7280' ?>;">
                                    <?= $c['estado'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Emergente: Registrar Comercio -->
<div class="modal-backdrop" id="modalComercio">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Registrar Nuevo Comercio</h3>
            <button class="modal-close" onclick="cerrarModalComercio()">&times;</button>
        </div>
        <div class="modal-body">
            <form action="" method="POST" novalidate>
                <input type="hidden" name="action" value="crear">

                <div class="form-group">
                    <label for="nombre_comercial">Nombre del Establecimiento</label>
                    <input type="text" name="nombre_comercial" id="nombre_comercial" class="form-control" required placeholder="Ej. Taquería El Paisa">
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label for="telefono">Teléfono de Contacto</label>
                        <input type="text" name="telefono" id="telefono" class="form-control" required placeholder="5555-0192">
                    </div>

                    <div class="form-group">
                        <label for="email">Correo Acceso (Usuario)</label>
                        <input type="email" name="email" id="email" class="form-control" required placeholder="contacto@comercio.com">
                    </div>
                </div>

                <div class="form-group">
                    <label for="direccion">Dirección Física</label>
                    <input type="text" name="direccion" id="direccion" class="form-control" required placeholder="Av. Principal #123, Col. Centro">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña de Acceso</label>
                    <input type="password" name="password" id="password" class="form-control" required placeholder="••••••••">
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn" onclick="cerrarModalComercio()" style="margin-right: 8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Comercio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalComercio() {
    document.getElementById('modalComercio').classList.add('show');
}
function cerrarModalComercio() {
    document.getElementById('modalComercio').classList.remove('show');
}
document.getElementById('modalComercio').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalComercio();
});
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>