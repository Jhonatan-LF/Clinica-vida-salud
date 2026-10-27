-- =====================================================================
--  Clínica Vida Salud · 04_datos_iniciales.sql
--  Datos mínimos para que el sistema funcione: roles, permisos,
--  permisos por rol, especialidades y la cuenta del administrador.
--
--  Contraseña de las cuentas de demostración: Clinica2026*
--  (se guarda solo su hash bcrypt, generado con password_hash() de PHP)
-- =====================================================================

-- ---------------------------------------------------------------------
--  Roles
-- ---------------------------------------------------------------------
INSERT INTO rol (nombre, descripcion) VALUES
    ('administrador', 'Acceso total: usuarios, permisos, registros, citas, reportes y bitácora.'),
    ('recepcionista', 'Registra pacientes y programa, reprograma, confirma y cancela citas.'),
    ('medico',        'Consulta su propia agenda, registra la atención y ve el historial de sus pacientes.'),
    ('paciente',      'Portal del paciente: perfil, reserva en línea y seguimiento de sus citas.');

-- ---------------------------------------------------------------------
--  Permisos (RN12: cada operación exige su permiso)
-- ---------------------------------------------------------------------
INSERT INTO permiso (codigo, descripcion, modulo) VALUES
    ('usuarios.gestionar',       'Registrar usuarios, asignar roles y definir permisos',          'seguridad'),
    ('bitacora.ver',             'Consultar la bitácora de acciones',                              'seguridad'),
    ('pacientes.ver',            'Listar, buscar y ver la ficha de los pacientes',                 'pacientes'),
    ('pacientes.gestionar',      'Registrar, modificar y desactivar pacientes',                    'pacientes'),
    ('pacientes.historial',      'Consultar el historial de citas de un paciente',                 'pacientes'),
    ('medicos.ver',              'Listar, buscar y ver la ficha de los médicos',                   'medicos'),
    ('medicos.gestionar',        'Registrar, modificar y desactivar médicos',                      'medicos'),
    ('especialidades.gestionar', 'Registrar, modificar y desactivar especialidades',               'medicos'),
    ('horarios.gestionar',       'Definir horarios de atención, feriados y ausencias',             'medicos'),
    ('citas.ver',                'Listar y consultar las citas de la clínica',                     'citas'),
    ('citas.programar',          'Programar citas para los pacientes',                             'citas'),
    ('citas.reprogramar',        'Cambiar la fecha u hora de una cita',                            'citas'),
    ('citas.cancelar',           'Cancelar citas indicando el motivo',                             'citas'),
    ('citas.confirmar',          'Confirmar la asistencia o registrar la inasistencia',            'citas'),
    ('citas.atender',            'Marcar una cita como atendida',                                  'citas'),
    ('agenda.ver',               'Ver la agenda de cualquier médico',                              'agenda'),
    ('agenda.propia',            'Ver solo la agenda propia (médico)',                             'agenda'),
    ('reportes.ver',             'Generar y exportar reportes',                                    'reportes'),
    ('portal.perfil',            'Ver y actualizar el perfil propio (paciente)',                   'portal'),
    ('portal.reservar',          'Reservar citas en línea (paciente)',                             'portal'),
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

-- Médico: su agenda, marcar como atendida o inasistencia (solo sus citas) e historial de sus pacientes
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
    ('Medicina General', 'Consulta general, control de salud y derivación a especialistas.',   '#2563c9'),  -- azul
    ('Pediatría',        'Atención de niños y adolescentes, control de crecimiento y vacunas.', '#0ea5e9'),  -- celeste
    ('Cardiología',      'Prevención, diagnóstico y control de enfermedades del corazón.',      '#e05252'),  -- rojo suave
    ('Ginecología',      'Salud de la mujer, control ginecológico y prenatal.',                 '#db5f9a'),  -- rosado
    ('Traumatología',    'Lesiones de huesos, músculos y articulaciones.',                      '#8b5cf6'),  -- morado
    ('Odontología',      'Salud bucal: limpiezas, caries, extracciones y control dental.',      '#14b8a6');  -- verde agua

-- ---------------------------------------------------------------------
--  Administrador del sistema (usuario: admin · contraseña: Clinica2026*)
-- ---------------------------------------------------------------------
INSERT INTO usuario (nombre_usuario, correo, password_hash, nombre_completo, id_rol)
SELECT 'admin', 'ronaldcarrillocallisaya@gmail.com',
       '$2y$10$0yRfD1926PtAtv1lHDF9Mu/Bw3qSrEWeqCXSkfeqbB6zYPxT3Q.2u',
       'Administrador del Sistema', id_rol
  FROM rol WHERE nombre = 'administrador';
