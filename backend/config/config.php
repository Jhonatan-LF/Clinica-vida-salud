<?php
/**
 * Configuración general del sistema.
 *
 * Para cambiar la conexión SIN tocar este archivo, cree
 * backend/config/config.local.php (no se sube a GitHub) devolviendo solo las
 * claves que quiera reemplazar, por ejemplo:
 *
 *   <?php return ['bd' => ['clave' => 'mi_clave_de_postgres']];
 */
$config = [
    'app' => [
        'nombre'       => 'Clínica Vida Salud',
        'zona_horaria' => 'America/La_Paz',
        // true solo en desarrollo: muestra el detalle técnico de los errores.
        'depuracion'   => false,
    ],

    // Conexión PDO con el controlador pdo_pgsql (requerimiento técnico 7)
    'bd' => [
        'host'    => getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1'),
        'puerto'  => (int)(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 5432)),
        'nombre'  => getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'clinica_vida_salud'),
        'usuario' => getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'postgres'),
        'clave'   => getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? 'postgres'),
        'sslmode' => getenv('DB_SSLMODE') ?: ($_ENV['DB_SSLMODE'] ?? null),
    ],

    'sesion' => [
        'nombre'              => 'VIDASALUD_SESION',
        'minutos_inactividad' => 30,   // la sesión se cierra tras 30 min sin uso
    ],

    'seguridad' => [
        'max_intentos'        => 3,    // intentos fallidos antes de bloquear (RF01)
        'minutos_bloqueo_base'=> 1,    // 1er bloqueo: 1 min, 2do: 2 min, sucesivos: 4, 8, etc.
    ],

    'citas' => [
        'dias_maximos_reserva'          => 60, // hasta cuántos días adelante se reserva
        'horas_minimas_cancelar_portal' => 2,  // el paciente cancela con 2 h de anticipación
    ],

    // Configuración para el envío de correos (recuperación de contraseña, notificaciones)
    // Puede usar 'smtp' (Gmail, Resend, Brevo, Mailtrap, etc.) o 'mail' (PHP nativo)
    'correo' => [
        'driver'      => getenv('MAIL_DRIVER') ?: ($_ENV['MAIL_DRIVER'] ?? 'smtp'),
        'host'        => getenv('MAIL_HOST') ?: ($_ENV['MAIL_HOST'] ?? 'smtp.gmail.com'),
        'puerto'      => (int) (getenv('MAIL_PORT') ?: ($_ENV['MAIL_PORT'] ?? 587)),
        'seguridad'   => getenv('MAIL_ENCRYPTION') ?: ($_ENV['MAIL_ENCRYPTION'] ?? 'tls'), // 'tls' o 'ssl'
        'usuario'     => getenv('MAIL_USERNAME') ?: ($_ENV['MAIL_USERNAME'] ?? ''),
        'clave'       => getenv('MAIL_PASSWORD') ?: ($_ENV['MAIL_PASSWORD'] ?? ''),
        'remitente'   => getenv('MAIL_FROM_ADDRESS') ?: ($_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@clinicavidasalud.com'),
        'nombre_from' => getenv('MAIL_FROM_NAME') ?: ($_ENV['MAIL_FROM_NAME'] ?? 'Clínica Vida Salud'),
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
