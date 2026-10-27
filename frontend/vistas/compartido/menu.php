<?php
/**
 * Menú lateral: muestra SOLO las opciones que el rol del usuario tiene permitidas (RF01, RN12).
 * Cada opción: [texto, ruta, ícono, permiso (código, lista o 'autenticado'), prefijos que la marcan activa].
 * Variable: $ruta (ruta actual, p. ej. "/citas/15").
 */
use App\Nucleo\Autenticacion;

$esMedico = Autenticacion::rol() === 'medico';

$grupos = [
    'Inicio' => [
        ['Panel principal', '/panel', 'inicio', 'autenticado', ['/panel']],
    ],
    'Citas' => [
        ['Programar cita', '/citas/nueva', 'calendario-mas', 'citas.programar', ['/citas/nueva']],
        ['Citas', '/citas', 'citas', 'citas.ver', ['/citas']],
        [$esMedico ? 'Mi agenda' : 'Agenda médica', '/agenda', 'agenda', ['agenda.ver', 'agenda.propia'], ['/agenda']],
    ],
    'Registros' => [
        ['Pacientes', '/pacientes', 'pacientes', 'pacientes.ver', ['/pacientes']],
        ['Médicos', '/medicos', 'medico', 'medicos.ver', ['/medicos']],
        ['Especialidades', '/especialidades', 'especialidad', 'especialidades.gestionar', ['/especialidades']],
        ['Horario de atención', '/horarios', 'reloj', 'horarios.gestionar', ['/horarios']],
    ],
    'Administración' => [
        ['Reportes', '/reportes', 'reporte', 'reportes.ver', ['/reportes']],
        ['Usuarios', '/usuarios', 'usuarios', 'usuarios.gestionar', ['/usuarios']],
        ['Roles y permisos', '/permisos', 'escudo', 'usuarios.gestionar', ['/permisos']],
        ['Bitácora', '/bitacora', 'bitacora', 'bitacora.ver', ['/bitacora']],
    ],
    'Mi salud' => [
        ['Mis citas', '/mis-citas', 'citas', 'portal.mis_citas', ['/mis-citas']],
        ['Reservar cita', '/reservar', 'calendario-mas', 'portal.reservar', ['/reservar']],
        ['Mi perfil', '/mi-perfil', 'persona', 'portal.perfil', ['/mi-perfil']],
    ],
];

// Las opciones del portal ("Mi salud") solo tienen sentido para una cuenta vinculada a un paciente.
$tienePaciente = !empty(Autenticacion::usuario()['id_paciente']);
$visible = static fn (array $o): bool => match (true) {
    $o[3] === 'autenticado'                                 => Autenticacion::hayUsuario(),
    is_string($o[3]) && str_starts_with($o[3], 'portal.')   => $tienePaciente && Autenticacion::puede($o[3]),
    default                                                 => Autenticacion::puede($o[3]),
};

/** ¿Qué opción visible corresponde a la página actual? (la coincidencia más larga gana) */
$activa = null;
$largo = 0;
foreach ($grupos as $opciones) {
    foreach (array_filter($opciones, $visible) as $o) {
        foreach ($o[4] as $prefijo) {
            if (($ruta === $prefijo || str_starts_with($ruta, $prefijo . '/')) && strlen($prefijo) > $largo) {
                $activa = $o[1];
                $largo = strlen($prefijo);
            }
        }
    }
}
// El detalle de una cita del médico se ve desde su agenda.
if ($activa === null && str_starts_with($ruta, '/citas/') && !Autenticacion::puede('citas.ver')) {
    $activa = Autenticacion::puede('portal.mis_citas') ? '/mis-citas' : '/agenda';
}
?>
<nav class="menu">
    <?php foreach ($grupos as $grupo => $opciones):
        $visibles = array_filter($opciones, $visible);
        if (!$visibles) {
            continue;
        } ?>
    <div class="menu-grupo">
        <p class="menu-titulo"><?= e($grupo) ?></p>
        <ul>
            <?php foreach ($visibles as $o): ?>
            <li>
                <a href="<?= url($o[1]) ?>"<?= $activa === $o[1] ? ' class="activo" aria-current="page"' : '' ?>>
                    <?= icono($o[2]) ?><span><?= e($o[0]) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
</nav>
