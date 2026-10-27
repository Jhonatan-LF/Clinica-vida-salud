<?php
/**
 * Diseño principal (usuarios con sesión): menú lateral fijo, barra superior y contenido.
 * Variables: $titulo, $contenido (HTML de la plantilla), $mensajes (flash), $scripts (opcional).
 */
use App\Nucleo\Aplicacion;
use App\Nucleo\Autenticacion;
use App\Nucleo\Peticion;
use App\Nucleo\Vista;

$usuario = Autenticacion::usuario();
$ruta = Peticion::ruta();
$titulo = $titulo ?? 'Panel';

// Migas de pan según la sección de la URL: Grupo › Sección › Página
$secciones = [
    'panel'          => ['Inicio', null, null],
    'citas'          => ['Citas', 'Citas', '/citas'],
    'agenda'         => ['Citas', 'Agenda médica', '/agenda'],
    'pacientes'      => ['Registros', 'Pacientes', '/pacientes'],
    'medicos'        => ['Registros', 'Médicos', '/medicos'],
    'especialidades' => ['Registros', 'Especialidades', null],
    'horarios'       => ['Registros', 'Horario de atención', null],
    'reportes'       => ['Administración', 'Reportes', null],
    'usuarios'       => ['Administración', 'Usuarios', '/usuarios'],
    'permisos'       => ['Administración', 'Roles y permisos', null],
    'bitacora'       => ['Administración', 'Bitácora', null],
    'mis-citas'      => ['Mi salud', 'Mis citas', null],
    'reservar'       => ['Mi salud', 'Reservar cita', null],
    'mi-perfil'      => ['Mi salud', 'Mi perfil', null],
    'cuenta'         => ['Mi cuenta', null, null],
];
$segmento = explode('/', trim($ruta, '/'))[0] ?: 'panel';
[$grupo, $seccion, $enlaceSeccion] = $secciones[$segmento] ?? ['Inicio', null, null];
$migas = [['Inicio', '/panel']];
if ($grupo !== 'Inicio') {
    $migas[] = [$grupo, null];
}
if ($seccion && $seccion !== $titulo) {
    $migas[] = [$seccion, $enlaceSeccion];
}

$tiposAlerta = ['exito' => ['alerta-exito', 'check-circulo'], 'error' => ['alerta-error', 'alerta'],
                'aviso' => ['alerta-aviso', 'info'], 'info' => ['alerta-info', 'info']];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f3d73">
    <title><?= e($titulo) ?> · <?= e(Aplicacion::config('app.nombre')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= recurso('img/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= recurso('css/app.css') ?>">
</head>
<body data-base="<?= e(Peticion::base()) ?>">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="app">
    <aside class="lateral" id="menu-lateral" aria-label="Menú principal">
        <a class="marca" href="<?= url('/panel') ?>">
            <span class="marca-icono"><?= icono('logo') ?></span>
            <span>
                <span class="marca-nombre">Vida Salud</span>
                <span class="marca-lema">Gestión de citas médicas</span>
            </span>
        </a>

        <?= Vista::parcial('compartido/menu', ['ruta' => $ruta]) ?>

        <?php if ($usuario): ?>
        <div class="lateral-pie">
            <div class="usuario-sesion">
                <span class="avatar" aria-hidden="true"><?= e(iniciales($usuario['nombre_completo'])) ?></span>
                <div>
                    <div class="usuario-nombre"><?= e($usuario['nombre_completo']) ?></div>
                    <div class="usuario-rol"><?= e($usuario['rol']) ?> · <a href="<?= url('/cuenta/clave') ?>">Cambiar contraseña</a></div>
                </div>
            </div>
            <form method="post" action="<?= url('/salir') ?>">
                <?= csrf_campo() ?>
                <button type="submit" class="boton boton-salir"><?= icono('salir') ?> Cerrar sesión</button>
            </form>
        </div>
        <?php endif; ?>
    </aside>
    <div class="velo" data-cerrar-menu></div>

    <div class="principal">
        <header class="barra">
            <button type="button" class="boton-menu" data-abrir-menu aria-controls="menu-lateral" aria-expanded="false">
                <?= icono('menu') ?><span class="solo-lector">Abrir menú</span>
            </button>
            <div class="barra-titulo">
                <ol class="migas" aria-label="Migas de pan">
                    <?php foreach ($migas as [$texto, $enlace]): ?>
                        <li><?= $enlace ? '<a href="' . url($enlace) . '">' . e($texto) . '</a>' : e($texto) ?></li>
                    <?php endforeach; ?>
                </ol>
                <h1><?= e($titulo) ?></h1>
            </div>
            <?php if ($usuario): ?>
            <div class="barra-info">
                <strong><?= e(saludo()) ?>, <?= e(explode(' ', preg_replace('/^(Dr|Dra)\.\s+/u', '', $usuario['nombre_completo']))[0]) ?></strong>
                <?= e(ucfirst(fecha_larga(date('Y-m-d')))) ?><br>
                <span class="chip-rol"><?= e($usuario['rol']) ?></span>
            </div>
            <?php endif; ?>
        </header>

        <main class="contenido" id="contenido" tabindex="-1">
            <?php if (!empty($mensajes)): ?>
            <div class="mensajes">
                <?php foreach ($mensajes as $m): [$clase, $ico] = $tiposAlerta[$m['tipo']] ?? $tiposAlerta['info']; ?>
                <div class="alerta <?= $clase ?>" role="<?= $m['tipo'] === 'error' ? 'alert' : 'status' ?>">
                    <?= icono($ico) ?>
                    <p><?= e($m['mensaje']) ?></p>
                    <button type="button" class="alerta-cerrar" data-cerrar-alerta aria-label="Cerrar mensaje"><?= icono('cerrar') ?></button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?= $contenido ?>
        </main>

        <footer class="pie">
            <?= e(Aplicacion::config('app.nombre')) ?> · Sistema de gestión de citas médicas · Grupo 19 · Sistemas I (UAGRM) · <?= date('Y') ?>
        </footer>
    </div>
</div>

<script src="<?= recurso('js/app.js') ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?>
<script src="<?= recurso('js/' . $s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
