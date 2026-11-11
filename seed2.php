INSERT INTO usuarios (nombre, email, password, rol) VALUES 
('Admin General', 'admin@delivery.local', 'admin123', 'admin'),
('Comercio Central', 'pizza@delivery.local', 'comercio123', 'comercio'),
('Juan Repartidor', 'repartidor@delivery.local', 'driver123', 'repartidor');

INSERT INTO comercios (usuario_id, nombre_comercial, telefono, direccion) VALUES 
(2, 'Pizzería Don Luigi', '7777-8888', 'Calle Principal #123, Centro');

INSERT INTO repartidores (usuario_id, comercio_id, vehiculo, placa, estado_disponibilidad) VALUES 
(3, 1, 'Motocicleta', 'M-123456', 'disponible');

INSERT INTO pedidos (codigo_seguimiento, comercio_id, repartidor_id, cliente_nombre, cliente_direccion, cliente_telefono, monto_mando, costo_envio, estado) VALUES 
('TRK-001', 1, 1, 'María López', 'Av. Las Flores #45', '6666-9999', 25.50, 3.00, 'en_camino');

INSERT INTO liquidaciones (repartidor_id, fecha, total_envios, monto_recaudado, pago_comision, estado_pago) VALUES 
(1, CURDATE(), 1, 3.00, 2.00, 'pendiente');

INSERT INTO notificaciones (usuario_id, pedido_id, mensaje) VALUES 
(2, 1, 'Tu pedido TRK-001 ha sido asignado y está en camino.');