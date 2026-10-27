<?php
/**
 * CU03 · Lista de pacientes con búsqueda por CI, código, nombre o apellido (sin importar tildes).
 * Variables: $pacientes, $total, $pagina, $paginas, $filtros (buscar, estado), $puedeGestionar.
 */
use App\Nucleo\Autenticacion;
use App\Nucleo\Vista;

$puedeProgramar = Autenticacion::puede('citas.programar');
?>
<div class="cabecera-seccion">
    <p>Registro de pacientes de la clínica. Un paciente desactivado conserva todo su historial (RN11).</p>
    <?php if ($puedeGestionar): ?>
    <div class="acciones">
        <a href="<?= url('/pacientes/nuevo') ?>" class="boton boton-primario"><?= icono('persona-mas') ?> Registrar paciente</a>
    </div>
    <?php endif; ?>
</div>

<section class="tarjeta">
    <form method="get" action="<?= url('/pacientes') ?>" class="filtros" role="search">
        <div class="campo campo-ancho">
            <label for="q">Buscar paciente</label>
            <div class="buscador"><?= icono('buscar') ?><input type="search" id="q" name="q" value="<?= e($filtros['buscar']) ?>" placeholder="CI, código, nombre o apellido" autofocus></div>
        </div>
        <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="">Todos</option>
                <option value="activo"<?= seleccionado($filtros['estado'], 'activo') ?>>Activos</option>
                <option value="inactivo"<?= seleccionado($filtros['estado'], 'inactivo') ?>>Inactivos</option>
            </select>
        </div>
        <div class="acciones">
            <button class="boton boton-primario"><?= icono('buscar') ?> Buscar</button>
            <?php if ($filtros['buscar'] || $filtros['estado']): ?><a class="boton boton-texto" href="<?= url('/pacientes') ?>">Limpiar</a><?php endif; ?>
        </div>
    </form>

    <?php if (!$pacientes): ?>
        <div class="vacio"><?= icono('pacientes') ?>
            <p>No se encontraron pacientes<?= $filtros['buscar'] ? ' para «' . e($filtros['buscar']) . '»' : '' ?>.</p>
            <?php if ($puedeGestionar): ?><p class="mt-1"><a href="<?= url('/pacientes/nuevo') ?>">Registrar un paciente nuevo</a></p><?php endif; ?>
        </div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla tabla-adaptable">
            <thead><tr><th>Paciente</th><th>CI</th><th>Edad</th><th>Teléfono</th><th>Última atención</th><th>Próxima cita</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pacientes as $p): ?>
                <tr class="<?= $p['estado'] !== 'activo' ? 'fila-inactiva' : '' ?>">
                    <td data-titulo="Paciente">
                        <a class="persona-celda" href="<?= url("/pacientes/{$p['id_paciente']}") ?>">
                            <span class="avatar"><?= e(iniciales($p['nombres'] . ' ' . $p['apellidos'])) ?></span>
                            <div><strong><?= e($p['apellidos'] . ', ' . $p['nombres']) ?></strong><span><?= e($p['codigo']) ?></span></div>
                        </a>
                    </td>
                    <td data-titulo="CI" class="mono"><?= e($p['ci'] ?: '—') ?></td>
                    <td data-titulo="Edad" class="nowrap"><?= e(edad($p['fecha_nacimiento'])) ?></td>
                    <td data-titulo="Teléfono" class="nowrap"><?= e($p['telefono']) ?></td>
                    <td data-titulo="Última atención" class="nowrap"><?= $p['ultima_atencion'] ? e(fecha_corta($p['ultima_atencion'])) : '<span class="texto-tenue">—</span>' ?></td>
                    <td data-titulo="Próxima cita" class="nowrap"><?= $p['proxima_cita'] ? e(fecha_corta($p['proxima_cita'])) : '<span class="texto-tenue">—</span>' ?></td>
                    <td data-titulo="Estado"><?= estado_registro($p['estado']) ?></td>
                    <td class="celda-acciones">
                        <div class="acciones">
                            <a class="boton boton-secundario boton-chico" href="<?= url("/pacientes/{$p['id_paciente']}") ?>"><?= icono('ojo') ?> Ver</a>
                            <?php if ($puedeProgramar && $p['estado'] === 'activo'): ?>
                            <a class="boton boton-texto boton-chico" href="<?= url('/citas/nueva', ['paciente' => $p['id_paciente']]) ?>" title="Programar cita"><?= icono('calendario-mas') ?><span class="solo-lector">Programar cita</span></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?= Vista::parcial('compartido/paginacion', ['pagina' => $pagina, 'paginas' => $paginas, 'total' => $total, 'ruta' => '/pacientes',
                                               'parametros' => ['q' => $filtros['buscar'], 'estado' => $filtros['estado']]]) ?>
</section>
