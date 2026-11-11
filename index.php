<?php
// 1. Iniciar la sesión
session_start();

// 2. Verificar si el usuario está logueado
// (Asegúrate de que el nombre de la variable coincida con el que usas en login.php)
if (!isset($_SESSION['usuario_id'])) {
    // Si no hay sesión, redirigir al login
    header("Location: login.php");
    exit();
}

// 3. (Opcional pero recomendado) Validar que el rol exista para evitar errores
if (!isset($_SESSION['usuario_rol'])) {
    $_SESSION['usuario_rol'] = 'comercio'; // Rol por defecto o redirigir a logout
}

// 4. Ahora sí, cargar el header
require_once __DIR__ . '/layout/header.php';
?>

<div class="card">
    <h2>Panel de Control</h2>
    <p style="color: var(--so-gray-text); margin-top: 5px;">Bienvenido al sistema de administración de envíos locales.</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
    
    <!-- Tarjeta Comercios -->
    <?php if (in_array($_SESSION['usuario_rol'], ['admin'])): ?>
    <div class="card">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="<?= $ASSETS_IMAGES['icon_store'] ?>" alt="Comercios" style="width: 40px;">
            <div>
                <h3 style="font-size: 1.1rem;">Comercios</h3>
                <p style="color: var(--so-gray-text); font-size: 0.85rem;">Gestión de afiliados</p>
            </div>
        </div>
        <div style="margin-top: 15px;">
            <a href="<?= BASE_URL ?>modules/comercios/index.php" class="btn">Gestión de Afiliados</a>
        </div>
    </div>
    <?php endif; ?>

   <!-- Tarjeta Repartidores (Visible para Admin y Comercio) -->
    <?php if (in_array($_SESSION['usuario_rol'], ['admin', 'comercio'])): ?>
    <div class="card">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="<?= $ASSETS_IMAGES['icon_delivery'] ?>" alt="Repartidores" style="width: 40px;">
            <div>
                <h3 style="font-size: 1.1rem;">Repartidores</h3>
                <p style="color: var(--so-gray-text); font-size: 0.85rem;">Control y registro de repartidores</p>
            </div>
        </div>
        <div style="margin-top: 15px;">
            <a href="<?= BASE_URL ?>modules/repartidores/index.php" class="btn">Ver Repartidores</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tarjeta Pedidos Activos -->
    <div class="card">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="<?= $ASSETS_IMAGES['icon_delivery'] ?>" alt="Pedidos" style="width: 40px;">
            <div>
                <h3 style="font-size: 1.1rem;">Pedidos Activos</h3>
                <p style="color: var(--so-gray-text); font-size: 0.85rem;">Seguimiento en tiempo real</p>
            </div>
        </div>
        <div style="margin-top: 15px;">
            <a href="<?= BASE_URL ?>modules/pedidos/index.php" class="btn btn-primary">Gestión de Envíos</a>
        </div>
    </div>

    <!-- Tarjeta Reportes y Liquidaciones -->
    <?php if ($_SESSION['usuario_rol'] === 'admin'): ?>
    <div class="card">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="<?= $ASSETS_IMAGES['logo'] ?>" alt="Liquidaciones" style="width: 40px;">
            <div>
                <h3 style="font-size: 1.1rem;">Liquidaciones</h3>
                <p style="color: var(--so-gray-text); font-size: 0.85rem;">Cierre diario de caja y reportes</p>
            </div>
        </div>
        <div style="margin-top: 15px;">
            <a href="<?= BASE_URL ?>modules/reportes/index.php" class="btn">Ver Cierres</a>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>