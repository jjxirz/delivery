CREATE DATABASE IF NOT EXISTS delivery_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE delivery_db;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================================
-- 1. USUARIOS DEL SISTEMA
-- =========================================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'comercio', 'repartidor') NOT NULL DEFAULT 'comercio',
    telefono VARCHAR(20) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 2. COMERCIOS / TIENDAS AFILIADAS
-- =========================================================================
CREATE TABLE IF NOT EXISTS comercios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre_comercial VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) NULL,
    direccion TEXT NULL,
    latitud DECIMAL(10, 6) DEFAULT 13.701200,
    longitud DECIMAL(10, 6) DEFAULT -89.224400,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comercios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 3. REPARTIDORES / FLOTA DE ENTREGA
-- =========================================================================
CREATE TABLE IF NOT EXISTS repartidores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    comercio_id INT NULL,
    vehiculo VARCHAR(80) NOT NULL DEFAULT 'Motocicleta',
    placa VARCHAR(30) NULL,
    zona_asignada VARCHAR(120) NULL DEFAULT 'Zona General',
    estado_disponibilidad ENUM('disponible', 'en_ruta', 'inactivo') NOT NULL DEFAULT 'disponible',
    latitud_actual DECIMAL(10, 6) DEFAULT 13.701200,
    longitud_actual DECIMAL(10, 6) DEFAULT -89.224400,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_repartidores_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_repartidores_comercio FOREIGN KEY (comercio_id) REFERENCES comercios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 4. PEDIDOS Y ENVÍOS
-- =========================================================================
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_seguimiento VARCHAR(30) NOT NULL UNIQUE,
    comercio_id INT NOT NULL,
    repartidor_id INT NULL,
    descripcion_paquete VARCHAR(255) NOT NULL DEFAULT 'Paquete estándar',
    cliente_nombre VARCHAR(120) NOT NULL,
    cliente_telefono VARCHAR(20) NOT NULL,
    cliente_direccion TEXT NOT NULL,
    latitud DECIMAL(10, 6) DEFAULT 13.701200,
    longitud DECIMAL(10, 6) DEFAULT -89.224400,
    monto_mando DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    costo_envio DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    es_envio_gratis TINYINT(1) NOT NULL DEFAULT 0,
    metodo_pago ENUM('por_definir', 'efectivo', 'tarjeta', 'transferencia', 'credito') NOT NULL DEFAULT 'por_definir',
    nota_pago TEXT NULL,
    estado ENUM('pendiente', 'asignado', 'en_camino', 'entregado', 'cancelado') NOT NULL DEFAULT 'pendiente',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_entrega DATETIME NULL,
    CONSTRAINT fk_pedidos_comercio FOREIGN KEY (comercio_id) REFERENCES comercios(id) ON DELETE CASCADE,
    CONSTRAINT fk_pedidos_repartidor FOREIGN KEY (repartidor_id) REFERENCES repartidores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 5. TURNOS DE CAJA Y LIQUIDACIONES DIARIAS
-- =========================================================================
CREATE TABLE IF NOT EXISTS turnos_caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comercio_id INT NULL,
    usuario_apertura_id INT NOT NULL,
    usuario_cierre_id INT NULL,
    monto_inicial DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    ingresos_efectivo DECIMAL(10, 2) NULL DEFAULT 0.00,
    ingresos_tarjeta DECIMAL(10, 2) NULL DEFAULT 0.00,
    ingresos_transferencia DECIMAL(10, 2) NULL DEFAULT 0.00,
    credito_pendiente DECIMAL(10, 2) NULL DEFAULT 0.00,
    egresos_envios_gratis DECIMAL(10, 2) NULL DEFAULT 0.00,
    dinero_esperado DECIMAL(10, 2) NULL DEFAULT 0.00,
    dinero_contado DECIMAL(10, 2) NULL DEFAULT 0.00,
    diferencia DECIMAL(10, 2) NULL DEFAULT 0.00,
    notas TEXT NULL,
    estado ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
    fecha_apertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME NULL,
    CONSTRAINT fk_turnos_comercio FOREIGN KEY (comercio_id) REFERENCES comercios(id) ON DELETE CASCADE,
    CONSTRAINT fk_turnos_apertura FOREIGN KEY (usuario_apertura_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_turnos_cierre FOREIGN KEY (usuario_cierre_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 6. MOVIMIENTOS DE CAJA (INGRESOS AUTOMÁTICOS Y EGRESOS MANUALES)
-- =========================================================================
CREATE TABLE IF NOT EXISTS movimientos_caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turno_id INT NOT NULL,
    pedido_id INT NULL,
    usuario_id INT NOT NULL,
    tipo ENUM('ingreso', 'egreso') NOT NULL DEFAULT 'egreso',
    concepto VARCHAR(255) NOT NULL,
    monto DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_turno FOREIGN KEY (turno_id) REFERENCES turnos_caja(id) ON DELETE CASCADE,
    CONSTRAINT fk_mov_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE SET NULL,
    CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 7. CONFIGURACIÓN DE APARIENCIA INDEPENDIENTE POR TIENDA / ADMIN
-- =========================================================================
CREATE TABLE IF NOT EXISTS configuracion_apariencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comercio_id INT NULL,
    nombre_sistema VARCHAR(120) NOT NULL DEFAULT 'Delivery Local',
    logo_path VARCHAR(255) NULL,
    tema_diseno ENUM('stackoverflow', 'github', 'linear', 'soft') NOT NULL DEFAULT 'stackoverflow',
    color_acento VARCHAR(20) NOT NULL DEFAULT '#f48024',
    color_topbar VARCHAR(20) NOT NULL DEFAULT '#f8f9f9',
    color_sidebar VARCHAR(20) NOT NULL DEFAULT '#ffffff',
    fuente_iconos ENUM('lucide', 'phosphor', 'bootstrap', 'emoji') NOT NULL DEFAULT 'lucide',
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_apariencia_comercio (comercio_id),
    CONSTRAINT fk_apariencia_comercio FOREIGN KEY (comercio_id) REFERENCES comercios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 8. BITÁCORA DE AUDITORÍA (SOLO VISIBLE PARA ADMINISTRADOR)
-- =========================================================================
CREATE TABLE IF NOT EXISTS bitacora_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    rol_usuario VARCHAR(30) NOT NULL,
    accion VARCHAR(100) NOT NULL,
    entidad VARCHAR(50) NOT NULL,
    entidad_id INT NULL,
    detalle TEXT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 9. NOTIFICACIONES EN TIEMPO REAL POR USUARIO
-- =========================================================================
CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    mensaje VARCHAR(255) NOT NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 10. REGISTRO BASE DEL PANEL ADMINISTRADOR (APARIENCIA GLOBAL)
-- =========================================================================
INSERT INTO configuracion_apariencia (comercio_id, nombre_sistema, tema_diseno, color_acento, color_topbar, color_sidebar, fuente_iconos)
SELECT NULL, 'Delivery Local', 'stackoverflow', '#f48024', '#f8f9f9', '#ffffff', 'lucide'
WHERE NOT EXISTS (SELECT 1 FROM configuracion_apariencia WHERE comercio_id IS NULL);

SET FOREIGN_KEY_CHECKS = 1;