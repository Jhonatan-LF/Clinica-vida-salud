<?php
namespace App\Nucleo;

/**
 * Cliente de correo electrónico robusto y autónomo (sin dependencias externas).
 * Soporta SMTP autenticado con TLS/SSL (Gmail, Outlook, Brevo, Mailtrap, etc.)
 * y fallback con copia local en backend/storage/correos/ para trazabilidad.
 */
final class Correo
{
    public static function enviar(string $para, string $asunto, string $mensajeHtml, string $mensajeTexto = ''): bool
    {
        $driver    = (string) Aplicacion::config('correo.driver', 'smtp');
        $remitente = (string) Aplicacion::config('correo.remitente', 'no-reply@clinicavidasalud.com');
        $nombreDe  = (string) Aplicacion::config('correo.nombre_from', 'Clínica Vida Salud');

        // Siempre dejamos una copia local para auditoría y desarrollo
        self::guardarCopiaLocal($para, $asunto, $mensajeHtml);

        if ($driver === 'smtp') {
            $host   = (string) Aplicacion::config('correo.host', '');
            $user   = (string) Aplicacion::config('correo.usuario', '');
            $pass   = (string) Aplicacion::config('correo.clave', '');
            $puerto = (int) Aplicacion::config('correo.puerto', 587);
            $cifrado= (string) Aplicacion::config('correo.seguridad', 'tls');

            // Si hay credenciales SMTP configuradas, enviamos vía socket SMTP
            if ($host !== '' && $user !== '' && $pass !== '') {
                try {
                    return self::enviarPorSmtp($host, $puerto, $cifrado, $user, $pass, $remitente, $nombreDe, $para, $asunto, $mensajeHtml);
                } catch (\Throwable $e) {
                    error_log('Error enviando correo SMTP: ' . $e->getMessage());
                }
            }
        }

        // Fallback a mail() de PHP
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($nombreDe) . "?= <{$remitente}>\r\n";
        $headers .= "X-Mailer: ClinicaVidaSalud/1.0\r\n";

        try {
            return @mail($para, "=?UTF-8?B?" . base64_encode($asunto) . "?=", $mensajeHtml, $headers);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Envío directo vía socket SMTP con soporte STARTTLS y AUTH LOGIN.
     */
    private static function enviarPorSmtp(
        string $host,
        int $puerto,
        string $cifrado,
        string $usuario,
        string $clave,
        string $remitente,
        string $nombreRemitente,
        string $destinatario,
        string $asunto,
        string $cuerpoHtml
    ): bool {
        $prefijo = ($cifrado === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefijo . $host, $puerto, $errno, $errstr, 15);
        if (!$socket) {
            throw new \RuntimeException("No se pudo conectar al servidor SMTP {$host}:{$puerto} ($errstr)");
        }

        self::leerRespuestaSmtp($socket, 220);

        // Saludo EHLO
        $localHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        self::ejecutarComandoSmtp($socket, "EHLO {$localHost}", 250);

        // Negociar STARTTLS si está indicado
        if ($cifrado === 'tls') {
            self::ejecutarComandoSmtp($socket, "STARTTLS", 220);
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                throw new \RuntimeException("Fallo al establecer cifrado TLS con el servidor SMTP.");
            }
            self::ejecutarComandoSmtp($socket, "EHLO {$localHost}", 250);
        }

        // Autenticación AUTH LOGIN
        self::ejecutarComandoSmtp($socket, "AUTH LOGIN", 334);
        self::ejecutarComandoSmtp($socket, base64_encode($usuario), 334);
        self::ejecutarComandoSmtp($socket, base64_encode($clave), 235);

        // Envío de remitente y destinatario
        self::ejecutarComandoSmtp($socket, "MAIL FROM:<{$remitente}>", 250);
        self::ejecutarComandoSmtp($socket, "RCPT TO:<{$destinatario}>", 250);

        // Cabeceras y cuerpo
        self::ejecutarComandoSmtp($socket, "DATA", 354);

        $asuntoEncoded = "=?UTF-8?B?" . base64_encode($asunto) . "?=";
        $fromEncoded   = "=?UTF-8?B?" . base64_encode($nombreRemitente) . "?=";

        $email = "Date: " . date('r') . "\r\n";
        $email .= "To: <{$destinatario}>\r\n";
        $email .= "From: {$fromEncoded} <{$remitente}>\r\n";
        $email .= "Subject: {$asuntoEncoded}\r\n";
        $email .= "MIME-Version: 1.0\r\n";
        $email .= "Content-Type: text/html; charset=UTF-8\r\n";
        $email .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $email .= chunk_split(base64_encode($cuerpoHtml)) . "\r\n.\r\n";

        fwrite($socket, $email);
        self::leerRespuestaSmtp($socket, 250);

        // Desconexión
        self::ejecutarComandoSmtp($socket, "QUIT", 221);
        fclose($socket);

        return true;
    }

    private static function ejecutarComandoSmtp($socket, string $comando, int $codigoEsperado): string
    {
        fwrite($socket, $comando . "\r\n");
        return self::leerRespuestaSmtp($socket, $codigoEsperado);
    }

    private static function leerRespuestaSmtp($socket, int $codigoEsperado): string
    {
        $respuesta = '';
        while ($linea = fgets($socket, 515)) {
            $respuesta .= $linea;
            // La última línea de respuesta SMTP tiene un espacio tras el código (ej: "250 OK")
            if (isset($linea[3]) && $linea[3] === ' ') {
                break;
            }
        }
        $codigo = (int) substr($respuesta, 0, 3);
        if ($codigo !== $codigoEsperado) {
            throw new \RuntimeException("Respuesta SMTP inesperada: {$respuesta} (esperado {$codigoEsperado})");
        }
        return $respuesta;
    }

    private static function guardarCopiaLocal(string $para, string $asunto, string $html): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/correos';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $archivo = sprintf('%s/%s_%s.html', $dir, date('Ymd_His'), preg_replace('/[^a-zA-Z0-9_-]/', '', substr($para, 0, 20)));
        $contenido = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{$asunto}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; padding: 20px; }
        .meta { background: #e2e8f0; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; }
        .cuerpo { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="meta">
        <strong>Para:</strong> {$para}<br>
        <strong>Asunto:</strong> {$asunto}<br>
        <strong>Fecha:</strong> {date('Y-m-d H:i:s')}
    </div>
    <div class="cuerpo">
        {$html}
    </div>
</body>
</html>
HTML;
        @file_put_contents($archivo, $contenido);
    }
}
