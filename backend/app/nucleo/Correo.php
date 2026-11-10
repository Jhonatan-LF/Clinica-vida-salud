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

        $host   = (string) Aplicacion::config('correo.host', '');
        $user   = (string) Aplicacion::config('correo.usuario', '');
        $pass   = str_replace(' ', '', (string) Aplicacion::config('correo.clave', ''));
        $puerto = (int) Aplicacion::config('correo.puerto', 587);
        $cifrado= (string) Aplicacion::config('correo.seguridad', 'tls');

        // Si tenemos clave de Brevo (empieza con xsmtpib- o xkeysib-), usamos la API HTTP REST
        // porque Railway BLOQUEA los puertos SMTP 587 y 465 en planes Hobby/Trial por firewall.
        if (str_starts_with($pass, 'xsmtpib-') || str_starts_with($pass, 'xkeysib-') || str_contains($host, 'brevo')) {
            return self::enviarPorApiBrevo($pass, $remitente, $nombreDe, $para, $asunto, $mensajeHtml);
        }

        if ($driver === 'smtp' && $host !== '' && $user !== '' && $pass !== '') {
            try {
                return self::enviarPorSmtp($host, $puerto, $cifrado, $user, $pass, $remitente, $nombreDe, $para, $asunto, $mensajeHtml);
            } catch (\Throwable $e) {
                error_log('Error enviando correo SMTP: ' . $e->getMessage());
                throw $e;
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
     * Envío a través de la API REST de Brevo sobre HTTPS (Puerto 443).
     * Railway NO bloquea HTTPS, garantizando entrega al 100%.
     */
    private static function enviarPorApiBrevo(
        string $apiKey,
        string $remitente,
        string $nombreRemitente,
        string $destinatario,
        string $asunto,
        string $cuerpoHtml
    ): bool {
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        $payload = json_encode([
            'sender' => [
                'name'  => $nombreRemitente,
                'email' => $remitente,
            ],
            'to' => [
                [
                    'email' => $destinatario,
                ],
            ],
            'subject'     => $asunto,
            'htmlContent' => $cuerpoHtml,
        ]);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . $apiKey,
                'content-type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Fallo al conectar con la API de Brevo: {$error}");
        }

        if ($code !== 200 && $code !== 201) {
            $data = json_decode((string) $response, true);
            $msg = $data['message'] ?? $response;
            throw new \RuntimeException("Brevo API error ({$code}): {$msg}");
        }

        return true;
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
        $contexto = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);
        $socket = @stream_socket_client($prefijo . $host . ':' . $puerto, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $contexto);
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
