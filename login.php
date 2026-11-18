
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

<style>
    .login-wrapper {
        min-height: calc(100vh - 150px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 45px 20px;
    }

    .login-card {
        width: 100%;
        max-width: 480px;
        padding: 45px 42px;
        border-radius: 16px;
    }

    .login-header {
        text-align: center;
        margin-bottom: 36px;
    }

    .login-logo {
        width: 76px;
        height: 76px;
        object-fit: contain;
        margin-bottom: 20px;
    }

    .login-title {
        font-size: 1.9rem;
        font-weight: 700;
        margin: 0 0 9px;
    }

    .login-subtitle {
        color: var(--so-gray-text);
        font-size: 1rem;
        margin: 0;
    }

    .login-card .form-group {
        margin-bottom: 24px;
    }

    .login-card .form-group label {
        display: block;
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .login-card .form-control {
        width: 100%;
        min-height: 54px;
        padding: 13px 16px;
        font-size: 1rem;
        border-radius: 9px;
        box-sizing: border-box;
    }

    .login-button {
        width: 100%;
        min-height: 56px;
        margin-top: 8px;
        font-size: 1.05rem;
        font-weight: 700;
        border-radius: 9px;
        cursor: pointer;
        transition: opacity 0.2s, transform 0.2s;
    }

    .login-button:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }

    .login-footer {
        text-align: center;
        margin-top: 28px;
        font-size: 0.85rem;
        color: var(--so-gray-text);
    }

    .login-error {
        margin-bottom: 24px;
    }

    @media (max-width: 520px) {
        .login-wrapper {
            padding: 25px 15px;
        }

        .login-card {
            padding: 32px 22px;
        }

        .login-title {
            font-size: 1.6rem;
        }

        .login-logo {
            width: 66px;
            height: 66px;
        }
    }
    body {
    background-color: #e9e9e9;
    background-image: url('/assets/images/login-background.png');
    background-repeat: repeat;
    background-size: 480px auto;
    background-attachment: fixed;
}

.login-card {
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
}
</style>

<div class="login-wrapper">
    <div class="card login-card">

        <div class="login-header">
         

            <img src="/assets/uploads/logo.jpg" alt="Delivery" width="150">
   <!-- <img
                src="<?= htmlspecialchars($ASSETS_IMAGES['logo']) ?>"
                alt="Logo"
                class="login-logo"
            > -->

            <h2 class="login-title">Iniciar Sesión</h2>

            <p class="login-subtitle">
                Acceso al panel administrativo
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-banner error login-error" role="alert">
                <div class="alert-banner-message">
                    <?= Apariencia::icono('eliminar') ?>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>

                <button
                    type="button"
                    class="alert-banner-dismiss"
                    aria-label="Cerrar aviso"
                    onclick="this.parentElement.remove()"
                >&times;</button>
            </div>
        <?php endif; ?>

        <form action="" method="POST">

            <div class="form-group">
                <label for="email">Correo Electrónico</label>

                <input
                    type="email"
                    name="email"
                    id="email"
                    class="form-control"
                    required
                    autocomplete="username"
                    placeholder="admin@delivery.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>

                <input
                    type="password"
                    name="password"
                    id="password"
                    class="form-control"
                    required
                    autocomplete="current-password"
                    placeholder="Ingresa tu contraseña"
                >
            </div>

            <button type="submit" class="btn btn-primary login-button">
                Ingresar
            </button>

        </form>

        <div class="login-footer">
            Panel de administración
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
