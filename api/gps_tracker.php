<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido. Usa POST.']);
    exit;
}

// Aceptar tanto JSON crudo como $_POST estándar
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$repartidor_id = (int)($data['repartidor_id'] ?? 0);
$pedido_id     = !empty($data['pedido_id']) ? (int)$data['pedido_id'] : null;
$latitud       = isset($data['latitud']) ? (float)$data['latitud'] : null;
$longitud      = isset($data['longitud']) ? (float)$data['longitud'] : null;
$direccion     = trim($data['direccion_exacta'] ?? '');
$velocidad     = (float)($data['velocidad_kmh'] ?? 0);
$nuevo_estado  = trim($data['estado_pedido'] ?? '');

if ($repartidor_id <= 0 || $latitud === null || $longitud === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan parámetros obligatorios (repartidor_id, latitud, longitud).']);
    exit;
}

try {
    $db = Database::getConnection();

    // 1. Actualizar ubicación en vivo del repartidor
    $estadoRep = ($nuevo_estado === 'entregado') ? 'disponible' : 'en_ruta';
    $stmtRep = $db->prepare("UPDATE repartidores 
                             SET latitud_actual = :lat, 
                                 longitud_actual = :lng, 
                                 estado_disponibilidad = :est 
                             WHERE id = :rid");
    $stmtRep->execute([
        ':lat' => $latitud,
        ':lng' => $longitud,
        ':est' => $estadoRep,
        ':rid' => $repartidor_id
    ]);

    // 2. Guardar punto en historial_rutas_gps para trazar la ruta en el mapa
    $stmtHist = $db->prepare("INSERT INTO historial_rutas_gps (repartidor_id, pedido_id, latitud, longitud, direccion_aprox, velocidad_kmh, registrado_en)
                              VALUES (:rid, :pid, :lat, :lng, :dir, :vel, NOW())");
    $stmtHist->execute([
        ':rid' => $repartidor_id,
        ':pid' => $pedido_id,
        ':lat' => $latitud,
        ':lng' => $longitud,
        ':dir' => $direccion !== '' ? $direccion : null,
        ':vel' => $velocidad
    ]);

    // 3. Si viene vinculado a un pedido, actualizar coordenadas y estado si corresponde
    if ($pedido_id) {
        if (in_array($nuevo_estado, ['en_camino', 'entregado'], true)) {
            require_once __DIR__ . '/../modules/pedidos/Pedido.php';
            Pedido::actualizarEstado($pedido_id, $nuevo_estado);
        }

        // Actualizar dirección/coordenadas del punto actual del envío si no está finalizado
        $stmtPed = $db->prepare("UPDATE pedidos 
                                 SET latitud = :lat, longitud = :lng 
                                 WHERE id = :pid");
        $stmtPed->execute([
            ':lat' => $latitud,
            ':lng' => $longitud,
            ':pid' => $pedido_id
        ]);
    }

    echo json_encode([
        'ok' => true,
        'repartidor_id' => $repartidor_id,
        'pedido_id' => $pedido_id,
        'coordenadas' => ['lat' => $latitud, 'lng' => $longitud],
        'direccion' => $direccion,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}