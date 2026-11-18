<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modules/usuarios/auth.php';

session_start();
if (isset($_SESSION['usuario_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        $resultado = Auth::login($email, $password);
        if ($resultado === true) {
            header("Location: " . BASE_URL . "index.php");
            exit;
        } else {
            $error = $resultado;
        }
    } else {
        $error = "Por favor, completa todos los campos.";
    }
}

require_once __DIR__ . '/layout/header.php';
?>

<div style="max-width: 400px; margin: 40px auto;">
    <div class="card">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="<?= $ASSETS_IMAGES['logo'] ?>" alt="Logo" style="width: 48px;">
            <h2 style="margin-top: 10px; font-size: 1.4rem;">Iniciar Sesión</h2>
            <p style="color: var(--so-gray-text); font-size: 0.85rem;">Acceso al panel administrativo</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-banner error" role="alert">
                <div class="alert-banner-message">
                    <?= Apariencia::icono('eliminar') ?>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <button type="button" class="alert-banner-dismiss" aria-label="Cerrar aviso" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" name="email" id="email" class="form-control" required placeholder="admin@delivery.com">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" name="password" id="password" class="form-control" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; font-weight: bold; padding: 10px;">Ingresar</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>