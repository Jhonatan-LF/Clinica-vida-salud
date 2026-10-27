-- =====================================================================
--  Clínica Vida Salud · 05_datos_prueba.sql
--  Datos de demostración: médicos, horarios, feriados, pacientes,
--  usuarios y citas. Las fechas se calculan RESPECTO AL DÍA DE
--  INSTALACIÓN, así siempre hay "citas de hoy", citas pasadas
--  (atendidas, canceladas, inasistencias) y citas futuras.
--
--  Cuentas (contraseña Clinica2026* para todas):
--    admin         Administrador          (04_datos_iniciales.sql)
--    recepcion     Recepcionista
--    dr.perez      Médico  · Dr. Juan Pérez Rojas (Medicina General)
--    lucia.vargas  Paciente · Lucía Vargas Montaño
--
--  Notas para las pruebas del examen (backend/tests/pruebas_sistema.php):
--    - El médico 1 (Juan Pérez, Medicina General = especialidad 1) atiende
--      de lunes a viernes de 08:00 a 12:00 en citas de 30 min.
--    - Ningún feriado de prueba cae en lunes.
-- =====================================================================

SET client_min_messages = warning;
-- Permite cargar citas pasadas (el trigger RN06 las rechazaría).
SET app.carga_datos = 'si';
DO $$ BEGIN PERFORM setseed(0.2026); END $$;

-- ---------------------------------------------------------------------
--  Médicos (RF03) — el 6.º queda inactivo al final del script
-- ---------------------------------------------------------------------
INSERT INTO medico (registro_profesional, nombres, apellidos, telefono, correo) VALUES
    ('MP-SC-1021', 'Juan',          'Pérez Rojas',           '70011001', 'juan.perez@vidasalud.bo'),
    ('MP-SC-1187', 'María Fernanda','Gutiérrez Soliz',       '70011002', 'maria.gutierrez@vidasalud.bo'),
    ('MP-SC-0954', 'Carlos Alberto','Méndez Vaca',           '70011003', 'carlos.mendez@vidasalud.bo'),
    ('MP-SC-1342', 'Ana Lucía',     'Rivero Suárez',         '70011004', 'ana.rivero@vidasalud.bo'),
    ('MP-SC-1103', 'Roberto Daniel','Áñez Justiniano',       '70011005', 'roberto.anez@vidasalud.bo'),
    ('MP-SC-0876', 'Luis Fernando', 'Castro Ortiz',          '70011006', 'luis.castro@vidasalud.bo'),
    ('MP-SC-1450', 'Mauricio Javier','Soliz Antelo',         '70011007', 'mauricio.soliz@vidasalud.bo');

INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES
    (1, 1),            -- Juan Pérez: Medicina General
    (2, 1), (2, 2),    -- María Fernanda Gutiérrez: Medicina General y Pediatría
    (3, 3),            -- Carlos Méndez: Cardiología
    (4, 4),            -- Ana Lucía Rivero: Ginecología
    (5, 5),            -- Roberto Áñez: Traumatología
    (6, 2),            -- Luis Castro: Pediatría (inactivo)
    (7, 6);            -- Mauricio Soliz: Odontología

-- ---------------------------------------------------------------------
--  Horarios de atención (RF05) — día 1 = lunes … 7 = domingo
-- ---------------------------------------------------------------------
INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita)
SELECT 1, d, '08:00', '12:00', 30 FROM generate_series(1, 5) d;                -- Lun–Vie mañana

INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita) VALUES
    (2, 1, '14:00', '18:00', 20), (2, 3, '14:00', '18:00', 20), (2, 5, '14:00', '18:00', 20),
    (2, 6, '08:00', '12:00', 30), (2, 7, '08:00', '12:00', 30),                 -- guardia de fin de semana
    (3, 2, '08:00', '12:00', 30), (3, 4, '08:00', '12:00', 30), (3, 3, '15:00', '18:00', 30),
    (4, 1, '15:00', '19:00', 30), (4, 2, '15:00', '19:00', 30),
    (4, 3, '15:00', '19:00', 30), (4, 4, '15:00', '19:00', 30),
    (5, 1, '08:00', '13:00', 30), (5, 3, '08:00', '13:00', 30), (5, 5, '08:00', '13:00', 30),
    (5, 6, '09:00', '12:00', 30),
    (6, 2, '09:00', '12:00', 30), (6, 4, '09:00', '12:00', 30),
    (7, 6, '08:00', '12:00', 30);                                               -- Odontología: sábado

INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita)
SELECT 7, d, '14:00', '18:00', 30 FROM generate_series(1, 5) d;                -- Odontología: Lun–Vie tarde

-- Un bloque desactivado (para mostrar que se puede activar/desactivar).
INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita, estado)
VALUES (3, 5, '15:00', '17:00', 30, 'inactivo');

-- ---------------------------------------------------------------------
--  Feriados y ausencias (dia_no_laborable), nunca en lunes
-- ---------------------------------------------------------------------
INSERT INTO dia_no_laborable (fecha, id_medico, descripcion)
SELECT min(d)::DATE, NULL::INT, 'Aniversario de la clínica (feriado de prueba)'
  FROM generate_series(CURRENT_DATE + 12, CURRENT_DATE + 18, INTERVAL '1 day') d
 WHERE EXTRACT(ISODOW FROM d) = 3;

INSERT INTO dia_no_laborable (fecha, id_medico, descripcion)
SELECT min(d)::DATE, 3, 'Congreso Nacional de Cardiología'
  FROM generate_series(CURRENT_DATE + 3, CURRENT_DATE + 9, INTERVAL '1 day') d
 WHERE EXTRACT(ISODOW FROM d) = 4;

INSERT INTO dia_no_laborable (fecha, id_medico, descripcion)
SELECT min(d)::DATE, 5, 'Capacitación en el Hospital Japonés'
  FROM generate_series(CURRENT_DATE + 6, CURRENT_DATE + 12, INTERVAL '1 day') d
 WHERE EXTRACT(ISODOW FROM d) = 5;

-- ---------------------------------------------------------------------
--  Pacientes (RF02) con información médica (punto 3.4)
-- ---------------------------------------------------------------------
INSERT INTO paciente (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion,
                      tipo_sangre, alergias, enfermedades_cronicas, medicamentos, antecedentes) VALUES
    ('6234517',    'Lucía',          'Vargas Montaño',      '1992-03-14', 'femenino',  '71234501', 'lucia.vargas@correo.com',   'Barrio Equipetrol, calle 7 Oeste #23',
     'O+', 'Penicilina', NULL, NULL, 'Apendicectomía en 2015.'),
    ('5893210',    'Jorge Luis',     'Salvatierra Peña',    '1978-11-02', 'masculino', '71234502', 'jorge.salvatierra@correo.com', 'Av. Banzer, 4.º anillo',
     'A+', NULL, 'Hipertensión arterial', 'Losartán 50 mg', 'Padre con cardiopatía.'),
    ('7456123',    'Carla Andrea',   'Suárez Rivero',       '1995-07-21', 'femenino',  '71234503', 'carla.suarez@correo.com',   'Urb. Las Palmas, calle 3',
     'B+', 'Mariscos', NULL, NULL, NULL),
    ('4321987',    'Pedro',          'Mamani Quispe',       '1965-01-30', 'masculino', '71234504', NULL,                        'Plan 3000, UV 160',
     'O+', NULL, 'Diabetes tipo 2', 'Metformina 850 mg', NULL),
    ('8123456',    'Valeria',        'Justiniano Roca',     '2001-09-05', 'femenino',  '71234505', 'valeria.justiniano@correo.com', 'Av. Alemana, 3.er anillo',
     'AB+', NULL, NULL, NULL, NULL),
    ('9012345',    'Mateo',          'Rojas Aguilera',      '2016-04-18', 'masculino', '71234506', NULL,                        'Villa 1.º de Mayo',
     'A-', 'Ibuprofeno', 'Asma leve', 'Salbutamol inhalador', 'Prematuro de 35 semanas.'),
    ('6789012',    'Gabriela',       'Ortiz Céspedes',      '1988-12-11', 'femenino',  '71234507', 'gabriela.ortiz@correo.com', 'Barrio Sirari, calle Los Pinos',
     'O-', NULL, NULL, NULL, 'Dos cesáreas.'),
    ('5567890',    'Fernando',       'Chávez Antelo',       '1970-06-25', 'masculino', '71234508', 'fernando.chavez@correo.com', 'Av. Santos Dumont, 5.º anillo',
     'B-', NULL, 'Artrosis de rodilla', 'Paracetamol 500 mg', NULL),
    ('7890123',    'Daniela',        'Paz Saucedo',         '1999-02-08', 'femenino',  '71234509', 'daniela.paz@correo.com',    'Urb. Villa Bolívar',
     NULL, NULL, NULL, NULL, NULL),
    ('3456789',    'Rosa María',     'Flores Vaca',         '1958-08-19', 'femenino',  '71234510', NULL,                        'Barrio Hamacas',
     'A+', 'Sulfas', 'Hipotiroidismo', 'Levotiroxina 75 mcg', NULL),
    ('9234567',    'Sofía',          'Méndez Arteaga',      '2019-10-02', 'femenino',  '71234511', NULL,                        'Urb. La Colorada',
     'O+', NULL, NULL, NULL, 'Vacunas al día.'),
    ('6345678',    'Andrés',         'Gutiérrez Lijerón',   '1985-05-15', 'masculino', '71234512', 'andres.gutierrez@correo.com', 'Av. Cristo Redentor, 6.º anillo',
     'A+', NULL, NULL, NULL, 'Fractura de tibia en 2020.'),
    ('7123456-1B', 'Camila',         'Roca Vargas',         '1997-01-27', 'femenino',  '71234513', 'camila.roca@correo.com',    'Barrio Urbarí',
     'B+', NULL, NULL, NULL, NULL),
    ('4987654',    'Miguel Ángel',   'Terrazas Rivas',      '1962-03-03', 'masculino', '71234514', NULL,                        'Av. Virgen de Cotoca',
     'O+', NULL, 'Insuficiencia cardiaca', 'Enalapril 10 mg', 'Infarto en 2019.'),
    ('8456789',    'Paola',          'Aguilera Montero',    '1990-11-30', 'femenino',  '71234515', 'paola.aguilera@correo.com', 'Urb. Los Mangales',
     'A-', 'Látex', NULL, NULL, NULL),
    ('6012345',    'Ricardo',        'Saucedo Ribera',      '1982-07-07', 'masculino', '71234516', 'ricardo.saucedo@correo.com', 'Barrio Polanco',
     NULL, NULL, NULL, NULL, NULL),
    ('9345678',    'Isabella',       'Peña Coímbra',        '2012-06-12', 'femenino',  '71234517', NULL,                        'Av. Paraguá',
     'O+', NULL, NULL, NULL, NULL),
    ('5234567',    'Héctor',         'Ribera Lino',         '1975-09-09', 'masculino', '71234518', NULL,                        'Barrio Lazareto',
     'B+', NULL, 'Gastritis crónica', 'Omeprazol 20 mg', NULL),
    ('7654321',    'Natalia',        'Cuéllar Egüez',       '1993-04-04', 'femenino',  '71234519', 'natalia.cuellar@correo.com', 'Urb. Santa Rosita',
     'AB-', NULL, NULL, NULL, NULL),
    ('4123456',    'Oscar',          'Lino Moreno',         '1968-12-24', 'masculino', '71234520', NULL,                        'Barrio Cupesí',
     'O+', NULL, NULL, NULL, NULL);

-- Fechas de registro escalonadas en los últimos meses (reporte "Pacientes registrados").
UPDATE paciente SET creado_en = localtimestamp - make_interval(days => (21 - id_paciente) * 6, hours => id_paciente);

-- Un paciente no quiere compartir su información médica con el médico (preferencia de privacidad).
UPDATE paciente SET compartir_info_medica = FALSE WHERE id_paciente = 16;

-- Contactos de emergencia
INSERT INTO contacto_emergencia (id_paciente, nombre, parentesco, telefono) VALUES
    (1,  'Raúl Vargas Montaño',      'Hermano', '72100001'),
    (2,  'Elena Peña de Salvatierra','Esposa',  '72100002'),
    (4,  'Juana Quispe de Mamani',   'Esposa',  '72100004'),
    (6,  'Patricia Aguilera Rojas',  'Madre',   '72100006'),
    (10, 'Marcelo Flores Vaca',      'Hijo',    '72100010'),
    (11, 'Diego Méndez Arteaga',     'Padre',   '72100011'),
    (14, 'Lourdes Rivas de Terrazas','Esposa',  '72100014'),
    (17, 'Mónica Coímbra Peña',      'Madre',   '72100017');

-- ---------------------------------------------------------------------
--  Usuarios de demostración (contraseña: Clinica2026*)
-- ---------------------------------------------------------------------
INSERT INTO usuario (nombre_usuario, correo, password_hash, nombre_completo, id_rol, id_medico, id_paciente)
SELECT 'recepcion', 'recepcion@vidasalud.bo', '$2y$10$0yRfD1926PtAtv1lHDF9Mu/Bw3qSrEWeqCXSkfeqbB6zYPxT3Q.2u',
       'Carla Ribera Justiniano', id_rol, NULL::INT, NULL::INT FROM rol WHERE nombre = 'recepcionista'
UNION ALL
SELECT 'dr.perez', 'juan.perez@vidasalud.bo', '$2y$10$0yRfD1926PtAtv1lHDF9Mu/Bw3qSrEWeqCXSkfeqbB6zYPxT3Q.2u',
       'Dr. Juan Pérez Rojas', id_rol, 1, NULL FROM rol WHERE nombre = 'medico'
UNION ALL
SELECT 'lucia.vargas', 'lucia.vargas@correo.com', '$2y$10$0yRfD1926PtAtv1lHDF9Mu/Bw3qSrEWeqCXSkfeqbB6zYPxT3Q.2u',
       'Lucía Vargas Montaño', id_rol, NULL, 1 FROM rol WHERE nombre = 'paciente';

-- ---------------------------------------------------------------------
--  Citas (cientos), generadas alrededor de la fecha de instalación
-- ---------------------------------------------------------------------
DO $$
DECLARE
    v_recepcion INT := (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'recepcion');
    v_portal    INT := (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'lucia.vargas');
    v_motivos   TEXT[] := ARRAY['Control de rutina', 'Dolor de cabeza frecuente', 'Revisión de exámenes',
                                'Control de presión arterial', 'Malestar general y fiebre', 'Dolor de espalda',
                                'Chequeo anual', 'Seguimiento de tratamiento', 'Consulta por tos persistente',
                                'Control prenatal', 'Dolor en la rodilla', 'Certificado médico', NULL, NULL];
    v_cancelar  TEXT[] := ARRAY['El paciente avisó que no podrá asistir', 'Viaje imprevisto del paciente',
                                'El paciente pidió otra fecha', 'Problemas de transporte', 'Se sintió mejor y no requiere la consulta'];
    v_dia     DATE;
    v_slot    RECORD;
    v_prob    NUMERIC;
    v_pac     INT;
    v_esp     INT;
    v_cita    RECORD;
    v_r       NUMERIC;
    v_inicio  TIMESTAMP;
BEGIN
    -- 1. Citas fijas de la paciente de demostración (recordatorios de las próximas 48 h).
    FOR v_slot IN
        SELECT d::DATE AS fecha, m.id_medico, i.hora_inicio, i.hora_fin
          FROM generate_series(CURRENT_DATE, CURRENT_DATE + 2, INTERVAL '1 day') d
         CROSS JOIN medico m
         CROSS JOIN LATERAL fn_intervalos_medico(m.id_medico, d::DATE) i
         WHERE i.libre AND m.id_medico IN (1, 2) AND d::DATE + i.hora_inicio > localtimestamp + INTERVAL '3 hours'
         ORDER BY d, i.hora_inicio LIMIT 1
    LOOP
        INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin, modalidad, motivo,
                          id_usuario_registro, creado_en)
        VALUES (1, v_slot.id_medico, 1, v_slot.fecha, v_slot.hora_inicio, v_slot.hora_fin, 'presencial',
                'Control de rutina', v_portal, localtimestamp - INTERVAL '3 days');
    END LOOP;
    FOR v_slot IN
        SELECT d::DATE AS fecha, i.hora_inicio, i.hora_fin
          FROM generate_series(CURRENT_DATE + 5, CURRENT_DATE + 14, INTERVAL '1 day') d
         CROSS JOIN LATERAL fn_intervalos_medico(3, d::DATE) i
         WHERE i.libre ORDER BY d, i.hora_inicio LIMIT 1
    LOOP
        INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin, modalidad, motivo,
                          id_usuario_registro, creado_en)
        VALUES (1, 3, 3, v_slot.fecha, v_slot.hora_inicio, v_slot.hora_fin, 'virtual',
                'Revisión de electrocardiograma', v_portal, localtimestamp - INTERVAL '1 day');
    END LOOP;

    -- 2. Citas aleatorias: 45 días atrás hasta 21 días adelante.
    FOR v_dia IN SELECT generate_series(CURRENT_DATE - 45, CURRENT_DATE + 21, INTERVAL '1 day')::DATE LOOP
        v_prob := CASE
                      WHEN v_dia < CURRENT_DATE      THEN 0.38
                      WHEN v_dia = CURRENT_DATE      THEN 0.65
                      WHEN v_dia <= CURRENT_DATE + 7 THEN 0.35
                      ELSE 0.15
                  END;
        FOR v_slot IN
            SELECT m.id_medico, i.hora_inicio, i.hora_fin
              FROM medico m
             CROSS JOIN LATERAL fn_intervalos_medico(m.id_medico, v_dia) i
             WHERE i.libre
             ORDER BY m.id_medico, i.hora_inicio
        LOOP
            CONTINUE WHEN random() > v_prob;

            -- Paciente activo al azar que no tenga otra cita a esa hora.
            SELECT p.id_paciente INTO v_pac
              FROM paciente p
             WHERE p.estado = 'activo'
               AND NOT EXISTS (SELECT 1 FROM cita c
                                WHERE c.id_paciente = p.id_paciente AND c.fecha = v_dia AND c.estado <> 'cancelada'
                                  AND c.hora_inicio < v_slot.hora_fin AND c.hora_fin > v_slot.hora_inicio)
             ORDER BY random() LIMIT 1;
            CONTINUE WHEN v_pac IS NULL;

            SELECT id_especialidad INTO v_esp FROM medico_especialidad
             WHERE id_medico = v_slot.id_medico ORDER BY random() LIMIT 1;

            v_inicio := v_dia + v_slot.hora_inicio;
            INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin, modalidad, motivo,
                              id_usuario_registro, creado_en)
            VALUES (v_pac, v_slot.id_medico, v_esp, v_dia, v_slot.hora_inicio, v_slot.hora_fin,
                    CASE WHEN random() < 0.12 THEN 'virtual' ELSE 'presencial' END,
                    v_motivos[1 + floor(random() * array_length(v_motivos, 1))::INT],
                    CASE WHEN v_pac = 1 AND random() < 0.5 THEN v_portal ELSE v_recepcion END,
                    LEAST(localtimestamp, v_inicio) - make_interval(days => 1 + floor(random() * 10)::INT,
                                                                    hours => floor(random() * 8)::INT))
            RETURNING id_cita, fecha, hora_fin INTO v_cita;

            -- Estado según si la cita ya pasó (RF10).
            v_r := random();
            IF v_dia + v_slot.hora_fin <= localtimestamp THEN
                IF v_r < 0.72 THEN
                    UPDATE cita SET estado = 'atendida' WHERE id_cita = v_cita.id_cita;
                ELSIF v_r < 0.84 THEN
                    UPDATE cita SET estado = 'inasistencia' WHERE id_cita = v_cita.id_cita;
                ELSE
                    UPDATE cita SET estado = 'cancelada',
                                    motivo_cancelacion = v_cancelar[1 + floor(random() * array_length(v_cancelar, 1))::INT]
                     WHERE id_cita = v_cita.id_cita;
                END IF;
            ELSIF v_dia + v_slot.hora_inicio > localtimestamp THEN
                IF v_r < 0.10 THEN
                    UPDATE cita SET estado = 'cancelada',
                                    motivo_cancelacion = v_cancelar[1 + floor(random() * array_length(v_cancelar, 1))::INT]
                     WHERE id_cita = v_cita.id_cita;
                ELSIF v_dia <= CURRENT_DATE + 2 AND v_r < 0.55 THEN
                    UPDATE cita SET estado = 'confirmada' WHERE id_cita = v_cita.id_cita;
                END IF;
            ELSE
                -- En curso ahora mismo: el paciente ya llegó.
                UPDATE cita SET estado = 'confirmada' WHERE id_cita = v_cita.id_cita;
            END IF;
        END LOOP;
    END LOOP;
END;
$$;

-- El historial toma la fecha real de cada evento y la recepcionista como responsable.
UPDATE cita_historial h
   SET fecha_hora = c.creado_en,
       id_usuario = COALESCE(c.id_usuario_registro, h.id_usuario)
  FROM cita c
 WHERE c.id_cita = h.id_cita AND h.accion = 'registro';

UPDATE cita_historial h
   SET fecha_hora = CASE
                        WHEN h.estado_nuevo IN ('atendida', 'inasistencia') THEN c.fecha + c.hora_fin
                        WHEN h.estado_nuevo = 'confirmada' THEN LEAST(c.fecha + c.hora_inicio - INTERVAL '20 hours' , localtimestamp)
                        ELSE LEAST(c.creado_en + INTERVAL '1 day', c.fecha + c.hora_inicio - INTERVAL '1 hour', localtimestamp)
                    END,
       detalle = COALESCE(h.detalle, CASE h.estado_nuevo
                                         WHEN 'atendida' THEN 'Consulta realizada'
                                         WHEN 'inasistencia' THEN 'El paciente no se presentó'
                                         WHEN 'confirmada' THEN 'El paciente confirmó su asistencia'
                                     END),
       id_usuario = (SELECT id_usuario FROM usuario WHERE nombre_usuario =
                        CASE WHEN h.estado_nuevo = 'atendida' AND c.id_medico = 1 THEN 'dr.perez' ELSE 'recepcion' END)
  FROM cita c
 WHERE c.id_cita = h.id_cita AND h.accion = 'estado';

-- ---------------------------------------------------------------------
--  El Dr. Luis Castro deja la clínica: sus citas futuras se cancelan
--  (quedan en el historial) y queda INACTIVO (RN04, RN11).
-- ---------------------------------------------------------------------
UPDATE cita SET estado = 'cancelada', motivo_cancelacion = 'El médico fue dado de baja en la clínica'
 WHERE id_medico = 6 AND estado IN ('programada', 'confirmada');
UPDATE medico SET estado = 'inactivo' WHERE id_medico = 6;

-- Un paciente inactivo (se conserva su historial).
UPDATE cita SET estado = 'cancelada', motivo_cancelacion = 'Paciente desactivado por la clínica'
 WHERE id_paciente = 20 AND estado IN ('programada', 'confirmada');
UPDATE paciente SET estado = 'inactivo' WHERE id_paciente = 20;

-- Primeros registros de la bitácora.
INSERT INTO bitacora (id_usuario, usuario, accion, modulo, detalle, ip)
SELECT id_usuario, nombre_usuario, 'instalacion', 'sistema', 'Base de datos instalada con datos de prueba.', '127.0.0.1'
  FROM usuario WHERE nombre_usuario = 'admin';

RESET app.carga_datos;

-- Resumen de lo cargado
SELECT (SELECT count(*) FROM medico)   AS medicos,
       (SELECT count(*) FROM paciente) AS pacientes,
       (SELECT count(*) FROM usuario)  AS usuarios,
       (SELECT count(*) FROM cita)     AS citas,
       (SELECT count(*) FROM cita WHERE fecha = CURRENT_DATE) AS citas_hoy;
