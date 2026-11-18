<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$pedido_id = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;
$db = Database::getConnection();

$stmt = $db->prepare("SELECT p.id, p.codigo_seguimiento, p.estado, p.cliente_nombre, p.cliente_direccion,
                             p.latitud, p.longitud, p.repartidor_id, u.nombre as repartidor_nombre
                      FROM pedidos p
                      LEFT JOIN repartidores r ON p.repartidor_id = r.id
                      LEFT JOIN usuarios u ON r.usuario_id = u.id
                      WHERE p.id = :id LIMIT 1");
$stmt->execute([':id' => $pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['error' => 'Pedido no encontrado']);
    exit;
}

// 5 perfiles de movimiento de repartidores en San Salvador
$rutasSimuladas = [
    [
        'perfil' => 'Ruta Poniente (Alameda Roosevelt)',
        'puntos' => [
            [13.692940, -89.218190], [13.695200, -89.222500], [13.698100, -89.226800],
            [13.700900, -89.231200], [13.702400, -89.235800], [13.703800, -89.240100]
        ]
    ],
    [
        'perfil' => 'Ruta Sur-Poniente (Los Próceres)',
        'puntos' => [
            [13.692940, -89.218190], [13.688500, -89.224100], [13.684200, -89.231500],
            [13.680500, -89.241000], [13.678200, -89.254000], [13.676800, -89.279700]
        ]
    ],
    [
        'perfil' => 'Ruta Oriente (Blvd. del Ejército)',
        'puntos' => [
            [13.692940, -89.218190], [13.696500, -89.205000], [13.701000, -89.191000],
            [13.704800, -89.176500], [13.707500, -89.162000], [13.709500, -89.151200]
        ]
    ],
    [
        'perfil' => 'Ruta Norte (Blvd. Constitución)',
        'puntos' => [
            [13.692940, -89.218190], [13.699500, -89.216000], [13.706200, -89.213500],
            [13.711800, -89.210200], [13.715800, -89.206500], [13.720100, -89.203100]
        ]
    ],
    [
        'perfil' => 'Ruta Sur (Autopista a Comalapa)',
        'puntos' => [
            [13.692940, -89.218190], [13.687000, -89.211000], [13.681200, -89.204500],
            [13.676000, -89.199000], [13.671800, -89.195200], [13.668900, -89.191400]
        ]
    ]
];

// La ruta se vincula al repartidor (si tiene varios pedidos, comparte su misma trayectoria base)
$idBase = !empty($pedido['repartidor_id']) ? (int)$pedido['repartidor_id'] : (int)$pedido['id'];
$semilla = $idBase % count($rutasSimuladas);
$rutaAsignada = $rutasSimuladas[$semilla];
$puntos = $rutaAsignada['puntos'];

$destinoPrincipal = [
    (float)($pedido['latitud'] ?: end($puntos)[0]),
    (float)($pedido['longitud'] ?: end($puntos)[1])
];

if ($pedido['estado'] === 'entregado') {
    $posActual = $destinoPrincipal;
} elseif (in_array($pedido['estado'], ['pendiente', 'cancelado'], true)) {
    $posActual = $puntos[0];
} else {
    $indicePaso = (int)(floor(time() / 4) % count($puntos));
    $posActual = $puntos[$indicePaso];
}

// Consultar TODOS los pedidos activos asignados a este mismo repartidor
$paradasActivas = [];
if (!empty($pedido['repartidor_id'])) {
    $stmtOtras = $db->prepare("SELECT id, codigo_seguimiento, cliente_nombre, cliente_direccion, estado, latitud, longitud
                               FROM pedidos
                               WHERE repartidor_id = :rid
                                 AND estado IN ('pendiente', 'asignado', 'en_camino')
                               ORDER BY id ASC");
    $stmtOtras->execute([':rid' => (int)$pedido['repartidor_id']]);
    foreach ($stmtOtras->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $paradasActivas[] = [
            'id'        => (int)$row['id'],
            'codigo'    => $row['codigo_seguimiento'],
            'cliente'   => $row['cliente_nombre'],
            'direccion' => $row['cliente_direccion'],
            'estado'    => strtoupper($row['estado']),
            'coords'    => [(float)($row['latitud'] ?: $destinoPrincipal[0]), (float)($row['longitud'] ?: $destinoPrincipal[1])],
            'es_actual' => ((int)$row['id'] === (int)$pedido['id'])
        ];
    }
}

echo json_encode([
    'pedido_id'       => (int)$pedido['id'],
    'estado'          => $pedido['estado'],
    'repartidor'      => $pedido['repartidor_nombre'] ?? 'Sin asignar',
    'ruta_nombre'     => $rutaAsignada['perfil'],
    'origen'          => $puntos[0],
    'actual'          => $posActual,
    'destino'         => $destinoPrincipal,
    'trazado'         => $puntos,
    'paradas_activas' => $paradasActivas,
    'timestamp'       => date('H:i:s')
]);