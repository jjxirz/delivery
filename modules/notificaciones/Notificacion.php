<?php
require_once __DIR__ . '/../../config/database.php';

class Notificacion {

    // Crear una nueva notificación para un usuario específico
    public static function crear($usuario_id, $pedido_id, $mensaje) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, pedido_id, mensaje) VALUES (:usuario_id, :pedido_id, :mensaje)");
        return $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':pedido_id' => $pedido_id,
            ':mensaje' => $mensaje
        ]);
    }

    // Obtener notificaciones no leídas de un usuario
    public static function obtenerNoLeidas($usuario_id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM notificaciones WHERE usuario_id = :usuario_id AND leido = 0 ORDER BY id DESC LIMIT 10");
        $stmt->execute([':usuario_id' => $usuario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Marcar notificaciones como leídas
    public static function marcarComoLeidas($usuario_id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE notificaciones SET leido = 1 WHERE usuario_id = :usuario_id");
        return $stmt->execute([':usuario_id' => $usuario_id]);
    }
}