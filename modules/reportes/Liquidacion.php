<?php
require_once __DIR__ . '/../../config/database.php';

class Liquidacion {

    // Genera el cálculo diario en tiempo real con base en los pedidos en estado 'entregado'
    public static function calcularCierreDiario($repartidor_id, $fecha) {
        $db = Database::getConnection();
        
        $sql = "SELECT 
                    COUNT(p.id) as total_envios,
                    COALESCE(SUM(p.monto_mando + p.costo_envio), 0) as monto_recaudado,
                    COALESCE(SUM(p.costo_envio), 0) as pago_comision
                FROM pedidos p
                WHERE p.repartidor_id = :repartidor_id 
                  AND p.estado = 'entregado'
                  AND DATE(p.fecha_creacion) = :fecha";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':repartidor_id' => $repartidor_id,
            ':fecha' => $fecha
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Guarda el registro definitivo de liquidación diaria
    public static function procesarLiquidacion($repartidor_id, $fecha, $porcentaje_comision = 1.0) {
        $db = Database::getConnection();
        $calculo = self::calcularCierreDiario($repartidor_id, $fecha);

        if ($calculo['total_envios'] == 0) {
            return "No existen envíos entregados para este repartidor en la fecha seleccionada.";
        }

        // Verificar si ya fue liquidado
        $stmtCheck = $db->prepare("SELECT id FROM liquidaciones WHERE repartidor_id = :r_id AND fecha = :fecha LIMIT 1");
        $stmtCheck->execute([':r_id' => $repartidor_id, ':fecha' => $fecha]);
        if ($stmtCheck->fetch()) {
            return "Esta jornada ya fue liquidada previamente.";
        }

        $comision_final = $calculo['pago_comision'] * $porcentaje_comision;

        $sql = "INSERT INTO liquidaciones (repartidor_id, fecha, total_envios, monto_recaudado, pago_comision, estado_pago) 
                VALUES (:repartidor_id, :fecha, :total_envios, :monto_recaudado, :pago_comision, 'liquidado')";
        
        $stmt = $db->prepare($sql);
        $resultado = $stmt->execute([
            ':repartidor_id' => $repartidor_id,
            ':fecha' => $fecha,
            ':total_envios' => $calculo['total_envios'],
            ':monto_recaudado' => $calculo['monto_recaudado'],
            ':pago_comision' => $comision_final
        ]);

        return $resultado ? true : "Error al guardar el registro de liquidación.";
    }

    // Reporte de liquidaciones registradas con filtros de fecha
    public static function listarLiquidaciones($fecha_inicio = null, $fecha_fin = null) {
        $db = Database::getConnection();
        
        $sql = "SELECT l.*, u.nombre as repartidor_nombre, r.vehiculo 
                FROM liquidaciones l
                INNER JOIN repartidores r ON l.repartidor_id = r.id
                INNER JOIN usuarios u ON r.usuario_id = u.id";

        $params = [];
        if (!empty($fecha_inicio) && !empty($fecha_fin)) {
            $sql .= " WHERE l.fecha BETWEEN :inicio AND :fin";
            $params[':inicio'] = $fecha_inicio;
            $params[':fin'] = $fecha_fin;
        }

        $sql .= " ORDER BY l.fecha DESC, l.id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}