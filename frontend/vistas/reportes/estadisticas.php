<?php
/**
 * CU17 · Panel de estadísticas.
 * Variables: $periodo, $periodos, $desde, $hasta, $unidad (month|week), $actualizado, $kpi, $variacion,
 *            $contra, $tendencias, $hallazgos, $tramos, $especialidades, $medicos.
 *
 * Gráficos hechos con HTML/CSS (se adaptan al celular y se imprimen). Cada marca lleva
 * data-tip (JSON [[etiqueta, valor, clase]]) para el tooltip de estadisticas.js, y cada
 * gráfico tiene su tabla de datos, así ningún valor depende solo del color o del hover.
 */

/** Eje con valores "redondos": prueba 4, 5 y 6 divisiones y se queda con el tope más ajustado. */
$escala = static function (float $maximo): array {
    $maximo = max($maximo, 1);
    $mejor = null;
    foreach ([4, 5, 6] as $divisiones) {
        $bruto = $maximo / $divisiones;
        $potencia = 10 ** floor(log10($bruto));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $potencia >= $bruto) {
                $paso = $m * $potencia;
                break;
            }
        }
        $tope = $paso * ceil($maximo / $paso);
        if ($mejor === null || $tope < $mejor['tope']) {
            $mejor = ['tope' => $tope, 'paso' => $paso];
        }
    }
    return $mejor + ['marcas' => range(0, $mejor['tope'], $mejor['paso'])];
};
$numero = static fn ($v): string => number_format((float) $v, fmod((float) $v, 1) ? 1 : 0, ',', '.');
$pct = static fn (?float $v): string => $v === null ? '—' : number_format($v, 1, ',', '.') . "\u{00A0}%";   // espacio duro
/** Escapa el texto y convierte {b}…{/b} en negrita (los hallazgos los arma el caso de uso). */
$negritas = static fn (string $t): string => str_replace(['{b}', '{/b}'], ['<strong>', '</strong>'], e($t));

/** Minigráfica de tendencia (los vacíos cortan la línea; el último punto se resalta). */
$mini = static function (array $valores): string {
    $n = count($valores);
    $existentes = array_filter($valores, static fn ($v) => $v !== null);
    if ($n < 2 || count($existentes) < 2) {
        return '';
    }
    $tope = max($existentes) ?: 1;
    $tramos = [];
    $actual = [];
    $ultimo = null;
    foreach ($valores as $i => $v) {
        if ($v === null) {
            if ($actual) { $tramos[] = $actual; }
            $actual = [];
            continue;
        }
        $ultimo = [round(100 * $i / ($n - 1), 2), round(92 - 84 * $v / $tope, 2)];
        $actual[] = $ultimo[0] . ',' . $ultimo[1];
    }
    if ($actual) { $tramos[] = $actual; }
    $svg = '<svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">';
    foreach ($tramos as $t) {
        $svg .= count($t) > 1 ? '<polyline points="' . implode(' ', $t) . '"></polyline>' : '';
    }
    return '<span class="est-mini">' . $svg . '</svg><i style="left:' . $ultimo[0] . '%;top:' . $ultimo[1] . '%"></i></span>';
};

$hayCitas = $kpi['total'] > 0;
$n = count($tramos);
$porSemana = $unidad === 'week';
$denso = $n > 6 ? ' est-ejex-denso' : '';

// Series del gráfico de columnas, de abajo hacia arriba (este orden mantiene distinguibles
// los colores con daltonismo; las canceladas, en gris, llevan además textura rayada).
$series = [
    'atendidas'     => 'Atendidas',
    'pendientes'    => 'Pendientes',
    'inasistencias' => 'Inasistencias',
    'canceladas'    => 'Canceladas',
];
$maxTotal = max(array_column($tramos, 'total') ?: [0]);
$ejeCitas = $escala($maxTotal);

$tasas = array_filter(array_column($tramos, 'inasistencia'), static fn ($v) => $v !== null);
$ejeTasa = $escala(max(10, $kpi['inasistencia'] ?? 0, ...($tasas ?: [0])));
$ultimaConTasa = null;
foreach ($tramos as $i => $t) {
    if ($t['inasistencia'] !== null) {
        $ultimaConTasa = $i;
    }
}

$maxEsp = max(array_column($especialidades, 'total') ?: [0]);
$tasasMedicos = array_filter(array_column($medicos, 'inasistencia'), static fn ($v) => $v !== null);
$ejeMedicos = $escala(max(5, $kpi['inasistencia'] ?? 0, ...($tasasMedicos ?: [0])));

$tarjetas = [
    ['total',        'Citas del período',  $numero($kpi['total']),      $numero($kpi['pacientes']) . ' pacientes nuevos'],
    ['asistencia',   'Asistencia',         $pct($kpi['asistencia']),    $numero($kpi['atendidas']) . ' atendidas'],
    ['inasistencia', 'Inasistencia',       $pct($kpi['inasistencia']),  $numero($kpi['inasistencias']) . ' no se presentaron'],
    ['cancelacion',  'Cancelación',        $pct($kpi['cancelacion']),   $numero($kpi['canceladas']) . ' canceladas'],
];
$iconoTipo = ['bien' => 'check-circulo', 'mal' => 'x-circulo', 'alerta' => 'alerta', 'info' => 'info'];
?>
<section class="tarjeta no-imprimir">
    <div class="filtros est-filtros">
        <div class="est-periodos" role="group" aria-label="Período">
            <?php foreach ($periodos as $clave => $texto): ?>
                <a class="boton boton-chico <?= $clave === $periodo ? 'boton-primario' : 'boton-secundario' ?>"
                   href="<?= url('/estadisticas', ['periodo' => $clave]) ?>"<?= $clave === $periodo ? ' aria-current="true"' : '' ?>><?= e($texto) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="acciones">
            <button type="button" class="boton boton-secundario boton-chico" data-imprimir><?= icono('imprimir') ?> Imprimir / PDF</button>
            <a class="boton boton-secundario boton-chico" href="<?= url('/estadisticas', ['periodo' => $periodo, 'formato' => 'csv']) ?>"><?= icono('descargar') ?> CSV</a>
        </div>
    </div>
    <p class="est-contexto">
        <?= icono('calendario') ?> Del <?= e(fecha_corta($desde)) ?> al <?= e(fecha_corta($hasta)) ?>
        · <?= $porSemana ? 'por semana' : 'por mes' ?> · comparado con <?= e(substr($contra, 4)) ?>
        <span>Datos actualizados al <?= e($actualizado) ?></span>
    </p>
</section>

<div class="reporte-titulo-impresion">
    <h1>Clínica Vida Salud · Estadísticas</h1>
    <p><?= e($periodos[$periodo]) ?>: del <?= e(fecha_corta($desde)) ?> al <?= e(fecha_corta($hasta)) ?>
       (comparado con <?= e(substr($contra, 4)) ?>) · datos al <?= e($actualizado) ?></p>
</div>

<!-- Indicadores: valor, variación frente al período anterior y tendencia -->
<div class="rejilla rejilla-4">
    <?php foreach ($tarjetas as [$clave, $etiqueta, $valor, $detalle]):
        $var = $variacion[$clave]; ?>
    <div class="tarjeta est-kpi">
        <p class="est-kpi-etiqueta"><?= e($etiqueta) ?></p>
        <p class="est-kpi-valor"><?= e($valor) ?></p>
        <p class="est-kpi-detalle"><?= e($detalle) ?></p>
        <p class="est-variacion <?= $var ? e($var[1]) : 'neutro' ?>">
            <?php if ($var): ?><b><?= e($var[0]) ?></b> <?= e($contra) ?><?php else: ?>Sin datos del período anterior<?php endif; ?>
        </p>
        <?= $mini($tendencias[$clave]) ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Hallazgos del período -->
<?php if ($hallazgos): ?>
<section class="tarjeta">
    <div class="tarjeta-cabecera">
        <div><h2><?= icono('info') ?> Hallazgos del período</h2><p>Lo más importante de los datos, calculado automáticamente.</p></div>
    </div>
    <ul class="est-hallazgos">
        <?php foreach ($hallazgos as $h): ?>
        <li class="est-hallazgo est-h-<?= e($h['tipo']) ?>">
            <span class="est-hallazgo-icono"><?= icono($iconoTipo[$h['tipo']] ?? 'info') ?></span>
            <p><?= $negritas($h['texto']) ?></p>
            <?php if (!empty($h['enlace'])): ?>
                <a class="boton boton-secundario boton-chico no-imprimir" href="<?= e($h['enlace']) ?>"><?= e($h['enlace_texto']) ?></a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<!-- 1 · Citas por mes o semana -->
<section class="tarjeta">
    <div class="tarjeta-cabecera">
        <div><h2><?= icono('reporte') ?> Citas por <?= $porSemana ? 'semana' : 'mes' ?></h2><p>Todas las citas del período según su estado actual.</p></div>
    </div>
    <div class="tarjeta-cuerpo">
        <?php if (!$hayCitas): ?>
            <div class="vacio"><?= icono('reporte') ?><p>No hay citas en el período elegido.</p></div>
        <?php else: ?>
        <ul class="est-leyenda" aria-label="Leyenda">
            <?php foreach ($series as $clave => $texto): ?>
                <li><i class="est-muestra est-<?= $clave ?>" aria-hidden="true"></i><?= e($texto) ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="est-grafico" role="group" aria-label="Citas por <?= $porSemana ? 'semana' : 'mes' ?>, por estado">
            <div class="est-area">
                <?php foreach ($ejeCitas['marcas'] as $t): ?>
                    <div class="est-guia" style="bottom: <?= round(100 * $t / $ejeCitas['tope'], 3) ?>%"><span><?= $numero($t) ?></span></div>
                <?php endforeach; ?>
                <div class="est-bandas" style="--n: <?= $n ?>">
                    <?php foreach ($tramos as $i => $t):
                        $tip = array_map(static fn ($c, $x) => [$x, $t[$c], 'est-' . $c], array_keys($series), $series);
                        $tip[] = ['Total', $t['total'], ''];
                        $leer = $t['titulo'] . ': ' . $t['total'] . ' citas; ' . implode(', ', array_map(static fn ($c, $x) => $t[$c] . ' ' . mb_strtolower($x), array_keys($series), $series));
                        $mostrarValor = $t['total'] > 0 && ($n <= 6 || $t['total'] === $maxTotal || $i === $n - 1); ?>
                    <div class="est-banda" tabindex="0" aria-label="<?= e($leer) ?>"
                         data-tip-titulo="<?= e($t['titulo']) ?>" data-tip="<?= e(json_encode($tip, JSON_UNESCAPED_UNICODE)) ?>">
                        <?php if ($mostrarValor): ?><span class="est-cap"><?= $numero($t['total']) ?></span><?php endif; ?>
                        <?php if ($t['total'] > 0): ?>
                        <div class="est-pila" style="height: <?= round(100 * $t['total'] / $ejeCitas['tope'], 3) ?>%">
                            <?php foreach (array_keys($series) as $c): if ($t[$c] > 0): ?>
                                <span class="est-<?= $c ?>" style="flex-grow: <?= (int) $t[$c] ?>"></span>
                            <?php endif; endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="est-ejex<?= $denso ?>" style="--n: <?= $n ?>" aria-hidden="true">
                <?php foreach ($tramos as $t): ?><span><?= e($t['corto']) ?></span><?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <details class="est-datos">
            <summary>Ver datos en tabla</summary>
            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead><tr><th><?= $porSemana ? 'Semana' : 'Mes' ?></th><th class="texto-derecha">Total</th><?php foreach ($series as $x): ?><th class="texto-derecha"><?= e($x) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($tramos as $t): ?>
                        <tr><td><?= e($t['titulo']) ?></td><td class="texto-derecha mono"><?= $numero($t['total']) ?></td>
                            <?php foreach (array_keys($series) as $c): ?><td class="texto-derecha mono"><?= $numero($t[$c]) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td>Total</td><td class="texto-derecha mono"><?= $numero($kpi['total']) ?></td>
                        <?php foreach (array_keys($series) as $c): ?><td class="texto-derecha mono"><?= $numero($kpi[$c]) ?></td><?php endforeach; ?></tr></tfoot>
                </table>
            </div>
        </details>
    </div>
</section>

<div class="rejilla rejilla-2">
    <!-- 2 · Tasa de inasistencia con su promedio -->
    <section class="tarjeta">
        <div class="tarjeta-cabecera">
            <div><h2><?= icono('x-circulo') ?> Tasa de inasistencia</h2><p>Inasistencias ÷ (atendidas + inasistencias), por <?= $porSemana ? 'semana' : 'mes' ?>.</p></div>
        </div>
        <div class="tarjeta-cuerpo">
            <?php if (!$tasas): ?>
                <div class="vacio"><?= icono('reporte') ?><p>Todavía no hay citas atendidas ni inasistencias en el período.</p></div>
            <?php else:
                // Puntos del gráfico (x en el centro de cada tramo; y en % del eje). Un tramo sin datos corta la línea.
                $puntos = [];
                foreach ($tramos as $i => $t) {
                    $puntos[$i] = $t['inasistencia'] === null ? null
                        : [round(100 * ($i + .5) / $n, 3), round(100 - 100 * $t['inasistencia'] / $ejeTasa['tope'], 3)];
                }
                $lineas = [];
                $actual = [];
                foreach ($puntos as $p) {
                    if ($p === null) { if ($actual) { $lineas[] = $actual; } $actual = []; continue; }
                    $actual[] = $p;
                }
                if ($actual) { $lineas[] = $actual; }
                $promedio = $kpi['inasistencia']; ?>
            <div class="est-grafico" role="group" aria-label="Tasa de inasistencia por <?= $porSemana ? 'semana' : 'mes' ?>; promedio del período <?= e($pct($promedio)) ?>">
                <div class="est-area est-area-linea">
                    <?php foreach ($ejeTasa['marcas'] as $t): ?>
                        <div class="est-guia" style="bottom: <?= round(100 * $t / $ejeTasa['tope'], 3) ?>%"><span><?= $numero($t) ?>%</span></div>
                    <?php endforeach; ?>
                    <?php if ($promedio !== null): ?>
                        <div class="est-promedio" style="bottom: <?= round(100 * $promedio / $ejeTasa['tope'], 3) ?>%"><span>Promedio <?= e($pct($promedio)) ?></span></div>
                    <?php endif; ?>
                    <svg class="est-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                        <?php foreach ($lineas as $linea):
                            $pts = implode(' ', array_map(static fn ($p) => $p[0] . ',' . $p[1], $linea));
                            $area = $linea[0][0] . ',100 ' . $pts . ' ' . end($linea)[0] . ',100'; ?>
                            <polygon class="est-relleno" points="<?= $area ?>"></polygon>
                            <polyline class="est-trazo" points="<?= $pts ?>"></polyline>
                        <?php endforeach; ?>
                    </svg>
                    <?php foreach ($puntos as $i => $p): if ($p === null) { continue; } ?>
                        <i class="est-punto" style="left: <?= $p[0] ?>%; top: <?= $p[1] ?>%" aria-hidden="true"></i>
                        <?php if ($i === $ultimaConTasa): ?>
                            <span class="est-etiqueta-fin" style="left: <?= $p[0] ?>%; top: <?= $p[1] ?>%"><?= $pct($tramos[$i]['inasistencia']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="est-bandas est-bandas-cruz" style="--n: <?= $n ?>">
                        <?php foreach ($tramos as $t):
                            $tip = [['Inasistencia', $pct($t['inasistencia']), 'est-inasistencias-linea'],
                                    ['Promedio del período', $pct($promedio), ''],
                                    ['Inasistencias', $t['inasistencias'], ''], ['Atendidas', $t['atendidas'], '']]; ?>
                            <div class="est-banda" tabindex="0" aria-label="<?= e($t['titulo'] . ': ' . $pct($t['inasistencia'])) ?>"
                                 data-tip-titulo="<?= e($t['titulo']) ?>" data-tip="<?= e(json_encode($tip, JSON_UNESCAPED_UNICODE)) ?>"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="est-ejex<?= $n > 7 ? ' est-ejex-alterno' : $denso ?>" style="--n: <?= $n ?>" aria-hidden="true">
                    <?php foreach ($tramos as $t): ?><span><?= e($t['corto']) ?></span><?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <details class="est-datos">
                <summary>Ver datos en tabla</summary>
                <div class="tabla-contenedor">
                    <table class="tabla">
                        <thead><tr><th><?= $porSemana ? 'Semana' : 'Mes' ?></th><th class="texto-derecha">Atendidas</th><th class="texto-derecha">Inasistencias</th><th class="texto-derecha">Tasa</th></tr></thead>
                        <tbody>
                        <?php foreach ($tramos as $t): ?>
                            <tr><td><?= e($t['titulo']) ?></td><td class="texto-derecha mono"><?= $numero($t['atendidas']) ?></td>
                                <td class="texto-derecha mono"><?= $numero($t['inasistencias']) ?></td><td class="texto-derecha mono"><?= $pct($t['inasistencia']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><td>Período</td><td class="texto-derecha mono"><?= $numero($kpi['atendidas']) ?></td>
                            <td class="texto-derecha mono"><?= $numero($kpi['inasistencias']) ?></td><td class="texto-derecha mono"><?= $pct($kpi['inasistencia']) ?></td></tr></tfoot>
                    </table>
                </div>
            </details>
        </div>
    </section>

    <!-- 3 · Especialidades más pedidas -->
    <section class="tarjeta">
        <div class="tarjeta-cabecera">
            <div><h2><?= icono('especialidad') ?> Especialidades más pedidas</h2><p>Citas solicitadas en el período (incluye las canceladas).</p></div>
        </div>
        <div class="tarjeta-cuerpo">
            <?php if (!$maxEsp): ?>
                <div class="vacio"><?= icono('reporte') ?><p>No hay citas en el período elegido.</p></div>
            <?php else: ?>
            <ul class="est-barras-h" aria-label="Citas por especialidad">
                <?php foreach ($especialidades as $esp):
                    $parte = $kpi['total'] ? round(100 * $esp['total'] / $kpi['total']) : 0;
                    $tip = [['Citas', $esp['total'], 'est-total'], ['Del total', $parte . "\u{00A0}%", ''], ['Atendidas', $esp['atendidas'], ''],
                            ['Inasistencias', $esp['inasistencias'], ''], ['Canceladas', $esp['canceladas'], '']]; ?>
                <li tabindex="0" aria-label="<?= e($esp['nombre'] . ': ' . $esp['total'] . ' citas, ' . $parte . ' % del total') ?>"
                    data-tip-titulo="<?= e($esp['nombre']) ?>" data-tip="<?= e(json_encode($tip, JSON_UNESCAPED_UNICODE)) ?>">
                    <span class="est-barra-nombre"><?= especialidad_color($esp['nombre'], $esp['color']) ?></span>
                    <span class="est-barra-pista">
                        <?php if ($esp['total'] > 0): ?><span class="est-barra" style="--p: <?= round($esp['total'] / $maxEsp, 4) ?>"></span><?php endif; ?>
                        <b class="est-barra-valor"><?= $numero($esp['total']) ?> <small><?= $parte ?>&nbsp;%</small></b>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <details class="est-datos">
                <summary>Ver datos en tabla</summary>
                <div class="tabla-contenedor">
                    <table class="tabla">
                        <thead><tr><th>Especialidad</th><th class="texto-derecha">Citas</th><th class="texto-derecha">Atendidas</th><th class="texto-derecha">Inasistencias</th><th class="texto-derecha">Canceladas</th></tr></thead>
                        <tbody>
                        <?php foreach ($especialidades as $esp): ?>
                            <tr><td><?= e($esp['nombre']) ?></td><td class="texto-derecha mono"><?= $numero($esp['total']) ?></td>
                                <td class="texto-derecha mono"><?= $numero($esp['atendidas']) ?></td><td class="texto-derecha mono"><?= $numero($esp['inasistencias']) ?></td>
                                <td class="texto-derecha mono"><?= $numero($esp['canceladas']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    </section>
</div>

<!-- 4 · Asistencia por médico, comparada con el promedio de la clínica -->
<section class="tarjeta">
    <div class="tarjeta-cabecera">
        <div><h2><?= icono('medico') ?> Asistencia por médico</h2>
            <p>La marca vertical es el promedio de la clínica (<?= e($pct($kpi['inasistencia'])) ?>). Se señala a quien lo supera con al menos 10 citas cerradas.</p></div>
    </div>
    <?php if (!$medicos): ?>
        <div class="vacio"><?= icono('medico') ?><p>No hay citas en el período elegido.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla tabla-adaptable">
            <thead><tr><th>Médico</th><th class="texto-derecha">Citas</th><th class="texto-derecha">Atendidas</th><th class="texto-derecha">Inasistencias</th><th class="texto-derecha">Canceladas</th><th>Tasa de inasistencia</th></tr></thead>
            <tbody>
            <?php foreach ($medicos as $r): ?>
                <tr>
                    <td data-titulo="Médico"><?= e($r['medico']) ?></td>
                    <td data-titulo="Citas" class="texto-derecha mono"><?= $numero($r['total']) ?></td>
                    <td data-titulo="Atendidas" class="texto-derecha mono"><?= $numero($r['atendidas']) ?></td>
                    <td data-titulo="Inasistencias" class="texto-derecha mono"><?= $numero($r['inasistencias']) ?></td>
                    <td data-titulo="Canceladas" class="texto-derecha mono"><?= $numero($r['canceladas']) ?></td>
                    <td data-titulo="Tasa de inasistencia">
                        <span class="est-medidor<?= $r['sobre_promedio'] ? ' sobre' : '' ?>">
                            <span class="est-medidor-pista">
                                <?php if ($r['inasistencia']): ?><span class="est-medidor-relleno" style="width: <?= round(100 * $r['inasistencia'] / $ejeMedicos['tope'], 2) ?>%"></span><?php endif; ?>
                                <?php if ($kpi['inasistencia'] !== null): ?><i class="est-medidor-promedio" style="left: <?= round(100 * $kpi['inasistencia'] / $ejeMedicos['tope'], 2) ?>%" title="Promedio de la clínica"></i><?php endif; ?>
                            </span>
                            <span class="mono est-medidor-valor"><?= $pct($r['inasistencia']) ?></span>
                            <?php if ($r['sobre_promedio']): ?><span class="est-sobre"><?= icono('alerta') ?> Sobre el promedio</span><?php endif; ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<div class="est-tooltip" role="status" hidden></div>
