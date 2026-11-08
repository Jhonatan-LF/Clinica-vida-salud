<?php
namespace App\Paquetes\Reportes\CasosUso;

use App\Nucleo\Bitacora;
use App\Nucleo\Controlador;
use App\Nucleo\Peticion;
use App\Nucleo\Respuesta;
use App\Paquetes\Reportes\Modelos\EstadisticaModelo;

/**
 * CU17 · Consultar estadísticas de la clínica
 * Requisito: RF13 (ampliación) · Actor: Administrador
 *
 * Flujo principal:
 *  1. El administrador abre «Estadísticas» y elige el período (3, 6 o 12 meses, o el año en curso).
 *  2. El sistema calcula los indicadores del período (total de citas, asistencia, inasistencia y
 *     cancelación) y los compara con el período anterior de igual duración (el año: con el mismo
 *     tramo del año pasado). Cada indicador muestra su tendencia en una minigráfica.
 *  3. Redacta los hallazgos principales (citas sin registrar, cambio de la inasistencia, especialidad
 *     más pedida, médico con más inasistencia, tramo con más citas).
 *  4. Muestra los gráficos: citas por tramo y estado (por semana si el período es de 3 meses, por mes
 *     si es más largo), tasa de inasistencia con su promedio, especialidades más pedidas y asistencia
 *     por médico comparada con el promedio de la clínica. Cada gráfico tiene su tabla de datos.
 *  5. Opcional: imprime o guarda como PDF, o descarga el resumen por tramo en CSV.
 *
 * Definiciones:
 *  - Tasa de inasistencia = inasistencias / (atendidas + inasistencias): de las citas que debían
 *    realizarse y no se cancelaron, cuántas el paciente no se presentó.
 *  - Tasa de cancelación = canceladas / total de citas del período.
 *  - Sin registrar = citas cuya hora ya pasó y siguen programadas o confirmadas.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: reportes
 * Actores.........: Administrador
 * Requisitos......: RF13
 * Base de datos...: cita, especialidad, medico, paciente (solo lectura)
 * Rutas...........: GET /estadisticas?periodo=3m|6m|12m|anio[&formato=csv] (reportes.ver)
 * Vistas..........: frontend/vistas/reportes/estadisticas.php + frontend/assets/js/estadisticas.js
 * Usa.............: EstadisticaModelo
 * Precondición....: El administrador inició sesión con «reportes.ver».
 * Postcondición...: Se consultan los indicadores del período sin modificar ningún dato.
 * Flujo...........: rutas.php -> este archivo -> EstadisticaModelo -> PostgreSQL -> vista en
 *                   frontend/vistas/reportes/
 * Estado..........: IMPLEMENTADO.
 */
final class CU17ConsultarEstadisticas extends Controlador
{
    public const PERIODOS = [
        '3m'   => 'Últimos 3 meses',
        '6m'   => 'Últimos 6 meses',
        '12m'  => 'Últimos 12 meses',
        'anio' => 'Este año',
    ];

    /** Un médico entra en el hallazgo de inasistencia solo con esta cantidad mínima de citas cerradas. */
    private const MINIMO_CITAS_MEDICO = 10;

    /** GET /estadisticas */
    public function mostrar(): void
    {
        $periodo = (string) Peticion::entrada('periodo', '6m');
        $periodo = array_key_exists($periodo, self::PERIODOS) ? $periodo : '6m';
        [$desde, $hasta] = self::rango($periodo);
        [$antDesde, $antHasta] = self::rangoAnterior($periodo, $desde, $hasta);
        $unidad = $periodo === '3m' ? 'week' : 'month';     // períodos cortos: por semana

        $modelo = new EstadisticaModelo();
        $tramos = array_map(
            fn (array $t) => $t + $this->etiquetas($t['inicio'], $unidad, $desde, $hasta) + [
                'asistencia'   => self::tasa($t['atendidas'], $t['atendidas'] + $t['inasistencias']),
                'inasistencia' => self::tasa($t['inasistencias'], $t['atendidas'] + $t['inasistencias']),
                'cancelacion'  => self::tasa($t['canceladas'], $t['total']),
            ],
            $modelo->citasPorTramo($desde, $hasta, $unidad)
        );

        if (Peticion::entrada('formato') === 'csv') {
            $this->exportarCsv($tramos, $unidad, $desde, $hasta);
        }

        // Indicadores del período y del período anterior
        $suma = static fn (string $col): int => array_sum(array_column($tramos, $col));
        $kpi = self::indicadores($suma('total'), $suma('atendidas'), $suma('inasistencias'), $suma('canceladas'))
             + ['pendientes' => $suma('pendientes'), 'pacientes' => $modelo->pacientesNuevos($desde, $hasta)];
        $a = $modelo->resumen($antDesde, $antHasta);
        $anterior = self::indicadores($a['total'], $a['atendidas'], $a['inasistencias'], $a['canceladas']);
        $contra = 'vs. ' . self::etiquetaRango($antDesde, $antHasta);

        $especialidades = $modelo->especialidadesMasPedidas($desde, $hasta);
        $medicos = array_map(static fn ($r) => $r + [
            'inasistencia' => self::tasa($r['inasistencias'], $r['atendidas'] + $r['inasistencias']),
            'cerradas'     => $r['atendidas'] + $r['inasistencias'],
        ], $modelo->asistenciaPorMedico($desde, $hasta));
        foreach ($medicos as &$m) {
            $m['sobre_promedio'] = $m['inasistencia'] !== null && $kpi['inasistencia'] !== null
                && $m['cerradas'] >= self::MINIMO_CITAS_MEDICO && $m['inasistencia'] > $kpi['inasistencia'];
        }
        unset($m);
        $sinRegistrar = $modelo->sinRegistrar($desde, $hasta);

        $this->vista('reportes/estadisticas', [
            'titulo'     => 'Estadísticas',
            'periodo'    => $periodo,
            'periodos'   => self::PERIODOS,
            'desde'      => $desde,
            'hasta'      => $hasta,
            'unidad'     => $unidad,
            'actualizado'=> date('d/m/Y H:i'),
            'kpi'        => $kpi,
            'variacion'  => [
                // [texto del cambio, sentido: bien | mal | neutro] o null si no hay con qué comparar
                'total'        => self::variacionPorcentual($kpi['total'], $anterior['total']),
                'asistencia'   => self::variacionPuntos($kpi['asistencia'], $anterior['asistencia'], true),
                'inasistencia' => self::variacionPuntos($kpi['inasistencia'], $anterior['inasistencia'], false),
                'cancelacion'  => self::variacionPuntos($kpi['cancelacion'], $anterior['cancelacion'], false),
            ],
            'contra'     => $contra,
            'tendencias' => [
                'total'        => array_column($tramos, 'total'),
                'asistencia'   => array_column($tramos, 'asistencia'),
                'inasistencia' => array_column($tramos, 'inasistencia'),
                'cancelacion'  => array_column($tramos, 'cancelacion'),
            ],
            'hallazgos'      => self::hallazgos($kpi, $anterior, $contra, $tramos, $unidad, $especialidades, $medicos,
                                                $sinRegistrar, $desde),
            'tramos'         => $tramos,
            'especialidades' => $especialidades,
            'medicos'        => $medicos,
            'scripts'        => ['estadisticas.js'],
        ]);
    }

    /* ----------------------------- Períodos ----------------------------- */

    /** @return array{0:string,1:string} primer día del primer mes y último día del mes actual */
    public static function rango(string $periodo): array
    {
        $hasta = date('Y-m-t');
        $desde = match ($periodo) {
            '3m'   => date('Y-m-01', strtotime('first day of -2 months')),
            '12m'  => date('Y-m-01', strtotime('first day of -11 months')),
            'anio' => date('Y-01-01'),
            default => date('Y-m-01', strtotime('first day of -5 months')),
        };
        return [$desde, $hasta];
    }

    /**
     * Período con el que se compara: los N meses inmediatamente anteriores;
     * para «Este año», el mismo tramo del año pasado.
     * @return array{0:string,1:string}
     */
    public static function rangoAnterior(string $periodo, string $desde, string $hasta): array
    {
        if ($periodo === 'anio') {
            return [date('Y-m-d', strtotime("$desde -1 year")), date('Y-m-t', strtotime(substr($hasta, 0, 7) . '-01 -1 year'))];
        }
        $meses = ['3m' => 3, '12m' => 12][$periodo] ?? 6;
        return [date('Y-m-01', strtotime("$desde -{$meses} months")), date('Y-m-d', strtotime("$desde -1 day"))];
    }

    /** "2026-06-01".."2026-08-31" -> "jun – ago 2026" */
    public static function etiquetaRango(string $desde, string $hasta): string
    {
        $mes = static fn (string $f): string => mb_substr(MESES[(int) substr($f, 5, 2)], 0, 3);
        [$a1, $a2] = [substr($desde, 0, 4), substr($hasta, 0, 4)];
        if (substr($desde, 0, 7) === substr($hasta, 0, 7)) {
            return $mes($desde) . ' ' . $a1;
        }
        return $a1 === $a2 ? $mes($desde) . ' – ' . $mes($hasta) . ' ' . $a2
                           : $mes($desde) . ' ' . $a1 . ' – ' . $mes($hasta) . ' ' . $a2;
    }

    /** Nombres de un tramo (mes o semana) para el eje, el tooltip y las tablas. */
    private function etiquetas(string $inicio, string $unidad, string $desde, string $hasta): array
    {
        if ($unidad === 'month') {
            $nombre = self::nombreMes(substr($inicio, 0, 7));
            return ['nombre' => $nombre, 'titulo' => ucfirst($nombre), 'corto' => self::nombreMes(substr($inicio, 0, 7), true)];
        }
        $ini = max($inicio, $desde);
        $fin = min(date('Y-m-d', strtotime("$inicio +6 days")), $hasta);
        $titulo = 'Semana del ' . date('d/m', strtotime($ini)) . ' al ' . date('d/m/Y', strtotime($fin));
        return ['nombre' => 'la semana del ' . date('d/m', strtotime($ini)), 'titulo' => $titulo, 'corto' => date('d/m', strtotime($ini))];
    }

    /* ----------------------------- Cálculos ----------------------------- */

    /** @return array{total:int,atendidas:int,inasistencias:int,canceladas:int,asistencia:?float,inasistencia:?float,cancelacion:?float} */
    private static function indicadores(int $total, int $atendidas, int $inasistencias, int $canceladas): array
    {
        return [
            'total'         => $total,
            'atendidas'     => $atendidas,
            'inasistencias' => $inasistencias,
            'canceladas'    => $canceladas,
            'asistencia'    => self::tasa($atendidas, $atendidas + $inasistencias),
            'inasistencia'  => self::tasa($inasistencias, $atendidas + $inasistencias),
            'cancelacion'   => self::tasa($canceladas, $total),
        ];
    }

    /** Porcentaje con un decimal, o null si no hay base para calcularlo. */
    public static function tasa(int $parte, int $base): ?float
    {
        return $base > 0 ? round(100 * $parte / $base, 1) : null;
    }

    /** Cambio del total de citas en %: ['↑ 12 %', 'neutro'] (más citas no es bueno ni malo por sí solo). */
    private static function variacionPorcentual(int $actual, int $anterior): ?array
    {
        if ($anterior === 0) {
            return null;
        }
        $cambio = (int) round(100 * ($actual - $anterior) / $anterior);
        return [($cambio > 0 ? '↑ ' : ($cambio < 0 ? '↓ ' : '= ')) . abs($cambio) . "\u{00A0}%", 'neutro'];
    }

    /** Cambio de una tasa en puntos: ['↓ 2,1 pts', 'bien']. $subirEsBueno decide el sentido. */
    private static function variacionPuntos(?float $actual, ?float $anterior, bool $subirEsBueno): ?array
    {
        if ($actual === null || $anterior === null) {
            return null;
        }
        $dif = round($actual - $anterior, 1);
        if (abs($dif) < 0.1) {
            return ['= sin cambio', 'neutro'];
        }
        $sentido = ($dif > 0) === $subirEsBueno ? 'bien' : 'mal';
        return [($dif > 0 ? '↑ ' : '↓ ') . number_format(abs($dif), 1, ',', '.') . "\u{00A0}pts", $sentido];
    }

    /**
     * Frases con los hallazgos principales. Texto plano: {b}…{/b} marca lo que va en negrita
     * (la vista escapa todo y luego convierte esas marcas).
     * @return array<int,array{tipo:string,icono:string,texto:string,enlace?:string,enlace_texto?:string}>
     */
    private static function hallazgos(array $kpi, array $anterior, string $contra, array $tramos, string $unidad,
                                      array $especialidades, array $medicos, int $sinRegistrar, string $desde): array
    {
        $h = [];
        $pct = static fn (?float $v): string => number_format((float) $v, 1, ',', '.') . "\u{00A0}%";

        if ($sinRegistrar > 0) {
            $h[] = ['tipo' => 'alerta', 'icono' => 'alerta',
                    'texto' => "{b}{$sinRegistrar} " . ($sinRegistrar === 1 ? 'cita ya pasó' : 'citas ya pasaron') . '{/b} y siguen sin registrar asistencia; no cuentan en las tasas.',
                    'enlace' => url('/citas', ['desde' => $desde, 'hasta' => date('Y-m-d')]), 'enlace_texto' => 'Revisar citas'];
        }

        if ($kpi['inasistencia'] !== null && $anterior['inasistencia'] !== null
            && abs($kpi['inasistencia'] - $anterior['inasistencia']) >= 0.5) {
            $bajo = $kpi['inasistencia'] < $anterior['inasistencia'];
            $h[] = ['tipo' => $bajo ? 'bien' : 'mal', 'icono' => $bajo ? 'check-circulo' : 'x-circulo',
                    'texto' => 'La inasistencia {b}' . ($bajo ? 'bajó ' : 'subió ')
                             . number_format(abs($kpi['inasistencia'] - $anterior['inasistencia']), 1, ',', '.')
                             . " puntos{/b} {$contra}: de " . $pct($anterior['inasistencia']) . ' a ' . $pct($kpi['inasistencia']) . '.'];
        }

        if ($kpi['total'] > 0 && ($top = $especialidades[0] ?? null) && $top['total'] > 0) {
            $h[] = ['tipo' => 'info', 'icono' => 'especialidad',
                    'texto' => "{b}{$top['nombre']}{/b} es la especialidad más pedida: {b}"
                             . round(100 * $top['total'] / $kpi['total']) . "\u{00A0}%{/b} de las citas ({$top['total']} de {$kpi['total']})."];
        }

        $candidatos = array_filter($medicos, static fn ($m) => $m['sobre_promedio']);
        usort($candidatos, static fn ($x, $y) => $y['inasistencia'] <=> $x['inasistencia']);
        if ($peor = $candidatos[0] ?? null) {
            $h[] = ['tipo' => 'mal', 'icono' => 'medico',
                    'texto' => "{b}{$peor['medico']}{/b} tiene la inasistencia más alta: {b}" . $pct($peor['inasistencia'])
                             . '{/b}, frente al ' . $pct($kpi['inasistencia']) . ' de la clínica.'];
        }

        if (count($h) < 4 && $kpi['total'] > 0) {
            $pico = array_reduce($tramos, static fn ($c, $t) => $c === null || $t['total'] > $c['total'] ? $t : $c);
            $h[] = ['tipo' => 'info', 'icono' => 'calendario',
                    'texto' => $unidad === 'month'
                        ? "El mes con más citas fue {b}{$pico['nombre']}{/b}, con {$pico['total']}."
                        : 'La semana con más citas fue la {b}' . mb_strtolower(substr($pico['titulo'], 7)) . "{/b}, con {$pico['total']}."];
        }
        return array_slice($h, 0, 4);
    }

    /* ----------------------------- Exportación ----------------------------- */

    private function exportarCsv(array $tramos, string $unidad, string $desde, string $hasta): never
    {
        Bitacora::registrar('exportacion', 'reportes', 'Exportó las estadísticas a CSV.');
        $tasa = static fn (?float $t): string => $t === null ? '' : number_format($t, 1, ',', '');
        Respuesta::csv(
            "estadisticas_{$desde}_{$hasta}.csv",
            [$unidad === 'month' ? 'Mes' : 'Semana', 'Total', 'Atendidas', 'Pendientes', 'Inasistencias', 'Canceladas',
             'Tasa de inasistencia (%)'],
            array_map(static fn ($t) => [
                $t['titulo'], $t['total'], $t['atendidas'], $t['pendientes'], $t['inasistencias'], $t['canceladas'],
                $tasa($t['inasistencia']),
            ], $tramos)
        );
    }

    /** "2026-11" -> "noviembre 2026" (o "nov 26") */
    public static function nombreMes(string $mes, bool $corto = false): string
    {
        [$a, $m] = array_map('intval', explode('-', $mes));
        $nombre = MESES[$m];
        return $corto ? mb_substr($nombre, 0, 3) . ' ' . substr((string) $a, 2) : "{$nombre} {$a}";
    }
}
