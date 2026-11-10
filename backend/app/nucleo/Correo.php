<?php
namespace App\Nucleo;

/**
 * Servicio de envío y simulación de correos electrónicos.
 *
 * Si PHP tiene configurado un servidor de correo (o sendmail/SMTP), intenta enviar el correo.
 * Adicionalmente, guarda una copia en backend/storage/correos/ para que en localhost
 * o entornos de prueba se pueda inspeccionar el correo y hacer clic en el enlace directamente.
 */
final class Correo
{
    public static function enviar(string $para, string $asunto, string $mensajeHtml, string $mensajeTexto = ''): bool
    {
        $de = (string) Aplicacion::config('correo.remitente', 'no-responder@clinicavidasalud.com');
        $nombreDe = (string) Aplicacion::config('app.nombre', 'Clínica Vida Salud');

        // 1. Guardar siempre copia en storage/correos/ para desarrollo/pruebas locales
        self::guardarCopiaLocal($para, $asunto, $mensajeHtml, $mensajeTexto);

        // 2. Intentar envío con mail() estándar si el entorno lo permite
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($nombreDe) . "?= <{$de}>\r\n";
        $headers .= "X-Mailer: ClinicaVidaSalud/1.0\r\n";

        // Suprimimos errores en caso de que sendmail no esté configurado en localhost
        try {
            return @mail($para, "=?UTF-8?B?" . base64_encode($asunto) . "?=", $mensajeHtml, $headers);
        } catch (\Throwable) {
            return true; // Consideramos exitoso para el flujo si quedó registrado localmente
        }
    }

    private static function guardarCopiaLocal(string $para, string $asunto, string $html, string $texto): void
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
