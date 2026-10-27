-- =====================================================================
--  Clínica Vida Salud · 03_vistas.sql
--  Vistas de solo lectura que usa el backend (agenda, historial, reportes).
-- =====================================================================

-- Cita con todos sus datos legibles (RF11, RF12, RF13).
CREATE OR REPLACE VIEW v_cita_detalle AS
SELECT c.id_cita,
       c.codigo,
       c.fecha,
       c.hora_inicio,
       c.hora_fin,
       EXTRACT(ISODOW FROM c.fecha)::INT        AS dia_semana,
       c.modalidad,
       c.motivo,
       c.estado,
       c.motivo_cancelacion,
       c.creado_en,
       c.id_paciente,
       p.codigo                                 AS codigo_paciente,
       p.nombres || ' ' || p.apellidos          AS paciente,
       p.ci                                     AS ci_paciente,
       p.telefono                               AS telefono_paciente,
       p.correo                                 AS correo_paciente,
       c.id_medico,
       m.codigo                                 AS codigo_medico,
       'Dr(a). ' || m.nombres || ' ' || m.apellidos AS medico,
       c.id_especialidad,
       e.nombre                                 AS especialidad,
       u.nombre_completo                        AS registrado_por,
       e.color                                  AS color_especialidad
  FROM cita c
  JOIN paciente p     ON p.id_paciente = c.id_paciente
  JOIN medico m       ON m.id_medico = c.id_medico
  JOIN especialidad e ON e.id_especialidad = c.id_especialidad
  LEFT JOIN usuario u ON u.id_usuario = c.id_usuario_registro;

-- Médico con sus especialidades en una sola fila (RF03).
CREATE OR REPLACE VIEW v_medico_especialidades AS
SELECT m.id_medico,
       m.codigo,
       m.registro_profesional,
       m.nombres,
       m.apellidos,
       'Dr(a). ' || m.nombres || ' ' || m.apellidos AS nombre,
       m.telefono,
       m.correo,
       m.estado,
       m.creado_en,
       COALESCE(string_agg(e.nombre, ', ' ORDER BY e.nombre), '') AS especialidades,
       count(e.id_especialidad)::INT                               AS total_especialidades
  FROM medico m
  LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
  LEFT JOIN especialidad e         ON e.id_especialidad = me.id_especialidad
 GROUP BY m.id_medico;

-- Totales de citas por día y estado (panel y reporte de asistencia).
CREATE OR REPLACE VIEW v_resumen_citas_dia AS
SELECT fecha,
       count(*)::INT                                          AS total,
       count(*) FILTER (WHERE estado = 'programada')::INT     AS programadas,
       count(*) FILTER (WHERE estado = 'confirmada')::INT     AS confirmadas,
       count(*) FILTER (WHERE estado = 'atendida')::INT       AS atendidas,
       count(*) FILTER (WHERE estado = 'cancelada')::INT      AS canceladas,
       count(*) FILTER (WHERE estado = 'inasistencia')::INT   AS inasistencias
  FROM cita
 GROUP BY fecha;
