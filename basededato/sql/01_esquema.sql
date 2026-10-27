-- =====================================================================
--  Clínica Vida Salud · Sistema web de gestión de citas médicas
--  01_esquema.sql — tipos, tablas, claves, CHECK, UNIQUE, índices y EXCLUDE
--
--  Orden de instalación: 01 → 02 → 03 → 04 → 05 (ver LEEME.md)
--  Todas las claves primarias se llaman id_<tabla>.
--  Los nombres de las restricciones son los que traduce
--  backend/app/nucleo/ManejadorErrores.php a mensajes para el usuario.
-- =====================================================================

SET client_encoding = 'UTF8';
SET client_min_messages = warning;

-- btree_gist permite combinar "=" con "&&" en las restricciones EXCLUDE
-- (un médico no puede tener dos citas que se crucen el mismo día).
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- ---------------------------------------------------------------------
--  TIPOS
-- ---------------------------------------------------------------------

-- Estado de cualquier registro maestro (paciente, médico, usuario…).
CREATE DOMAIN dom_estado_registro AS VARCHAR(10)
    NOT NULL DEFAULT 'activo'
    CHECK (VALUE IN ('activo', 'inactivo'));

-- Estados de la cita (RF10), en minúscula.
CREATE DOMAIN dom_estado_cita AS VARCHAR(15)
    NOT NULL DEFAULT 'programada'
    CHECK (VALUE IN ('programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'));

-- Teléfono: dígitos, espacios, "+" y "-" (7 a 20 caracteres).
CREATE DOMAIN dom_telefono AS VARCHAR(20)
    CHECK (VALUE ~ '^[0-9+\- ]{7,20}$');

-- Correo electrónico básico, siempre en minúscula.
CREATE DOMAIN dom_correo AS VARCHAR(120)
    CHECK (VALUE ~ '^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$');

-- Rango de horas de un día (08:00–08:30). Lo usan las restricciones
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
--  MÉDICOS: especialidad, medico, medico_especialidad,
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
-- RF04: nombre único NORMALIZADO (índice uq_especialidad_nombre_normalizado,
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

-- RF05: bloques de atención por médico y día de la semana (1 = lunes … 7 = domingo).
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
    -- Un médico no tiene dos bloques ACTIVOS que se crucen el mismo día.
    CONSTRAINT ex_horario_sin_solape EXCLUDE USING gist (
        id_medico WITH =,
        dia_semana WITH =,
        rango_hora(hora_inicio, hora_fin) WITH &&
    ) WHERE (estado = 'activo')
);

-- Feriados (id_medico NULL = toda la clínica) y ausencias de un médico.
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
    ci                    VARCHAR(15),                        -- opcional (p. ej. un bebé), pero único
    nombres               VARCHAR(60) NOT NULL,
    apellidos             VARCHAR(60) NOT NULL,
    fecha_nacimiento      DATE        NOT NULL,
    sexo                  VARCHAR(10) NOT NULL,
    telefono              dom_telefono NOT NULL,
    correo                dom_correo,
    direccion             VARCHAR(200),
    -- Información médica del perfil (punto 3.4 del examen)
    tipo_sangre           VARCHAR(3),
    alergias              VARCHAR(250),
    enfermedades_cronicas VARCHAR(250),
    medicamentos          VARCHAR(250),
    antecedentes          VARCHAR(500),
    -- Preferencias de la cuenta
    recibir_recordatorios BOOLEAN NOT NULL DEFAULT TRUE,
    compartir_info_medica BOOLEAN NOT NULL DEFAULT TRUE,     -- el médico ve la información médica
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
--  USUARIO (va después de médico y paciente porque puede vincularse a ellos)
-- ---------------------------------------------------------------------

CREATE TABLE usuario (
    id_usuario        INT GENERATED ALWAYS AS IDENTITY,
    nombre_usuario    VARCHAR(40)  NOT NULL,
    correo            dom_correo   NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,                 -- password_hash() de PHP (bcrypt)
    nombre_completo   VARCHAR(120) NOT NULL,
    id_rol            INT          NOT NULL,
    id_medico         INT,                                   -- solo rol médico
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

-- Bitácora de acciones (CU02). Nunca guarda contraseñas ni formularios.
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
    -- RN03: paciente y médico válidos. RN11: no se borra un paciente/médico con citas (RESTRICT).
    CONSTRAINT fk_cita_paciente FOREIGN KEY (id_paciente) REFERENCES paciente (id_paciente) ON DELETE RESTRICT,
    CONSTRAINT fk_cita_medico   FOREIGN KEY (id_medico)   REFERENCES medico (id_medico)     ON DELETE RESTRICT,
    -- RN03: el médico debe atender la especialidad de la cita (FK compuesta).
    CONSTRAINT fk_cita_medico_especialidad FOREIGN KEY (id_medico, id_especialidad)
        REFERENCES medico_especialidad (id_medico, id_especialidad) ON DELETE RESTRICT,
    CONSTRAINT fk_cita_usuario FOREIGN KEY (id_usuario_registro) REFERENCES usuario (id_usuario) ON DELETE SET NULL,
    CONSTRAINT ck_cita_rango     CHECK (hora_fin > hora_inicio),
    CONSTRAINT ck_cita_modalidad CHECK (modalidad IN ('presencial', 'virtual')),
    -- RF09: una cita cancelada siempre tiene motivo.
    CONSTRAINT ck_cita_cancelada CHECK (estado <> 'cancelada' OR motivo_cancelacion IS NOT NULL),
    -- RF07 / RN07: última barrera contra citas superpuestas.
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
