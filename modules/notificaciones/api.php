<?php
require_once __DIR__ . '/../../config/config.php';
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
echo json_encode($notificaciones);