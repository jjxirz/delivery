<?php
/**
 * Simulador CLI de 5 Rutas GPS con Puntos Marcados para Repartidores Activos
 *
 * Uso en consola:
 *   php scripts/script_repartidor.php              -> Mueve todos los repartidores activos por las 5 rutas
 *   php scripts/script_repartidor.php --entregar   -> Recorre las rutas y marca los pedidos como entregados al final
 *   php scripts/script_repartidor.php 2            -> Simula únicamente al repartidor ID #2
 */

if (php_sapi_name() !== 'cli') {
    exit("Este script debe ejecutarse desde la línea de comandos: php scripts/script_repartidor.php\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Parsear argumentos CLI
$repartidorFiltro = 0;
$marcarEntregadoAlFinal = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--entregar') {
        $marcarEntregadoAlFinal = true;
    } elseif (is_numeric($arg)) {
        $repartidorFiltro = (int)$arg;
    }
}

$endpointUrl = rtrim(BASE_URL, '/') . '/api/gps_tracker.php';

echo "=================================================================\n";
echo " SIMULADOR DE 5 RUTAS GPS EN VIVO (CON PUNTOS MARCADOS)\n";
echo " Endpoint: {$endpointUrl}\n";
echo " Modo: " . ($marcarEntregadoAlFinal ? "Recorrido + Entrega final" : "Patrullaje / Movimiento continuo") . "\n";
echo "=================================================================\n\n";

// 1. Definir las 5 rutas reales con sus puntos/waypoints marcados
$catalogoRutas = [
    [
        'nombre' => 'Ruta 1 · Escalón - San Benito',
        'puntos' => [
            ['lat' => 13.705800, 'lng' => -89.245200, 'calle' => 'P1: Redondel Masferrer, Col. Escalón'],
            ['lat' => 13.703400, 'lng' => -89.240100, 'calle' => 'P2: Paseo General Escalón y 89 Av. Norte'],
            ['lat' => 13.701200, 'lng' => -89.235500, 'calle' => 'P3: Fuentes Beethoven, Paseo Escalón'],
            ['lat' => 13.697500, 'lng' => -89.237800, 'calle' => 'P4: Calle La Mascota, Col. Maquilishuat'],
            ['lat' => 13.693900, 'lng' => -89.240900, 'calle' => 'P5: Blvd. del Hipódromo, Zona Rosa'],
            ['lat' => 13.691500, 'lng' => -89.243200, 'calle' => 'P6: Punto de entrega Col. San Benito']
        ]
    ],
    [
        'nombre' => 'Ruta 2 · Próceres - Antiguo Cuscatlán - Santa Elena',
        'puntos' => [
            ['lat' => 13.681900, 'lng' => -89.218300, 'calle' => 'P1: Blvd. Los Próceres, Plaza Sur'],
            ['lat' => 13.679200, 'lng' => -89.226500, 'calle' => 'P2: Lomas de San Francisco, Calle 3'],
            ['lat' => 13.675800, 'lng' => -89.236400, 'calle' => 'P3: Redondel El Platillo, Antiguo Cuscatlán'],
            ['lat' => 13.672500, 'lng' => -89.244800, 'calle' => 'P4: Blvd. Santa Elena, Embajada'],
            ['lat' => 13.670100, 'lng' => -89.252100, 'calle' => 'P5: Calle Conchagua Poniente, Santa Elena'],
            ['lat' => 13.668900, 'lng' => -89.255400, 'calle' => 'P6: Punto de entrega Plaza Madero / Santa Elena']
        ]
    ],
    [
        'nombre' => 'Ruta 3 · Colonia Médica - Miramonte - Constitución',
        'puntos' => [
            ['lat' => 13.708800, 'lng' => -89.205400, 'calle' => 'P1: Av. Dr. Emilio Álvarez, Col. Médica'],
            ['lat' => 13.707600, 'lng' => -89.210200, 'calle' => 'P2: Diagonal Dr. Arturo Romero'],
            ['lat' => 13.710100, 'lng' => -89.215800, 'calle' => 'P3: Calle Los Sisimiles, Col. Miramonte'],
            ['lat' => 13.712400, 'lng' => -89.219500, 'calle' => 'P4: Av. Bernal y Calle Toluca'],
            ['lat' => 13.714800, 'lng' => -89.222900, 'calle' => 'P5: Redondel Integración / Blvd. Constitución'],
            ['lat' => 13.716500, 'lng' => -89.225100, 'calle' => 'P6: Punto de entrega Zona Constitución']
        ]
    ],
    [
        'nombre' => 'Ruta 4 · Santa Tecla - Paseo El Carmen - Merliot',
        'puntos' => [
            ['lat' => 13.673900, 'lng' => -89.286500, 'calle' => 'P1: Paseo El Carmen, Santa Tecla'],
            ['lat' => 13.675200, 'lng' => -89.279800, 'calle' => 'P2: Carretera Panamericana, El Cafetalón'],
            ['lat' => 13.676800, 'lng' => -89.272400, 'calle' => 'P3: Blvd. Merliot y Calle Chiltiupán'],
            ['lat' => 13.675900, 'lng' => -89.266500, 'calle' => 'P4: Jardines de la Hacienda, Calle Principal'],
            ['lat' => 13.674400, 'lng' => -89.261200, 'calle' => 'P5: Redondel Merliot / Plaza Merliot'],
            ['lat' => 13.673100, 'lng' => -89.257800, 'calle' => 'P6: Punto de entrega Residencial Ciudad Merliot']
        ]
    ],
    [
        'nombre' => 'Ruta 5 · Flor Blanca - Estadio Cuscatlán - Altamira',
        'puntos' => [
            ['lat' => 13.697800, 'lng' => -89.216500, 'calle' => 'P1: 45 Av. Sur, Col. Flor Blanca'],
            ['lat' => 13.693200, 'lng' => -89.218900, 'calle' => 'P2: 49 Av. Sur, Paso a Desnivel'],
            ['lat' => 13.688400, 'lng' => -89.221200, 'calle' => 'P3: Calle Antigua a Huizúcar, Estadio Cuscatlán'],
            ['lat' => 13.685100, 'lng' => -89.222500, 'calle' => 'P4: Lomas de Altamira, Acceso Principal'],
            ['lat' => 13.683200, 'lng' => -89.221100, 'calle' => 'P5: Blvd. Luis Poma, Altamira'],
            ['lat' => 13.681500, 'lng' => -89.219900, 'calle' => 'P6: Punto de entrega Residencial Altamira']
        ]
    ]
];

// 2. Obtener TODOS los repartidores activos (sin importar cuántos haya)
// 2. Obtener TODOS los repartidores activos (sin importar cuántos haya)
$sqlReps = "SELECT r.id AS repartidor_id, u.nombre AS repartidor_nombre, r.vehiculo
            FROM repartidores r
            INNER JOIN usuarios u ON r.usuario_id = u.id
            WHERE r.estado_disponibilidad != 'inactivo'";

if ($repartidorFiltro > 0) {
    $sqlReps .= " AND r.id = :rid";
}
$sqlReps .= " ORDER BY r.id ASC";

$stmtReps = $db->prepare($sqlReps);
$stmtReps->execute($repartidorFiltro > 0 ? [':rid' => $repartidorFiltro] : []);
$repartidores = $stmtReps->fetchAll(PDO::FETCH_ASSOC);

if (empty($repartidores)) {
    echo "[!] No hay repartidores activos disponibles para simular.\n";
    exit;
}

// 3. Asignar una de las 5 rutas a cada repartidor activo (rotación cíclica 1..5) y buscar si tiene pedido activo
$asignaciones = [];
$totalRutas = count($catalogoRutas);

foreach ($repartidores as $idx => $rep) {
    $rutaElegida = $catalogoRutas[$idx % $totalRutas];

    // Buscar si tiene un pedido activo (asignado o en_camino)
    $stmtPed = $db->prepare("SELECT id, codigo_seguimiento, cliente_direccion, estado 
                             FROM pedidos 
                             WHERE repartidor_id = :rid AND estado IN ('asignado', 'en_camino') 
                             ORDER BY id ASC LIMIT 1");
    $stmtPed->execute([':rid' => $rep['repartidor_id']]);
    $pedidoActivo = $stmtPed->fetch(PDO::FETCH_ASSOC);

    $asignaciones[] = [
        'repartidor' => $rep,
        'pedido'     => $pedidoActivo ?: null,
        'ruta'       => $rutaElegida
    ];

    $tagPedido = $pedidoActivo ? "Pedido {$pedidoActivo['codigo_seguimiento']}" : "Sin pedido (Recorrido preventivo)";
    echo " -> {$rep['repartidor_nombre']} (#{$rep['repartidor_id']}) asignado a [{$rutaElegida['nombre']}] | {$tagPedido}\n";
}

echo "\nIniciando transmisión de puntos GPS...\n\n";

function enviarTelemetriaGps($url, array $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 5
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'error' => $err];
    }
    return json_decode($resp, true) ?: ['ok' => false, 'error' => 'Respuesta inválida: ' . $resp];
}

// 4. Recorrer los 6 puntos marcados simultáneamente para todos los repartidores activos
$pasosTotales = 6;

for ($paso = 0; $paso < $pasosTotales; $paso++) {
    echo "=== Punto de Control " . ($paso + 1) . "/{$pasosTotales} [" . date('H:i:s') . "] ===\n";

    foreach ($asignaciones as $item) {
        $rep    = $item['repartidor'];
        $pedido = $item['pedido'];
        $punto  = $item['ruta']['puntos'][$paso];

        $estadoPedido = '';
        if ($pedido) {
            if ($paso === $pasosTotales - 1 && $marcarEntregadoAlFinal) {
                $estadoPedido = 'entregado';
            } else {
                $estadoPedido = 'en_camino';
            }
        }

        $payload = [
            'repartidor_id'    => (int)$rep['repartidor_id'],
            'pedido_id'        => $pedido ? (int)$pedido['id'] : null,
            'latitud'          => $punto['lat'],
            'longitud'         => $punto['lng'],
            'direccion_exacta' => $punto['calle'],
            'velocidad_kmh'    => ($paso === $pasosTotales - 1) ? 0 : rand(25, 48),
            'estado_pedido'    => $estadoPedido
        ];

        $res = enviarTelemetriaGps($endpointUrl, $payload);

        if (!empty($res['ok'])) {
            $codTxt = $pedido ? "[{$pedido['codigo_seguimiento']}]" : "[LIBRE]";
            echo sprintf(
                "[OK] %-18s %-12s | %.6f, %.6f | %s\n",
                $rep['repartidor_nombre'],
                $codTxt,
                $punto['lat'],
                $punto['lng'],
                $punto['calle']
            );
        } else {
            echo "[ERROR] {$rep['repartidor_nombre']}: " . ($res['error'] ?? 'Error desconocido') . "\n";
        }
    }

    echo "\n";
    if ($paso < $pasosTotales - 1) {
        sleep(2);
    }
}

echo "=================================================================\n";
echo " RECORRIDO COMPLETADO: Todos los puntos GPS fueron registrados.\n";
echo "=================================================================\n";