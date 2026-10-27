<?php
/**
 * CU13 · Agenda diaria de un médico (RF11): hora, código, paciente, especialidad, estado y motivo.
 * Incluye los intervalos libres del día. El médico solo ve su propia agenda.
 * Variables: $medicos, $medico, $fecha, $estado, $citas, $libres, $soloPropia, $verMotivo,
 *            $puedeAtender, $puedeConfirmar, $puedeProgramar.
 */
use App\Nucleo\Autenticacion;
use App\Nucleo\Peticion;
use App\Paquetes\Citas\Servicios\EstadoCita;

$diaAnterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$diaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
$idMedico = $medico['id_medico'] ?? null;
$volver = Peticion::ruta() . '?' . http_build_query(array_filter(['medico' => $soloPropia ? null : $idMedico, 'fecha' => $fecha, 'estado' => $estado]));
$u = Autenticacion::usuario();

// Citas y huecos libres en una sola línea de tiempo ordenada por hora.
$items = [];
foreach ($citas as $c) {
    $items[] = ['tipo' => 'cita', 'hora' => hora($c['hora_inicio']), 'cita' => $c];
}
foreach ($libres as $l) {
    $items[] = ['tipo' => 'libre', 'hora' => $l['inicio'], 'fin' => $l['fin']];
}
usort($items, static fn ($a, $b) => strcmp($a['hora'], $b['hora']));

$conteo = array_count_values(array_column($citas, 'estado'));
$textosEstado = ['confirmada' => ['Confirmar', 'check'], 'atendida' => ['Atendida', 'check-circulo'], 'inasistencia' => ['No asistió', 'alerta']];
?>
<section class="tarjeta">
    <form method="get" action="<?= url('/agenda') ?>" class="filtros">
        <?php if (!$soloPropia): ?>
        <div class="campo campo-ancho">
            <label for="medico">Médico</label>
            <select id="medico" name="medico" data-enviar-al-cambiar>
                <?php foreach ($medicos as $m): ?><option value="<?= $m['id_medico'] ?>"<?= seleccionado($idMedico, $m['id_medico']) ?>><?= e($m['nombre'] . ($m['estado'] !== 'activo' ? ' (inactivo)' : '')) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="campo">
            <label for="fecha">Fecha</label>
            <div class="selector-fecha">
                <a class="boton boton-secundario boton-icono" href="<?= url('/agenda', ['medico' => $soloPropia ? null : $idMedico, 'fecha' => $diaAnterior, 'estado' => $estado]) ?>" aria-label="Día anterior"><?= icono('izquierda') ?></a>
                <input type="date" id="fecha" name="fecha" value="<?= e($fecha) ?>" data-enviar-al-cambiar>
                <a class="boton boton-secundario boton-icono" href="<?= url('/agenda', ['medico' => $soloPropia ? null : $idMedico, 'fecha' => $diaSiguiente, 'estado' => $estado]) ?>" aria-label="Día siguiente"><?= icono('derecha') ?></a>
            </div>
        </div>
        <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" data-enviar-al-cambiar>
                <option value="">Todos</option>
                <?php foreach (array_keys(EstadoCita::TRANSICIONES) as $est): ?><option value="<?= $est ?>"<?= seleccionado($estado, $est) ?>><?= ucfirst($est) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="acciones">
            <noscript><button class="boton boton-primario">Ver</button></noscript>
            <a class="boton boton-texto" href="<?= url('/agenda', ['medico' => $soloPropia ? null : $idMedico]) ?>">Hoy</a>
            <a class="boton boton-secundario" href="<?= url('/agenda/semana', ['medico' => $soloPropia ? null : $idMedico, 'fecha' => $fecha]) ?>"><?= icono('semana') ?> Semana</a>
            <button type="button" class="boton boton-secundario" data-imprimir><?= icono('imprimir') ?> Imprimir</button>
        </div>
    </form>

    <?php if (!$medico): ?>
        <div class="vacio"><?= icono('medico') ?><p>No hay médicos registrados.</p></div>
    <?php else: ?>
    <div class="tarjeta-cuerpo">
        <div class="reporte-titulo-impresion">
            <h1>Agenda de <?= e($medico['nombre']) ?></h1>
            <p><?= e(ucfirst(fecha_larga($fecha))) ?> · Clínica Vida Salud · impreso el <?= date('d/m/Y H:i') ?></p>
        </div>
        <div class="cabecera-seccion mb-2">
            <div>
                <h2 class="mb-0"><?= e(ucfirst(fecha_larga($fecha))) ?><?= $fecha === date('Y-m-d') ? ' · hoy' : '' ?></h2>
                <p><?= e($medico['nombre']) ?> · <?= e($medico['especialidades']) ?></p>
            </div>
            <div class="totales">
                <span class="chip"><b><?= count($citas) ?> cita(s)</b></span>
                <?php foreach ($conteo as $est => $n): ?><span class="total-estado"><?= estado_cita($est) ?> <b><?= $n ?></b></span><?php endforeach; ?>
                <?php if ($libres): ?><span class="chip"><?= count($libres) ?> libre(s)</span><?php endif; ?>
            </div>
        </div>

        <?php if (!$items): ?>
            <div class="vacio"><?= icono('agenda') ?><p>No hay citas <?= $estado ? 'en estado «' . e($estado) . '» ' : '' ?>para este día.</p></div>
        <?php else: ?>
        <div class="agenda-dia">
            <?php foreach ($items as $it): if ($it['tipo'] === 'libre'): ?>
                <div class="agenda-item libre">
                    <div class="agenda-hora"><?= e($it['hora']) ?><span><?= e($it['fin']) ?></span></div>
                    <div>Horario libre</div>
                    <?php if ($puedeProgramar): ?><div class="acciones"><a class="boton boton-texto boton-chico" href="<?= url('/citas/nueva') ?>"><?= icono('calendario-mas') ?> Programar</a></div><?php endif; ?>
                </div>
            <?php else: $c = $it['cita'];
                $acciones = array_filter(EstadoCita::acciones($c), static fn ($a) => match ($a) {
                    'confirmada', 'inasistencia' => $puedeConfirmar,
                    'atendida' => $puedeAtender && ($u['rol'] !== 'medico' || (int) $u['id_medico'] === (int) $c['id_medico']),
                    default => false,
                });
                $info = $c['compartir_info_medica'] || Autenticacion::puede('pacientes.ver'); ?>
                <div class="agenda-item <?= e($c['estado']) ?>">
                    <div class="agenda-hora"><?= e(hora($c['hora_inicio'])) ?><span><?= e(hora($c['hora_fin'])) ?></span></div>
                    <div>
                        <div class="agenda-paciente"><strong><?= e($c['paciente']) ?></strong> · <?= estado_cita($c['estado']) ?></div>
                        <div class="agenda-detalle">
                            <a href="<?= url("/citas/{$c['id_cita']}") ?>"><?= e($c['codigo']) ?></a>
                            <?= especialidad_color($c['especialidad'], $c['color_especialidad']) ?>
                            <span><?= e(edad($c['fecha_nacimiento'])) ?></span>
                            <?php if ($c['modalidad'] === 'virtual'): ?><span class="chip chip-virtual"><?= icono('video') ?> Virtual</span><?php endif; ?>
                            <?php if ($info && $c['alergias']): ?><span class="chip chip-alerta"><?= icono('alerta') ?> Alergia: <?= e($c['alergias']) ?></span><?php endif; ?>
                            <?php if ($info && $c['enfermedades_cronicas']): ?><span class="chip"><?= e($c['enfermedades_cronicas']) ?></span><?php endif; ?>
                        </div>
                        <?php if ($verMotivo && ($c['motivo'] || $c['motivo_cancelacion'])): ?>
                            <div class="agenda-motivo texto-suave"><?= e($c['estado'] === 'cancelada' ? 'Cancelada: ' . $c['motivo_cancelacion'] : 'Motivo: ' . $c['motivo']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="acciones">
                        <?php foreach ($acciones as $a): [$texto, $ico] = $textosEstado[$a]; ?>
                        <form method="post" action="<?= url("/citas/{$c['id_cita']}/estado") ?>"<?= $a === 'inasistencia' ? ' data-confirmar="¿Registrar que el paciente no se presentó?"' : '' ?>>
                            <?= csrf_campo() ?>
                            <input type="hidden" name="estado" value="<?= $a ?>">
                            <input type="hidden" name="volver" value="<?= e($volver) ?>">
                            <button class="boton <?= $a === 'atendida' ? 'boton-exito' : 'boton-secundario' ?> boton-chico"><?= icono($ico) ?> <?= $texto ?></button>
                        </form>
                        <?php endforeach; ?>
                        <a class="boton boton-texto boton-chico" href="<?= url("/citas/{$c['id_cita']}") ?>">Detalle</a>
                    </div>
                </div>
            <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
