<?php
namespace App\Paquetes\Seguridad\Modelos;

use App\Nucleo\Aplicacion;
use App\Nucleo\BaseDatos;

/**
 * Clase ENTIDAD/acceso a datos de la tabla USUARIO.
 * Solo contiene consultas SQL parametrizadas; las reglas de negocio
 * están en los casos de uso (CU01, CU02).
 */
final class UsuarioModelo
{
    /** Busca por nombre de usuario O correo (RF01: "usuario o correo"). */
    public function buscarParaLogin(string $usuarioOCorreo): ?array
    {
        return BaseDatos::uno(
            "SELECT u.id_usuario, u.nombre_usuario, u.password_hash, u.estado, u.intentos_fallidos,
                    u.bloqueado_hasta, u.bloqueado_hasta > now() AS esta_bloqueado,
                    CEIL(EXTRACT(EPOCH FROM (u.bloqueado_hasta - now())) / 60) AS minutos_restantes,
                    r.nombre AS rol, m.estado AS estado_medico, p.estado AS estado_paciente
               FROM usuario u
               JOIN rol r            ON r.id_rol = u.id_rol
               LEFT JOIN medico m    ON m.id_medico = u.id_medico
               LEFT JOIN paciente p  ON p.id_paciente = u.id_paciente
              WHERE u.nombre_usuario = :v OR u.correo = :v",
            ['v' => mb_strtolower($usuarioOCorreo)]
        );
    }

    /**
     * Suma un intento fallido.
     * Al llegar a max_intentos (3), bloquea progresivamente:
     * 1er bloqueo = 1 min, 2do bloqueo = 2 min, 3er bloqueo = 4 min, etc. (exponencial).
     * @return array{bloqueado:bool, minutos:int, intentos:int}
     */
    public function registrarIntentoFallido(int $id): array
    {
        $max = (int) Aplicacion::config('seguridad.max_intentos', 3);
        $baseMin = (int) Aplicacion::config('seguridad.minutos_bloqueo_base', 1);

        $fila = BaseDatos::uno(
            "UPDATE usuario
                SET intentos_fallidos = CASE WHEN intentos_fallidos + 1 >= :max THEN 0 ELSE intentos_fallidos + 1 END,
                    bloqueos_consecutivos = CASE WHEN intentos_fallidos + 1 >= :max
                                                 THEN bloqueos_consecutivos + 1 ELSE bloqueos_consecutivos END,
                    bloqueado_hasta = CASE WHEN intentos_fallidos + 1 >= :max
                                           THEN now() + make_interval(mins => LEAST(60, :base * POWER(2, GREATEST(0, bloqueos_consecutivos))::INT))
                                           ELSE bloqueado_hasta END
              WHERE id_usuario = :id
          RETURNING bloqueado_hasta IS NOT NULL AND bloqueado_hasta > now() AS esta_bloqueado,
                    intentos_fallidos,
                    CEIL(EXTRACT(EPOCH FROM (bloqueado_hasta - now())) / 60) AS minutos_restantes",
            [
                'id'   => $id,
                'max'  => $max,
                'base' => $baseMin,
            ]
        );

        return [
            'bloqueado' => !empty($fila['esta_bloqueado']),
            'minutos'   => max(1, (int) ($fila['minutos_restantes'] ?? 1)),
            'intentos'  => (int) ($fila['intentos_fallidos'] ?? 0),
        ];
    }

    public function registrarAccesoExitoso(int $id): void
    {
        BaseDatos::ejecutar(
            "UPDATE usuario
                SET intentos_fallidos = 0,
                    bloqueos_consecutivos = 0,
                    bloqueado_hasta = NULL,
                    ultimo_acceso = now()
              WHERE id_usuario = :id",
            ['id' => $id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listar(?string $buscar, ?string $rol, ?string $estado): array
    {
        $sql = "SELECT u.id_usuario, u.nombre_usuario, u.nombre_completo, u.correo, u.estado,
                       u.ultimo_acceso, u.bloqueado_hasta > now() AS esta_bloqueado,
                       r.nombre AS rol, m.codigo AS codigo_medico, p.codigo AS codigo_paciente
                  FROM usuario u
                  JOIN rol r           ON r.id_rol = u.id_rol
                  LEFT JOIN medico m   ON m.id_medico = u.id_medico
                  LEFT JOIN paciente p ON p.id_paciente = u.id_paciente
                 WHERE 1 = 1";
        $p = [];
        if ($buscar) {
            $sql .= " AND (u.nombre_usuario ILIKE :q OR u.nombre_completo ILIKE :q OR u.correo ILIKE :q)";
            $p['q'] = '%' . $buscar . '%';
        }
        if ($rol) {
            $sql .= " AND r.nombre = :rol";
            $p['rol'] = $rol;
        }
        if ($estado) {
            $sql .= " AND u.estado = :estado";
            $p['estado'] = $estado;
        }
        return BaseDatos::todos($sql . " ORDER BY r.id_rol, u.nombre_completo", $p);
    }

    public function obtener(int $id): ?array
    {
        return BaseDatos::uno(
            "SELECT u.*, r.nombre AS rol FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = :id",
            ['id' => $id]
        );
    }

    public function crear(array $d): int
    {
        return (int) BaseDatos::valor(
            "INSERT INTO usuario (nombre_usuario, correo, password_hash, nombre_completo, id_rol, id_medico, id_paciente)
             VALUES (:nombre_usuario, :correo, :hash, :nombre_completo, :id_rol, :id_medico, :id_paciente)
             RETURNING id_usuario",
            [
                'nombre_usuario'  => $d['nombre_usuario'],
                'correo'          => $d['correo'],
                'hash'            => $d['password_hash'],
                'nombre_completo' => $d['nombre_completo'],
                'id_rol'          => $d['id_rol'],
                'id_medico'       => $d['id_medico'] ?? null,
                'id_paciente'     => $d['id_paciente'] ?? null,
            ]
        );
    }

    public function actualizar(int $id, array $d): void
    {
        BaseDatos::ejecutar(
            "UPDATE usuario
                SET nombre_usuario = :nombre_usuario, correo = :correo, nombre_completo = :nombre_completo,
                    id_rol = :id_rol, id_medico = :id_medico, id_paciente = :id_paciente
              WHERE id_usuario = :id",
            [
                'id'              => $id,
                'nombre_usuario'  => $d['nombre_usuario'],
                'correo'          => $d['correo'],
                'nombre_completo' => $d['nombre_completo'],
                'id_rol'          => $d['id_rol'],
                'id_medico'       => $d['id_medico'] ?? null,
                'id_paciente'     => $d['id_paciente'] ?? null,
            ]
        );
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        BaseDatos::ejecutar(
            "UPDATE usuario SET estado = :e, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = :id",
            ['id' => $id, 'e' => $estado]
        );
    }

    public function cambiarClave(int $id, string $hash): void
    {
        BaseDatos::ejecutar(
            "UPDATE usuario
                SET password_hash = :h,
                    intentos_fallidos = 0,
                    bloqueos_consecutivos = 0,
                    bloqueado_hasta = NULL
              WHERE id_usuario = :id",
            ['id' => $id, 'h' => $hash]
        );
    }

    /** Busca una cuenta activa por su dirección exacta de correo. */
    public function buscarPorCorreo(string $correo): ?array
    {
        return BaseDatos::uno(
            "SELECT u.id_usuario, u.nombre_usuario, u.nombre_completo, u.correo, u.estado
               FROM usuario u
              WHERE LOWER(u.correo) = :c",
            ['c' => mb_strtolower(trim($correo))]
        );
    }

    /** Genera y guarda un token de recuperación que vence en 30 minutos. */
    public function crearTokenRecuperacion(int $idUsuario): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        // Invalida tokens anteriores no usados
        BaseDatos::ejecutar(
            "UPDATE recuperacion_clave
                SET usado_en = now()
              WHERE id_usuario = :id AND usado_en IS NULL",
            ['id' => $idUsuario]
        );

        BaseDatos::ejecutar(
            "INSERT INTO recuperacion_clave (id_usuario, token_hash, expira_en)
             VALUES (:id, :hash, now() + interval '30 minutes')",
            ['id' => $idUsuario, 'hash' => $tokenHash]
        );

        return $token;
    }

    /** Valida el token y retorna los datos del usuario si es vigente. */
    public function verificarTokenRecuperacion(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);
        return BaseDatos::uno(
            "SELECT r.id_recuperacion, r.id_usuario, u.nombre_usuario, u.nombre_completo, u.correo, u.estado
               FROM recuperacion_clave r
               JOIN usuario u ON u.id_usuario = r.id_usuario
              WHERE r.token_hash = :hash
                AND r.usado_en IS NULL
                AND r.expira_en > now()",
            ['hash' => $tokenHash]
        );
    }

    /** Marca el token como usado. */
    public function marcarTokenUsado(int $idRecuperacion): void
    {
        BaseDatos::ejecutar(
            "UPDATE recuperacion_clave SET usado_en = now() WHERE id_recuperacion = :id",
            ['id' => $idRecuperacion]
        );
    }

    public function hashActual(int $id): ?string
    {
        return BaseDatos::valor("SELECT password_hash FROM usuario WHERE id_usuario = :id", ['id' => $id]);
    }

    /** Médicos que todavía no tienen cuenta (para vincular). */
    public function medicosSinCuenta(?int $incluirId = null): array
    {
        return BaseDatos::todos(
            "SELECT m.id_medico, m.codigo, m.nombres || ' ' || m.apellidos AS nombre
               FROM medico m
              WHERE m.estado = 'activo'
                AND (NOT EXISTS (SELECT 1 FROM usuario u WHERE u.id_medico = m.id_medico) OR m.id_medico = :inc)
              ORDER BY m.apellidos",
            ['inc' => $incluirId ?? 0]
        );
    }

    /** Pacientes activos sin cuenta (para vincular). */
    public function pacientesSinCuenta(?int $incluirId = null): array
    {
        return BaseDatos::todos(
            "SELECT p.id_paciente, p.codigo, p.ci, p.nombres || ' ' || p.apellidos AS nombre
               FROM paciente p
              WHERE p.estado = 'activo'
                AND (NOT EXISTS (SELECT 1 FROM usuario u WHERE u.id_paciente = p.id_paciente) OR p.id_paciente = :inc)
              ORDER BY p.apellidos",
            ['inc' => $incluirId ?? 0]
        );
    }

    /** Cuántos administradores activos quedan (no se puede dejar al sistema sin ninguno). */
    public function administradoresActivos(): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.nombre = 'administrador' AND u.estado = 'activo'"
        );
    }
}
