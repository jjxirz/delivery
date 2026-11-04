<?php
require_once __DIR__ . '/../../config/database.php';

class Repartidor {

    public static function listar($comercio_id = null) {
        $db = Database::getConnection();
        
        if ($comercio_id !== null) {
            $sql = "SELECT r.*, u.nombre, u.email, u.estado as estado_usuario, c.nombre_comercial 
                    FROM repartidores r 
                    INNER JOIN usuarios u ON r.usuario_id = u.id 
                    LEFT JOIN comercios c ON r.comercio_id = c.id
                    WHERE r.comercio_id = :comercio_id
                    ORDER BY r.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([':comercio_id' => $comercio_id]);
        } else {
            $sql = "SELECT r.*, u.nombre, u.email, u.estado as estado_usuario, c.nombre_comercial 
                    FROM repartidores r 
                    INNER JOIN usuarios u ON r.usuario_id = u.id 
                    LEFT JOIN comercios c ON r.comercio_id = c.id
                    ORDER BY r.id DESC";
            $stmt = $db->query($sql);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function cambiarEstado($repartidor_id, $nuevo_estado) {
        $db = Database::getConnection();
        
        $stmtUsr = $db->prepare("SELECT usuario_id FROM repartidores WHERE id = :id");
        $stmtUsr->execute([':id' => $repartidor_id]);
        $usuario_id = $stmtUsr->fetchColumn();

        if ($usuario_id) {
            $stmt = $db->prepare("UPDATE usuarios SET estado = :estado WHERE id = :uid");
            return $stmt->execute([':estado' => $nuevo_estado, ':uid' => $usuario_id]);
        }
        return false;
    }

    public static function registrar($nombre, $email, $password, $vehiculo, $placa, $comercio_id = null) {
        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $stmtUser = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (:nombre, :email, :password, 'repartidor', 1)");
            $stmtUser->execute([
                ':nombre' => $nombre,
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_BCRYPT)
            ]);
            $usuario_id = $db->lastInsertId();

            $stmtRep = $db->prepare("INSERT INTO repartidores (usuario_id, comercio_id, vehiculo, placa, estado_disponibilidad) VALUES (:usuario_id, :comercio_id, :vehiculo, :placa, 'disponible')");
            $stmtRep->execute([
                ':usuario_id' => $usuario_id,
                ':comercio_id' => $comercio_id,
                ':vehiculo' => $vehiculo,
                ':placa' => !empty($placa) ? $placa : 'N/A'
            ]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return "Error al registrar repartidor: " . $e->getMessage();
        }
    }
}