<?php
namespace App\Paquetes\Seguridad\CasosUso;

use App\Nucleo\BaseDatos;
use App\Nucleo\Bitacora;
use App\Nucleo\Controlador;
use App\Nucleo\Correo;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Nucleo\Validador;
use App\Nucleo\Vista;
use App\Paquetes\Seguridad\Modelos\UsuarioModelo;
use Throwable;

/**
 * CU · Recuperar contraseña por correo electrónico
 *
 * Flujo:
 *  1. El usuario solicita restablecer su contraseña indicando su correo.
 *  2. Para evitar enumeración de cuentas, el sistema responde siempre con un mensaje neutral.
 *  3. Si la cuenta existe y está activa, genera un token criptográfico seguro (30 min) y envía el correo.
 *  4. El usuario accede al enlace con el token y define su nueva contraseña.
 */
final class CURecuperarContrasena extends Controlador
{
    private UsuarioModelo $usuarios;

    public function __construct()
    {
        $this->usuarios = new UsuarioModelo();
    }

    /** GET /recuperar-clave — Formulario para solicitar enlace */
    public function formulario(): void
    {
        Vista::mostrar('seguridad/recuperar_solicitar', [
            'titulo' => 'Recuperar contraseña',
        ], 'simple');
    }

    /** POST /recuperar-clave — Envía el correo con el enlace seguro */
    public function solicitar(): void
    {
        try {
            $datos = Validador::validar($_POST, [
                'correo' => ['Correo electrónico', 'requerido|correo|max:120'],
            ]);

            $usuario = $this->usuarios->buscarPorCorreo($datos['correo']);

            if ($usuario && $usuario['estado'] === 'activo') {
                $token = $this->usuarios->crearTokenRecuperacion((int) $usuario['id_usuario']);
                $enlace = Peticion::origen() . url('/restablecer-clave', ['token' => $token]);

                $nombre = e($usuario['nombre_completo'] ?: $usuario['nombre_usuario']);
                $html = <<<HTML
<div style="font-family: sans-serif; max-width: 580px; margin: 0 auto; color: #1e293b; line-height: 1.6;">
    <h2 style="color: #0f3d68;">Clínica Vida Salud</h2>
    <p>Hola, <strong>{$nombre}</strong>:</p>
    <p>Hemos recibido una solicitud para restablecer la contraseña de su cuenta en el sistema de la Clínica Vida Salud.</p>
    <p style="margin: 25px 0;">
        <a href="{$enlace}" style="background-color: #0f3d68; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
            Restablecer mi contraseña
        </a>
    </p>
    <p style="font-size: 13px; color: #64748b;">
        Este enlace es de uso único y vencerá en <strong>30 minutos</strong>.<br>
        Si usted no solicitó este cambio, ignore este mensaje; su cuenta continuará segura.
    </p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;">
    <p style="font-size: 12px; color: #94a3b8;">
        Si el botón no funciona, copie y pegue esta dirección en su navegador:<br>
        <span style="word-break: break-all;">{$enlace}</span>
    </p>
</div>
HTML;

                $enviado = Correo::enviar($usuario['correo'], 'Restablecer contraseña · Clínica Vida Salud', $html);
                if (!$enviado) {
                    throw new ErrorNegocio('No se pudo enviar el correo de recuperación. Revise la configuración del servidor de correo.');
                }

                Bitacora::registrar('recuperacion_clave', 'seguridad', 'Solicitó enlace de recuperación de contraseña.', [
                    'id_usuario'     => $usuario['id_usuario'],
                    'nombre_usuario' => $usuario['nombre_usuario'],
                ]);
            }

            // Mensaje idéntico para evitar enumeración de correos
            $this->exito(
                'Si el correo está registrado en el sistema, recibirá un enlace en unos instantes para restablecer su contraseña.',
                '/login'
            );
        } catch (Throwable $e) {
            $this->volverConError($e, '/recuperar-clave');
        }
    }

    /** GET /restablecer-clave — Formulario con token para ingresar nueva contraseña */
    public function formularioRestablecer(): void
    {
        $token = (string) Peticion::entrada('token', '');
        if ($token === '') {
            $this->redirigirConError('El enlace no es válido o ha expirado.', '/login');
        }

        $registro = $this->usuarios->verificarTokenRecuperacion($token);
        if (!$registro) {
            $this->redirigirConError('El enlace para restablecer la contraseña ha vencido o ya fue utilizado. Por favor solicite uno nuevo.', '/recuperar-clave');
        }

        Vista::mostrar('seguridad/recuperar_restablecer', [
            'titulo' => 'Crear nueva contraseña',
            'token'  => $token,
            'nombre' => $registro['nombre_completo'] ?: $registro['nombre_usuario'],
        ], 'simple');
    }

    /** POST /restablecer-clave — Procesa el cambio de contraseña */
    public function restablecer(): void
    {
        try {
            $d = Validador::validar($_POST, [
                'token'              => ['Token', 'requerido'],
                'clave'              => ['Nueva contraseña', 'requerido|clave'],
                'clave_confirmacion' => ['Confirmación', 'requerido'],
            ]);

            if ($d['clave'] !== $d['clave_confirmacion']) {
                throw new ErrorNegocio('Revise los datos.', ['clave_confirmacion' => 'La confirmación no coincide con la nueva contraseña.']);
            }

            $registro = $this->usuarios->verificarTokenRecuperacion($d['token']);
            if (!$registro) {
                throw new ErrorNegocio('El enlace ha vencido o ya fue utilizado. Por favor solicite uno nuevo.');
            }

            $idUsuario = (int) $registro['id_usuario'];
            $idRecuperacion = (int) $registro['id_recuperacion'];
            $hash = password_hash($d['clave'], PASSWORD_DEFAULT);

            BaseDatos::transaccion(function () use ($idUsuario, $idRecuperacion, $hash) {
                $this->usuarios->cambiarClave($idUsuario, $hash);
                $this->usuarios->marcarTokenUsado($idRecuperacion);
            });

            Bitacora::registrar('cambio_clave', 'seguridad', 'Restableció su contraseña mediante enlace por correo.', [
                'id_usuario'     => $idUsuario,
                'nombre_usuario' => $registro['nombre_usuario'],
            ]);

            $this->exito('Su contraseña ha sido restablecida exitosamente. Ya puede iniciar sesión.', '/login');
        } catch (Throwable $e) {
            $token = (string) Peticion::entrada('token', '');
            $this->volverConError($e, url('/restablecer-clave', ['token' => $token]));
        }
    }
}

