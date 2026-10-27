-- =====================================================================
--  Clínica Vida Salud · pruebas/pruebas_bd.sql
--  Pruebas DIRECTAS en la base de datos: comprueban que las
--  restricciones y los triggers protegen los datos aunque alguien
--  se salte la aplicación PHP (doble control de las reglas de negocio).
--
--  Uso (desde basededato\sql):
--    psql -U postgres -d clinica_vida_salud -f pruebas\pruebas_bd.sql > pruebas\resultados_pruebas_bd.txt
--
--  Todo se ejecuta dentro de una transacción que se REVIERTE al final:
--  la base queda exactamente como estaba.
-- =====================================================================

\set QUIET on
SET client_min_messages = warning;
BEGIN;

CREATE TEMP TABLE resultado (
    n       INT,
    prueba  TEXT,
    estado  TEXT,
    mensaje TEXT
);

-- Ejecuta una sentencia que DEBE fallar. Si falla, la prueba se aprueba
-- y se guarda el mensaje de PostgreSQL; el error no corta el script.
CREATE FUNCTION pg_temp.debe_fallar(p_n INT, p_prueba TEXT, p_sql TEXT)
RETURNS VOID LANGUAGE plpgsql AS $$
BEGIN
    EXECUTE p_sql;
    INSERT INTO resultado VALUES (p_n, p_prueba, 'FALLIDA', 'La base aceptó la operación.');
EXCEPTION WHEN OTHERS THEN
    INSERT INTO resultado VALUES (p_n, p_prueba, 'APROBADA', SQLERRM);
END;
$$;

-- Horarios libres del Dr. Juan Pérez (médico 1, Medicina General) en los próximos días.
CREATE TEMP TABLE libre AS
SELECT row_number() OVER (ORDER BY d, i.hora_inicio) AS n, d::DATE AS fecha, i.hora_inicio, i.hora_fin
  FROM generate_series(CURRENT_DATE + 1, CURRENT_DATE + 21, INTERVAL '1 day') d
 CROSS JOIN LATERAL fn_intervalos_medico(1, d::DATE) i
 WHERE i.libre
 ORDER BY d, i.hora_inicio
 LIMIT 5;

-- Cita válida de referencia (paciente 2 en el primer horario libre).
CREATE TEMP TABLE ref AS
WITH nueva AS (
    INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin, motivo)
    SELECT 2, 1, 1, fecha, hora_inicio, hora_fin, 'Cita de referencia para las pruebas' FROM libre WHERE n = 1
    RETURNING id_cita, fecha, hora_inicio, hora_fin
)
SELECT * FROM nueva;

-- 1 · RN01
SELECT pg_temp.debe_fallar(1, 'CI duplicado (UNIQUE uq_paciente_ci)',
    $q$INSERT INTO paciente (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono)
       VALUES ('6234517', 'Otra', 'Persona', '1990-01-01', 'femenino', '70000000')$q$);

-- 2 · RN07 (EXCLUDE)
SELECT pg_temp.debe_fallar(2, 'Cita superpuesta del mismo médico (EXCLUDE ex_cita_medico_sin_solape)',
    format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
              VALUES (3, 1, 1, %L, %L, %L)$q$, (SELECT fecha FROM ref), (SELECT hora_inicio FROM ref), (SELECT hora_fin FROM ref)));

-- 3 · RN05
SELECT pg_temp.debe_fallar(3, 'Cita fuera del horario de atención (trigger RN05)',
    format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
              VALUES (3, 1, 1, %L, '20:00', '20:30')$q$,
           (SELECT min(d)::DATE FROM generate_series(CURRENT_DATE + 1, CURRENT_DATE + 7, INTERVAL '1 day') d WHERE EXTRACT(ISODOW FROM d) = 1)));

-- 4 · RN06
SELECT pg_temp.debe_fallar(4, 'Cita en fecha pasada (trigger RN06)',
    format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
              VALUES (3, 1, 1, %L, '08:00', '08:30')$q$, CURRENT_DATE - 7));

-- 5 · RN04
SELECT pg_temp.debe_fallar(5, 'Cita con médico inactivo (trigger RN04)',
    format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
              VALUES (3, 6, 2, %L, '09:00', '09:30')$q$, CURRENT_DATE + 1));

-- 6 · RN03
SELECT pg_temp.debe_fallar(6, 'Médico con especialidad que no atiende (FK compuesta)',
    format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
              VALUES (3, 1, 3, %L, %L, %L)$q$, (SELECT fecha FROM libre WHERE n = 2), (SELECT hora_inicio FROM libre WHERE n = 2),
              (SELECT hora_fin FROM libre WHERE n = 2)));

-- 7 · RN08
SELECT pg_temp.debe_fallar(7, 'Cita que no respeta la duración configurada (trigger RN08)',
    format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
              VALUES (3, 1, 1, %L, %L, %L)$q$, (SELECT fecha FROM libre WHERE n = 3), (SELECT hora_inicio FROM libre WHERE n = 3),
              (SELECT hora_inicio + INTERVAL '45 minutes' FROM libre WHERE n = 3)));

-- 8 · RF10
SELECT pg_temp.debe_fallar(8, 'Cita cancelada marcada como atendida (trigger RF10)',
    format($q$UPDATE cita SET estado = 'atendida' WHERE id_cita = %s$q$,
           (SELECT id_cita FROM cita WHERE estado = 'cancelada' ORDER BY id_cita LIMIT 1)));

-- 9 · RN10
SELECT pg_temp.debe_fallar(9, 'Borrado físico de una cita (trigger RN10)',
    format('DELETE FROM cita WHERE id_cita = %s', (SELECT id_cita FROM ref)));

-- 10 · RF09
SELECT pg_temp.debe_fallar(10, 'Cancelar sin motivo (CHECK ck_cita_cancelada)',
    format($q$UPDATE cita SET estado = 'cancelada' WHERE id_cita = %s$q$, (SELECT id_cita FROM ref)));

-- 11 · RF04
SELECT pg_temp.debe_fallar(11, 'Especialidad con el mismo nombre normalizado',
    $q$INSERT INTO especialidad (nombre) VALUES ('  PEDIATRIA ')$q$);

-- 12 · RF05
SELECT pg_temp.debe_fallar(12, 'Horario con hora final anterior a la inicial (CHECK)',
    $q$INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin) VALUES (1, 6, '12:00', '08:00')$q$);

-- 13 · RF05
SELECT pg_temp.debe_fallar(13, 'Horario superpuesto del mismo médico (EXCLUDE)',
    $q$INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin) VALUES (1, 1, '10:00', '13:00')$q$);

-- 14 · RN12
SELECT pg_temp.debe_fallar(14, 'Usuario con rol médico sin vincular a un médico (trigger RN12)',
    $q$INSERT INTO usuario (nombre_usuario, correo, password_hash, nombre_completo, id_rol)
       SELECT 'medico.suelto', 'suelto@vidasalud.bo', 'x', 'Médico Suelto', id_rol FROM rol WHERE nombre = 'medico'$q$);

-- 15 · Bitácora inalterable
SELECT pg_temp.debe_fallar(15, 'Borrar una fila de la bitácora (trigger fn_tg_bitacora_inalterable)',
    'DELETE FROM bitacora');
SELECT pg_temp.debe_fallar(16, 'Modificar una fila de la bitácora (trigger fn_tg_bitacora_inalterable)',
    $q$UPDATE bitacora SET detalle = 'alterado'$q$);

-- 17 · RN11: borrar un paciente con citas lo DESACTIVA en lugar de eliminarlo
DO $$
DECLARE
    v_estado TEXT;
BEGIN
    DELETE FROM paciente WHERE id_paciente = 4;
    SELECT estado INTO v_estado FROM paciente WHERE id_paciente = 4;
    INSERT INTO resultado VALUES (17, 'Eliminar un paciente con citas (trigger RN11)',
        CASE WHEN v_estado = 'inactivo' THEN 'APROBADA' ELSE 'FALLIDA' END,
        CASE WHEN v_estado = 'inactivo' THEN 'No se eliminó: el paciente quedó inactivo y conserva sus citas.'
             ELSE 'El paciente fue eliminado.' END);
END;
$$;

-- 18 · RN09 y RN10: cancelar libera el horario y conserva el historial
DO $$
DECLARE
    r       RECORD;
    v_hist  INT;
BEGIN
    SELECT * INTO r FROM ref;
    UPDATE cita SET estado = 'cancelada', motivo_cancelacion = 'Prueba de cancelación' WHERE id_cita = r.id_cita;
    -- Otro paciente activo que esté libre a esa hora ocupa el horario liberado.
    INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
    SELECT p.id_paciente, 1, 1, r.fecha, r.hora_inicio, r.hora_fin
      FROM paciente p
     WHERE p.estado = 'activo' AND p.id_paciente <> 2
       AND NOT EXISTS (SELECT 1 FROM cita c WHERE c.id_paciente = p.id_paciente AND c.fecha = r.fecha
                          AND c.estado <> 'cancelada' AND c.hora_inicio < r.hora_fin AND c.hora_fin > r.hora_inicio)
     ORDER BY p.id_paciente LIMIT 1;
    SELECT count(*) INTO v_hist FROM cita_historial WHERE id_cita = r.id_cita;
    INSERT INTO resultado VALUES (18, 'Cancelar libera el horario y conserva el historial (RN09, RN10)',
        CASE WHEN v_hist = 2 THEN 'APROBADA' ELSE 'FALLIDA' END,
        format('Horario reutilizado; la cita cancelada tiene %s registros de historial', v_hist));
EXCEPTION WHEN OTHERS THEN
    INSERT INTO resultado VALUES (18, 'Cancelar libera el horario y conserva el historial (RN09, RN10)', 'FALLIDA', SQLERRM);
END;
$$;

\set QUIET off
\echo
\echo '===================== RESULTADOS ====================='
SELECT n AS "N.º", prueba AS "Prueba", estado AS "Estado", left(mensaje, 95) AS "Mensaje de PostgreSQL"
  FROM resultado ORDER BY n;

SELECT format('%s de %s pruebas aprobadas', count(*) FILTER (WHERE estado = 'APROBADA'), count(*)) AS "Resumen"
  FROM resultado;

ROLLBACK;
