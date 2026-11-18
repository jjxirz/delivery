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
$mensaje = '';
$tipo_mensaje = 'success';

// Determinar qué perfil está editando el administrador ('admin' o ID numérico de comercio)
$objetivo = $_GET['target'] ?? $_POST['target'] ?? 'admin';
$targetComercioId = ($objetivo === 'admin' || $objetivo === '') ? null : (int)$objetivo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'restaurar') {
        if ($targetComercioId === null) {
            $db->exec("UPDATE configuracion_apariencia SET 
                nombre_sistema = 'Delivery Local',
                logo_path = NULL,
                tema_diseno = 'stackoverflow',
                color_acento = '#f48024',
                color_topbar = '#f8f9f9',
                color_sidebar = '#ffffff',
                fuente_iconos = 'lucide'
                WHERE comercio_id IS NULL");
        } else {
            $stmtDel = $db->prepare("DELETE FROM configuracion_apariencia WHERE comercio_id = :cid");
            $stmtDel->execute([':cid' => $targetComercioId]);
        }
        $redir = $targetComercioId ? "index.php?target={$targetComercioId}&ok=1" : "index.php?target=admin&ok=1";
        header("Location: $redir");
        exit;
    }

    $nombre_sistema = trim($_POST['nombre_sistema'] ?? 'Delivery Local');
    $tema_diseno    = $_POST['tema_diseno'] ?? 'stackoverflow';
    $color_acento   = $_POST['color_acento'] ?? '#f48024';
    $color_topbar   = $_POST['color_topbar'] ?? '#f8f9f9';
    $color_sidebar  = $_POST['color_sidebar'] ?? '#ffffff';
    $fuente_iconos  = $_POST['fuente_iconos'] ?? 'lucide';

    $cfgActual = Apariencia::obtener($targetComercioId);
    $logo_path = $cfgActual['logo_path'];

    // Subida de logo independiente para esta tienda o panel
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $permitidas = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
        if (in_array($ext, $permitidas, true)) {
            $dirDestino = __DIR__ . '/../../assets/uploads/';
            if (!is_dir($dirDestino)) {
                mkdir($dirDestino, 0775, true);
            }
            $prefijoLogo = $targetComercioId ? "logo_com_{$targetComercioId}_" : "logo_admin_";
            $nombreArchivo = $prefijoLogo . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dirDestino . $nombreArchivo)) {
                $logo_path = 'assets/uploads/' . $nombreArchivo;
            }
        } else {
            $mensaje = "Formato de logo no válido. Usa PNG, JPG, SVG o WEBP.";
            $tipo_mensaje = 'error';
        }
    }

    if ($tipo_mensaje !== 'error') {
        if ($targetComercioId === null) {
            // Actualizar fila del Panel Administrador (comercio_id IS NULL)
            $existeAdmin = (int)$db->query("SELECT COUNT(*) FROM configuracion_apariencia WHERE comercio_id IS NULL")->fetchColumn();
            if ($existeAdmin > 0) {
                $sql = "UPDATE configuracion_apariencia SET 
                            nombre_sistema = :nombre, logo_path = :logo, tema_diseno = :tema,
                            color_acento = :acento, color_topbar = :topbar, color_sidebar = :sidebar, fuente_iconos = :iconos
                        WHERE comercio_id IS NULL";
            } else {
                $sql = "INSERT INTO configuracion_apariencia (comercio_id, nombre_sistema, logo_path, tema_diseno, color_acento, color_topbar, color_sidebar, fuente_iconos)
                        VALUES (NULL, :nombre, :logo, :tema, :acento, :topbar, :sidebar, :iconos)";
            }
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':nombre' => $nombre_sistema, ':logo' => $logo_path, ':tema' => $tema_diseno,
                ':acento' => $color_acento, ':topbar' => $color_topbar, ':sidebar' => $color_sidebar, ':iconos' => $fuente_iconos
            ]);
        } else {
            // Insertar o actualizar fila exclusiva del comercio seleccionado
            $sql = "INSERT INTO configuracion_apariencia (comercio_id, nombre_sistema, logo_path, tema_diseno, color_acento, color_topbar, color_sidebar, fuente_iconos)
                    VALUES (:cid, :nombre, :logo, :tema, :acento, :topbar, :sidebar, :iconos)
                    ON DUPLICATE KEY UPDATE
                        nombre_sistema = VALUES(nombre_sistema),
                        logo_path = VALUES(logo_path),
                        tema_diseno = VALUES(tema_diseno),
                        color_acento = VALUES(color_acento),
                        color_topbar = VALUES(color_topbar),
                        color_sidebar = VALUES(color_sidebar),
                        fuente_iconos = VALUES(fuente_iconos)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':cid' => $targetComercioId, ':nombre' => $nombre_sistema, ':logo' => $logo_path, ':tema' => $tema_diseno,
                ':acento' => $color_acento, ':topbar' => $color_topbar, ':sidebar' => $color_sidebar, ':iconos' => $fuente_iconos
            ]);
        }

        $redir = $targetComercioId ? "index.php?target={$targetComercioId}&ok=1" : "index.php?target=admin&ok=1";
        header("Location: $redir");
        exit;
    }
}

if (isset($_GET['ok'])) {
    $mensaje = "Personalización guardada correctamente para el perfil seleccionado.";
}

// Listar comercios para el selector superior
$comerciosList = $db->query("SELECT c.id, c.nombre_comercial, 
                                    (SELECT COUNT(*) FROM configuracion_apariencia ca WHERE ca.comercio_id = c.id) as tiene_estilo
                             FROM comercios c ORDER BY c.nombre_comercial ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../layout/header.php';
$cfgEditando = Apariencia::obtener($targetComercioId);
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
    .option-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 12px;
        margin-top: 8px;
    }
    .radio-card {
        border: 1px solid #d6d9dc;
        border-radius: 4px;
        padding: 12px;
        cursor: pointer;
        background: #fff;
        display: block;
        transition: all 0.15s;
    }
    .radio-card:hover { border-color: var(--so-orange); }
    .radio-card input[type="radio"] { margin-right: 6px; }
    .preset-swatch {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border: 1px solid #d6d9dc;
        border-radius: 3px;
        background: #f8f9f9;
        cursor: pointer;
        font-size: 0.78rem;
    }
    .swatch-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h2 style="margin: 0 0 4px 0;">Personalización Independiente por Tienda</h2>
        <p style="margin: 0; font-size: 0.85rem; color: var(--so-gray-text);">Configura identidad, colores, logo y fuente de iconos por separado para el Panel Administrador o cada Comercio.</p>
    </div>

    <form method="POST" onsubmit="return confirm('¿Restaurar los valores por defecto de este perfil?');">
        <input type="hidden" name="action" value="restaurar">
        <input type="hidden" name="target" value="<?= htmlspecialchars($objetivo) ?>">
        <button type="submit" class="btn" style="font-size: 0.8rem;">Restaurar Perfil Actual</button>
    </form>
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

<!-- SELECTOR DE PERFIL OBJETIVO (PANEL ADMINISTRADOR O TIENDA ESPECÍFICA) -->
<div class="card" style="margin-bottom: 18px; background: #fcfcfd;">
    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; align-items: start;">
        <div class="form-group" style="margin-bottom: 0;">
            <label for="selectorPerfilTarget">Seleccionar Entidad / Tienda a Personalizar</label>
            <div class="so-input-group">
                <span class="so-input-group-prepend"><?= Apariencia::icono('comercios') ?></span>
                <select id="selectorPerfilTarget" class="form-control" onchange="window.location.href='index.php?target=' + this.value">
                    <option value="admin" <?= $targetComercioId === null ? 'selected' : '' ?>>
                        🛡️ Panel Administrador (Central)
                    </option>
                    <?php foreach ($comerciosList as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $targetComercioId === (int)$c['id'] ? 'selected' : '' ?>>
                            🏪 <?= htmlspecialchars($c['nombre_comercial']) ?> <?= (int)$c['tiene_estilo'] > 0 ? '✓ (Personalizada)' : '(Por defecto)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="font-size: 0.82rem; color: #525960; padding-top: 23px; line-height: 1.45;">
            <?php if ($targetComercioId === null): ?>
                Editando la apariencia exclusiva del <strong>Panel Administrador</strong>. Los cambios aquí no alteran el diseño de las tiendas.
            <?php else: ?>
                Editando el diseño exclusivo de esta <strong>Tienda y sus Repartidores</strong>. Solo ellos verán esta marca, colores e iconos al iniciar sesión.
            <?php endif; ?>
        </div>
    </div>
</div>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="target" value="<?= htmlspecialchars($objetivo) ?>">

    <!-- 1. IDENTIDAD Y LOGO PRINCIPAL -->
    <div class="card" style="margin-bottom: 18px;">
        <h3 style="font-size: 1rem; margin-top: 0; border-bottom: 1px solid #e3e6e8; padding-bottom: 8px;">1. Identidad y Logo Principal</h3>
        
        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; align-items: center;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="nombre_sistema">Nombre de la Plataforma / Empresa</label>
                <div class="so-input-group">
                    <span class="so-input-group-prepend"><?= Apariencia::icono('identidad') ?></span>
                    <input type="text" name="nombre_sistema" id="nombre_sistema" class="form-control" required value="<?= htmlspecialchars($cfgEditando['nombre_sistema']) ?>">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="logo">Logo Principal (PNG, SVG, JPG)</label>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <img src="<?= htmlspecialchars(Apariencia::logoUrl($ASSETS_IMAGES['logo'], $targetComercioId)) ?>" alt="Logo actual" style="height: 36px; border: 1px solid #d6d9dc; padding: 4px; border-radius: 3px; background: #fff;">
                    <input type="file" name="logo" id="logo" accept="image/*" class="form-control" style="padding: 4px;">
                </div>
            </div>
        </div>
    </div>

    <!-- 2. DISEÑO ESTRUCTURAL DE LA INTERFAZ -->
    <div class="card" style="margin-bottom: 18px;">
        <h3 style="font-size: 1rem; margin-top: 0; border-bottom: 1px solid #e3e6e8; padding-bottom: 8px;">2. Estilo Estructural de la Interfaz</h3>
        
        <div class="option-cards-grid">
            <label class="radio-card">
                <input type="radio" name="tema_diseno" value="stackoverflow" <?= $cfgEditando['tema_diseno'] === 'stackoverflow' ? 'checked' : '' ?>>
                <strong>StackOverflow Flat</strong>
                <p style="font-size: 0.78rem; color: #6a737c; margin: 6px 0 0 0;">Borde superior acentuado de 3px, esquinas rectas (3px) y pestaña activa lateral derecha.</p>
            </label>

            <label class="radio-card">
                <input type="radio" name="tema_diseno" value="github" <?= $cfgEditando['tema_diseno'] === 'github' ? 'checked' : '' ?>>
                <strong>GitHub Primer</strong>
                <p style="font-size: 0.78rem; color: #6a737c; margin: 6px 0 0 0;">Bordes medios (6px), barra superior limpia y menú lateral con bloques redondeados.</p>
            </label>

            <label class="radio-card">
                <input type="radio" name="tema_diseno" value="linear" <?= $cfgEditando['tema_diseno'] === 'linear' ? 'checked' : '' ?>>
                <strong>Linear Minimal</strong>
                <p style="font-size: 0.78rem; color: #6a737c; margin: 6px 0 0 0;">Líneas divisorias ultra finas, fondo cálido sutil y tipografía resaltada en el menú activo.</p>
            </label>

            <label class="radio-card">
                <input type="radio" name="tema_diseno" value="soft" <?= $cfgEditando['tema_diseno'] === 'soft' ? 'checked' : '' ?>>
                <strong>Modern SaaS Pill</strong>
                <p style="font-size: 0.78rem; color: #6a737c; margin: 6px 0 0 0;">Tarjetas suaves (8px), fondo gris claro y botón activo relleno con el color principal.</p>
            </label>
        </div>
    </div>

    <!-- 3. PALETA DE COLORES -->
    <div class="card" style="margin-bottom: 18px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e3e6e8; padding-bottom: 8px; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <h3 style="font-size: 1rem; margin: 0;">3. Paleta de Colores</h3>
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                <span class="preset-swatch" onclick="aplicarPaleta('#f48024', '#f8f9f9', '#ffffff')"><span class="swatch-dot" style="background:#f48024;"></span> Stack Naranja</span>
                <span class="preset-swatch" onclick="aplicarPaleta('#0969da', '#f6f8fa', '#ffffff')"><span class="swatch-dot" style="background:#0969da;"></span> Azul GitHub</span>
                <span class="preset-swatch" onclick="aplicarPaleta('#16a34a', '#f8fafc', '#ffffff')"><span class="swatch-dot" style="background:#16a34a;"></span> Verde Logística</span>
                <span class="preset-swatch" onclick="aplicarPaleta('#4f46e5', '#f8fafc', '#ffffff')"><span class="swatch-dot" style="background:#4f46e5;"></span> Índigo Tech</span>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="color_acento">Color Principal / Acento</label>
                <input type="color" name="color_acento" id="color_acento" value="<?= htmlspecialchars($cfgEditando['color_acento']) ?>" style="width: 100%; height: 38px; border: 1px solid #d6d9dc; border-radius: 3px; cursor: pointer;">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="color_topbar">Fondo Barra Superior</label>
                <input type="color" name="color_topbar" id="color_topbar" value="<?= htmlspecialchars($cfgEditando['color_topbar']) ?>" style="width: 100%; height: 38px; border: 1px solid #d6d9dc; border-radius: 3px; cursor: pointer;">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="color_sidebar">Fondo Menú Lateral</label>
                <input type="color" name="color_sidebar" id="color_sidebar" value="<?= htmlspecialchars($cfgEditando['color_sidebar']) ?>" style="width: 100%; height: 38px; border: 1px solid #d6d9dc; border-radius: 3px; cursor: pointer;">
            </div>
        </div>
    </div>

    <!-- 4. LIBRERÍA / FUENTE DE ICONOS -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="font-size: 1rem; margin-top: 0; border-bottom: 1px solid #e3e6e8; padding-bottom: 8px;">4. Fuente y Estilo de Iconos</h3>
        
        <div class="option-cards-grid">
            <label class="radio-card">
                <input type="radio" name="fuente_iconos" value="lucide" <?= $cfgEditando['fuente_iconos'] === 'lucide' ? 'checked' : '' ?>>
                <strong>Lucide Icons</strong>
                <div style="margin-top: 8px; font-size: 0.82rem; color: #3b4045;">
                    Vectoriales minimalistas de trazo fino.
                </div>
            </label>

            <label class="radio-card">
                <input type="radio" name="fuente_iconos" value="phosphor" <?= $cfgEditando['fuente_iconos'] === 'phosphor' ? 'checked' : '' ?>>
                <strong>Phosphor Icons</strong>
                <div style="margin-top: 8px; font-size: 0.82rem; color: #3b4045;">
                    Iconografía geométrica limpia y moderna.
                </div>
            </label>

            <label class="radio-card">
                <input type="radio" name="fuente_iconos" value="bootstrap" <?= $cfgEditando['fuente_iconos'] === 'bootstrap' ? 'checked' : '' ?>>
                <strong>Bootstrap Icons</strong>
                <div style="margin-top: 8px; font-size: 0.82rem; color: #3b4045;">
                    Iconos clásicos de sistemas administrativos.
                </div>
            </label>

            <label class="radio-card">
                <input type="radio" name="fuente_iconos" value="emoji" <?= $cfgEditando['fuente_iconos'] === 'emoji' ? 'checked' : '' ?>>
                <strong>Emojis Nativos (Original)</strong>
                <div style="margin-top: 8px; font-size: 0.95rem;">
                    📊 📦 🏪 🛵 💵 📄 🎨
                </div>
            </label>
        </div>
    </div>

    <div style="text-align: right;">
        <button type="submit" class="btn btn-primary" style="padding: 10px 22px; font-size: 0.92rem;">
            Guardar Personalización de este Perfil
        </button>
    </div>
</form>

<script>
function aplicarPaleta(acento, topbar, sidebar) {
    document.getElementById('color_acento').value = acento;
    document.getElementById('color_topbar').value = topbar;
    document.getElementById('color_sidebar').value = sidebar;
}
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>