<?php
// config/config.php

// Detectar la URL base del servidor (ej: http://localhost:8000/)
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    define('BASE_URL', $protocol . $domainName . '/');
}

define('SITE_NAME', 'DeliveryAdmin');

// Variables para imágenes indexadas en línea (Placeholders/SVG/Iconos)
$ASSETS_IMAGES = [
    'logo' => 'https://cdn-icons-png.flaticon.com/512/2972/2972185.png',
    'avatar_placeholder' => 'https://cdn-icons-png.flaticon.com/512/847/847969.png',
    'icon_delivery' => 'https://cdn-icons-png.flaticon.com/512/1048/1048313.png',
    'icon_store' => 'https://cdn-icons-png.flaticon.com/512/869/869636.png'
];