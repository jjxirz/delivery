<?php
require_once __DIR__ . '/../../config/database.php';

class Auth {
    
    public static function login($email, $password) {
        $db = Database::getConnection();

        // Sanitización previa del correo
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "El formato del correo electrónico es inválido.";
        }

        // Consulta Preparada: Previene SQL Injection
        $stmt = $db->prepare("SELECT id, nombre, email, password, rol, estado FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password'])) {
            if ($usuario['estado'] != 1) {
                return "Usuario inactivo. Contacte al administrador.";
            }

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_rol'] = $usuario['rol'];

            return true;
        }

        return "Credenciales incorrectas.";
    }

    public static function verificarRol(array $rolesPermitidos) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['usuario_rol']) || !in_array($_SESSION['usuario_rol'], $rolesPermitidos)) {
            header("HTTP/1.1 403 Forbidden");
            echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
                    <h2>403 - Acceso No Autorizado</h2>
                    <p>No tienes permisos suficientes para consultar este módulo.</p>
                    <a href='" . BASE_URL . "index.php'>Volver al inicio</a>
                  </div>";
            exit;
        }
    }
}