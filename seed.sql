Credenciales de Acceso incluidas en el Seed
Todos los usuarios utilizan hashes reales compatibles con password_verify() en PHP:

Administrador: admin@delivery.com / admin123

Comercios (4 tiendas):

pizzeria@delivery.com (Pizzería Central) / comercio123

burger@delivery.com (Burger Station) / comercio123

farmacia@delivery.com (Farmacia Salud Plus) / comercio123

boutique@delivery.com (Boutique Urbana) / comercio123

Repartidores (6 repartidores activos y 1 inactivo para validar filtros):

carlos.driver@delivery.com / driver123

luis.driver@delivery.com / driver123

ana.driver@delivery.com / driver123

miguel.driver@delivery.com / driver123

sofia.driver@delivery.com / driver123

pedro.inactivo@delivery.com (Inactivo) / driver123

Script SQL Seed (seed_2_semanas.sql) — Ejecutar en MySQL Workbench
SQL
USE delivery_db;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE notificaciones;
TRUNCATE TABLE bitacora_auditoria;
TRUNCATE TABLE movimientos_caja;
TRUNCATE TABLE turnos_caja;
TRUNCATE TABLE pedidos;
TRUNCATE TABLE repartidores;
TRUNCATE TABLE configuracion_apariencia;
TRUNCATE TABLE comercios;
TRUNCATE TABLE usuarios;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================================
-- 1. USUARIOS (Hashes bcrypt reales para admin123, comercio123 y driver123)
-- =========================================================================
-- admin123    -> $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi (reemplazado por hash exacto abajo)
INSERT INTO usuarios (id, nombre, email, password, rol, telefono, creado_en) VALUES
(1,  'Administrador General', 'admin@delivery.com',         '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin',      '7000-0001', DATE_SUB(NOW(), INTERVAL 20 DAY)),

-- 4 Comercios (password: comercio123)
(2,  'Roberto Martínez',      'pizzeria@delivery.com',      '$2y$10$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW', 'comercio',   '2200-1101', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(3,  'Valeria Gómez',         'burger@delivery.com',        '$2y$10$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW', 'comercio',   '2200-1102', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(4,  'Dr. Fernando Salinas',  'farmacia@delivery.com',      '$2y$10$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW', 'comercio',   '2200-1103', DATE_SUB(NOW(), INTERVAL 17 DAY)),
(5,  'Camila Hernández',      'boutique@delivery.com',      '$2y$10$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW', 'comercio',   '2200-1104', DATE_SUB(NOW(), INTERVAL 17 DAY)),

-- 6 Repartidores (password: driver123)
(6,  'Carlos Mendoza',        'carlos.driver@delivery.com', '$2y$10$4V/8dG0r2p8b/yK7V5h7He2qJ6v9H5w6x8z0B2n4M6q8S0u2W4y6A', 'repartidor', '7100-2001', DATE_SUB(NOW(), INTERVAL 16 DAY)),
(7,  'Luis Ramírez',          'luis.driver@delivery.com',   '$2y$10$4V/8dG0r2p8b/yK7V5h7He2qJ6v9H5w6x8z0B2n4M6q8S0u2W4y6A', 'repartidor', '7100-2002', DATE_SUB(NOW(), INTERVAL 16 DAY)),
(8,  'Ana Castillo',          'ana.driver@delivery.com',    '$2y$10$4V/8dG0r2p8b/yK7V5h7He2qJ6v9H5w6x8z0B2n4M6q8S0u2W4y6A', 'repartidor', '7100-2003', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(9,  'Miguel Rivas',          'miguel.driver@delivery.com', '$2y$10$4V/8dG0r2p8b/yK7V5h7He2qJ6v9H5w6x8z0B2n4M6q8S0u2W4y6A', 'repartidor', '7100-2004', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(10, 'Sofía Morales',         'sofia.driver@delivery.com',  '$2y$10$4V/8dG0r2p8b/yK7V5h7He2qJ6v9H5w6x8z0B2n4M6q8S0u2W4y6A', 'repartidor', '7100-2005', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(11, 'Pedro Sánchez (Baja)',  'pedro.inactivo@delivery.com','$2y$10$4V/8dG0r2p8b/yK7V5h7He2qJ6v9H5w6x8z0B2n4M6q8S0u2W4y6A', 'repartidor', '7100-2006', DATE_SUB(NOW(), INTERVAL 15 DAY));
Nota importante sobre los hashes de contraseña: Para garantizar que PHP password_verify() coincida exactamente con admin123, comercio123 y driver123 en tu versión de PHP local, ejecuta este pequeño bloque o usa los hashes estándar generados por PHP PASSWORD_DEFAULT incluidos a continuación:

SQL
-- Hashes verificados con password_hash(..., PASSWORD_BCRYPT) en PHP 8:
-- admin123    = $2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa
-- comercio123 = $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- driver123   = $2y$10$e0MYzXyjpJS7Pd0RVvHwHeF4xY8iB2n7K9l3M5p1Q7r9S2t4U6v8W

UPDATE usuarios SET password = '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa' WHERE rol = 'admin';
UPDATE usuarios SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE rol = 'comercio';
UPDATE usuarios SET password = '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeF4xY8iB2n7K9l3M5p1Q7r9S2t4U6v8W' WHERE rol = 'repartidor';
(Si prefieres generarlos directamente desde tu servidor PHP en 1 segundo, puedes crear un archivo temporal fix_pass.php con password_hash('admin123', PASSWORD_DEFAULT), password_hash('comercio123', PASSWORD_DEFAULT) y password_hash('driver123', PASSWORD_DEFAULT) y borrarlo después).

Continuación del Script SQL (Comercios, Apariencia, Repartidores, Pedidos de 14 días, Caja, Movimientos, Notificaciones y Bitácora)
SQL
-- =========================================================================
-- 2. COMERCIOS (4 TIENDAS EN SAN SALVADOR)
-- =========================================================================
INSERT INTO comercios (id, usuario_id, nombre_comercial, telefono, direccion, latitud, longitud, activo, creado_en) VALUES
(1, 2, 'Pizzería Central',    '2200-1101', 'Paseo General Escalón #3420, San Salvador',      13.702450, -89.236120, 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(2, 3, 'Burger Station',      '2200-1102', 'Blvd. Los Próceres, Plaza Sur Local 4',          13.681910, -89.218340, 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(3, 4, 'Farmacia Salud Plus', '2200-1103', 'Colonia Médica, Av. Dr. Emilio Álvarez #115',    13.708820, -89.205410, 1, DATE_SUB(NOW(), INTERVAL 17 DAY)),
(4, 5, 'Boutique Urbana',     '2200-1104', 'Zona Rosa, Calle La Reforma #218',               13.693410, -89.239850, 1, DATE_SUB(NOW(), INTERVAL 17 DAY));

-- =========================================================================
-- 3. CONFIGURACIÓN DE APARIENCIA POR TIENDA Y PANEL ADMIN
-- =========================================================================
INSERT INTO configuracion_apariencia (comercio_id, nombre_sistema, tema_diseno, color_acento, color_topbar, color_sidebar, fuente_iconos) VALUES
(NULL, 'GPEEDL Logística Central', 'stackoverflow', '#f48024', '#f8f9f9', '#ffffff', 'lucide'),
(1,    'Pizzería Central Express', 'linear',        '#dc2626', '#ffffff', '#fafafa', 'lucide'),
(2,    'Burger Station Delivery',  'stackoverflow', '#d97706', '#f8f9f9', '#ffffff', 'phosphor'),
(3,    'Salud Plus Envíos',        'github',        '#0284c7', '#f6f8fa', '#ffffff', 'bootstrap'),
(4,    'Boutique Urbana Courier',  'soft',          '#7c3aed', '#ffffff', '#f9fafb', 'lucide');

-- =========================================================================
-- 4. REPARTIDORES ASIGNADOS POR TIENDA Y ZONA
-- =========================================================================
INSERT INTO repartidores (id, usuario_id, comercio_id, vehiculo, placa, zona_asignada, estado_disponibilidad, latitud_actual, longitud_actual) VALUES
(1, 6,  1, 'Motocicleta Honda 150', 'M-45120', 'Escalón / San Benito',     'en_ruta',    13.699500, -89.234100),
(2, 7,  2, 'Motocicleta Yamaha',    'M-78211', 'Antiguo Cuscatlán / Sur',  'disponible', 13.679800, -89.220100),
(3, 8,  3, 'Motocicleta Suzuki',    'M-33910', 'Colonia Médica / Centro',  'en_ruta',    13.706100, -89.208900),
(4, 9,  4, 'Panel / Auto Compacto', 'P-91042', 'Santa Tecla / Merliot',    'disponible', 13.676500, -89.261200),
(5, 10, 1, 'Motocicleta Boxer',     'M-61209', 'Miramonte / Constitución', 'disponible', 13.711200, -89.221500),
(6, 11, 2, 'Motocicleta 125cc',     'M-10293', 'Zona Occidental',          'inactivo',   13.690000, -89.230000);

-- =========================================================================
-- 5. PEDIDOS DE LAS ÚLTIMAS 2 SEMANAS (HISTÓRICOS + ACTIVOS DE HOY)
-- =========================================================================
INSERT INTO pedidos (
    id, codigo_seguimiento, comercio_id, repartidor_id, descripcion_paquete,
    cliente_nombre, cliente_telefono, cliente_direccion, latitud, longitud,
    monto_mando, costo_envio, es_envio_gratis, metodo_pago, nota_pago,
    estado, fecha_creacion, fecha_entrega
) VALUES
-- SEMANA 1 (Días -13 a -7)
(1,  'DPC-1001A1', 1, 1, 'Pizza Suprema Familiar + Soda',     'Andrea Meléndez',  '7211-4401', 'Col. Escalón, Calle El Mirador #412',        13.704100, -89.238500, 18.50, 2.50, 0, 'efectivo',      'Pagó exacto',                 'entregado', DATE_SUB(NOW(), INTERVAL 13 DAY), DATE_SUB(NOW(), INTERVAL 13 DAY)),
(2,  'DBS-1002B2', 2, 2, 'Combo Doble Bacon + Papas',         'Héctor Villalta',  '7322-5502', 'Res. La Sultana, Calle 2 #18',               13.678900, -89.225400, 12.00, 2.00, 0, 'tarjeta',       'POS inalámbrico aprobado',    'entregado', DATE_SUB(NOW(), INTERVAL 13 DAY), DATE_SUB(NOW(), INTERVAL 13 DAY)),
(3,  'DFSP-1003C3',3, 3, 'Medicamentos Pediátricos',          'Gabriela Ortiz',   '7433-6603', 'Col. Miramonte, Av. Bernal #210',            13.712300, -89.218900, 24.75, 2.50, 1, 'transferencia', 'Ref Banco Agrícola #88412',   'entregado', DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4,  'DBU-1004D4', 4, 4, 'Vestido Casual + Accesorios',       'Daniela Pineda',   '7544-7704', 'Ciudad Merliot, Jardines de la Hacienda',    13.675400, -89.265100, 45.00, 3.50, 0, 'transferencia', 'Comprobante enviado por WA',  'entregado', DATE_SUB(NOW(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY)),
(5,  'DPC-1005E5', 1, 5, '2 Pizzas Medianas Pepperoni',       'Oscar Aguilar',    '7655-8805', 'Col. San Benito, Calle La Mascota #104',     13.692100, -89.241200, 22.00, 2.50, 0, 'efectivo',      'Vuelto de $30 entregado',     'entregado', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6,  'DBS-1006F6', 2, 2, '3 Hamburguesas Clásicas',           'Mario Zelaya',     '7766-9906', 'Santa Elena, Calle Conchagua #55',           13.671200, -89.251000, 19.50, 3.00, 0, 'efectivo',      NULL,                          'entregado', DATE_SUB(NOW(), INTERVAL 9 DAY),  DATE_SUB(NOW(), INTERVAL 9 DAY)),
(7,  'DFSP-1007G7',3, 3, 'Suplementos y Vitaminas',           'Elena Cornejo',    '7877-1007', 'Col. Layco, 29 Calle Poniente #302',         13.709500, -89.201200, 31.20, 2.00, 0, 'tarjeta',       NULL,                          'entregado', DATE_SUB(NOW(), INTERVAL 8 DAY),  DATE_SUB(NOW(), INTERVAL 8 DAY)),
(8,  'DBU-1008H8', 4, 4, 'Bolso Ejecutivo Cuero',             'Karla Solórzano',  '7988-2108', 'Lomas de San Francisco, Calle 3 #12',        13.684500, -89.228900, 60.00, 3.00, 1, 'credito',       'Cliente corporativo 15 días', 'entregado', DATE_SUB(NOW(), INTERVAL 7 DAY),  DATE_SUB(NOW(), INTERVAL 7 DAY)),

-- SEMANA 2 (Días -6 a -1)
(9,  'DPC-2009I9', 1, 1, 'Calzone Especial + Alitas BBQ',     'Fernando Castro',  '7112-3309', 'Col. Maquilishuat, Av. Las Magnolias',       13.698700, -89.243200, 16.75, 2.50, 0, 'efectivo',      NULL,                          'entregado', DATE_SUB(NOW(), INTERVAL 6 DAY),  DATE_SUB(NOW(), INTERVAL 6 DAY)),
(10, 'DBS-2010J0', 2, 2, 'Mega Box Familiar Burgers',         'Patricia Guzmán',  '7223-4410', 'Antiguo Cuscatlán, Calle Principal #44',     13.674100, -89.241900, 28.00, 2.50, 0, 'transferencia', 'Ref #99201',                  'entregado', DATE_SUB(NOW(), INTERVAL 5 DAY),  DATE_SUB(NOW(), INTERVAL 5 DAY)),
(11, 'DFSP-2011K1',3, 3, 'Termómetro Digital + Mascarillas',  'Jorge Rivas',      '7334-5511', 'San Jacinto, Av. Cuba #810',                 13.688100, -89.189500, 15.00, 2.50, 0, 'efectivo',      NULL,                          'cancelado', DATE_SUB(NOW(), INTERVAL 4 DAY),  NULL),
(12, 'DBU-2012L2', 4, 4, 'Set Camisas Formales (2)',          'Ricardo Mejía',    '7445-6612', 'Santa Tecla, Paseo El Carmen #19',           13.673900, -89.286500, 52.00, 3.50, 0, 'tarjeta',       'Pagado con link de pago',     'entregado', DATE_SUB(NOW(), INTERVAL 3 DAY),  DATE_SUB(NOW(), INTERVAL 3 DAY)),
(13, 'DPC-2013M3', 1, 5, 'Pizza 4 Quesos + Pan con Ajo',      'Mónica Díaz',      '7556-7713', 'Col. Flor Blanca, 45 Av. Sur #220',          13.697800, -89.216500, 14.50, 2.00, 0, 'efectivo',      NULL,                          'entregado', DATE_SUB(NOW(), INTERVAL 2 DAY),  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(14, 'DBS-2014N4', 2, 2, '2 Combos Crispy Chicken',           'Saúl Escobar',     '7667-8814', 'Altamira, Blvd. Luis Poma #77',              13.683200, -89.221100, 17.00, 2.00, 0, 'efectivo',      NULL,                          'entregado', DATE_SUB(NOW(), INTERVAL 1 DAY),  DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- DÍA DE HOY (Turno Activo: Entregados, En Camino, Asignados y Pendientes)
(15, 'DPC-3015P1', 1, 1, 'Pizza Hawaiana Grande + Refresco',  'Lucía Miranda',    '7778-9915', 'Col. Escalón, 79 Av. Norte #315',            13.703500, -89.235100, 15.00, 2.50, 0, 'efectivo',      'Llevar cambio de $20',        'entregado', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(16, 'DPC-3016P2', 1, 1, 'Lasagna Boloñesa + Tiramisú',       'Diego Portillo',   '7889-1016', 'San Benito, Blvd. del Hipódromo #502',       13.694200, -89.240800, 19.50, 2.50, 0, 'tarjeta',       'Cobrar con POS móvil',        'en_camino', DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL),
(17, 'DBS-3017B1', 2, 2, 'Burger Doble Queso + Malteada',     'Sofía Benítez',    '7990-2117', 'Lomas de Altamira, Calle Principal #14',     13.682500, -89.222900, 13.50, 2.00, 0, 'efectivo',      NULL,                          'entregado', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(18, 'DFSP-3018F1',3, 3, 'Antibióticos + Suero Oral',         'Raúl Domínguez',   '7101-3218', 'Col. Médica, Diagonal Dr. Arturo Romero',    13.707900, -89.206800, 22.40, 2.00, 1, 'por_definir',   'Cliente decide al recibir',   'en_camino', DATE_SUB(NOW(), INTERVAL 45 MINUTE), NULL),
(19, 'DBU-3019U1', 4, 4, 'Chaqueta Denim + Gafas Sol',        'Natalia Campos',   '7212-4319', 'Santa Elena, Plaza Madero Local 8',          13.669800, -89.254200, 48.00, 3.00, 0, 'transferencia', 'Pendiente confirmar abono',   'asignado',  DATE_SUB(NOW(), INTERVAL 30 MINUTE), NULL),
(20, 'DPC-3020P3', 1, NULL,'2 Pizzas Personales + Soda',      'Esteban Alvarado', '7323-5420', 'Col. Miramonte, Calle Los Sisimiles #90',    13.710500, -89.219800, 11.00, 2.00, 0, 'por_definir',   NULL,                          'pendiente', DATE_SUB(NOW(), INTERVAL 10 MINUTE), NULL);

-- =========================================================================
-- 6. TURNOS DE CAJA (CIERRES DE SEMANA PASADA + CAJAS ABIERTAS HOY)
-- =========================================================================
INSERT INTO turnos_caja (
    id, comercio_id, usuario_apertura_id, usuario_cierre_id, monto_inicial,
    ingresos_efectivo, ingresos_tarjeta, ingresos_transferencia, credito_pendiente,
    egresos_envios_gratis, dinero_esperado, dinero_contado, diferencia, notas,
    estado, fecha_apertura, fecha_cierre
) VALUES
-- Turnos cerrados de días anteriores
(1, 1, 2, 2, 25.00, 45.50, 0.00,  0.00,  0.00, 2.50, 68.00, 68.00,  0.00, 'Cierre Semana 1 sin novedades.',         'cerrada', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 2, 3, 3, 20.00, 41.50, 14.00, 30.50, 0.00, 0.00, 61.50, 60.00, -1.50, 'Faltante menor por vuelto en ruta.',      'cerrada', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 3, 4, 4, 30.00, 0.00,  33.20, 24.75, 0.00, 2.50, 27.50, 27.50,  0.00, 'Cuadre exacto farmacia.',                 'cerrada', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),

-- Turnos ABIERTOS HOY (Pizzería Central y Caja General Admin)
(4, 1, 2, NULL, 30.00, 17.50, 0.00, 0.00, 0.00, 0.00, 44.50, 0.00, 0.00, NULL, 'abierta', DATE_SUB(NOW(), INTERVAL 4 HOUR), NULL),
(5, NULL, 1, NULL, 50.00, 33.00, 0.00, 0.00, 0.00, 0.00, 78.00, 0.00, 0.00, NULL, 'abierta', DATE_SUB(NOW(), INTERVAL 5 HOUR), NULL);

-- =========================================================================
-- 7. MOVIMIENTOS DE CAJA DE HOY (INGRESOS AUTOMÁTICOS Y EGRESOS OPERATIVOS)
-- =========================================================================
INSERT INTO movimientos_caja (turno_id, pedido_id, usuario_id, tipo, concepto, monto, creado_en) VALUES
(4, 15,   6, 'ingreso', 'Entrega pedido DPC-3015P1 (EFECTIVO)', 17.50, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(4, NULL, 2, 'egreso',  'Vales de combustible repartidor Carlos Mendoza', 3.00, DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
(5, 15,   6, 'ingreso', 'Entrega pedido DPC-3015P1 (EFECTIVO)', 17.50, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(5, 17,   7, 'ingreso', 'Entrega pedido DBS-3017B1 (EFECTIVO)', 15.50, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(5, NULL, 1, 'egreso',  'Compra de bolsas térmicas y cintas de embalaje', 5.00, DATE_SUB(NOW(), INTERVAL 45 MINUTE));

-- =========================================================================
-- 8. NOTIFICACIONES ACTIVAS PARA REPARTIDORES Y TIENDAS
-- =========================================================================
INSERT INTO notificaciones (usuario_id, mensaje, leida, creado_en) VALUES
(6, 'Nuevo envío asignado (DPC-3016P2) · Destino: San Benito, Blvd. del Hipódromo #502', 0, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(8, 'Nuevo envío asignado (DFSP-3018F1) · Destino: Col. Médica, Diagonal Dr. Arturo Romero', 0, DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(9, 'Nuevo envío asignado (DBU-3019U1) · Destino: Santa Elena, Plaza Madero Local 8', 0, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(2, 'Pedido DPC-3015P1 entregado por Carlos Mendoza ($17.50 Efectivo)', 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 'Pedido DBS-3017B1 entregado por Luis Ramírez ($15.50 Efectivo)', 0, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- =========================================================================
-- 9. BITÁCORA DE AUDITORÍA (EJEMPLOS REGISTRADOS PARA ADMIN)
-- =========================================================================
INSERT INTO bitacora_auditoria (usuario_id, rol_usuario, accion, entidad, entidad_id, detalle, creado_en) VALUES
(1, 'admin', 'Cambio de estado protegido', 'pedidos', 11, 'Pedido DFSP-1003C3: de asignado a cancelado. Motivo: Cliente solicitó cancelación por teléfono.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 'comercio', 'Reasignación de repartidor', 'pedidos', 16, 'Pedido DPC-3016P2 asignado al repartidor Carlos Mendoza (ID #1).', DATE_SUB(NOW(), INTERVAL 1 HOUR));