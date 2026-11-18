<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/apariencia.php';
require_once __DIR__ . '/Notificacion.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([]);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'marcar_leidas') {
    Notificacion::marcarComoLeidas($usuario_id);
    echo json_encode(['status' => 'ok']);
    exit;
}

$notificaciones = Notificacion::obtenerNoLeidas($usuario_id);
foreach ($notificaciones as &$notificacion) {
    $mensaje = (string)($notificacion['mensaje'] ?? '');
    $notificacion['icono'] = Apariencia::iconoNotificacion($mensaje);
    $notificacion['cancelada'] = stripos($mensaje, 'cancelado') !== false;
}
unset($notificacion);

echo json_encode($notificaciones);