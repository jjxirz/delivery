<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>

<?php if (isset($_SESSION['usuario_id'])): ?>
<header>
    <div class="logo-container">
        <a href="<?= BASE_URL ?>index.php" style="display:flex; align-items:center; gap:8px; text-decoration:none; color:inherit;">
            <img src="<?= $ASSETS_IMAGES['logo'] ?>" alt="Logo Delivery">
            <span><?= SITE_NAME ?></span>
        </a>
    </div>
    
    <nav style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>index.php" class="btn" style="padding: 4px 8px; font-size: 0.8rem;">Inicio</a>
        
        <!-- Comercios solo accesible por Admin -->
        <?php if ($_SESSION['usuario_rol'] === 'admin'): ?>
            <a href="<?= BASE_URL ?>modules/comercios/index.php" class="btn" style="padding: 4px 8px; font-size: 0.8rem;">Comercios</a>
        <?php endif; ?>

        <!-- Repartidores accesible por Admin y Comercio -->
        <?php if (in_array($_SESSION['usuario_rol'], ['admin', 'comercio'])): ?>
            <a href="<?= BASE_URL ?>modules/repartidores/index.php" class="btn" style="padding: 4px 8px; font-size: 0.8rem;">Repartidores</a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>modules/pedidos/index.php" class="btn" style="padding: 4px 8px; font-size: 0.8rem;">Pedidos</a>

        <?php if (in_array($_SESSION['usuario_rol'], ['admin', 'comercio'])): ?>
            <a href="<?= BASE_URL ?>modules/reportes/index.php" class="btn btn-primary" style="padding: 4px 8px; font-size: 0.8rem;">Liquidaciones</a>
        <?php endif; ?>

        <!-- Campanita de Notificaciones -->
        <div style="position: relative;">
            <button id="btnNotificaciones" onclick="toggleDropdownNotificaciones()" class="btn" style="padding: 4px 10px; font-size: 0.9rem; position: relative;">
                🔔 <span id="badgeNotificacion" style="display:none; position:absolute; top:-5px; right:-5px; background:#d9534f; color:#fff; font-size:0.7rem; padding:2px 5px; border-radius:50%; font-weight:bold;">0</span>
            </button>
            <div id="dropdownNotificaciones" style="display:none; position:absolute; right:0; top:35px; width:280px; background:#fff; border:1px solid var(--so-gray-border); border-radius:4px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:9999; padding:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--so-gray-border); padding-bottom:5px; margin-bottom:8px;">
                    <strong style="font-size:0.85rem;">Notificaciones</strong>
                    <small style="cursor:pointer; color:var(--so-orange);" onclick="limpiarNotificaciones()">Marcar leídas</small>
                </div>
                <div id="listaNotificaciones" style="max-height:200px; overflow-y:auto; font-size:0.8rem; color:var(--so-black);">
                    <p style="color:var(--so-gray-text); text-align:center; padding:10px;">Sin notificaciones nuevas.</p>
                </div>
            </div>
        </div>

        <span style="font-size: 0.85rem; color: var(--so-gray-text); margin-left: 5px;">
            <?= htmlspecialchars($_SESSION['usuario_nombre']) ?> (<em><?= ucfirst($_SESSION['usuario_rol']) ?></em>)
        </span>
        <a href="<?= BASE_URL ?>logout.php" class="btn" style="padding: 4px 8px; font-size: 0.8rem; background-color: #fbe9e7; color: #c2410c; border-color: #ffccbc;">Salir</a>
    </nav>
</header>

<script>
function toggleDropdownNotificaciones() {
    const drop = document.getElementById('dropdownNotificaciones');
    drop.style.display = drop.style.display === 'none' ? 'block' : 'none';
}

function cargarNotificaciones() {
    fetch('<?= BASE_URL ?>modules/notificaciones/api.php')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('badgeNotificacion');
            const lista = document.getElementById('listaNotificaciones');

            if (data.length > 0) {
                badge.style.display = 'inline-block';
                badge.innerText = data.length;
                lista.innerHTML = data.map(n => `
                    <div style="padding: 6px 0; border-bottom: 1px solid #f1f2f3;">
                        <p style="margin:0;">${n.mensaje}</p>
                        <small style="color:#888; font-size:0.7rem;">${n.creado_en}</small>
                    </div>
                `).join('');
            } else {
                badge.style.display = 'none';
                lista.innerHTML = '<p style="color:var(--so-gray-text); text-align:center; padding:10px;">Sin notificaciones nuevas.</p>';
            }
        });
}

function limpiarNotificaciones() {
    fetch('<?= BASE_URL ?>modules/notificaciones/api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=marcar_leidas'
    }).then(() => cargarNotificaciones());
}

// Consultar notificaciones automáticamente cada 8 segundos
setInterval(cargarNotificaciones, 8000);
document.addEventListener('DOMContentLoaded', cargarNotificaciones);
</script>
<?php endif; ?>

<div class="container">