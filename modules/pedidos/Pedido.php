<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../notificaciones/Notificacion.php';

class Pedido {

    /**
     * Estados que cuentan como "activos" para el repartidor.
     * Un repartidor se considera 'ocupado' si tiene >=1 pedido en estos estados.
     */
    private static function estadosActivos() {
        return ['pendiente', 'asignado', 'en_camino'];
    }

    public static function generarCodigoSeguimiento($comercio_id = null) {
        $db = Database::getConnection();
        $prefijo = 'ORD';

        if (!empty($comercio_id)) {
            $comercio_id = (int)$comercio_id;
            // Traer todos los comercios ordenados por ID para garantizar asignación determinista y única de siglas
            $comercios = $db->query("SELECT id, nombre_comercial FROM comercios ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

            $prefijosAsignados = [];
            $conteoBase = [];

            foreach ($comercios as $c) {
                $limpio = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $c['nombre_comercial']);
                $limpio = preg_replace('/[^A-Za-z0-9\s]/', '', strtoupper($limpio));
                $palabras = array_values(array_filter(explode(' ', trim($limpio))));

                $siglas = '';
                foreach ($palabras as $palabra) {
                    $siglas .= substr($palabra, 0, 1);
                }
                $siglas = substr($siglas, 0, 4);
                $base = !empty($siglas) ? 'D' . $siglas : 'ORD';

                if (!isset($conteoBase[$base])) {
                    $conteoBase[$base] = 1;
                    $prefijosAsignados[(int)$c['id']] = $base;
                } else {
                    $conteoBase[$base]++;
                    $prefijosAsignados[(int)$c['id']] = $base . $conteoBase[$base];
                }
            }

            if (isset($prefijosAsignados[$comercio_id])) {
                $prefijo = $prefijosAsignados[$comercio_id];
            }
        }

        // Generar sufijo aleatorio de 6 caracteres verificando que el código completo no exista en BD
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE codigo_seguimiento = :cod");
        do {
            $aleatorio = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $codigoFinal = $prefijo . '-' . $aleatorio;
            $stmtCheck->execute([':cod' => $codigoFinal]);
        } while ((int)$stmtCheck->fetchColumn() > 0);

        return $codigoFinal;
    }

    // =========================================================================
    // LISTADOS
    // =========================================================================

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
                                    AND (DATE(p.fecha_creacion) = CURDATE() OR p.estado IN ('asignado', 'en_camino'))
                ORDER BY p.id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario_id' => $usuario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // CREAR PEDIDO
    // =========================================================================

    public static function crear($datos) {
        $db = Database::getConnection();
        $codigo = self::generarCodigoSeguimiento($datos['comercio_id'] ?? null);
        $repartidorId = !empty($datos['repartidor_id']) ? (int)$datos['repartidor_id'] : null;
        $estadoInicial = $repartidorId ? 'asignado' : 'pendiente';
        $esEnvioGratis = !empty($datos['es_envio_gratis']) ? 1 : 0;
        $pedidoId = null;

        // Dentro de Pedido::crear() cuando $repartidor_id está vacío:
$stmtAutoRep = $db->prepare("SELECT id FROM repartidores 
                             WHERE estado_disponibilidad = 'disponible'
                               AND (comercio_id = :cid OR comercio_id IS NULL)
                             ORDER BY (comercio_id = :cid_prio) DESC, id ASC 
                             LIMIT 1");
$stmtAutoRep->execute([
    ':cid'      => $comercio_id,
    ':cid_prio' => $comercio_id
]);
$autoRepId = $stmtAutoRep->fetchColumn();
if ($autoRepId) {
    $repartidor_id = (int)$autoRepId;
}

        try {
            $db->beginTransaction();

            if ($repartidorId) {
                $stmtRep = $db->prepare(
                    "SELECT r.id
                     FROM repartidores r
                     INNER JOIN usuarios u ON u.id = r.usuario_id
                     WHERE r.id = :r_id
                       AND u.estado = 1
                       AND r.estado_disponibilidad != 'inactivo'
                     FOR UPDATE"
                );
                $stmtRep->execute([':r_id' => $repartidorId]);
                if (!$stmtRep->fetchColumn()) {
                    $db->rollBack();
                    return false;
                }
            }

            $sql = "INSERT INTO pedidos (
                        codigo_seguimiento, comercio_id, repartidor_id, 
                        cliente_nombre, cliente_telefono, cliente_direccion, 
                        descripcion_paquete, latitud, longitud,
                        monto_mando, costo_envio, metodo_pago, es_envio_gratis, estado
                    ) VALUES (
                        :codigo, :comercio_id, :repartidor_id,
                        :cliente_nombre, :cliente_telefono, :cliente_direccion,
                        :descripcion_paquete, :latitud, :longitud,
                        :monto_mando, :costo_envio, :metodo_pago, :es_envio_gratis, :estado
                    )";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':codigo'              => $codigo,
                ':comercio_id'         => (int)$datos['comercio_id'],
                ':repartidor_id'       => $repartidorId,
                ':cliente_nombre'      => $datos['cliente_nombre'],
                ':cliente_telefono'    => $datos['cliente_telefono'],
                ':cliente_direccion'   => $datos['cliente_direccion'],
                ':descripcion_paquete' => !empty($datos['descripcion_paquete']) ? $datos['descripcion_paquete'] : 'Paquete estándar',
                ':latitud'             => isset($datos['latitud']) && $datos['latitud'] !== '' ? (float)$datos['latitud'] : 13.69294000,
                ':longitud'            => isset($datos['longitud']) && $datos['longitud'] !== '' ? (float)$datos['longitud'] : -89.21819000,
                ':monto_mando'         => (float)$datos['monto_mando'],
                ':costo_envio'         => (float)$datos['costo_envio'],
                ':metodo_pago'         => $datos['metodo_pago'] ?? 'efectivo',
                ':es_envio_gratis'     => $esEnvioGratis,
                ':estado'              => $estadoInicial
            ]);

            $pedidoId = (int)$db->lastInsertId();

            // Si nació asignado a un repartidor, recalcular su disponibilidad
            if ($repartidorId) {
                self::recalcularDisponibilidadRepartidor($db, $repartidorId);
            }

            $db->commit();

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[Pedido::crear] " . $e->getMessage());
            return false;
        }

        // Asegurar formato de teléfono 0000-0000
        $telDigitos = preg_replace('/\D/', '', $datos['cliente_telefono'] ?? '');
        $telefonoFormateado = (strlen($telDigitos) === 8)
            ? substr($telDigitos, 0, 4) . '-' . substr($telDigitos, 4, 4)
            : trim($datos['cliente_telefono']);
            
        // Notificar al repartidor si fue asignado desde la creación
        if ($repartidorId && $pedidoId) {
            try {
                $stmtUsr = $db->prepare("SELECT usuario_id FROM repartidores WHERE id = :r_id");
                $stmtUsr->execute([':r_id' => $repartidorId]);
                $repUsuarioId = $stmtUsr->fetchColumn();

                if ($repUsuarioId) {
                    Notificacion::crear(
                        $repUsuarioId,
                        $pedidoId,
                        "Se te ha asignado la nueva orden $codigo."
                    );
                }
            } catch (Exception $e) {
                error_log("[Pedido::crear notif] " . $e->getMessage());
            }
        }

        return true;
    }

    public static function listar($rol, $usuario_id) {
        $db = Database::getConnection();
        $usuario_id = (int)$usuario_id;

        $sql = "SELECT p.*, 
                       c.nombre_comercial, 
                       u.nombre AS repartidor_nombre
                FROM pedidos p
                INNER JOIN comercios c ON p.comercio_id = c.id
                LEFT JOIN repartidores r ON p.repartidor_id = r.id
                LEFT JOIN usuarios u ON r.usuario_id = u.id";

        $params = [];

        if ($rol === 'comercio') {
            $stmtCom = $db->prepare("SELECT id FROM comercios WHERE usuario_id = :uid LIMIT 1");
            $stmtCom->execute([':uid' => $usuario_id]);
            $cid = (int)$stmtCom->fetchColumn();

            $sql .= " WHERE p.comercio_id = :cid ";
            $params[':cid'] = $cid;
        } elseif ($rol === 'repartidor') {
            $stmtRep = $db->prepare("SELECT id FROM repartidores WHERE usuario_id = :uid LIMIT 1");
            $stmtRep->execute([':uid' => $usuario_id]);
            $rid = (int)$stmtRep->fetchColumn();

            $sql .= " WHERE p.repartidor_id = :rid 
                        AND (DATE(p.fecha_creacion) = CURDATE() OR p.estado IN ('asignado', 'en_camino')) ";
            $params[':rid'] = $rid;
        }

        $sql .= " ORDER BY p.id DESC LIMIT 200";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // =========================================================================
    // ASIGNAR / REASIGNAR REPARTIDOR
    // Modelo: 1 repartidor puede tener N pedidos activos.
    // =========================================================================

    public static function asignarRepartidor($pedido_id, $nuevo_repartidor_id) {
        $db = Database::getConnection();

        $pedido_id           = (int)$pedido_id;
        $nuevo_repartidor_id = (int)$nuevo_repartidor_id;

        if ($pedido_id <= 0 || $nuevo_repartidor_id <= 0) {
            return false;
        }

        $repartidorAnteriorInfo = null;
        $nuevoRepartidorInfo    = null;
        $repartidorAnteriorId   = null;

        try {
            $db->beginTransaction();

            // -----------------------------------------------------------------
            // 1. Cargar pedido y validar existencia + estado reasignable
            // -----------------------------------------------------------------
            $stmtPedido = $db->prepare(
                "SELECT p.id, p.codigo_seguimiento, p.comercio_id, p.repartidor_id, p.estado
                 FROM pedidos p
                 WHERE p.id = :id
                 FOR UPDATE"
            );
            $stmtPedido->execute([':id' => $pedido_id]);
            $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $db->rollBack();
                return false;
            }

            if (in_array($pedido['estado'], ['entregado', 'cancelado'], true)) {
                $db->rollBack();
                return false;
            }

            $repartidorAnteriorId = !empty($pedido['repartidor_id'])
                ? (int)$pedido['repartidor_id']
                : null;

            // Nada que hacer si es el mismo repartidor
            if ($repartidorAnteriorId === $nuevo_repartidor_id) {
                $db->rollBack();
                return true;
            }

            // -----------------------------------------------------------------
            // 2. Validar el nuevo repartidor: existe, activo y del mismo comercio
            // -----------------------------------------------------------------
            $stmtNuevoRep = $db->prepare(
                "SELECT r.id, r.usuario_id, r.comercio_id, u.estado AS usuario_activo, r.estado_disponibilidad
                 FROM repartidores r
                 INNER JOIN usuarios u ON u.id = r.usuario_id
                 WHERE r.id = :r_id
                   AND u.estado = 1
                   AND r.estado_disponibilidad != 'inactivo'
                 FOR UPDATE"
            );
            $stmtNuevoRep->execute([':r_id' => $nuevo_repartidor_id]);
            $nuevoRep = $stmtNuevoRep->fetch(PDO::FETCH_ASSOC);

            if (!$nuevoRep) {
                $db->rollBack();
                return false;
            }

            if ((int)$nuevoRep['usuario_activo'] !== 1 || $nuevoRep['estado_disponibilidad'] === 'inactivo') {
                $db->rollBack();
                return false;
            }

            // El repartidor debe pertenecer al mismo comercio del pedido
            if (!empty($nuevoRep['comercio_id']) && (int)$nuevoRep['comercio_id'] !== (int)$pedido['comercio_id']) {
                $db->rollBack();
                return false;
            }

            // -----------------------------------------------------------------
            // 3. Determinar nuevo estado del pedido (preservar en_camino)
            // -----------------------------------------------------------------
            $nuevoEstado = in_array($pedido['estado'], ['en_camino'], true)
                ? 'en_camino'
                : 'asignado';

            // -----------------------------------------------------------------
            // 4. Actualizar pedido
            // -----------------------------------------------------------------
            $stmtUpd = $db->prepare(
                "UPDATE pedidos
                 SET repartidor_id = :repartidor_id, estado = :estado
                 WHERE id = :id"
            );
            $stmtUpd->execute([
                ':repartidor_id' => $nuevo_repartidor_id,
                ':estado'        => $nuevoEstado,
                ':id'            => $pedido_id
            ]);

            // -----------------------------------------------------------------
            // 5. Recalcular disponibilidad de ambos repartidores
            // -----------------------------------------------------------------
            if ($repartidorAnteriorId) {
                self::recalcularDisponibilidadRepartidor($db, $repartidorAnteriorId);
            }
            self::recalcularDisponibilidadRepartidor($db, $nuevo_repartidor_id);

            // -----------------------------------------------------------------
            // 6. Datos para notificaciones (dentro de la transacción)
            // -----------------------------------------------------------------
            if ($repartidorAnteriorId) {
                $stmtPrevUsr = $db->prepare("SELECT usuario_id FROM repartidores WHERE id = :r_id");
                $stmtPrevUsr->execute([':r_id' => $repartidorAnteriorId]);
                $prevUsuarioId = $stmtPrevUsr->fetchColumn();

                if ($prevUsuarioId) {
                    $repartidorAnteriorInfo = [
                        'usuario_id' => (int)$prevUsuarioId,
                        'codigo'     => $pedido['codigo_seguimiento']
                    ];
                }
            }

            $nuevoRepartidorInfo = [
                'usuario_id' => (int)$nuevoRep['usuario_id'],
                'codigo'     => $pedido['codigo_seguimiento']
            ];

            $db->commit();

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[Pedido::asignarRepartidor] " . $e->getMessage());
            return false;
        }

        // ---------------------------------------------------------------------
        // 7. Auditoría (fuera de la transacción)
        // ---------------------------------------------------------------------
        try {
            error_log(sprintf(
                "[REASIGNACION] pedido=%d codigo=%s de_rep=%s a_rep=%d por_usuario=%s fecha=%s",
                $pedido_id,
                $pedido['codigo_seguimiento'],
                $repartidorAnteriorId ?? 'null',
                $nuevo_repartidor_id,
                $_SESSION['usuario_id'] ?? 'sistema',
                date('Y-m-d H:i:s')
            ));
        } catch (Exception $e) {
            // No bloquea el flujo
        }

        // ---------------------------------------------------------------------
        // 8. Notificaciones (fuera de la transacción)
        // ---------------------------------------------------------------------
        try {
            if ($repartidorAnteriorInfo) {
                Notificacion::crear(
                    $repartidorAnteriorInfo['usuario_id'],
                    $pedido_id,
                    "El pedido " . $repartidorAnteriorInfo['codigo'] . " ha sido reasignado a otro repartidor."
                );
            }

            if ($nuevoRepartidorInfo) {
                Notificacion::crear(
                    $nuevoRepartidorInfo['usuario_id'],
                    $pedido_id,
                    "Se te ha asignado la orden " . $nuevoRepartidorInfo['codigo'] . "."
                );
            }
        } catch (Exception $e) {
            error_log("[Pedido::asignarRepartidor notif] " . $e->getMessage());
        }

        return true;
    }

    // =========================================================================
    // ACTUALIZAR ESTADO
    // =========================================================================

    public static function actualizarEstado($pedido_id, $nuevo_estado) {
        $db = Database::getConnection();
        $codigo = '';
        $comercioUsuarioId = null;
        $repartidorId = null;

        // Validar estado permitido
        $estadosValidos = ['pendiente', 'asignado', 'en_camino', 'entregado', 'cancelado'];
        if (!in_array($nuevo_estado, $estadosValidos, true)) {
            return false;
        }

        try {
            $db->beginTransaction();

            // 1. Bloquear y obtener el estado previo y datos para notificaciones
            $stmtData = $db->prepare(
                "SELECT p.estado, p.codigo_seguimiento, p.repartidor_id, p.comercio_id, c.usuario_id 
                 FROM pedidos p 
                 INNER JOIN comercios c ON p.comercio_id = c.id 
                 WHERE p.id = :id
                 FOR UPDATE"
            );
            $stmtData->execute([':id' => $pedido_id]);
            $info = $stmtData->fetch(PDO::FETCH_ASSOC);

            if (!$info) {
                $db->rollBack();
                return false;
            }

            $estadoAnterior = $info['estado'];
            $codigo = $info['codigo_seguimiento'];
            $comercioUsuarioId = (int)$info['usuario_id'];
            $repartidorId = !empty($info['repartidor_id']) ? (int)$info['repartidor_id'] : null;

            // 2. Actualizar estado
            $stmt = $db->prepare("UPDATE pedidos SET estado = :estado WHERE id = :id");
            $stmt->execute([':estado' => $nuevo_estado, ':id' => $pedido_id]);

            // 3. Registrar el ingreso automáticamente en el turno abierto correspondiente
            if ($nuevo_estado === 'entregado') {
                $stmtPedInfo = $db->prepare("SELECT comercio_id, codigo_seguimiento, cliente_nombre, metodo_pago, (monto_mando + costo_envio) as total
                                             FROM pedidos WHERE id = :id LIMIT 1");
                $stmtPedInfo->execute([':id' => $pedido_id]);
                $pedRow = $stmtPedInfo->fetch(PDO::FETCH_ASSOC);

                if ($pedRow && (float)$pedRow['total'] > 0) {
                    $stmtTurno = $db->prepare("SELECT id FROM turnos_caja
                                               WHERE estado = 'abierta'
                                                 AND (comercio_id = :cid OR comercio_id IS NULL)
                                               ORDER BY comercio_id DESC, id DESC LIMIT 1");
                    $stmtTurno->execute([':cid' => (int)$pedRow['comercio_id']]);
                    $turnoAbiertoId = $stmtTurno->fetchColumn();

                    if ($turnoAbiertoId) {
                        $stmtDup = $db->prepare("SELECT COUNT(*) FROM movimientos_caja WHERE turno_id = :tid AND pedido_id = :pid AND tipo = 'ingreso'");
                        $stmtDup->execute([':tid' => $turnoAbiertoId, ':pid' => $pedido_id]);

                        if ((int)$stmtDup->fetchColumn() === 0) {
                            $uidReg = $_SESSION['usuario_id'] ?? $comercioUsuarioId;
                            $conceptoIng = "Entrega pedido " . $pedRow['codigo_seguimiento'] . " (" . strtoupper($pedRow['metodo_pago']) . ")";
                            $stmtIng = $db->prepare("INSERT INTO movimientos_caja (turno_id, pedido_id, usuario_id, tipo, concepto, monto, creado_en)
                                                     VALUES (:tid, :pid, :uid, 'ingreso', :con, :mon, NOW())");
                            $stmtIng->execute([
                                ':tid' => $turnoAbiertoId,
                                ':pid' => $pedido_id,
                                ':uid' => $uidReg,
                                ':con' => $conceptoIng,
                                ':mon' => (float)$pedRow['total']
                            ]);
                        }
                    }
                }
            } elseif ($estadoAnterior === 'entregado' && $nuevo_estado === 'cancelado') {
                $stmtDelIngreso = $db->prepare(
                    "DELETE FROM movimientos_caja
                     WHERE pedido_id = :pid AND tipo = 'ingreso'"
                );
                $stmtDelIngreso->execute([':pid' => $pedido_id]);
            }

            // 4. Si el pedido se cerró, recalcular disponibilidad del repartidor
            if (in_array($nuevo_estado, ['entregado', 'cancelado'], true) && $repartidorId) {
                self::recalcularDisponibilidadRepartidor($db, $repartidorId);
            }

            $db->commit();

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[Pedido::actualizarEstado] " . $e->getMessage());
            return false;
        }

        // 4. Notificaciones fuera de la transacción
        try {
            $mensaje = "El pedido $codigo ha cambiado de estado a " . strtoupper($nuevo_estado) . ".";

            if ($comercioUsuarioId) {
                Notificacion::crear($comercioUsuarioId, $pedido_id, $mensaje);
            }

            $admins = $db->query("SELECT id FROM usuarios WHERE rol = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $admin_id) {
                if ((int)$admin_id !== $comercioUsuarioId) {
                    Notificacion::crear((int)$admin_id, $pedido_id, $mensaje);
                }
            }
        } catch (Exception $e) {
            error_log("[Pedido::actualizarEstado notif] " . $e->getMessage());
        }

        return true;
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * Recalcula `estado_disponibilidad` de un repartidor según sus pedidos activos.
     * - 'ocupado'    → tiene >=1 pedido en estado activo
     * - 'disponible' → no tiene pedidos activos
     *
     * Debe llamarse SIEMPRE dentro de una transacción abierta.
     */
    private static function recalcularDisponibilidadRepartidor(PDO $db, $repartidor_id) {
        $estados = self::estadosActivos();
        $placeholders = implode(',', array_fill(0, count($estados), '?'));

        $sql = "SELECT COUNT(*) 
                FROM pedidos 
                WHERE repartidor_id = ? 
                  AND estado IN ($placeholders)";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([(int)$repartidor_id], $estados));
        $activos = (int)$stmt->fetchColumn();

        $nuevoEstadoDisp = $activos > 0 ? 'ocupado' : 'disponible';

        $stmtUpd = $db->prepare(
            "UPDATE repartidores
             SET estado_disponibilidad = :estado
             WHERE id = :r_id AND estado_disponibilidad != 'inactivo'"
        );
        $stmtUpd->execute([
            ':estado' => $nuevoEstadoDisp,
            ':r_id'   => (int)$repartidor_id
        ]);
    }
}