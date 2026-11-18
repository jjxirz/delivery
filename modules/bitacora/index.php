<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../usuarios/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/apariencia.php';

Auth::verificarRol(['admin']);

$db = Database::getConnection();
$registros = $db->query("SELECT b.*, u.nombre as usuario_nombre
                         FROM bitacora_auditoria b
                         INNER JOIN usuarios u ON b.usuario_id = u.id
                         ORDER BY b.id DESC LIMIT 150")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../layout/header.php';
?>

<div style="margin-bottom: 20px;">
    <h2 style="margin: 0 0 4px 0;">Bitácora de Auditoría del Sistema</h2>
    <p style="margin: 0; font-size: 0.85rem; color: var(--so-gray-text);">Registro histórico de modificaciones críticas autorizadas con contraseña (solo visible para Administradores).</p>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha y Hora</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Acción</th>
                    <th>Módulo / ID</th>
                    <th>Detalle y Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registros)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px; color: var(--so-gray-text);">No hay eventos registrados en la bitácora.</td></tr>
                <?php else: ?>
                    <?php foreach ($registros as $r): ?>
                        <tr>
                            <td><code>#<?= (int)$r['id'] ?></code></td>
                            <td><small><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($r['creado_en']))) ?></small></td>
                            <td><strong><?= htmlspecialchars($r['usuario_nombre']) ?></strong></td>
                            <td><small><?= ucfirst(htmlspecialchars($r['rol_usuario'])) ?></small></td>
                            <td><strong><?= htmlspecialchars($r['accion']) ?></strong></td>
                            <td><code><?= htmlspecialchars($r['entidad']) ?> #<?= (int)$r['entidad_id'] ?></code></td>
                            <td><?= htmlspecialchars($r['detalle']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
