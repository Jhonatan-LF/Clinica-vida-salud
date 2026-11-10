-- =====================================================================
--  ClÃ­nica Vida Salud Â· Sistema web de gestiÃ³n de citas mÃ©dicas
--  01_esquema.sql â€” tipos, tablas, claves, CHECK, UNIQUE, Ã­ndices y EXCLUDE
--
--  Orden de instalaciÃ³n: 01 â†’ 02 â†’ 03 â†’ 04 â†’ 05 (ver LEEME.md)
--  Todas las claves primarias se llaman id_<tabla>.
--  Los nombres de las restricciones son los que traduce
--  backend/app/nucleo/ManejadorErrores.php a mensajes para el usuario.
-- =====================================================================

SET client_encoding = 'UTF8';
SET client_min_messages = warning;

-- btree_gist permite combinar "=" con "&&" en las restricciones EXCLUDE
-- (un mÃ©dico no puede tener dos citas que se crucen el mismo dÃ­a).
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- ---------------------------------------------------------------------
--  TIPOS
-- ---------------------------------------------------------------------

-- Estado de cualquier registro maestro (paciente, mÃ©dico, usuarioâ€¦).
CREATE DOMAIN dom_estado_registro AS VARCHAR(10)
    NOT NULL DEFAULT 'activo'
    CHECK (VALUE IN ('activo', 'inactivo'));

-- Estados de la cita (RF10), en minÃºscula.
CREATE DOMAIN dom_estado_cita AS VARCHAR(15)
    NOT NULL DEFAULT 'programada'
    CHECK (VALUE IN ('programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'));

-- TelÃ©fono: dÃ­gitos, espacios, "+" y "-" (7 a 20 caracteres).
CREATE DOMAIN dom_telefono AS VARCHAR(20)
    CHECK (VALUE ~ '^[0-9+\- ]{7,20}$');

-- Correo electrÃ³nico bÃ¡sico, siempre en minÃºscula.
CREATE DOMAIN dom_correo AS VARCHAR(120)
    CHECK (VALUE ~ '^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$');

-- Rango de horas de un dÃ­a (08:00â€“08:30). Lo usan las restricciones
-- EXCLUDE para detectar horarios y citas superpuestos.
CREATE TYPE rango_hora AS RANGE (subtype = TIME);

-- ---------------------------------------------------------------------
--  SEGURIDAD: rol, permiso, rol_permiso, usuario, bitacora
-- ---------------------------------------------------------------------

CREATE TABLE rol (
    id_rol      INT GENERATED ALWAYS AS IDENTITY,
    nombre      VARCHAR(30)  NOT NULL,
    descripcion VARCHAR(200),
    CONSTRAINT pk_rol PRIMARY KEY (id_rol),
    CONSTRAINT uq_rol_nombre UNIQUE (nombre)
);

CREATE TABLE permiso (
    id_permiso  INT GENERATED ALWAYS AS IDENTITY,
    codigo      VARCHAR(40)  NOT NULL,
    descripcion VARCHAR(200) NOT NULL,
    modulo      VARCHAR(30)  NOT NULL,
    CONSTRAINT pk_permiso PRIMARY KEY (id_permiso),
    CONSTRAINT uq_permiso_codigo UNIQUE (codigo),
    CONSTRAINT ck_permiso_codigo CHECK (codigo ~ '^[a-z_]+\.[a-z_]+$')
);

CREATE TABLE rol_permiso (
    id_rol     INT NOT NULL,
    id_permiso INT NOT NULL,
    CONSTRAINT pk_rol_permiso PRIMARY KEY (id_rol, id_permiso),
    CONSTRAINT fk_rol_permiso_rol     FOREIGN KEY (id_rol)     REFERENCES rol (id_rol)         ON DELETE CASCADE,
    CONSTRAINT fk_rol_permiso_permiso FOREIGN KEY (id_permiso) REFERENCES permiso (id_permiso) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
--  MÃ‰DICOS: especialidad, medico, medico_especialidad,
--           horario_atencion, dia_no_laborable
-- ---------------------------------------------------------------------

CREATE TABLE especialidad (
    id_especialidad INT GENERATED ALWAYS AS IDENTITY,
    nombre          VARCHAR(80)  NOT NULL,
    descripcion     VARCHAR(250),
    color           VARCHAR(7)   NOT NULL DEFAULT '#2563c9',   -- color en la agenda (#RRGGBB)
    estado          dom_estado_registro,
    creado_en       TIMESTAMP NOT NULL DEFAULT localtimestamp,
    CONSTRAINT pk_especialidad PRIMARY KEY (id_especialidad),
    CONSTRAINT ck_especialidad_nombre CHECK (length(trim(nombre)) >= 3),
    CONSTRAINT ck_especialidad_color  CHECK (color ~ '^#[0-9a-fA-F]{6}$')
);
-- RF04: nombre Ãºnico NORMALIZADO (Ã­ndice uq_especialidad_nombre_normalizado,
-- se crea en 02_funciones_triggers.sql porque usa fn_normalizar_texto).

CREATE TABLE medico (
    id_medico            INT GENERATED ALWAYS AS IDENTITY,
    codigo               VARCHAR(12)  NOT NULL,              -- MED-0001 (lo asigna un trigger)
    registro_profesional VARCHAR(30)  NOT NULL,
    nombres              VARCHAR(60)  NOT NULL,
    apellidos            VARCHAR(60)  NOT NULL,
    telefono             dom_telefono NOT NULL,
    correo               dom_correo   NOT NULL,
    estado               dom_estado_registro,
    creado_en            TIMESTAMP NOT NULL DEFAULT localtimestamp,
    CONSTRAINT pk_medico PRIMARY KEY (id_medico),
    CONSTRAINT uq_medico_codigo   UNIQUE (codigo),           -- RN02
    CONSTRAINT uq_medico_registro UNIQUE (registro_profesional),
    CONSTRAINT uq_medico_correo   UNIQUE (correo)
);

CREATE TABLE medico_especialidad (
    id_medico       INT NOT NULL,
    id_especialidad INT NOT NULL,
    CONSTRAINT pk_medico_especialidad PRIMARY KEY (id_medico, id_especialidad),
    CONSTRAINT fk_me_medico       FOREIGN KEY (id_medico)       REFERENCES medico (id_medico),
    CONSTRAINT fk_me_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidad (id_especialidad)
);

-- RF05: bloques de atenciÃ³n por mÃ©dico y dÃ­a de la semana (1 = lunes â€¦ 7 = domingo).
CREATE TABLE horario_atencion (
    id_horario    INT GENERATED ALWAYS AS IDENTITY,
    id_medico     INT      NOT NULL,
    dia_semana    SMALLINT NOT NULL,
    hora_inicio   TIME     NOT NULL,
    hora_fin      TIME     NOT NULL,
    duracion_cita SMALLINT NOT NULL DEFAULT 30,             -- RN08: minutos por cita
    estado        dom_estado_registro,
    CONSTRAINT pk_horario_atencion PRIMARY KEY (id_horario),
    CONSTRAINT fk_horario_medico   FOREIGN KEY (id_medico) REFERENCES medico (id_medico),
    CONSTRAINT ck_horario_dia      CHECK (dia_semana BETWEEN 1 AND 7),
    CONSTRAINT ck_horario_rango    CHECK (hora_fin > hora_inicio),
    CONSTRAINT ck_horario_duracion CHECK (duracion_cita IN (15, 20, 30, 45, 60)),
    CONSTRAINT ck_horario_tamano_bloque  CHECK (hora_fin - hora_inicio >= make_interval(mins => duracion_cita)),
    -- Un mÃ©dico no tiene dos bloques ACTIVOS que se crucen el mismo dÃ­a.
    CONSTRAINT ex_horario_sin_solape EXCLUDE USING gist (
        id_medico WITH =,
        dia_semana WITH =,
        rango_hora(hora_inicio, hora_fin) WITH &&
    ) WHERE (estado = 'activo')
);

-- Feriados (id_medico NULL = toda la clÃ­nica) y ausencias de un mÃ©dico.
CREATE TABLE dia_no_laborable (
    id_dia_no_laborable INT GENERATED ALWAYS AS IDENTITY,
    fecha               DATE         NOT NULL,
    id_medico           INT,
    descripcion         VARCHAR(120) NOT NULL,
    creado_en           TIMESTAMP NOT NULL DEFAULT localtimestamp,
    CONSTRAINT pk_dia_no_laborable PRIMARY KEY (id_dia_no_laborable),
    CONSTRAINT fk_dnl_medico FOREIGN KEY (id_medico) REFERENCES medico (id_medico)
);
-- La misma fecha no se bloquea dos veces para el mismo alcance.
CREATE UNIQUE INDEX uq_dia_no_laborable ON dia_no_laborable (fecha, COALESCE(id_medico, 0));

-- ---------------------------------------------------------------------
--  PACIENTES: paciente, contacto_emergencia
-- ---------------------------------------------------------------------

CREATE TABLE paciente (
    id_paciente           INT GENERATED ALWAYS AS IDENTITY,
    codigo                VARCHAR(12) NOT NULL,              -- PAC-0001 (lo asigna un trigger)
    ci                    VARCHAR(15),                        -- opcional (p. ej. un bebÃ©), pero Ãºnico
    nombres               VARCHAR(60) NOT NULL,
    apellidos             VARCHAR(60) NOT NULL,
    fecha_nacimiento      DATE        NOT NULL,
    sexo                  VARCHAR(10) NOT NULL,
    telefono              dom_telefono NOT NULL,
    correo                dom_correo,
    direccion             VARCHAR(200),
    -- InformaciÃ³n mÃ©dica del perfil (punto 3.4 del examen)
    tipo_sangre           VARCHAR(3),
    alergias              VARCHAR(250),
    enfermedades_cronicas VARCHAR(250),
    medicamentos          VARCHAR(250),
    antecedentes          VARCHAR(500),
    -- Preferencias de la cuenta
    recibir_recordatorios BOOLEAN NOT NULL DEFAULT TRUE,
    compartir_info_medica BOOLEAN NOT NULL DEFAULT TRUE,     -- el mÃ©dico ve la informaciÃ³n mÃ©dica
    estado                dom_estado_registro,
    creado_en             TIMESTAMP NOT NULL DEFAULT localtimestamp,
    actualizado_en        TIMESTAMP NOT NULL DEFAULT localtimestamp,
    CONSTRAINT pk_paciente PRIMARY KEY (id_paciente),
    CONSTRAINT uq_paciente_codigo UNIQUE (codigo),           -- RN01
    CONSTRAINT uq_paciente_ci     UNIQUE (ci),               -- RN01
    CONSTRAINT ck_paciente_ci     CHECK (ci ~ '^[0-9]{5,12}([-A-Z0-9]{1,3})?$'),
    CONSTRAINT ck_paciente_sexo   CHECK (sexo IN ('femenino', 'masculino', 'otro')),
    CONSTRAINT ck_paciente_nacimiento CHECK (fecha_nacimiento BETWEEN DATE '1900-01-01' AND CURRENT_DATE),
    CONSTRAINT ck_paciente_sangre CHECK (tipo_sangre IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'))
);
CREATE INDEX ix_paciente_apellidos ON paciente (apellidos, nombres);

CREATE TABLE contacto_emergencia (
    id_contacto INT GENERATED ALWAYS AS IDENTITY,
    id_paciente INT          NOT NULL,
    nombre      VARCHAR(120) NOT NULL,
    parentesco  VARCHAR(40)  NOT NULL,
    telefono    dom_telefono NOT NULL,
    creado_en   TIMESTAMP NOT NULL DEFAULT localtimestamp,
    CONSTRAINT pk_contacto_emergencia PRIMARY KEY (id_contacto),
    CONSTRAINT fk_contacto_paciente FOREIGN KEY (id_paciente) REFERENCES paciente (id_paciente) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
--  USUARIO (va despuÃ©s de mÃ©dico y paciente porque puede vincularse a ellos)
-- ---------------------------------------------------------------------

CREATE TABLE usuario (
    id_usuario        INT GENERATED ALWAYS AS IDENTITY,
    nombre_usuario    VARCHAR(40)  NOT NULL,
    correo            dom_correo   NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,                 -- password_hash() de PHP (bcrypt)
    nombre_completo   VARCHAR(120) NOT NULL,
    id_rol            INT          NOT NULL,
    id_medico         INT,                                   -- solo rol mÃ©dico
    id_paciente       INT,                                   -- solo rol paciente
    estado            dom_estado_registro,
    intentos_fallidos SMALLINT     NOT NULL DEFAULT 0,       -- RF01: bloqueo tras 5 intentos
    bloqueado_hasta   TIMESTAMPTZ,
    ultimo_acceso     TIMESTAMPTZ,
    creado_en         TIMESTAMP NOT NULL DEFAULT localtimestamp,
    CONSTRAINT pk_usuario PRIMARY KEY (id_usuario),
    CONSTRAINT uq_usuario_nombre   UNIQUE (nombre_usuario),
    CONSTRAINT uq_usuario_correo   UNIQUE (correo),
    CONSTRAINT uq_usuario_medico   UNIQUE (id_medico),
    CONSTRAINT uq_usuario_paciente UNIQUE (id_paciente),
    CONSTRAINT ck_usuario_nombre   CHECK (nombre_usuario ~ '^[a-z0-9._]{4,40}$'),
    CONSTRAINT ck_usuario_intentos CHECK (intentos_fallidos >= 0),
    CONSTRAINT fk_usuario_rol      FOREIGN KEY (id_rol)      REFERENCES rol (id_rol),
    CONSTRAINT fk_usuario_medico   FOREIGN KEY (id_medico)   REFERENCES medico (id_medico),
    CONSTRAINT fk_usuario_paciente FOREIGN KEY (id_paciente) REFERENCES paciente (id_paciente)
);

-- BitÃ¡cora de acciones (CU02). Nunca guarda contraseÃ±as ni formularios.
CREATE TABLE bitacora (
    id_bitacora BIGINT GENERATED ALWAYS AS IDENTITY,
    fecha_hora  TIMESTAMP    NOT NULL DEFAULT localtimestamp,
    id_usuario  INT,
    usuario     VARCHAR(120) NOT NULL,                       -- se conserva aunque cambie el usuario
    accion      VARCHAR(40)  NOT NULL,
    modulo      VARCHAR(30)  NOT NULL,
    detalle     VARCHAR(1000),
    ip          VARCHAR(45)  NOT NULL,
    CONSTRAINT pk_bitacora PRIMARY KEY (id_bitacora),
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE SET NULL
);
CREATE INDEX ix_bitacora_fecha ON bitacora (fecha_hora DESC);

-- ---------------------------------------------------------------------
--  CITAS: cita, cita_historial
-- ---------------------------------------------------------------------

CREATE TABLE cita (
    id_cita             INT GENERATED ALWAYS AS IDENTITY,
    codigo              VARCHAR(12) NOT NULL,                -- CM-0001 (lo asigna un trigger)
    id_paciente         INT  NOT NULL,
    id_medico           INT  NOT NULL,
    id_especialidad     INT  NOT NULL,
    fecha               DATE NOT NULL,
    hora_inicio         TIME NOT NULL,
    hora_fin            TIME NOT NULL,
    modalidad           VARCHAR(12) NOT NULL DEFAULT 'presencial',
    motivo              VARCHAR(250),
    estado              dom_estado_cita,
    motivo_cancelacion  VARCHAR(250),
    id_usuario_registro INT,
    creado_en           TIMESTAMP NOT NULL DEFAULT localtimestamp,
    actualizado_en      TIMESTAMP NOT NULL DEFAULT localtimestamp,
    CONSTRAINT pk_cita PRIMARY KEY (id_cita),
    CONSTRAINT uq_cita_codigo UNIQUE (codigo),
    -- RN03: paciente y mÃ©dico vÃ¡lidos. RN11: no se borra un paciente/mÃ©dico con citas (RESTRICT).
    CONSTRAINT fk_cita_paciente FOREIGN KEY (id_paciente) REFERENCES paciente (id_paciente) ON DELETE RESTRICT,
    CONSTRAINT fk_cita_medico   FOREIGN KEY (id_medico)   REFERENCES medico (id_medico)     ON DELETE RESTRICT,
    -- RN03: el mÃ©dico debe atender la especialidad de la cita (FK compuesta).
    CONSTRAINT fk_cita_medico_especialidad FOREIGN KEY (id_medico, id_especialidad)
        REFERENCES medico_especialidad (id_medico, id_especialidad) ON DELETE RESTRICT,
    CONSTRAINT fk_cita_usuario FOREIGN KEY (id_usuario_registro) REFERENCES usuario (id_usuario) ON DELETE SET NULL,
    CONSTRAINT ck_cita_rango     CHECK (hora_fin > hora_inicio),
    CONSTRAINT ck_cita_modalidad CHECK (modalidad IN ('presencial', 'virtual')),
    -- RF09: una cita cancelada siempre tiene motivo.
    CONSTRAINT ck_cita_cancelada CHECK (estado <> 'cancelada' OR motivo_cancelacion IS NOT NULL),
    -- RF07 / RN07: Ãºltima barrera contra citas superpuestas.
    -- RN09: las canceladas NO ocupan horario (quedan fuera del WHERE).
    CONSTRAINT ex_cita_medico_sin_solape EXCLUDE USING gist (
        id_medico WITH =,
        fecha WITH =,
        rango_hora(hora_inicio, hora_fin) WITH &&
    ) WHERE (estado <> 'cancelada'),
    CONSTRAINT ex_cita_paciente_sin_solape EXCLUDE USING gist (
        id_paciente WITH =,
        fecha WITH =,
        rango_hora(hora_inicio, hora_fin) WITH &&
    ) WHERE (estado <> 'cancelada')
);
CREATE INDEX ix_cita_fecha_medico ON cita (fecha, id_medico);
CREATE INDEX ix_cita_paciente     ON cita (id_paciente, fecha DESC);
CREATE INDEX ix_cita_estado       ON cita (estado);

-- RN10: trazabilidad de cada cita (alta, cambios de estado y reprogramaciones).
CREATE TABLE cita_historial (
    id_historial    BIGINT GENERATED ALWAYS AS IDENTITY,
    id_cita         INT         NOT NULL,
    accion          VARCHAR(20) NOT NULL,                    -- registro | estado | reprogramacion
    estado_anterior VARCHAR(15),
    estado_nuevo    VARCHAR(15),
    fecha_anterior  DATE,
    hora_anterior   TIME,
    fecha_nueva     DATE,
    hora_nueva      TIME,
    detalle         VARCHAR(300),
    id_usuario      INT,
    fecha_hora      TIMESTAMP NOT NULL DEFAULT localtimestamp,
    CONSTRAINT pk_cita_historial PRIMARY KEY (id_historial),
    CONSTRAINT fk_historial_cita    FOREIGN KEY (id_cita)    REFERENCES cita (id_cita) ON DELETE RESTRICT,
    CONSTRAINT fk_historial_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE SET NULL,
    CONSTRAINT ck_historial_accion  CHECK (accion IN ('registro', 'estado', 'reprogramacion'))
);
CREATE INDEX ix_historial_cita ON cita_historial (id_cita, fecha_hora);
-- =====================================================================
--  ClÃ­nica Vida Salud Â· 02_funciones_triggers.sql
--  Funciones y triggers: las reglas de negocio se controlan tambiÃ©n en
--  la base de datos (doble control: servidor PHP + PostgreSQL).
--
--  Variables de sesiÃ³n que envÃ­a el backend en cada transacciÃ³n
--  (BaseDatos::transaccion):
--    app.id_usuario  -> quiÃ©n hace el cambio (para cita_historial)
--    app.detalle     -> descripciÃ³n del cambio (motivo, "Cita registrada"â€¦)
--  y solo el script de datos de prueba:
--    app.carga_datos = 'si' -> permite registrar citas pasadas (historial de ejemplo)
-- =====================================================================

SET client_min_messages = warning;

-- ---------------------------------------------------------------------
--  Funciones auxiliares
-- ---------------------------------------------------------------------

-- "  PediatrÃ­a  " -> "pediatria" : sin tildes, sin mayÃºsculas, sin espacios repetidos.
-- RF04 (nombre de especialidad Ãºnico) y bÃºsquedas de pacientes y mÃ©dicos.
CREATE OR REPLACE FUNCTION fn_normalizar_texto(p_texto TEXT)
RETURNS TEXT
LANGUAGE sql IMMUTABLE PARALLEL SAFE
AS $$
    SELECT lower(regexp_replace(trim(translate(COALESCE(p_texto, ''),
        'ÃÃ‰ÃÃ“ÃšÃœÃ‘Ã€ÃˆÃŒÃ’Ã™Ã¡Ã©Ã­Ã³ÃºÃ¼Ã±Ã Ã¨Ã¬Ã²Ã¹',
        'AEIOUUNAEIOUaeiouunaeiou')), '\s+', ' ', 'g'));
$$;

-- RF04: no hay dos especialidades con el mismo nombre normalizado.
CREATE UNIQUE INDEX uq_especialidad_nombre_normalizado ON especialidad (fn_normalizar_texto(nombre));

-- Valor de una variable de sesiÃ³n de la aplicaciÃ³n (vacÃ­o si no existe).
CREATE OR REPLACE FUNCTION fn_variable_app(p_nombre TEXT)
RETURNS TEXT
LANGUAGE sql STABLE
AS $$
    SELECT COALESCE(current_setting('app.' || p_nombre, true), '');
$$;

-- Intervalos de atenciÃ³n de un mÃ©dico en una fecha, con su situaciÃ³n (RF05, RF06).
-- Es la misma lÃ³gica que backend/app/paquetes/citas/servicios/Disponibilidad.php;
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
--  CÃ³digos legibles: PAC-0001, MED-0001, CM-0001 (RN01, RN02)
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
--  Normalizar texto: espacios, mayÃºsculas del CI y correos en minÃºscula
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
--  RN12: la cuenta de un mÃ©dico o paciente debe estar vinculada a Ã©l
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
        RAISE EXCEPTION 'RN12: un usuario con rol mÃ©dico debe estar vinculado a un mÃ©dico.';
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
--  fn_tg_validar_cita: RN03â€“RN06 y RN08 al registrar o reprogramar
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

    -- RN04: mÃ©dico activo para citas nuevas o al cambiar de mÃ©dico.
    IF TG_OP = 'INSERT' OR NEW.id_medico <> OLD.id_medico THEN
        SELECT estado INTO v_estado_medico FROM medico WHERE id_medico = NEW.id_medico;
        IF v_estado_medico IS DISTINCT FROM 'activo' THEN
            RAISE EXCEPTION 'RN04: el mÃ©dico estÃ¡ inactivo y no puede recibir nuevas citas.';
        END IF;
    END IF;

    IF TG_OP = 'INSERT' THEN
        SELECT estado INTO v_estado_paciente FROM paciente WHERE id_paciente = NEW.id_paciente;
        IF v_estado_paciente IS DISTINCT FROM 'activo' THEN
            RAISE EXCEPTION 'RN03: el paciente estÃ¡ inactivo y no puede recibir nuevas citas.';
        END IF;
    END IF;

    -- RN06: sin fechas u horas pasadas (salvo al cargar el historial de prueba).
    IF NOT v_carga AND (NEW.fecha + NEW.hora_inicio) <= localtimestamp THEN
        RAISE EXCEPTION 'RN06: no se pueden registrar citas en fechas u horas pasadas.';
    END IF;

    -- RN05: la fecha no puede estar bloqueada (feriado o ausencia del mÃ©dico).
    SELECT descripcion INTO v_feriado FROM dia_no_laborable
     WHERE fecha = NEW.fecha AND (id_medico IS NULL OR id_medico = NEW.id_medico)
     LIMIT 1;
    IF v_feriado IS NOT NULL THEN
        RAISE EXCEPTION 'RN05: la fecha estÃ¡ bloqueada (%).', v_feriado;
    END IF;

    -- RN05: dentro de un bloque activo del mÃ©dico para ese dÃ­a de la semana.
    SELECT * INTO v_bloque FROM horario_atencion h
     WHERE h.id_medico = NEW.id_medico
       AND h.dia_semana = EXTRACT(ISODOW FROM NEW.fecha)
       AND h.estado = 'activo'
       AND NEW.hora_inicio >= h.hora_inicio
       AND NEW.hora_fin <= h.hora_fin
     LIMIT 1;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'RN05: la cita estÃ¡ fuera del horario de atenciÃ³n del mÃ©dico.';
    END IF;

    -- RN08: respeta la duraciÃ³n y los intervalos configurados del bloque.
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
        RAISE EXCEPTION 'RF10: transiciÃ³n no permitida de "%" a "%".', OLD.estado, NEW.estado;
    END IF;
    -- Las citas cerradas no cambian de fecha ni de mÃ©dico en la misma operaciÃ³n.
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
--  RN10: historial de cada cita (alta, cambio de estado, reprogramaciÃ³n)
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
--  RN11: un paciente o mÃ©dico CON citas no se elimina: se desactiva
--  (sin citas sÃ­ puede borrarse, p. ej. un registro hecho por error)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_desactivar_en_lugar_de_borrar()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_TABLE_NAME = 'paciente' THEN
        IF EXISTS (SELECT 1 FROM cita WHERE id_paciente = OLD.id_paciente) THEN
            UPDATE paciente SET estado = 'inactivo' WHERE id_paciente = OLD.id_paciente;
            RAISE NOTICE 'RN11: el paciente % tiene citas; se desactivÃ³ en lugar de eliminarse.', OLD.codigo;
            RETURN NULL;   -- cancela el DELETE
        END IF;
    ELSIF TG_TABLE_NAME = 'medico' THEN
        IF EXISTS (SELECT 1 FROM cita WHERE id_medico = OLD.id_medico) THEN
            UPDATE medico SET estado = 'inactivo' WHERE id_medico = OLD.id_medico;
            RAISE NOTICE 'RN11: el mÃ©dico % tiene citas; se desactivÃ³ en lugar de eliminarse.', OLD.codigo;
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
--  BitÃ¡cora inalterable: solo se agregan registros (CU02)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_bitacora_inalterable()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'La bitÃ¡cora no se modifica ni se borra: solo se agregan registros.';
END;
$$;

CREATE TRIGGER tg_bitacora_inalterable BEFORE UPDATE OR DELETE ON bitacora
    FOR EACH ROW EXECUTE FUNCTION fn_tg_bitacora_inalterable();
-- =====================================================================
--  ClÃ­nica Vida Salud Â· 03_vistas.sql
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

-- MÃ©dico con sus especialidades en una sola fila (RF03).
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

-- Totales de citas por dÃ­a y estado (panel y reporte de asistencia).
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
-- =====================================================================
--  ClÃ­nica Vida Salud Â· 04_datos_iniciales.sql
--  Datos mÃ­nimos para que el sistema funcione: roles, permisos,
--  permisos por rol, especialidades y la cuenta del administrador.
--
--  ContraseÃ±a de las cuentas de demostraciÃ³n: Clinica2026*
--  (se guarda solo su hash bcrypt, generado con password_hash() de PHP)
-- =====================================================================

-- ---------------------------------------------------------------------
--  Roles
-- ---------------------------------------------------------------------
INSERT INTO rol (nombre, descripcion) VALUES
    ('administrador', 'Acceso total: usuarios, permisos, registros, citas, reportes y bitÃ¡cora.'),
    ('recepcionista', 'Registra pacientes y programa, reprograma, confirma y cancela citas.'),
    ('medico',        'Consulta su propia agenda, registra la atenciÃ³n y ve el historial de sus pacientes.'),
    ('paciente',      'Portal del paciente: perfil, reserva en lÃ­nea y seguimiento de sus citas.');

-- ---------------------------------------------------------------------
--  Permisos (RN12: cada operaciÃ³n exige su permiso)
-- ---------------------------------------------------------------------
INSERT INTO permiso (codigo, descripcion, modulo) VALUES
    ('usuarios.gestionar',       'Registrar usuarios, asignar roles y definir permisos',          'seguridad'),
    ('bitacora.ver',             'Consultar la bitÃ¡cora de acciones',                              'seguridad'),
    ('pacientes.ver',            'Listar, buscar y ver la ficha de los pacientes',                 'pacientes'),
    ('pacientes.gestionar',      'Registrar, modificar y desactivar pacientes',                    'pacientes'),
    ('pacientes.historial',      'Consultar el historial de citas de un paciente',                 'pacientes'),
    ('medicos.ver',              'Listar, buscar y ver la ficha de los mÃ©dicos',                   'medicos'),
    ('medicos.gestionar',        'Registrar, modificar y desactivar mÃ©dicos',                      'medicos'),
    ('especialidades.gestionar', 'Registrar, modificar y desactivar especialidades',               'medicos'),
    ('horarios.gestionar',       'Definir horarios de atenciÃ³n, feriados y ausencias',             'medicos'),
    ('citas.ver',                'Listar y consultar las citas de la clÃ­nica',                     'citas'),
    ('citas.programar',          'Programar citas para los pacientes',                             'citas'),
    ('citas.reprogramar',        'Cambiar la fecha u hora de una cita',                            'citas'),
    ('citas.cancelar',           'Cancelar citas indicando el motivo',                             'citas'),
    ('citas.confirmar',          'Confirmar la asistencia o registrar la inasistencia',            'citas'),
    ('citas.atender',            'Marcar una cita como atendida',                                  'citas'),
    ('agenda.ver',               'Ver la agenda de cualquier mÃ©dico',                              'agenda'),
    ('agenda.propia',            'Ver solo la agenda propia (mÃ©dico)',                             'agenda'),
    ('reportes.ver',             'Generar y exportar reportes',                                    'reportes'),
    ('portal.perfil',            'Ver y actualizar el perfil propio (paciente)',                   'portal'),
    ('portal.reservar',          'Reservar citas en lÃ­nea (paciente)',                             'portal'),
    ('portal.mis_citas',         'Ver y cancelar las citas propias (paciente)',                    'portal');

-- ---------------------------------------------------------------------
--  Permisos por rol (se pueden cambiar en "Permisos por rol")
-- ---------------------------------------------------------------------

-- Administrador: todo (salvo el portal del paciente, que exige una cuenta vinculada a un paciente)
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM rol r CROSS JOIN permiso p
 WHERE r.nombre = 'administrador' AND p.modulo <> 'portal';

-- Recepcionista: pacientes, citas, agenda y panel
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM rol r JOIN permiso p ON p.codigo IN (
    'pacientes.ver', 'pacientes.gestionar', 'pacientes.historial', 'medicos.ver',
    'citas.ver', 'citas.programar', 'citas.reprogramar', 'citas.cancelar', 'citas.confirmar',
    'agenda.ver')
 WHERE r.nombre = 'recepcionista';

-- MÃ©dico: su agenda, marcar como atendida o inasistencia (solo sus citas) e historial de sus pacientes
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM rol r JOIN permiso p ON p.codigo IN (
    'agenda.propia', 'citas.atender', 'citas.confirmar', 'pacientes.historial')
 WHERE r.nombre = 'medico';

-- Paciente: portal
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM rol r JOIN permiso p ON p.codigo IN (
    'portal.perfil', 'portal.reservar', 'portal.mis_citas')
 WHERE r.nombre = 'paciente';

-- ---------------------------------------------------------------------
--  Especialidades (RF04)
-- ---------------------------------------------------------------------
-- Cada especialidad tiene su color en la agenda (se cambia en la pantalla Especialidades).
INSERT INTO especialidad (nombre, descripcion, color) VALUES
    ('Medicina General', 'Consulta general, control de salud y derivaciÃ³n a especialistas.',   '#2563c9'),  -- azul
    ('PediatrÃ­a',        'AtenciÃ³n de niÃ±os y adolescentes, control de crecimiento y vacunas.', '#0ea5e9'),  -- celeste
    ('CardiologÃ­a',      'PrevenciÃ³n, diagnÃ³stico y control de enfermedades del corazÃ³n.',      '#e05252'),  -- rojo suave
    ('GinecologÃ­a',      'Salud de la mujer, control ginecolÃ³gico y prenatal.',                 '#db5f9a'),  -- rosado
    ('TraumatologÃ­a',    'Lesiones de huesos, mÃºsculos y articulaciones.',                      '#8b5cf6'),  -- morado
    ('OdontologÃ­a',      'Salud bucal: limpiezas, caries, extracciones y control dental.',      '#14b8a6');  -- verde agua

-- ---------------------------------------------------------------------
--  Administrador del sistema (usuario: admin Â· contraseÃ±a: Clinica2026*)
-- ---------------------------------------------------------------------
INSERT INTO usuario (nombre_usuario, correo, password_hash, nombre_completo, id_rol)
SELECT 'admin', 'ronaldcarrillocallisaya@gmail.com',
       '$2y$10$0yRfD1926PtAtv1lHDF9Mu/Bw3qSrEWeqCXSkfeqbB6zYPxT3Q.2u',
       'Administrador del Sistema', id_rol
  FROM rol WHERE nombre = 'administrador';
-- =====================================================================
--  ClÃ­nica Vida Salud Â· 05_datos_prueba.sql
--  Datos de demostraciÃ³n: mÃ©dicos, horarios, feriados, pacientes,
--  usuarios y citas. Las fechas se calculan RESPECTO AL DÃA DE
--  INSTALACIÃ“N, asÃ­ siempre hay "citas de hoy", citas pasadas
--  (atendidas, canceladas, inasistencias) y citas futuras.
--
--  Cuentas (contraseÃ±a Clinica2026* para todas):
--    admin         Administrador          (04_datos_iniciales.sql)
--    recepcion     Recepcionista
--    dr.perez      MÃ©dico  Â· Dr. Juan PÃ©rez Rojas (Medicina General)
--    lucia.vargas  Paciente Â· LucÃ­a Vargas MontaÃ±o
--
--  Notas para las pruebas del examen (backend/tests/pruebas_sistema.php):
--    - El mÃ©dico 1 (Juan PÃ©rez, Medicina General = especialidad 1) atiende
--      de lunes a viernes de 08:00 a 12:00 en citas de 30 min.
--    - NingÃºn feriado de prueba cae en lunes.
-- =====================================================================

SET client_min_messages = warning;
-- Permite cargar citas pasadas (el trigger RN06 las rechazarÃ­a).
SET app.carga_datos = 'si';
DO $$ BEGIN PERFORM setseed(0.2026); END $$;

-- ---------------------------------------------------------------------
--  MÃ©dicos (RF03) â€” el 6.Âº queda inactivo al final del script
-- ---------------------------------------------------------------------
INSERT INTO medico (registro_profesional, nombres, apellidos, telefono, correo) VALUES
    ('MP-SC-1021', 'Juan',          'PÃ©rez Rojas',           '70011001', 'juan.perez@vidasalud.bo'),
    ('MP-SC-1187', 'MarÃ­a Fernanda','GutiÃ©rrez Soliz',       '70011002', 'maria.gutierrez@vidasalud.bo'),
    ('MP-SC-0954', 'Carlos Alberto','MÃ©ndez Vaca',           '70011003', 'carlos.mendez@vidasalud.bo'),
    ('MP-SC-1342', 'Ana LucÃ­a',     'Rivero SuÃ¡rez',         '70011004', 'ana.rivero@vidasalud.bo'),
    ('MP-SC-1103', 'Roberto Daniel','ÃÃ±ez Justiniano',       '70011005', 'roberto.anez@vidasalud.bo'),
    ('MP-SC-0876', 'Luis Fernando', 'Castro Ortiz',          '70011006', 'luis.castro@vidasalud.bo'),
    ('MP-SC-1450', 'Mauricio Javier','Soliz Antelo',         '70011007', 'mauricio.soliz@vidasalud.bo');

INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES
    (1, 1),            -- Juan PÃ©rez: Medicina General
    (2, 1), (2, 2),    -- MarÃ­a Fernanda GutiÃ©rrez: Medicina General y PediatrÃ­a
    (3, 3),            -- Carlos MÃ©ndez: CardiologÃ­a
    (4, 4),            -- Ana LucÃ­a Rivero: GinecologÃ­a
    (5, 5),            -- Roberto ÃÃ±ez: TraumatologÃ­a
    (6, 2),            -- Luis Castro: PediatrÃ­a (inactivo)
    (7, 6);            -- Mauricio Soliz: OdontologÃ­a

-- ---------------------------------------------------------------------
--  Horarios de atenciÃ³n (RF05) â€” dÃ­a 1 = lunes â€¦ 7 = domingo
-- ---------------------------------------------------------------------
INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita)
SELECT 1, d, '08:00', '12:00', 30 FROM generate_series(1, 5) d;                -- Lunâ€“Vie maÃ±ana

INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita) VALUES
    (2, 1, '14:00', '18:00', 20), (2, 3, '14:00', '18:00', 20), (2, 5, '14:00', '18:00', 20),
    (2, 6, '08:00', '12:00', 30), (2, 7, '08:00', '12:00', 30),                 -- guardia de fin de semana
    (3, 2, '08:00', '12:00', 30), (3, 4, '08:00', '12:00', 30), (3, 3, '15:00', '18:00', 30),
    (4, 1, '15:00', '19:00', 30), (4, 2, '15:00', '19:00', 30),
    (4, 3, '15:00', '19:00', 30), (4, 4, '15:00', '19:00', 30),
    (5, 1, '08:00', '13:00', 30), (5, 3, '08:00', '13:00', 30), (5, 5, '08:00', '13:00', 30),
    (5, 6, '09:00', '12:00', 30),
    (6, 2, '09:00', '12:00', 30), (6, 4, '09:00', '12:00', 30),
    (7, 6, '08:00', '12:00', 30);                                               -- OdontologÃ­a: sÃ¡bado

INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita)
SELECT 7, d, '14:00', '18:00', 30 FROM generate_series(1, 5) d;                -- OdontologÃ­a: Lunâ€“Vie tarde

-- Un bloque desactivado (para mostrar que se puede activar/desactivar).
INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita, estado)
VALUES (3, 5, '15:00', '17:00', 30, 'inactivo');

-- ---------------------------------------------------------------------
--  Feriados y ausencias (dia_no_laborable), nunca en lunes
-- ---------------------------------------------------------------------
INSERT INTO dia_no_laborable (fecha, id_medico, descripcion)
SELECT min(d)::DATE, NULL::INT, 'Aniversario de la clÃ­nica (feriado de prueba)'
  FROM generate_series(CURRENT_DATE + 12, CURRENT_DATE + 18, INTERVAL '1 day') d
 WHERE EXTRACT(ISODOW FROM d) = 3;

INSERT INTO dia_no_laborable (fecha, id_medico, descripcion)
SELECT min(d)::DATE, 3, 'Congreso Nacional de CardiologÃ­a'
  FROM generate_series(CURRENT_DATE + 3, CURRENT_DATE + 9, INTERVAL '1 day') d
 WHERE EXTRACT(ISODOW FROM d) = 4;

INSERT INTO dia_no_laborable (fecha, id_medico, descripcion)
SELECT min(d)::DATE, 5, 'CapacitaciÃ³n en el Hospital JaponÃ©s'
  FROM generate_series(CURRENT_DATE + 6, CURRENT_DATE + 12, INTERVAL '1 day') d
 WHERE EXTRACT(ISODOW FROM d) = 5;

-- ---------------------------------------------------------------------
--  Pacientes (RF02) con informaciÃ³n mÃ©dica (punto 3.4)
-- ---------------------------------------------------------------------
INSERT INTO paciente (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion,
                      tipo_sangre, alergias, enfermedades_cronicas, medicamentos, antecedentes) VALUES
    ('6234517',    'LucÃ­a',          'Vargas MontaÃ±o',      '1992-03-14', 'femenino',  '71234501', 'lucia.vargas@correo.com',   'Barrio Equipetrol, calle 7 Oeste #23',
     'O+', 'Penicilina', NULL, NULL, 'ApendicectomÃ­a en 2015.'),
    ('5893210',    'Jorge Luis',     'Salvatierra PeÃ±a',    '1978-11-02', 'masculino', '71234502', 'jorge.salvatierra@correo.com', 'Av. Banzer, 4.Âº anillo',
     'A+', NULL, 'HipertensiÃ³n arterial', 'LosartÃ¡n 50 mg', 'Padre con cardiopatÃ­a.'),
    ('7456123',    'Carla Andrea',   'SuÃ¡rez Rivero',       '1995-07-21', 'femenino',  '71234503', 'carla.suarez@correo.com',   'Urb. Las Palmas, calle 3',
     'B+', 'Mariscos', NULL, NULL, NULL),
    ('4321987',    'Pedro',          'Mamani Quispe',       '1965-01-30', 'masculino', '71234504', NULL,                        'Plan 3000, UV 160',
     'O+', NULL, 'Diabetes tipo 2', 'Metformina 850 mg', NULL),
    ('8123456',    'Valeria',        'Justiniano Roca',     '2001-09-05', 'femenino',  '71234505', 'valeria.justiniano@correo.com', 'Av. Alemana, 3.er anillo',
     'AB+', NULL, NULL, NULL, NULL),
    ('9012345',    'Mateo',          'Rojas Aguilera',      '2016-04-18', 'masculino', '71234506', NULL,                        'Villa 1.Âº de Mayo',
     'A-', 'Ibuprofeno', 'Asma leve', 'Salbutamol inhalador', 'Prematuro de 35 semanas.'),
    ('6789012',    'Gabriela',       'Ortiz CÃ©spedes',      '1988-12-11', 'femenino',  '71234507', 'gabriela.ortiz@correo.com', 'Barrio Sirari, calle Los Pinos',
     'O-', NULL, NULL, NULL, 'Dos cesÃ¡reas.'),
    ('5567890',    'Fernando',       'ChÃ¡vez Antelo',       '1970-06-25', 'masculino', '71234508', 'fernando.chavez@correo.com', 'Av. Santos Dumont, 5.Âº anillo',
     'B-', NULL, 'Artrosis de rodilla', 'Paracetamol 500 mg', NULL),
    ('7890123',    'Daniela',        'Paz Saucedo',         '1999-02-08', 'femenino',  '71234509', 'daniela.paz@correo.com',    'Urb. Villa BolÃ­var',
     NULL, NULL, NULL, NULL, NULL),
    ('3456789',    'Rosa MarÃ­a',     'Flores Vaca',         '1958-08-19', 'femenino',  '71234510', NULL,                        'Barrio Hamacas',
     'A+', 'Sulfas', 'Hipotiroidismo', 'Levotiroxina 75 mcg', NULL),
    ('9234567',    'SofÃ­a',          'MÃ©ndez Arteaga',      '2019-10-02', 'femenino',  '71234511', NULL,                        'Urb. La Colorada',
     'O+', NULL, NULL, NULL, 'Vacunas al dÃ­a.'),
    ('6345678',    'AndrÃ©s',         'GutiÃ©rrez LijerÃ³n',   '1985-05-15', 'masculino', '71234512', 'andres.gutierrez@correo.com', 'Av. Cristo Redentor, 6.Âº anillo',
     'A+', NULL, NULL, NULL, 'Fractura de tibia en 2020.'),
    ('7123456-1B', 'Camila',         'Roca Vargas',         '1997-01-27', 'femenino',  '71234513', 'camila.roca@correo.com',    'Barrio UrbarÃ­',
     'B+', NULL, NULL, NULL, NULL),
    ('4987654',    'Miguel Ãngel',   'Terrazas Rivas',      '1962-03-03', 'masculino', '71234514', NULL,                        'Av. Virgen de Cotoca',
     'O+', NULL, 'Insuficiencia cardiaca', 'Enalapril 10 mg', 'Infarto en 2019.'),
    ('8456789',    'Paola',          'Aguilera Montero',    '1990-11-30', 'femenino',  '71234515', 'paola.aguilera@correo.com', 'Urb. Los Mangales',
     'A-', 'LÃ¡tex', NULL, NULL, NULL),
    ('6012345',    'Ricardo',        'Saucedo Ribera',      '1982-07-07', 'masculino', '71234516', 'ricardo.saucedo@correo.com', 'Barrio Polanco',
     NULL, NULL, NULL, NULL, NULL),
    ('9345678',    'Isabella',       'PeÃ±a CoÃ­mbra',        '2012-06-12', 'femenino',  '71234517', NULL,                        'Av. ParaguÃ¡',
     'O+', NULL, NULL, NULL, NULL),
    ('5234567',    'HÃ©ctor',         'Ribera Lino',         '1975-09-09', 'masculino', '71234518', NULL,                        'Barrio Lazareto',
     'B+', NULL, 'Gastritis crÃ³nica', 'Omeprazol 20 mg', NULL),
    ('7654321',    'Natalia',        'CuÃ©llar EgÃ¼ez',       '1993-04-04', 'femenino',  '71234519', 'natalia.cuellar@correo.com', 'Urb. Santa Rosita',
     'AB-', NULL, NULL, NULL, NULL),
    ('4123456',    'Oscar',          'Lino Moreno',         '1968-12-24', 'masculino', '71234520', NULL,                        'Barrio CupesÃ­',
     'O+', NULL, NULL, NULL, NULL);

-- Fechas de registro escalonadas en los Ãºltimos meses (reporte "Pacientes registrados").
UPDATE paciente SET creado_en = localtimestamp - make_interval(days => (21 - id_paciente) * 6, hours => id_paciente);

-- Un paciente no quiere compartir su informaciÃ³n mÃ©dica con el mÃ©dico (preferencia de privacidad).
UPDATE paciente SET compartir_info_medica = FALSE WHERE id_paciente = 16;

-- Contactos de emergencia
INSERT INTO contacto_emergencia (id_paciente, nombre, parentesco, telefono) VALUES
    (1,  'RaÃºl Vargas MontaÃ±o',      'Hermano', '72100001'),
    (2,  'Elena PeÃ±a de Salvatierra','Esposa',  '72100002'),
    (4,  'Juana Quispe de Mamani',   'Esposa',  '72100004'),
    (6,  'Patricia Aguilera Rojas',  'Madre',   '72100006'),
    (10, 'Marcelo Flores Vaca',      'Hijo',    '72100010'),
    (11, 'Diego MÃ©ndez Arteaga',     'Padre',   '72100011'),
    (14, 'Lourdes Rivas de Terrazas','Esposa',  '72100014'),
    (17, 'MÃ³nica CoÃ­mbra PeÃ±a',      'Madre',   '72100017');

-- ---------------------------------------------------------------------
--  Usuarios de demostraciÃ³n (contraseÃ±a: Clinica2026*)
-- ---------------------------------------------------------------------
INSERT INTO usuario (nombre_usuario, correo, password_hash, nombre_completo, id_rol, id_medico, id_paciente)
SELECT 'recepcion', 'recepcion@vidasalud.bo', '$2y$10$0yRfD1926PtAtv1lHDF9Mu/Bw3qSrEWeqCXSkfeqbB6zYPxT3Q.2u',
       'Carla Ribera Justiniano', id_rol, NULL::INT, NULL::INT FROM rol WHERE nombre = 'recepcionista'
UNION ALL
SELECT 'dr.perez', 'juan.perez@vidasalud.bo', '$2y$10$0yRfD1926PtAtv1lHDF9Mu/Bw3qSrEWeqCXSkfeqbB6zYPxT3Q.2u',
       'Dr. Juan PÃ©rez Rojas', id_rol, 1, NULL FROM rol WHERE nombre = 'medico'
UNION ALL
SELECT 'lucia.vargas', 'lucia.vargas@correo.com', '$2y$10$0yRfD1926PtAtv1lHDF9Mu/Bw3qSrEWeqCXSkfeqbB6zYPxT3Q.2u',
       'LucÃ­a Vargas MontaÃ±o', id_rol, NULL, 1 FROM rol WHERE nombre = 'paciente';

-- ---------------------------------------------------------------------
--  Citas (cientos), generadas alrededor de la fecha de instalaciÃ³n
-- ---------------------------------------------------------------------
DO $$
DECLARE
    v_recepcion INT := (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'recepcion');
    v_portal    INT := (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'lucia.vargas');
    v_motivos   TEXT[] := ARRAY['Control de rutina', 'Dolor de cabeza frecuente', 'RevisiÃ³n de exÃ¡menes',
                                'Control de presiÃ³n arterial', 'Malestar general y fiebre', 'Dolor de espalda',
                                'Chequeo anual', 'Seguimiento de tratamiento', 'Consulta por tos persistente',
                                'Control prenatal', 'Dolor en la rodilla', 'Certificado mÃ©dico', NULL, NULL];
    v_cancelar  TEXT[] := ARRAY['El paciente avisÃ³ que no podrÃ¡ asistir', 'Viaje imprevisto del paciente',
                                'El paciente pidiÃ³ otra fecha', 'Problemas de transporte', 'Se sintiÃ³ mejor y no requiere la consulta'];
    v_dia     DATE;
    v_slot    RECORD;
    v_prob    NUMERIC;
    v_pac     INT;
    v_esp     INT;
    v_cita    RECORD;
    v_r       NUMERIC;
    v_inicio  TIMESTAMP;
BEGIN
    -- 1. Citas fijas de la paciente de demostraciÃ³n (recordatorios de las prÃ³ximas 48 h).
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
                'RevisiÃ³n de electrocardiograma', v_portal, localtimestamp - INTERVAL '1 day');
    END LOOP;

    -- 2. Citas aleatorias: 45 dÃ­as atrÃ¡s hasta 21 dÃ­as adelante.
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

            -- Estado segÃºn si la cita ya pasÃ³ (RF10).
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
                -- En curso ahora mismo: el paciente ya llegÃ³.
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
                                         WHEN 'inasistencia' THEN 'El paciente no se presentÃ³'
                                         WHEN 'confirmada' THEN 'El paciente confirmÃ³ su asistencia'
                                     END),
       id_usuario = (SELECT id_usuario FROM usuario WHERE nombre_usuario =
                        CASE WHEN h.estado_nuevo = 'atendida' AND c.id_medico = 1 THEN 'dr.perez' ELSE 'recepcion' END)
  FROM cita c
 WHERE c.id_cita = h.id_cita AND h.accion = 'estado';

-- ---------------------------------------------------------------------
--  El Dr. Luis Castro deja la clÃ­nica: sus citas futuras se cancelan
--  (quedan en el historial) y queda INACTIVO (RN04, RN11).
-- ---------------------------------------------------------------------
UPDATE cita SET estado = 'cancelada', motivo_cancelacion = 'El mÃ©dico fue dado de baja en la clÃ­nica'
 WHERE id_medico = 6 AND estado IN ('programada', 'confirmada');
UPDATE medico SET estado = 'inactivo' WHERE id_medico = 6;

-- Un paciente inactivo (se conserva su historial).
UPDATE cita SET estado = 'cancelada', motivo_cancelacion = 'Paciente desactivado por la clÃ­nica'
 WHERE id_paciente = 20 AND estado IN ('programada', 'confirmada');
UPDATE paciente SET estado = 'inactivo' WHERE id_paciente = 20;

-- Primeros registros de la bitÃ¡cora.
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

-- Soporte para bloqueo progresivo y recuperacion de contrasena
ALTER TABLE usuario ADD COLUMN IF NOT EXISTS bloqueos_consecutivos SMALLINT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS recuperacion_clave (
    id_recuperacion INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario      INT NOT NULL REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    token_hash      VARCHAR(64) NOT NULL UNIQUE,
    expira_en       TIMESTAMPTZ NOT NULL,
    usado_en        TIMESTAMPTZ,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS ix_recuperacion_token ON recuperacion_clave (token_hash);

