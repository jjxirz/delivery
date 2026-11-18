<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/apariencia.php';





$cfgApariencia = Apariencia::obtener();
$logoSistema   = Apariencia::logoUrl($ASSETS_IMAGES['logo']);
$nombreSistema = $cfgApariencia['nombre_sistema'];
$uriActual     = $_SERVER['REQUEST_URI'];
$rol = $_SESSION['usuario_rol'] ?? '';
$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$infoZonaRepartidor = '';
$faviconUrl    = Apariencia::logoUrl($ASSETS_IMAGES['logo'] ?? (BASE_URL . 'assets/img/logo.png'));
$faviconVersion = time();
$cssVersion = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time();
$tituloSistema = htmlspecialchars($cfgApariencia['nombre_sistema']);

if ($rol === 'repartidor' && $usuario_id > 0) {
    try {
        $dbHeader = Database::getConnection();
        $stmtZona = $dbHeader->prepare("SELECT r.vehiculo, COALESCE(c.nombre_comercial, 'Flota General') AS tienda
                                        FROM repartidores r
                                        LEFT JOIN comercios c ON r.comercio_id = c.id
                                        WHERE r.usuario_id = :uid LIMIT 1");
        $stmtZona->execute([':uid' => $usuario_id]);
        $rowZona = $stmtZona->fetch(PDO::FETCH_ASSOC);
        if ($rowZona) {
            $tienda = $rowZona['tienda'] ?: 'Flota General';
            $vehiculo = $rowZona['vehiculo'] ?: 'Zona General';
            $infoZonaRepartidor = $tienda . ' · ' . $vehiculo;
        }
    } catch (Exception $e) {
        error_log('[Header repartidor] ' . $e->getMessage());
    }
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tituloSistema ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= $cssVersion ?>">

    <!-- Favicon dinámico del sistema / tienda activa (con parámetro de versión para evitar caché) -->
    <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>?v=<?= $faviconVersion ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars($faviconUrl) ?>?v=<?= $faviconVersion ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($faviconUrl) ?>?v=<?= $faviconVersion ?>">

    <!-- Librerías de Iconos Multi-Fuente -->
    <?php if ($cfgApariencia['fuente_iconos'] === 'lucide'): ?>
        <script src="https://unpkg.com/lucide@latest"></script>
    <?php elseif ($cfgApariencia['fuente_iconos'] === 'phosphor'): ?>
        <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <?php elseif ($cfgApariencia['fuente_iconos'] === 'bootstrap'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php endif; ?>

    <style>
        body {
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .so-topbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            height: 50px;
            width: 100%;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
        }
        .so-layout-wrapper {
            display: flex;
            flex: 1;
            max-width: 1440px;
            width: 100%;
            margin: 0 auto;
        }
        .so-sidebar {
            width: 215px;
            flex-shrink: 0;
            padding: 20px 0;
        }
        .so-nav-section {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #6a737c;
            padding: 14px 16px 6px 16px;
            letter-spacing: 0.5px;
        }
        .so-nav-link {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 16px;
            font-size: 0.85rem;
            color: #525960;
            text-decoration: none;
            border-right: 3px solid transparent;
            transition: all 0.15s;
        }
        .so-nav-link:hover {
            color: #0c0d0e;
            background-color: rgba(0, 0, 0, 0.03);
        }
        .notif-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .notif-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 310px;
            max-width: calc(100vw - 20px);
            background: #ffffff;
            border: 1px solid var(--so-gray-border, #d6d9dc);
            border-radius: var(--ui-radius, 6px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
            z-index: 9999;
            overflow: hidden;
        }
        .notif-dropdown.show { display: block; }
        @media (max-width: 480px) {
            .notif-dropdown {
                position: fixed;
                top: 58px;
                right: 10px;
                left: auto;
                width: 310px;
                max-width: calc(100vw - 20px);
            }
        }
        .notif-header {
            padding: 10px 12px;
            background: #f8f9f9;
            border-bottom: 1px solid #e3e6e8;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notif-list { max-height: 280px; overflow-y: auto; }
        .notif-item {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f2f3;
            font-size: 0.82rem;
            line-height: 1.35;
            color: #232629;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item.unread {
            background: color-mix(in srgb, var(--so-orange) 8%, #ffffff);
        }
        .notif-item.unread .notif-text {
            font-weight: 600;
        }
        .notif-icon-badge {
            width: 30px;
            height: 30px;
            min-width: 30px;
            border-radius: var(--ui-radius, 6px);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: color-mix(in srgb, var(--so-orange) 12%, #f8f9f9);
            border: 1px solid color-mix(in srgb, var(--so-orange) 30%, #d6d9dc);
            color: #0c0d0e;
            margin-top: 1px;
        }
        .notif-icon-badge.is-danger {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #b91c1c;
        }
        .notif-body { flex: 1; min-width: 0; }
        .alert-banner,
        .alert,
        .alerta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 11px 14px;
            margin-bottom: 16px;
            border-radius: var(--ui-radius, 4px);
            font-size: 0.88rem;
            font-weight: 500;
            line-height: 1.4;
            border: 1px solid #d6d9dc;
            background-color: #f8f9f9;
            color: #232629;
        }
        .alert-banner.success, .alert-success {
            background-color: #ecfdf5;
            border-color: #6ee7b7;
            color: #065f46;
        }
        .alert-banner.error, .alert-danger, .alert-error {
            background-color: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        .alert-banner.warning, .alert-warning {
            background-color: color-mix(in srgb, var(--so-orange) 12%, #ffffff);
            border-color: color-mix(in srgb, var(--so-orange) 45%, #d6d9dc);
            color: #0c0d0e;
        }
        .alert-banner-message { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .alert-banner-dismiss {
            background: none;
            border: none;
            font-size: 1.1rem;
            line-height: 1;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            flex: 0 0 auto;
        }
        .alert-banner-dismiss:hover { opacity: 1; }
        .so-main-content {
            flex: 1;
            padding: 24px;
            min-width: 0;
        }
        <?php if ($rol === 'repartidor'): ?>
        .so-sidebar { display: none !important; }
        .so-main-content { width: 100%; padding: 14px 12px !important; }
        .rep-top-subbar {
            background: color-mix(in srgb, var(--so-orange) 8%, #ffffff);
            border-bottom: 1px solid var(--so-gray-border, #d6d9dc);
            padding: 8px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.82rem;
            color: #232629;
            gap: 8px;
            flex-wrap: wrap;
        }
        <?php endif; ?>
        @media (max-width: 768px) {
            .so-layout-wrapper { flex-direction: column; }
            .so-topbar {
                height: auto;
                min-height: 50px;
                flex-direction: row;
                align-items: center;
                padding: 8px 12px;
                gap: 8px;
            }
            .so-topbar .logo-container { flex: 1 1 auto; min-width: 0; overflow: hidden; }
            .so-topbar .logo-container > a { min-width: 0; overflow: hidden; }
            .so-topbar .logo-container > a > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .so-topbar > div:last-child { flex: 0 0 auto; gap: 8px !important; }
            .so-topbar > div:last-child > span { max-width: 30vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-left: 6px !important; }
            .so-topbar > div:last-child > span em { display: none; }
            .so-sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--so-gray-border);
                padding: 8px 0;
                display: flex;
                overflow-x: auto;
            }
            .so-nav-section { display: none; }
            .so-nav-link { white-space: nowrap; }
        }
    </style>

    <!-- Inyección dinámica del tema, colores y bordes -->
    <?= Apariencia::estilosDinamicos() ?>
</head>
<body>

<?php if (isset($_SESSION['usuario_id'])): ?>
<header class="so-topbar">
    <div class="logo-container" style="display:flex; align-items:center; gap:10px;">
        <a href="<?= BASE_URL ?>index.php" style="display:flex; align-items:center; gap:8px; text-decoration:none; color:inherit; font-weight:700; font-size:1.05rem;">
            <img src="<?= htmlspecialchars($logoSistema) ?>" alt="Logo" style="height:28px; object-fit:contain;">
            <span><?= htmlspecialchars($nombreSistema) ?></span>
        </a>
    </div>
    
    <div style="display: flex; gap: 14px; align-items: center;">
        <!-- Campanita de Notificaciones -->
        <div class="notif-wrapper">
            <button id="btnNotificaciones" onclick="toggleDropdownNotificaciones(event)" class="btn" style="padding: 4px 10px; font-size: 0.9rem; position: relative; background:#fff;">
                <?= Apariencia::icono('notificaciones') ?>
                <span id="badgeNotificacion" style="display:none; position:absolute; top:-5px; right:-5px; background:#d9534f; color:#fff; font-size:0.7rem; padding:2px 5px; border-radius:50%; font-weight:bold;">0</span>
            </button>
            <div id="dropdownNotificaciones" class="notif-dropdown" onclick="event.stopPropagation()">
                <div class="notif-header">
                    <strong>Notificaciones</strong>
                    <button type="button" style="background:none; border:none; padding:0; cursor:pointer; color:var(--so-orange); font-size:0.75rem; font-weight:600;" onclick="limpiarNotificaciones(event)">Marcar leídas</button>
                </div>
                <div id="listaNotificaciones" class="notif-list">
                    <p style="color:var(--so-gray-text); text-align:center; padding:10px;">Sin notificaciones nuevas.</p>
                </div>
            </div>
        </div>

        <span style="font-size: 0.82rem; color: var(--so-gray-text); border-left: 1px solid var(--so-gray-border); padding-left: 12px;">
            <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> · <em><?= ucfirst($_SESSION['usuario_rol']) ?></em>
        </span>
        <a href="<?= BASE_URL ?>logout.php" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background-color: #fdf2f2; color: #c81e1e; border-color: #f8b4b4;">Salir</a>
    </div>
</header>

<?php if ($rol === 'repartidor'): ?>
    <div class="rep-top-subbar">
        <div style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; color: #0c0d0e;">
            <?= Apariencia::icono('pedidos') ?> <span>Mis Entregas</span>
        </div>
        <div style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.78rem; color: #3b4045;">
            <?= Apariencia::icono('ubicacion') ?> <span><?= htmlspecialchars($infoZonaRepartidor ?: 'Zona / Tienda no asignada') ?></span>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleDropdownNotificaciones(event) {
    if (event) event.stopPropagation();
    const drop = document.getElementById('dropdownNotificaciones');
    if (drop) drop.classList.toggle('show');
}
function cargarNotificaciones() {
    fetch('<?= BASE_URL ?>modules/notificaciones/api.php')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('badgeNotificacion');
            const lista = document.getElementById('listaNotificaciones');
            if (!badge || !lista) return;
            if (data.length > 0) {
                badge.style.display = 'inline-block';
                badge.innerText = data.length;
                lista.replaceChildren(...data.map(n => {
                    const item = document.createElement('div');
                    item.className = 'notif-item unread';
                    const badgeIcono = document.createElement('span');
                    badgeIcono.className = 'notif-icon-badge' + (n.cancelada ? ' is-danger' : '');
                    const iconoHtml = n.icono || '';
                    badgeIcono.innerHTML = iconoHtml;
                    const cuerpo = document.createElement('div');
                    cuerpo.className = 'notif-body';
                    const mensaje = document.createElement('div');
                    mensaje.className = 'notif-text';
                    mensaje.textContent = n.mensaje || '';
                    const fecha = document.createElement('small');
                    fecha.style.color = '#6a737c';
                    fecha.style.fontSize = '0.72rem';
                    fecha.textContent = n.creado_en || '';
                    cuerpo.append(mensaje, fecha);
                    item.append(badgeIcono, cuerpo);
                    return item;
                }));
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            } else {
                badge.style.display = 'none';
                const vacio = document.createElement('p');
                vacio.style.cssText = 'color:var(--so-gray-text); text-align:center; padding:10px;';
                vacio.textContent = 'Sin notificaciones nuevas.';
                lista.replaceChildren(vacio);
            }
        })
        .catch(() => {});
}
function limpiarNotificaciones(event) {
    if (event) event.stopPropagation();
    fetch('<?= BASE_URL ?>modules/notificaciones/api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=marcar_leidas'
    }).then(() => cargarNotificaciones());
}
document.addEventListener('click', event => {
    if (!event.target.closest('.notif-wrapper')) {
        document.getElementById('dropdownNotificaciones')?.classList.remove('show');
    }
});
setInterval(cargarNotificaciones, 8000);
document.addEventListener('DOMContentLoaded', cargarNotificaciones);
</script>

<div class="so-layout-wrapper">
    <aside class="so-sidebar">
        <div class="so-nav-section" style="padding-top:0;">Operación</div>
        <?php if ($_SESSION['usuario_rol'] !== 'repartidor'): ?>
            <a href="<?= BASE_URL ?>index.php" class="so-nav-link <?= (basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($uriActual, '/modules/') === false) ? 'active' : '' ?>">
                <?= Apariencia::icono('inicio') ?> <span>Inicio</span>
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>modules/pedidos/index.php" class="so-nav-link <?= strpos($uriActual, '/modules/pedidos/') !== false ? 'active' : '' ?>">
            <?= Apariencia::icono('pedidos') ?> <span>Pedidos y Envíos</span>
        </a>

        <?php if (in_array($_SESSION['usuario_rol'], ['admin', 'comercio'])): ?>
            <div class="so-nav-section">Directorio</div>
            <?php if ($_SESSION['usuario_rol'] === 'admin'): ?>
                <a href="<?= BASE_URL ?>modules/comercios/index.php" class="so-nav-link <?= strpos($uriActual, '/modules/comercios/') !== false ? 'active' : '' ?>">
                    <?= Apariencia::icono('comercios') ?> <span>Comercios</span>
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>modules/repartidores/index.php" class="so-nav-link <?= strpos($uriActual, '/modules/repartidores/') !== false ? 'active' : '' ?>">
                <?= Apariencia::icono('repartidores') ?> <span>Repartidores</span>
            </a>

            <div class="so-nav-section">Finanzas</div>
            <a href="<?= BASE_URL ?>modules/caja/index.php" class="so-nav-link <?= strpos($uriActual, '/caja/') !== false ? 'active' : '' ?>">
                <?= Apariencia::icono('caja') ?> Liquidaciones y Caja
            </a>
        <?php endif; ?>

        <?php if ($_SESSION['usuario_rol'] === 'admin'): ?>
            <div class="so-nav-section">Sistema</div>
            <a href="<?= BASE_URL ?>modules/bitacora/index.php" class="so-nav-link <?= strpos($uriActual, '/modules/bitacora/') !== false ? 'active' : '' ?>">
                <?= Apariencia::icono('bitacora') ?> <span>Bitácora de Auditoría</span>
            </a>
            <a href="<?= BASE_URL ?>modules/configuracion/index.php" class="so-nav-link <?= strpos($uriActual, '/modules/configuracion/') !== false ? 'active' : '' ?>">
                <?= Apariencia::icono('apariencia') ?> <span>Personalizar Interfaz</span>
            </a>
        <?php endif; ?>
    </aside>

    <main class="so-main-content">
<?php else: ?>
    <div class="container">
<?php endif; ?>