<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getConnection();

    // Desactivar restricciones de clave foránea temporalmente para limpiar
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $db->exec("TRUNCATE TABLE liquidaciones");
    $db->exec("TRUNCATE TABLE pedidos");
    $db->exec("TRUNCATE TABLE repartidores");
    $db->exec("TRUNCATE TABLE comercios");
    $db->exec("TRUNCATE TABLE usuarios");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 1. Crear Usuario Admin
    $stmtUser = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (:nombre, :email, :password, :rol)");
    $stmtUser->execute([
        ':nombre' => 'Administrador General',
        ':email' => 'admin@delivery.com',
        ':password' => password_hash('admin123', PASSWORD_BCRYPT),
        ':rol' => 'admin'
    ]);

    // 2. Crear Usuario Comercio y Ficha en 'comercios'
    $stmtUser->execute([
        ':nombre' => 'Pizzeria Central',
        ':email' => 'pizzeria@delivery.com',
        ':password' => password_hash('comercio123', PASSWORD_BCRYPT),
        ':rol' => 'comercio'
    ]);
    $usuarioComercioId = $db->lastInsertId();

    $stmtCom = $db->prepare("INSERT INTO comercios (usuario_id, nombre_comercial, telefono, direccion) VALUES (:usuario_id, :nombre, :telefono, :direccion)");
    $stmtCom->execute([
        ':usuario_id' => $usuarioComercioId,
        ':nombre' => 'Pizzeria Central',
        ':telefono' => '5555-0199',
        ':direccion' => 'Av. Principal #45'
    ]);

    // 3. Crear Usuario Repartidor y Ficha en 'repartidores'
    $stmtUser->execute([
        ':nombre' => 'Juan Repartidor',
        ':email' => 'repartidor@delivery.com',
        ':password' => password_hash('driver123', PASSWORD_BCRYPT),
        ':rol' => 'repartidor'
    ]);
    $usuarioRepartidorId = $db->lastInsertId();

    $stmtRep = $db->prepare("INSERT INTO repartidores (usuario_id, vehiculo, placa, estado_disponibilidad) VALUES (:usuario_id, :vehiculo, :placa, 'disponible')");
    $stmtRep->execute([
        ':usuario_id' => $usuarioRepartidorId,
        ':vehiculo' => 'Motocicleta',
        ':placa' => 'M-55421'
    ]);

    echo "<div style='font-family:sans-serif; padding:20px; background:#eef9f2; border:1px solid #bcf0da; color:#0e9f6e; border-radius:4px;'>";
    echo "<h3>Base de Datos Sembrada con Éxito</h3>";
    echo "<p><strong>Admin:</strong> admin@delivery.com / admin123</p>";
    echo "<p><strong>Comercio:</strong> pizzeria@delivery.com / comercio123</p>";
    echo "<p><strong>Repartidor:</strong> repartidor@delivery.com / driver123</p>";
    echo "<p><a href='login.php'>Ir al Login</a></p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "Error al sembrar datos: " . $e->getMessage();
}