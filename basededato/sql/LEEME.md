# Base de datos — PostgreSQL 13+

Base `clinica_vida_salud`, normalizada, con las reglas de negocio protegidas **también** en la base (restricciones,
triggers y vistas). Aunque alguien se salte la aplicación PHP, PostgreSQL no acepta datos que rompan las reglas.

## Instalación rápida (Windows)

```bat
cd C:\xampp\htdocs\clinica-vida-salud\basededato\sql
set PGPORT=5433          :: solo si su PostgreSQL NO usa el puerto 5432
instalar_bd.bat
```

Escriba la contraseña del usuario `postgres` y espere el mensaje **"Listo"**. El instalador busca `psql.exe` (en el
PATH o en `C:\Program Files\PostgreSQL\*\bin`), **borra y vuelve a crear** la base y ejecuta los scripts en orden.
Variables opcionales: `PGHOST` (127.0.0.1), `PGPORT` (5432), `PGUSER` (postgres), `PGPASSWORD`.

Repetir la instalación deja los datos como nuevos (útil el día antes de una presentación).

## Orden de ejecución

| Archivo | Contenido |
|---|---|
| `01_esquema.sql` | extensión `btree_gist`; tipos (`dom_estado_registro`, `dom_estado_cita`, `dom_telefono`, `dom_correo`, rango `rango_hora`); tablas, claves, `CHECK`, `UNIQUE`, índices y `EXCLUDE` |
| `02_funciones_triggers.sql` | `fn_normalizar_texto`, `fn_intervalos_medico`, códigos PAC/MED/CM, validación de citas, transiciones, historial, "no borrar", bitácora inalterable |
| `03_vistas.sql` | `v_cita_detalle`, `v_medico_especialidades`, `v_resumen_citas_dia` |
| `04_datos_iniciales.sql` | roles, 21 permisos, permisos por rol, 6 especialidades y la cuenta `admin` |
| `05_datos_prueba.sql` | 7 médicos (1 inactivo), horarios, feriados, 20 pacientes, cuentas de demostración y ~630 citas **relativas a la fecha de instalación** |
| `99_reiniciar.sql` | borra todos los objetos (esquema `public`) para reinstalar dentro de la misma base |
| `pruebas/pruebas_bd.sql` | 18 pruebas directas de restricciones y triggers (se revierten al final) |

Manual, sin el `.bat`: `createdb -U postgres clinica_vida_salud` y luego `psql -U postgres -d clinica_vida_salud -f 01_esquema.sql`
(y así 02 → 05).

## Tablas (claves `id_<tabla>`)

| Tabla | Para qué sirve | Reglas en la base |
|---|---|---|
| `rol`, `permiso`, `rol_permiso` | roles y permisos (RN12) | `uq_rol_nombre`, `uq_permiso_codigo` |
| `usuario` | cuentas de acceso; `intentos_fallidos`, `bloqueado_hasta`, `ultimo_acceso` | `uq_usuario_nombre`, `uq_usuario_correo`, `uq_usuario_medico`, `uq_usuario_paciente`; trigger RN12 |
| `especialidad` | RF04 | índice único `uq_especialidad_nombre_normalizado` sobre `fn_normalizar_texto(nombre)`; `color` de la agenda (`ck_especialidad_color`, formato #RRGGBB) |
| `medico`, `medico_especialidad` | RF03 (una o varias especialidades) | `uq_medico_codigo` (RN02), `uq_medico_registro`, `uq_medico_correo` |
| `horario_atencion` | RF05: bloques por día y duración de cita | `ck_horario_rango`, `ck_horario_duracion`, `ex_horario_sin_solape` |
| `dia_no_laborable` | feriados (`id_medico` NULL) y ausencias | `uq_dia_no_laborable` |
| `paciente` | RF02 + información médica y preferencias | `uq_paciente_ci` (RN01), `ck_paciente_ci`, `ck_paciente_nacimiento` |
| `contacto_emergencia` | contacto del perfil (punto 3.4) | FK al paciente |
| `cita` | RF06–RF10 | `fk_cita_medico_especialidad` (RN03), `ck_cita_cancelada` (RF09), `ex_cita_medico_sin_solape` y `ex_cita_paciente_sin_solape` (RN07, RN09) |
| `cita_historial` | trazabilidad de cada cita (RN10) | lo llena el trigger; no se borra |
| `bitacora` | acciones de los usuarios (CU02) | trigger: no se modifica ni se borra |

## Triggers

| Trigger / función | Regla |
|---|---|
| `fn_tg_validar_cita` | RN04 médico activo · RN03 paciente activo · RN06 sin fechas pasadas · RN05 feriados y horario de atención · RN08 duración e intervalos |
| `fn_tg_validar_transicion_cita` | RF10: programada → confirmada/atendida/cancelada/inasistencia; confirmada → atendida/cancelada/inasistencia |
| `fn_tg_historial_cita` | RN10: registra alta, cambios de estado y reprogramaciones con usuario y detalle |
| `fn_tg_no_borrar` | RN10: las citas y su historial no se borran |
| `fn_tg_desactivar_en_lugar_de_borrar` | RN11: un paciente o médico con citas se desactiva en lugar de eliminarse |
| `fn_tg_asignar_codigo` | códigos PAC-0001, MED-0001, CM-0001 |
| `fn_tg_normalizar_texto` | espacios, CI en mayúsculas, correos en minúsculas |
| `fn_tg_validar_usuario_rol` | RN12: cuenta de médico/paciente vinculada |
| `fn_tg_bitacora_inalterable` | la bitácora solo admite inserciones |

El backend envía en cada transacción `app.id_usuario` y `app.detalle` (`BaseDatos::transaccion()`), que el historial
usa para saber quién hizo el cambio y por qué.

## Vistas

- `v_cita_detalle` — cita con paciente, CI, médico (`Dr(a). …`), especialidad (y su color) y quién la registró.
- `v_medico_especialidades` — médico con sus especialidades en una fila.
- `v_resumen_citas_dia` — totales por día y estado (panel y reporte de asistencia).

## Pruebas en SQL

```bat
psql -U postgres -d clinica_vida_salud -f pruebas\pruebas_bd.sql > pruebas\resultados_pruebas_bd.txt
```

Ejecuta 18 pruebas (CI duplicado, citas superpuestas, fuera de horario, fecha pasada, médico inactivo, especialidad
no atendida, duración, transiciones, borrados, nombres normalizados, horarios, RN11, RN12, bitácora…) dentro de una
transacción que se revierte: la base no cambia.
