CREATE DATABASE IF NOT EXISTS delivery_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE delivery_db;

-- 1. Tabla de Usuarios (Administración y Autenticación)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'comercio', 'repartidor') NOT NULL DEFAULT 'comercio',
    estado TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Tabla de Comercios
CREATE TABLE comercios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre_comercial VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    direccion TEXT NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Tabla de Repartidores
CREATE TABLE repartidores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    vehiculo VARCHAR(50) NOT NULL,
    placa VARCHAR(20) NOT NULL,
    estado_disponibilidad ENUM('disponible', 'ocupado', 'inactivo') DEFAULT 'disponible',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Tabla de Pedidos / Transacciones
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_seguimiento VARCHAR(20) UNIQUE NOT NULL,
    comercio_id INT NOT NULL,
    repartidor_id INT NULL,
    cliente_nombre VARCHAR(100) NOT NULL,
    cliente_direccion TEXT NOT NULL,
    cliente_telefono VARCHAR(20) NOT NULL,
    monto_mando DECIMAL(10,2) NOT NULL,
    costo_envio DECIMAL(10,2) NOT NULL,
    estado ENUM('pendiente', 'asignado', 'en_camino', 'entregado', 'cancelado') DEFAULT 'pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comercio_id) REFERENCES comercios(id),
    FOREIGN KEY (repartidor_id) REFERENCES repartidores(id)
) ENGINE=InnoDB;

-- 5. Tabla de Liquidaciones Diarias
CREATE TABLE liquidaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repartidor_id INT NOT NULL,
    fecha DATE NOT NULL,
    total_envios INT NOT NULL,
    monto_recaudado DECIMAL(10,2) NOT NULL,
    pago_comision DECIMAL(10,2) NOT NULL,
    estado_pago ENUM('pendiente', 'liquidado') DEFAULT 'pendiente',
    FOREIGN KEY (repartidor_id) REFERENCES repartidores(id)
) ENGINE=InnoDB;    

CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pedido_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    leido TINYINT(1) DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE repartidores 
ADD COLUMN comercio_id INT NULL DEFAULT NULL AFTER usuario_id,
ADD CONSTRAINT fk_repartidor_comercio FOREIGN KEY (comercio_id) REFERENCES comercios(id) ON DELETE SET NULL;




2


-- 1. Tabla de Usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'comercio', 'repartidor') NOT NULL DEFAULT 'comercio',
    estado TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Tabla de Comercios
CREATE TABLE comercios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre_comercial VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    direccion TEXT NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Tabla de Repartidores
CREATE TABLE repartidores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    vehiculo VARCHAR(50) NOT NULL,
    placa VARCHAR(20) NOT NULL,
    estado_disponibilidad ENUM('disponible', 'ocupado', 'inactivo') DEFAULT 'disponible',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Tabla de Pedidos / Transacciones
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_seguimiento VARCHAR(20) UNIQUE NOT NULL,
    comercio_id INT NOT NULL,
    repartidor_id INT NULL,
    cliente_nombre VARCHAR(100) NOT NULL,
    cliente_direccion TEXT NOT NULL,
    cliente_telefono VARCHAR(20) NOT NULL,
    monto_mando DECIMAL(10,2) NOT NULL,
    costo_envio DECIMAL(10,2) NOT NULL,
    estado ENUM('pendiente', 'asignado', 'en_camino', 'entregado', 'cancelado') DEFAULT 'pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comercio_id) REFERENCES comercios(id),
    FOREIGN KEY (repartidor_id) REFERENCES repartidores(id)
) ENGINE=InnoDB;

-- 5. Tabla de Liquidaciones Diarias
CREATE TABLE liquidaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repartidor_id INT NOT NULL,
    fecha DATE NOT NULL,
    total_envios INT NOT NULL,
    monto_recaudado DECIMAL(10,2) NOT NULL,
    pago_comision DECIMAL(10,2) NOT NULL,
    estado_pago ENUM('pendiente', 'liquidado') DEFAULT 'pendiente',
    FOREIGN KEY (repartidor_id) REFERENCES repartidores(id)
) ENGINE=InnoDB;    

-- 6. Tabla de Notificaciones
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pedido_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    leido TINYINT(1) DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Modificación de la tabla repartidores (comercio_id)
ALTER TABLE repartidores 
ADD COLUMN comercio_id INT NULL DEFAULT NULL AFTER usuario_id,
ADD CONSTRAINT fk_repartidor_comercio FOREIGN KEY (comercio_id) REFERENCES comercios(id) ON DELETE SET NULL;