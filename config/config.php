<?php
// config/config.php

// Detectar la URL base del servidor (ej: http://localhost:8000/)
if (!defined('BASE_URL')) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $protocol = $https ? 'https://' : 'http://';
    $domainName = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
    define('BASE_URL', $protocol . $domainName . '/');
}

define('SITE_NAME', 'Delivery');

// Variables para imágenes indexadas en línea (Placeholders/SVG/Iconos)
$ASSETS_IMAGES = [
    'logo' => 'https://cdn-icons-png.flaticon.com/512/2972/2972185.png',
    'avatar_placeholder' => 'https://cdn-icons-png.flaticon.com/512/847/847969.png',
    'icon_delivery' => 'https://cdn-icons-png.flaticon.com/512/1048/1048313.png',
    'icon_store' => 'https://cdn-icons-png.flaticon.com/512/869/869636.png'
];