<?php
namespace App\Paquetes\Reportes\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Consultas del panel de estadísticas (CU17).
 * Todas reciben el mismo período [desde, hasta] para que los números coincidan entre sí.
 */
final class EstadisticaModelo
{
    /**
     * Citas de cada mes o semana del período, desglosadas por estado. Los tramos sin citas aparecen con 0.
     * $unidad: 'month' (meses) o 'week' (semanas de lunes a domingo).
     * @return array<int,array{inicio:string,total:int,atendidas:int,inasistencias:int,canceladas:int,pendientes:int}>
     */
    public function citasPorTramo(string $desde, string $hasta, string $unidad = 'month'): array
    {
        $unidad = $unidad === 'week' ? 'week' : 'month';     // nunca se concatena texto del usuario
        return BaseDatos::todos(
            "SELECT t.inicio::DATE::TEXT                                              AS inicio,
                    count(c.id_cita)::INT                                            AS total,
                    count(c.id_cita) FILTER (WHERE c.estado = 'atendida')::INT       AS atendidas,
                    count(c.id_cita) FILTER (WHERE c.estado = 'inasistencia')::INT   AS inasistencias,
                    count(c.id_cita) FILTER (WHERE c.estado = 'cancelada')::INT      AS canceladas,
                    count(c.id_cita) FILTER (WHERE c.estado IN ('programada', 'confirmada'))::INT AS pendientes
               FROM generate_series(date_trunc('$unidad', CAST(:a AS DATE)),
                                    date_trunc('$unidad', CAST(:b AS DATE)),
                                    INTERVAL '1 $unidad') AS t(inicio)
               LEFT JOIN cita c
                      ON c.fecha >= t.inicio::DATE
                     AND c.fecha <  (t.inicio + INTERVAL '1 $unidad')::DATE
                     AND c.fecha BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE)
              GROUP BY t.inicio
              ORDER BY t.inicio",
            ['a' => $desde, 'b' => $hasta]
        );
    }

    /**
     * Totales de un período (se usa para comparar con el período anterior).
     * @return array{total:int,atendidas:int,inasistencias:int,canceladas:int}
     */
    public function resumen(string $desde, string $hasta): array
    {
        return BaseDatos::uno(
            "SELECT count(*)::INT                                          AS total,
                    count(*) FILTER (WHERE estado = 'atendida')::INT       AS atendidas,
                    count(*) FILTER (WHERE estado = 'inasistencia')::INT   AS inasistencias,
                    count(*) FILTER (WHERE estado = 'cancelada')::INT      AS canceladas
               FROM cita
              WHERE fecha BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE)",
            ['a' => $desde, 'b' => $hasta]
        );
    }

    /** Citas cuya hora ya pasó y siguen programadas o confirmadas (nadie registró la asistencia). */
    public function sinRegistrar(string $desde, string $hasta): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM cita
              WHERE fecha BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE)
                AND estado IN ('programada', 'confirmada')
                AND (fecha + hora_fin) < LOCALTIMESTAMP",
            ['a' => $desde, 'b' => $hasta]
        );
    }

    /**
     * Especialidades más pedidas: todas las citas solicitadas (incluidas las canceladas).
     * @return array<int,array<string,mixed>>
     */
    public function especialidadesMasPedidas(string $desde, string $hasta): array
    {
        return BaseDatos::todos(
            "SELECT e.nombre, e.color,
                    count(c.id_cita)::INT                                           AS total,
                    count(c.id_cita) FILTER (WHERE c.estado = 'atendida')::INT      AS atendidas,
                    count(c.id_cita) FILTER (WHERE c.estado = 'inasistencia')::INT  AS inasistencias,
                    count(c.id_cita) FILTER (WHERE c.estado = 'cancelada')::INT     AS canceladas
               FROM especialidad e
               LEFT JOIN cita c ON c.id_especialidad = e.id_especialidad
                               AND c.fecha BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE)
              WHERE e.estado = 'activo' OR c.id_cita IS NOT NULL
              GROUP BY e.id_especialidad, e.nombre, e.color
              ORDER BY total DESC, e.nombre",
            ['a' => $desde, 'b' => $hasta]
        );
    }

    /**
     * Asistencia por médico (solo los que tuvieron citas en el período).
     * @return array<int,array<string,mixed>>
     */
    public function asistenciaPorMedico(string $desde, string $hasta): array
    {
        return BaseDatos::todos(
            "SELECT 'Dr(a). ' || m.nombres || ' ' || m.apellidos                    AS medico,
                    count(*)::INT                                                   AS total,
                    count(*) FILTER (WHERE c.estado = 'atendida')::INT              AS atendidas,
                    count(*) FILTER (WHERE c.estado = 'inasistencia')::INT          AS inasistencias,
                    count(*) FILTER (WHERE c.estado = 'cancelada')::INT             AS canceladas
               FROM cita c
               JOIN medico m ON m.id_medico = c.id_medico
              WHERE c.fecha BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE)
              GROUP BY m.id_medico, m.nombres, m.apellidos
              ORDER BY total DESC, medico",
            ['a' => $desde, 'b' => $hasta]
        );
    }

    /** Pacientes registrados en el período. */
    public function pacientesNuevos(string $desde, string $hasta): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM paciente WHERE creado_en::DATE BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE)",
            ['a' => $desde, 'b' => $hasta]
        );
    }
}
