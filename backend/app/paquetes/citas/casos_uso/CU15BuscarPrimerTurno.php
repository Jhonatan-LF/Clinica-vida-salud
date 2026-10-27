<?php
namespace App\Paquetes\Citas\CasosUso;

use App\Nucleo\Aplicacion;
use App\Nucleo\Controlador;
use App\Nucleo\Peticion;
use App\Paquetes\Citas\Servicios\Disponibilidad;
use App\Paquetes\Medicos\Modelos\EspecialidadModelo;
use App\Paquetes\Medicos\Modelos\MedicoModelo;

/**
 * CU15 · Buscar el primer turno libre (mejora de CU09)
 * Requisitos: RF06 · RN04 · RN05 · RN06 · Actores: Recepcionista, Paciente (en línea)
 *
 * Flujo principal:
 *  1. El usuario elige solo la especialidad.
 *  2. El sistema recorre los días desde hoy (hasta el máximo de reserva) y, para cada médico
 *     activo de esa especialidad, calcula los intervalos libres con el servicio Disponibilidad.
 *  3. Devuelve el turno libre más cercano y, como alternativas, el primer turno de los otros médicos.
 *  4. El usuario pulsa «Usar este turno» y la pantalla de CU09 se completa sola
 *     (médico, fecha y hora). Al guardar, CU09 vuelve a verificar todo en el servidor (RN13).
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: citas
 * Actores.........: Recepcionista, Paciente
 * Requisitos......: RF06 · RN04 (solo médicos activos) · RN05 (horario y feriados) · RN06 (sin horas pasadas)
 * Base de datos...: medico, medico_especialidad, horario_atencion, dia_no_laborable, cita (solo lectura)
 * Rutas...........: GET /api/primer-turno?especialidad= (citas.programar | portal.reservar)
 * Vistas..........: frontend/vistas/citas/programar.php + frontend/assets/js/citas.js
 * Usa.............: Disponibilidad, MedicoModelo, EspecialidadModelo
 * Precondición....: La especialidad está activa y tiene médicos activos con horario.
 * Postcondición...: Se muestra el turno libre más cercano; no se modifica ningún dato (la reserva la hace CU09).
 * Flujo...........: rutas.php -> este archivo -> servicio Disponibilidad -> PostgreSQL -> JSON -> citas.js
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo19_SI1_Clinica_Vida_Salud.docx (6.2 secuencia)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU15BuscarPrimerTurno extends Controlador
{
    /** GET /api/primer-turno?especialidad=ID */
    public function buscarJson(): void
    {
        $idEspecialidad = Peticion::entero('especialidad') ?? 0;
        $especialidad = (new EspecialidadModelo())->obtener($idEspecialidad);
        if (!$especialidad || $especialidad['estado'] !== 'activo') {
            $this->json(['ok' => false, 'mensaje' => 'Elija una especialidad disponible.'], 422);
        }

        $medicos = (new MedicoModelo())->activosPorEspecialidad($idEspecialidad);
        $disponibilidad = new Disponibilidad();
        $dias = (int) Aplicacion::config('citas.dias_maximos_reserva', 60);
        $primeros = [];                                   // id_medico => primer turno libre

        for ($i = 0; $i <= $dias && count($primeros) < count($medicos); $i++) {
            $fecha = date('Y-m-d', strtotime("+{$i} days"));
            foreach ($medicos as $m) {
                if (isset($primeros[$m['id_medico']])) {
                    continue;
                }
                foreach ($disponibilidad->delDia((int) $m['id_medico'], $fecha)['intervalos'] as $intervalo) {
                    if ($intervalo['libre']) {
                        $primeros[$m['id_medico']] = [
                            'id_medico'   => (int) $m['id_medico'],
                            'medico'      => $m['nombre'],
                            'fecha'       => $fecha,
                            'fecha_texto' => fecha_larga($fecha),
                            'inicio'      => $intervalo['inicio'],
                            'fin'         => $intervalo['fin'],
                        ];
                        break;
                    }
                }
            }
        }

        if (!$primeros) {
            $this->json(['ok' => true, 'turno' => null,
                         'mensaje' => "No hay turnos libres de {$especialidad['nombre']} en los próximos {$dias} días."]);
        }
        $turnos = array_values($primeros);
        usort($turnos, static fn ($a, $b) => strcmp($a['fecha'] . $a['inicio'], $b['fecha'] . $b['inicio']));
        $this->json(['ok' => true, 'turno' => $turnos[0], 'alternativas' => array_slice($turnos, 1, 3)]);
    }
}
