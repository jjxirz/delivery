<?php
require_once __DIR__ . '/database.php';

class Apariencia {
    private static $cachePorComercio = [];

    /**
     * Determina el comercio_id activo según la sesión del usuario:
     * - Admin: NULL (Panel Administrador)
     * - Comercio: su comercio_id
     * - Repartidor: el comercio_id al que está afiliado (o NULL si es flota general)
     */
    public static function resolverComercioSesion() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol'])) {
            return null;
        }

        $rol = $_SESSION['usuario_rol'];
        $uid = (int)$_SESSION['usuario_id'];

        if ($rol === 'admin') {
            return null;
        }

        try {
            $db = Database::getConnection();
            if ($rol === 'comercio') {
                $stmt = $db->prepare("SELECT id FROM comercios WHERE usuario_id = :uid LIMIT 1");
                $stmt->execute([':uid' => $uid]);
                $cid = $stmt->fetchColumn();
                return $cid ? (int)$cid : null;
            }
            if ($rol === 'repartidor') {
                $stmt = $db->prepare("SELECT comercio_id FROM repartidores WHERE usuario_id = :uid LIMIT 1");
                $stmt->execute([':uid' => $uid]);
                $cid = $stmt->fetchColumn();
                return $cid ? (int)$cid : null;
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Obtiene la apariencia para el usuario actual (o para un $comercio_id específico en el editor)
     */
    public static function obtener($comercio_id_forzado = false) {
        $cid = ($comercio_id_forzado === false)
            ? self::resolverComercioSesion()
            : ($comercio_id_forzado !== null ? (int)$comercio_id_forzado : null);

        $cacheKey = $cid === null ? 'admin' : 'com_' . $cid;
        if (isset(self::$cachePorComercio[$cacheKey])) {
            return self::$cachePorComercio[$cacheKey];
        }

        $defaults = [
            'comercio_id'    => $cid,
            'nombre_sistema' => defined('SITE_NAME') ? SITE_NAME : 'Delivery Local',
            'logo_path'      => null,
            'tema_diseno'    => 'stackoverflow',
            'color_acento'   => '#f48024',
            'color_topbar'   => '#f8f9f9',
            'color_sidebar'  => '#ffffff',
            'fuente_iconos'  => 'lucide'
        ];

        try {
            $db = Database::getConnection();
            $row = false;

            if ($cid !== null) {
                $stmt = $db->prepare("SELECT * FROM configuracion_apariencia WHERE comercio_id = :cid LIMIT 1");
                $stmt->execute([':cid' => $cid]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                // Si la tienda aún no tiene configuración propia, heredar nombre comercial por defecto
                if (!$row) {
                    $stmtNom = $db->prepare("SELECT nombre_comercial FROM comercios WHERE id = :cid LIMIT 1");
                    $stmtNom->execute([':cid' => $cid]);
                    $nomTienda = $stmtNom->fetchColumn();
                    if ($nomTienda) {
                        $defaults['nombre_sistema'] = $nomTienda;
                    }
                }
            } else {
                $stmt = $db->query("SELECT * FROM configuracion_apariencia WHERE comercio_id IS NULL LIMIT 1");
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            }

            self::$cachePorComercio[$cacheKey] = $row ? array_merge($defaults, $row) : $defaults;
        } catch (Exception $e) {
            self::$cachePorComercio[$cacheKey] = $defaults;
        }

        return self::$cachePorComercio[$cacheKey];
    }

    public static function logoUrl($fallback, $comercio_id_forzado = false) {
        $cfg = self::obtener($comercio_id_forzado);
        if (!empty($cfg['logo_path'])) {
            return BASE_URL . ltrim($cfg['logo_path'], '/');
        }
        return $fallback;
    }

    public static function icono($clave) {
        $fuente = self::obtener()['fuente_iconos'];

        $mapa = [
            'inicio' => [
                'emoji'     => '📊',
                'lucide'    => '<i data-lucide="layout-dashboard" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-squares-four ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-speedometer2 ui-icon"></i>'
            ],
            'pedidos' => [
                'emoji'     => '📦',
                'lucide'    => '<i data-lucide="package" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-package ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-box-seam ui-icon"></i>'
            ],
            'comercios' => [
                'emoji'     => '🏪',
                'lucide'    => '<i data-lucide="store" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-storefront ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-shop ui-icon"></i>'
            ],
            'repartidores' => [
                'emoji'     => '🛵',
                'lucide'    => '<i data-lucide="bike" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-moped ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-bicycle ui-icon"></i>'
            ],
            'caja' => [
                'emoji'     => '💵',
                'lucide'    => '<i data-lucide="wallet" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-cash-register ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-cash-coin ui-icon"></i>'
            ],
            'liquidaciones' => [
                'emoji'     => '📄',
                'lucide'    => '<i data-lucide="file-spreadsheet" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-file-text ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-file-earmark-bar-graph ui-icon"></i>'
            ],
            'apariencia' => [
                'emoji'     => '🎨',
                'lucide'    => '<i data-lucide="palette" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-paint-brush ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-palette ui-icon"></i>'
            ],
            'notificaciones' => [
                'emoji'     => '🔔',
                'lucide'    => '<i data-lucide="bell" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-bell ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-bell ui-icon"></i>'
            ],
            'usuario' => [
                'emoji'     => '👤',
                'lucide'    => '<i data-lucide="user" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-user ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-person ui-icon"></i>'
            ],
            'telefono' => [
                'emoji'     => '📞',
                'lucide'    => '<i data-lucide="phone" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-phone ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-telephone ui-icon"></i>'
            ],
            'ubicacion' => [
                'emoji'     => '📍',
                'lucide'    => '<i data-lucide="map-pin" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-map-pin ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-geo-alt ui-icon"></i>'
            ],
            'ruta' => [
                'emoji'     => '🗺️',
                'lucide'    => '<i data-lucide="map" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-map-trifold ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-map ui-icon"></i>'
            ],
            'reasignar' => [
                'emoji'     => '🔄',
                'lucide'    => '<i data-lucide="refresh-cw" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-arrows-clockwise ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-arrow-repeat ui-icon"></i>'
            ],
            'comentario' => [
                'emoji'     => '💬',
                'lucide'    => '<i data-lucide="message-square" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-chat-text ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-chat-left-text ui-icon"></i>'
            ],
            'pdf' => [
                'emoji'     => '📄',
                'lucide'    => '<i data-lucide="file-text" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-file-pdf ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-file-earmark-pdf ui-icon"></i>'
            ],
            'eliminar' => [
                'emoji'     => '🗑️',
                'lucide'    => '<i data-lucide="trash-2" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-trash ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-trash ui-icon"></i>'
            ],
            'identidad' => [
                'emoji'     => '🏷️',
                'lucide'    => '<i data-lucide="badge-check" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-identification-badge ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-patch-check ui-icon"></i>'
            ],
            'egreso' => [
                'emoji'     => '💸',
                'lucide'    => '<i data-lucide="arrow-up-right" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-arrow-circle-up-right ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-dash-circle ui-icon"></i>'
            ],
            'detalle' => [
                'emoji'     => '👁️',
                'lucide'    => '<i data-lucide="eye" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-eye ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-eye ui-icon"></i>'
            ],
            'confirmar' => [
                'emoji'     => '✅',
                'lucide'    => '<i data-lucide="circle-check" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-check-circle ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-check-circle ui-icon"></i>'
            ],
            'cancelar' => [
                'emoji'     => '❌',
                'lucide'    => '<i data-lucide="circle-x" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-x-circle ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-x-circle ui-icon"></i>'
            ],
            'tarjeta' => [
                'emoji'     => '💳',
                'lucide'    => '<i data-lucide="credit-card" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-credit-card ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-credit-card ui-icon"></i>'
            ],
            'banco' => [
                'emoji'     => '🏦',
                'lucide'    => '<i data-lucide="landmark" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-bank ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-bank ui-icon"></i>'
            ],
            'reloj' => [
                'emoji'     => '⏳',
                'lucide'    => '<i data-lucide="clock" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-clock ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-clock ui-icon"></i>'
            ],
            'editar' => [
                'emoji'     => '✎',
                'lucide'    => '<i data-lucide="pencil" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-pencil-simple ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-pencil ui-icon"></i>'
            ],
            'bitacora' => [
                'emoji'     => '🛡️',
                'lucide'    => '<i data-lucide="shield-alert" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-shield-check ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-shield-lock ui-icon"></i>'
            ],
            'auditoria' => [
                'emoji'     => '📋',
                'lucide'    => '<i data-lucide="clipboard-check" class="ui-icon"></i>',
                'phosphor'  => '<i class="ph ph-clipboard-text ui-icon"></i>',
                'bootstrap' => '<i class="bi bi-clipboard-check ui-icon"></i>'
            ]
        ];

        return $mapa[$clave][$fuente] ?? $mapa[$clave]['emoji'] ?? '';
    }

    public static function iconoNotificacion($mensaje) {
        $mensaje = (string)$mensaje;
        $txt = function_exists('mb_strtolower')
            ? mb_strtolower($mensaje, 'UTF-8')
            : strtolower($mensaje);

        if (strpos($txt, 'entregado') !== false || strpos($txt, 'liquidaci') !== false) {
            return self::icono('auditoria');
        }
        if (strpos($txt, 'cancelado') !== false || strpos($txt, 'eliminad') !== false) {
            return self::icono('eliminar');
        }
        if (strpos($txt, 'asignado') !== false || strpos($txt, 'ruta') !== false || strpos($txt, 'camino') !== false) {
            return self::icono('repartidores');
        }
        if (strpos($txt, 'pago') !== false || strpos($txt, 'caja') !== false || strpos($txt, 'efectivo') !== false || strpos($txt, '$') !== false) {
            return self::icono('caja');
        }
        if (strpos($txt, 'nota') !== false || strpos($txt, 'comentario') !== false) {
            return self::icono('comentario');
        }

        return self::icono('pedidos');
    }

    public static function estilosDinamicos() {
        $cfg = self::obtener();
        $acento  = htmlspecialchars($cfg['color_acento']);
        $topbar  = htmlspecialchars($cfg['color_topbar']);
        $sidebar = htmlspecialchars($cfg['color_sidebar']);
        $tema    = $cfg['tema_diseno'];

        $radius = '3px';
        $borderColor = '#d6d9dc';
        $bodyBg = '#ffffff';
        $topbarBorderTop = "3px solid {$acento}";
        $activeStyle = "background-color: #f1f2f3; border-right: 3px solid {$acento}; color: #0c0d0e; font-weight: 700;";

        if ($tema === 'github') {
            $radius = '6px';
            $borderColor = '#d0d7de';
            $bodyBg = '#ffffff';
            $topbarBorderTop = "1px solid {$borderColor}";
            $activeStyle = "background-color: #f3f4f6; border: 1px solid {$borderColor}; border-radius: 6px; margin: 0 8px; color: #1f2328; font-weight: 600;";
        } elseif ($tema === 'linear') {
            $radius = '4px';
            $borderColor = '#e2e8f0';
            $bodyBg = '#fcfcfd';
            $topbarBorderTop = "2px solid {$acento}";
            $activeStyle = "background-color: rgba(0,0,0,0.04); border-right: 2px solid {$acento}; color: {$acento}; font-weight: 600;";
        } elseif ($tema === 'soft') {
            $radius = '8px';
            $borderColor = '#e5e7eb';
            $bodyBg = '#f9fafb';
            $topbarBorderTop = "none";
            $activeStyle = "background-color: {$acento}; color: #ffffff !important; border-right: none; border-radius: 6px; margin: 2px 10px; font-weight: 600;";
        }

        return "
        <style>
            :root {
                --so-orange: {$acento};
                --so-gray-border: {$borderColor};
                --ui-radius: {$radius};
                --ui-topbar-bg: {$topbar};
                --ui-sidebar-bg: {$sidebar};
            }
            body { background-color: {$bodyBg} !important; }
            .so-topbar {
                background-color: var(--ui-topbar-bg) !important;
                border-top: {$topbarBorderTop} !important;
                border-bottom: 1px solid var(--so-gray-border) !important;
            }
            .so-sidebar {
                background-color: var(--ui-sidebar-bg) !important;
                border-right: 1px solid var(--so-gray-border) !important;
            }
            .so-main-content { background-color: {$bodyBg} !important; }
            .so-nav-link.active { {$activeStyle} }
            .card, .so-panel, .so-kpi-box, .btn, .form-control { border-radius: var(--ui-radius) !important; }
            .btn-primary {
                background-color: var(--so-orange) !important;
                border-color: var(--so-orange) !important;
            }
            .ui-icon {
                width: 16px;
                height: 16px;
                font-size: 16px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                vertical-align: middle;
                flex-shrink: 0;
            }
        </style>";
    }

    public static function formatearEstado($estado) {
        $mapa = [
            'pendiente' => 'Pendiente',
            'asignado'  => 'Asignado',
            'en_camino' => 'En Camino',
            'entregado' => 'Entregado',
            'cancelado' => 'Cancelado'
        ];
        return $mapa[$estado] ?? ucfirst(str_replace('_', ' ', (string)$estado));
    }

    public static function registrarBitacora($accion, $entidad, $entidad_id, $detalle) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['usuario_id'])) {
            return;
        }
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO bitacora_auditoria (usuario_id, rol_usuario, accion, entidad, entidad_id, detalle, creado_en)
                                  VALUES (:uid, :rol, :acc, :ent, :eid, :det, NOW())");
            $stmt->execute([
                ':uid' => (int)$_SESSION['usuario_id'],
                ':rol' => $_SESSION['usuario_rol'] ?? 'sistema',
                ':acc' => $accion,
                ':ent' => $entidad,
                ':eid' => $entidad_id ? (int)$entidad_id : null,
                ':det' => $detalle
            ]);
        } catch (Exception $e) {
            error_log("[Bitacora] " . $e->getMessage());
        }
    }

}