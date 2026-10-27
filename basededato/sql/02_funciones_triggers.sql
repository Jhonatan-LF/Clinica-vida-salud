-- =====================================================================
--  Clínica Vida Salud · 02_funciones_triggers.sql
--  Funciones y triggers: las reglas de negocio se controlan también en
--  la base de datos (doble control: servidor PHP + PostgreSQL).
--
--  Variables de sesión que envía el backend en cada transacción
--  (BaseDatos::transaccion):
--    app.id_usuario  -> quién hace el cambio (para cita_historial)
--    app.detalle     -> descripción del cambio (motivo, "Cita registrada"…)
--  y solo el script de datos de prueba:
--    app.carga_datos = 'si' -> permite registrar citas pasadas (historial de ejemplo)
-- =====================================================================

SET client_min_messages = warning;

-- ---------------------------------------------------------------------
--  Funciones auxiliares
-- ---------------------------------------------------------------------

-- "  Pediatría  " -> "pediatria" : sin tildes, sin mayúsculas, sin espacios repetidos.
-- RF04 (nombre de especialidad único) y búsquedas de pacientes y médicos.
CREATE OR REPLACE FUNCTION fn_normalizar_texto(p_texto TEXT)
RETURNS TEXT
LANGUAGE sql IMMUTABLE PARALLEL SAFE
AS $$
    SELECT lower(regexp_replace(trim(translate(COALESCE(p_texto, ''),
        'ÁÉÍÓÚÜÑÀÈÌÒÙáéíóúüñàèìòù',
        'AEIOUUNAEIOUaeiouunaeiou')), '\s+', ' ', 'g'));
$$;

-- RF04: no hay dos especialidades con el mismo nombre normalizado.
CREATE UNIQUE INDEX uq_especialidad_nombre_normalizado ON especialidad (fn_normalizar_texto(nombre));

-- Valor de una variable de sesión de la aplicación (vacío si no existe).
CREATE OR REPLACE FUNCTION fn_variable_app(p_nombre TEXT)
RETURNS TEXT
LANGUAGE sql STABLE
AS $$
    SELECT COALESCE(current_setting('app.' || p_nombre, true), '');
$$;

-- Intervalos de atención de un médico en una fecha, con su situación (RF05, RF06).
-- Es la misma lógica que backend/app/paquetes/citas/servicios/Disponibilidad.php;
-- se usa en los datos de prueba y en las pruebas SQL.
CREATE OR REPLACE FUNCTION fn_intervalos_medico(p_medico INT, p_fecha DATE)
RETURNS TABLE (hora_inicio TIME, hora_fin TIME, libre BOOLEAN)
LANGUAGE sql STABLE
AS $$
    SELECT s::TIME,
           (s + make_interval(mins => h.duracion_cita))::TIME,
           NOT EXISTS (SELECT 1 FROM cita c
                        WHERE c.id_medico = p_medico AND c.fecha = p_fecha AND c.estado <> 'cancelada'
                          AND c.hora_inicio < (s + make_interval(mins => h.duracion_cita))::TIME
                          AND c.hora_fin > s::TIME)
      FROM horario_atencion h
      JOIN medico m ON m.id_medico = h.id_medico AND m.estado = 'activo'
     CROSS JOIN LATERAL generate_series(
               p_fecha + h.hora_inicio,
               p_fecha + h.hora_fin - make_interval(mins => h.duracion_cita),
               make_interval(mins => h.duracion_cita)) AS s
     WHERE h.id_medico = p_medico
       AND h.dia_semana = EXTRACT(ISODOW FROM p_fecha)
       AND h.estado = 'activo'
       AND NOT EXISTS (SELECT 1 FROM dia_no_laborable d
                        WHERE d.fecha = p_fecha AND (d.id_medico IS NULL OR d.id_medico = p_medico))
     ORDER BY 1;
$$;

-- ---------------------------------------------------------------------
--  Códigos legibles: PAC-0001, MED-0001, CM-0001 (RN01, RN02)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_asignar_codigo()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    CASE TG_TABLE_NAME
        WHEN 'paciente' THEN NEW.codigo := 'PAC-' || lpad(NEW.id_paciente::TEXT, 4, '0');
        WHEN 'medico'   THEN NEW.codigo := 'MED-' || lpad(NEW.id_medico::TEXT, 4, '0');
        WHEN 'cita'     THEN NEW.codigo := 'CM-'  || lpad(NEW.id_cita::TEXT, 4, '0');
    END CASE;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_paciente_codigo BEFORE INSERT ON paciente FOR EACH ROW EXECUTE FUNCTION fn_tg_asignar_codigo();
CREATE TRIGGER tg_medico_codigo   BEFORE INSERT ON medico   FOR EACH ROW EXECUTE FUNCTION fn_tg_asignar_codigo();
CREATE TRIGGER tg_cita_codigo     BEFORE INSERT ON cita     FOR EACH ROW EXECUTE FUNCTION fn_tg_asignar_codigo();

-- ---------------------------------------------------------------------
--  Normalizar texto: espacios, mayúsculas del CI y correos en minúscula
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_normalizar_texto()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    CASE TG_TABLE_NAME
        WHEN 'paciente' THEN
            NEW.nombres   := regexp_replace(trim(NEW.nombres), '\s+', ' ', 'g');
            NEW.apellidos := regexp_replace(trim(NEW.apellidos), '\s+', ' ', 'g');
            NEW.ci        := NULLIF(upper(replace(NEW.ci, ' ', '')), '');
            NEW.correo    := NULLIF(lower(trim(NEW.correo)), '');
            NEW.actualizado_en := localtimestamp;
        WHEN 'medico' THEN
            NEW.nombres   := regexp_replace(trim(NEW.nombres), '\s+', ' ', 'g');
            NEW.apellidos := regexp_replace(trim(NEW.apellidos), '\s+', ' ', 'g');
            NEW.registro_profesional := upper(trim(NEW.registro_profesional));
            NEW.correo    := lower(trim(NEW.correo));
        WHEN 'especialidad' THEN
            NEW.nombre    := regexp_replace(trim(NEW.nombre), '\s+', ' ', 'g');
        WHEN 'usuario' THEN
            NEW.nombre_usuario := lower(trim(NEW.nombre_usuario));
            NEW.correo    := lower(trim(NEW.correo));
    END CASE;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_paciente_normalizar     BEFORE INSERT OR UPDATE ON paciente     FOR EACH ROW EXECUTE FUNCTION fn_tg_normalizar_texto();
CREATE TRIGGER tg_medico_normalizar       BEFORE INSERT OR UPDATE ON medico       FOR EACH ROW EXECUTE FUNCTION fn_tg_normalizar_texto();
CREATE TRIGGER tg_especialidad_normalizar BEFORE INSERT OR UPDATE ON especialidad FOR EACH ROW EXECUTE FUNCTION fn_tg_normalizar_texto();
CREATE TRIGGER tg_usuario_normalizar      BEFORE INSERT OR UPDATE ON usuario      FOR EACH ROW EXECUTE FUNCTION fn_tg_normalizar_texto();

-- ---------------------------------------------------------------------
--  RN12: la cuenta de un médico o paciente debe estar vinculada a él
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_validar_usuario_rol()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_rol TEXT;
BEGIN
    SELECT nombre INTO v_rol FROM rol WHERE id_rol = NEW.id_rol;
    IF v_rol = 'medico' AND NEW.id_medico IS NULL THEN
        RAISE EXCEPTION 'RN12: un usuario con rol médico debe estar vinculado a un médico.';
    END IF;
    IF v_rol = 'paciente' AND NEW.id_paciente IS NULL THEN
        RAISE EXCEPTION 'RN12: un usuario con rol paciente debe estar vinculado a un paciente.';
    END IF;
    IF v_rol <> 'medico' THEN
        NEW.id_medico := NULL;
    END IF;
    IF v_rol <> 'paciente' THEN
        NEW.id_paciente := NULL;
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_usuario_validar_rol BEFORE INSERT OR UPDATE ON usuario
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_usuario_rol();

-- ---------------------------------------------------------------------
--  fn_tg_validar_cita: RN03–RN06 y RN08 al registrar o reprogramar
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_validar_cita()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_estado_medico   TEXT;
    v_estado_paciente TEXT;
    v_feriado         TEXT;
    v_bloque          horario_atencion%ROWTYPE;
    v_carga           BOOLEAN := fn_variable_app('carga_datos') = 'si';
BEGIN
    -- Una cita cancelada o cerrada no se vuelve a validar contra la agenda.
    IF TG_OP = 'UPDATE' AND NEW.estado IN ('cancelada', 'atendida', 'inasistencia') THEN
        RETURN NEW;
    END IF;

    -- RN04: médico activo para citas nuevas o al cambiar de médico.
    IF TG_OP = 'INSERT' OR NEW.id_medico <> OLD.id_medico THEN
        SELECT estado INTO v_estado_medico FROM medico WHERE id_medico = NEW.id_medico;
        IF v_estado_medico IS DISTINCT FROM 'activo' THEN
            RAISE EXCEPTION 'RN04: el médico está inactivo y no puede recibir nuevas citas.';
        END IF;
    END IF;

    IF TG_OP = 'INSERT' THEN
        SELECT estado INTO v_estado_paciente FROM paciente WHERE id_paciente = NEW.id_paciente;
        IF v_estado_paciente IS DISTINCT FROM 'activo' THEN
            RAISE EXCEPTION 'RN03: el paciente está inactivo y no puede recibir nuevas citas.';
        END IF;
    END IF;

    -- RN06: sin fechas u horas pasadas (salvo al cargar el historial de prueba).
    IF NOT v_carga AND (NEW.fecha + NEW.hora_inicio) <= localtimestamp THEN
        RAISE EXCEPTION 'RN06: no se pueden registrar citas en fechas u horas pasadas.';
    END IF;

    -- RN05: la fecha no puede estar bloqueada (feriado o ausencia del médico).
    SELECT descripcion INTO v_feriado FROM dia_no_laborable
     WHERE fecha = NEW.fecha AND (id_medico IS NULL OR id_medico = NEW.id_medico)
     LIMIT 1;
    IF v_feriado IS NOT NULL THEN
        RAISE EXCEPTION 'RN05: la fecha está bloqueada (%).', v_feriado;
    END IF;

    -- RN05: dentro de un bloque activo del médico para ese día de la semana.
    SELECT * INTO v_bloque FROM horario_atencion h
     WHERE h.id_medico = NEW.id_medico
       AND h.dia_semana = EXTRACT(ISODOW FROM NEW.fecha)
       AND h.estado = 'activo'
       AND NEW.hora_inicio >= h.hora_inicio
       AND NEW.hora_fin <= h.hora_fin
     LIMIT 1;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'RN05: la cita está fuera del horario de atención del médico.';
    END IF;

    -- RN08: respeta la duración y los intervalos configurados del bloque.
    IF NEW.hora_fin - NEW.hora_inicio <> make_interval(mins => v_bloque.duracion_cita)
       OR (EXTRACT(EPOCH FROM NEW.hora_inicio - v_bloque.hora_inicio)::INT % (v_bloque.duracion_cita * 60)) <> 0 THEN
        RAISE EXCEPTION 'RN08: la cita debe durar % minutos y empezar en un intervalo del horario.', v_bloque.duracion_cita;
    END IF;

    NEW.actualizado_en := localtimestamp;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_cita_validar
    BEFORE INSERT OR UPDATE OF fecha, hora_inicio, hora_fin, id_medico ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_cita();

-- ---------------------------------------------------------------------
--  RF10: transiciones de estado permitidas (igual que EstadoCita::TRANSICIONES)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_validar_transicion_cita()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.estado = OLD.estado THEN
        RETURN NEW;
    END IF;
    IF NOT (
        (OLD.estado = 'programada' AND NEW.estado IN ('confirmada', 'atendida', 'cancelada', 'inasistencia')) OR
        (OLD.estado = 'confirmada' AND NEW.estado IN ('atendida', 'cancelada', 'inasistencia'))
    ) THEN
        RAISE EXCEPTION 'RF10: transición no permitida de "%" a "%".', OLD.estado, NEW.estado;
    END IF;
    -- Las citas cerradas no cambian de fecha ni de médico en la misma operación.
    IF NEW.estado IN ('atendida', 'inasistencia', 'cancelada')
       AND (NEW.fecha <> OLD.fecha OR NEW.hora_inicio <> OLD.hora_inicio OR NEW.id_medico <> OLD.id_medico) THEN
        RAISE EXCEPTION 'RF10: una cita % no puede reprogramarse.', NEW.estado;
    END IF;
    NEW.actualizado_en := localtimestamp;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_cita_transicion
    BEFORE UPDATE OF estado ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_transicion_cita();

-- ---------------------------------------------------------------------
--  RN10: historial de cada cita (alta, cambio de estado, reprogramación)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_historial_cita()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_usuario INT  := NULLIF(fn_variable_app('id_usuario'), '')::INT;
    v_detalle TEXT := NULLIF(fn_variable_app('detalle'), '');
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO cita_historial (id_cita, accion, estado_nuevo, fecha_nueva, hora_nueva, detalle, id_usuario)
        VALUES (NEW.id_cita, 'registro', NEW.estado, NEW.fecha, NEW.hora_inicio,
                COALESCE(v_detalle, 'Cita registrada'), COALESCE(v_usuario, NEW.id_usuario_registro));
        RETURN NEW;
    END IF;

    IF NEW.fecha <> OLD.fecha OR NEW.hora_inicio <> OLD.hora_inicio THEN
        INSERT INTO cita_historial (id_cita, accion, fecha_anterior, hora_anterior, fecha_nueva, hora_nueva, detalle, id_usuario)
        VALUES (NEW.id_cita, 'reprogramacion', OLD.fecha, OLD.hora_inicio, NEW.fecha, NEW.hora_inicio,
                COALESCE(v_detalle, 'Cita reprogramada'), v_usuario);
    END IF;

    IF NEW.estado <> OLD.estado THEN
        INSERT INTO cita_historial (id_cita, accion, estado_anterior, estado_nuevo, detalle, id_usuario)
        VALUES (NEW.id_cita, 'estado', OLD.estado, NEW.estado,
                COALESCE(v_detalle, CASE WHEN NEW.estado = 'cancelada' THEN 'Cancelada: ' || NEW.motivo_cancelacion END),
                v_usuario);
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_cita_historial
    AFTER INSERT OR UPDATE ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_historial_cita();

-- ---------------------------------------------------------------------
--  RN10: las citas y su historial NO se borran (se cambia el estado)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_no_borrar()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_TABLE_NAME = 'cita' THEN
        RAISE EXCEPTION 'RN10: los registros de cita no se eliminan; cambie su estado.';
    END IF;
    RAISE EXCEPTION 'RN10: el historial de las citas no se modifica ni se elimina.';
END;
$$;

CREATE TRIGGER tg_cita_no_borrar BEFORE DELETE ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_no_borrar();
CREATE TRIGGER tg_historial_no_borrar BEFORE DELETE ON cita_historial
    FOR EACH ROW EXECUTE FUNCTION fn_tg_no_borrar();

-- ---------------------------------------------------------------------
--  RN11: un paciente o médico CON citas no se elimina: se desactiva
--  (sin citas sí puede borrarse, p. ej. un registro hecho por error)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_desactivar_en_lugar_de_borrar()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_TABLE_NAME = 'paciente' THEN
        IF EXISTS (SELECT 1 FROM cita WHERE id_paciente = OLD.id_paciente) THEN
            UPDATE paciente SET estado = 'inactivo' WHERE id_paciente = OLD.id_paciente;
            RAISE NOTICE 'RN11: el paciente % tiene citas; se desactivó en lugar de eliminarse.', OLD.codigo;
            RETURN NULL;   -- cancela el DELETE
        END IF;
    ELSIF TG_TABLE_NAME = 'medico' THEN
        IF EXISTS (SELECT 1 FROM cita WHERE id_medico = OLD.id_medico) THEN
            UPDATE medico SET estado = 'inactivo' WHERE id_medico = OLD.id_medico;
            RAISE NOTICE 'RN11: el médico % tiene citas; se desactivó en lugar de eliminarse.', OLD.codigo;
            RETURN NULL;
        END IF;
    END IF;
    RETURN OLD;
END;
$$;

CREATE TRIGGER tg_paciente_no_borrar BEFORE DELETE ON paciente
    FOR EACH ROW EXECUTE FUNCTION fn_tg_desactivar_en_lugar_de_borrar();
CREATE TRIGGER tg_medico_no_borrar BEFORE DELETE ON medico
    FOR EACH ROW EXECUTE FUNCTION fn_tg_desactivar_en_lugar_de_borrar();

-- ---------------------------------------------------------------------
--  Bitácora inalterable: solo se agregan registros (CU02)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_bitacora_inalterable()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'La bitácora no se modifica ni se borra: solo se agregan registros.';
END;
$$;

CREATE TRIGGER tg_bitacora_inalterable BEFORE UPDATE OR DELETE ON bitacora
    FOR EACH ROW EXECUTE FUNCTION fn_tg_bitacora_inalterable();
