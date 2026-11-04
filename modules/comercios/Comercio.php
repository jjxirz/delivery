<?php
require_once __DIR__ . '/../../config/database.php';

class Comercio {

    public static function listar() {
        $db = Database::getConnection();
        $sql = "SELECT c.*, u.email, u.estado 
                FROM comercios c 
                INNER JOIN usuarios u ON c.usuario_id = u.id 
                ORDER BY c.id DESC";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function obtenerPorId($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT c.*, u.email, u.nombre as usuario_nombre 
                              FROM comercios c 
                              INNER JOIN usuarios u ON c.usuario_id = u.id 
                              WHERE c.id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function registrar($nombre_comercial, $telefono, $direccion, $email, $password) {
        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            // 1. Crear el usuario del comercio
            $stmtUser = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (:nombre, :email, :password, 'comercio')");
            $stmtUser->execute([
                ':nombre' => $nombre_comercial,
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_BCRYPT)
            ]);
            $usuario_id = $db->lastInsertId();

            // 2. Crear los datos del comercio
            $stmtCom = $db->prepare("INSERT INTO comercios (usuario_id, nombre_comercial, telefono, direccion) VALUES (:usuario_id, :nombre, :telefono, :direccion)");
            $stmtCom->execute([
                ':usuario_id' => $usuario_id,
                ':nombre' => $nombre_comercial,
                ':telefono' => $telefono,
                ':direccion' => $direccion
            ]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return "Error al registrar comercio: " . $e->getMessage();
        }
    }
}