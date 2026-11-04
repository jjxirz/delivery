<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../notificaciones/Notificacion.php';

class Pedido {

    public static function generarCodigoSeguimiento() {
        return 'ORD-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
    }

    public static function listarTodos() {
        $db = Database::getConnection();
        $sql = "SELECT p.*, c.nombre_comercial, r_usr.nombre as repartidor_nombre 
                FROM pedidos p 
                INNER JOIN comercios c ON p.comercio_id = c.id 
                LEFT JOIN repartidores r ON p.repartidor_id = r.id 
                LEFT JOIN usuarios r_usr ON r.usuario_id = r_usr.id 
                ORDER BY p.id DESC";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarPorComercio($usuario_id) {
        $db = Database::getConnection();
        $sql = "SELECT p.*, c.nombre_comercial, r_usr.nombre as repartidor_nombre 
                FROM pedidos p 
                INNER JOIN comercios c ON p.comercio_id = c.id 
                LEFT JOIN repartidores r ON p.repartidor_id = r.id 
                LEFT JOIN usuarios r_usr ON r.usuario_id = r_usr.id 
                WHERE c.usuario_id = :usuario_id 
                ORDER BY p.id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario_id' => $usuario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarPorRepartidor($usuario_id) {
        $db = Database::getConnection();
        $sql = "SELECT p.*, 
                       c.nombre_comercial, 
                       c.telefono as comercio_telefono, 
                       c.direccion as comercio_direccion,
                       r_usr.nombre as repartidor_nombre
                FROM pedidos p 
                INNER JOIN comercios c ON p.comercio_id = c.id 
                INNER JOIN repartidores r ON p.repartidor_id = r.id 
                INNER JOIN usuarios r_usr ON r.usuario_id = r_usr.id
                WHERE r.usuario_id = :usuario_id 
                ORDER BY p.id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario_id' => $usuario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function crear($comercio_id, $cliente_nombre, $cliente_direccion, $cliente_telefono, $monto_mando, $costo_envio) {
        $db = Database::getConnection();
        $codigo = self::generarCodigoSeguimiento();
        
        $sql = "INSERT INTO pedidos (codigo_seguimiento, comercio_id, cliente_nombre, cliente_direccion, cliente_telefono, monto_mando, costo_envio, estado) 
                VALUES (:codigo, :comercio_id, :nombre, :direccion, :telefono, :monto, :costo, 'pendiente')";
        
        $stmt = $db->prepare($sql);
        $res = $stmt->execute([
            ':codigo' => $codigo,
            ':comercio_id' => $comercio_id,
            ':nombre' => $cliente_nombre,
            ':direccion' => $cliente_direccion,
            ':telefono' => $cliente_telefono,
            ':monto' => $monto_mando,
            ':costo' => $costo_envio
        ]);

        if ($res) {
            $pedido_id = $db->lastInsertId();
            // Notificar a administradores de nuevo pedido
            $admins = $db->query("SELECT id FROM usuarios WHERE rol = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $admin_id) {
                Notificacion::crear($admin_id, $pedido_id, "Nuevo pedido registrado ($codigo).");
            }
        }
        return $res;
    }

    public static function asignarRepartidor($pedido_id, $nuevo_repartidor_id) {
        $db = Database::getConnection();
        $repartidorAnteriorInfo = null;
        $nuevoRepartidorInfo = null;

        try {
            $db->beginTransaction();

            // 1. Obtener información del repartidor actual asignado (si existe)
            $stmtPrev = $db->prepare("SELECT p.codigo_seguimiento, p.repartidor_id, r.usuario_id 
                                    FROM pedidos p 
                                    LEFT JOIN repartidores r ON p.repartidor_id = r.id 
                                    WHERE p.id = :id");
            $stmtPrev->execute([':id' => $pedido_id]);
            $pedidoPrevio = $stmtPrev->fetch(PDO::FETCH_ASSOC);

            // Si ya tenía un repartidor asignado diferente al nuevo, liberar el anterior
            if (!empty($pedidoPrevio['repartidor_id']) && $pedidoPrevio['repartidor_id'] != $nuevo_repartidor_id) {
                $stmtFreePrev = $db->prepare("UPDATE repartidores SET estado_disponibilidad = 'disponible' WHERE id = :prev_r_id");
                $stmtFreePrev->execute([':prev_r_id' => $pedidoPrevio['repartidor_id']]);
                
                $repartidorAnteriorInfo = [
                    'usuario_id' => $pedidoPrevio['usuario_id'],
                    'codigo' => $pedidoPrevio['codigo_seguimiento']
                ];
            }

            // 2. Asignar el nuevo repartidor y actualizar su estado a 'ocupado'
            $stmt = $db->prepare("UPDATE pedidos SET repartidor_id = :repartidor_id, estado = 'asignado' WHERE id = :id");
            $stmt->execute([':repartidor_id' => $nuevo_repartidor_id, ':id' => $pedido_id]);

            $stmtRep = $db->prepare("UPDATE repartidores SET estado_disponibilidad = 'ocupado' WHERE id = :repartidor_id");
            $stmtRep->execute([':repartidor_id' => $nuevo_repartidor_id]);

            // Obtener datos del nuevo repartidor para su notificación
            $stmtData = $db->prepare("SELECT p.codigo_seguimiento, r.usuario_id FROM pedidos p INNER JOIN repartidores r ON r.id = :r_id WHERE p.id = :p_id");
            $stmtData->execute([':r_id' => $nuevo_repartidor_id, ':p_id' => $pedido_id]);
            $nuevoRepartidorInfo = $stmtData->fetch(PDO::FETCH_ASSOC);

            $db->commit();

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }

        // 3. ENVIAR NOTIFICACIONES FUERA DE LA TRANSACCIÓN
        try {
            // A. Notificar al repartidor ANTERIOR (si aplica)
            if ($repartidorAnteriorInfo) {
                Notificacion::crear(
                    $repartidorAnteriorInfo['usuario_id'], 
                    $pedido_id, 
                    "El pedido " . $repartidorAnteriorInfo['codigo'] . " ha sido reasignado a otro repartidor."
                );
            }

            // B. Notificar al NUEVO repartidor
            if ($nuevoRepartidorInfo) {
                Notificacion::crear(
                    $nuevoRepartidorInfo['usuario_id'], 
                    $pedido_id, 
                    "Se te ha asignado la orden " . $nuevoRepartidorInfo['codigo_seguimiento'] . "."
                );
            }
        } catch (Exception $e) {
            // Manejo silencioso de notificaciones
        }

        return true;
    }

    public static function actualizarEstado($pedido_id, $nuevo_estado) {
        $db = Database::getConnection();
        $codigo = '';
        $comercioUsuarioId = null;
        
        try {
            $db->beginTransaction();

            // 1. Actualizar el estado del pedido
            $stmt = $db->prepare("UPDATE pedidos SET estado = :estado WHERE id = :id");
            $stmt->execute([':estado' => $nuevo_estado, ':id' => $pedido_id]);

            // 2. Si el pedido se entregó o canceló, liberar al repartidor
            if (in_array($nuevo_estado, ['entregado', 'cancelado'])) {
                $stmtPedido = $db->prepare("SELECT repartidor_id FROM pedidos WHERE id = :id");
                $stmtPedido->execute([':id' => $pedido_id]);
                $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

                if (!empty($pedido['repartidor_id'])) {
                    $stmtRep = $db->prepare("UPDATE repartidores SET estado_disponibilidad = 'disponible' WHERE id = :repartidor_id");
                    $stmtRep->execute([':repartidor_id' => $pedido['repartidor_id']]);
                }
            }

            // Obtener código del pedido y usuario_id del comercio propietario
            $stmtData = $db->prepare("SELECT p.codigo_seguimiento, c.usuario_id 
                                    FROM pedidos p 
                                    INNER JOIN comercios c ON p.comercio_id = c.id 
                                    WHERE p.id = :id");
            $stmtData->execute([':id' => $pedido_id]);
            $info = $stmtData->fetch(PDO::FETCH_ASSOC);

            if ($info) {
                $codigo = $info['codigo_seguimiento'];
                $comercioUsuarioId = $info['usuario_id'];
            }

            $db->commit();

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }

        // 3. ENVIAR NOTIFICACIONES (Fuera de la transacción)
        try {
            $mensaje = "El pedido $codigo ha cambiado de estado a " . strtoupper($nuevo_estado) . ".";

            // A. Notificar al Comercio dueño del pedido
            if ($comercioUsuarioId) {
                Notificacion::crear($comercioUsuarioId, $pedido_id, $mensaje);
            }

            // B. Notificar a los Administradores
            $admins = $db->query("SELECT id FROM usuarios WHERE rol = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $admin_id) {
                // Evitar duplicar notificación si el comercio también es admin (caso borde)
                if ($admin_id != $comercioUsuarioId) {
                    Notificacion::crear($admin_id, $pedido_id, $mensaje);
                }
            }
        } catch (Exception $e) {
            // Ignorar si falla la notificación pero mantener actualización
        }

        return true;
    }
}