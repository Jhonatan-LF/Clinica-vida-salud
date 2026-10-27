<?php
/**
 * Diseño simple (sin menú): inicio de sesión, registro del paciente y errores.
 * Panel verde con la marca a la izquierda y el contenido a la derecha.
 * Variables: $titulo, $contenido, $mensajes.
 */
use App\Nucleo\Aplicacion;
use App\Nucleo\Peticion;

$tiposAlerta = ['exito' => ['alerta-exito', 'check-circulo'], 'error' => ['alerta-error', 'alerta'],
                'aviso' => ['alerta-aviso', 'info'], 'info' => ['alerta-info', 'info']];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f3d73">
    <title><?= e($titulo ?? 'Bienvenido') ?> · <?= e(Aplicacion::config('app.nombre')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= recurso('img/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= recurso('css/app.css') ?>">
</head>
<body data-base="<?= e(Peticion::base()) ?>">
<div class="acceso">
    <section class="acceso-marca" aria-label="Clínica Vida Salud">
        <a class="marca" href="<?= url('/') ?>">
            <span class="marca-icono"><?= icono('logo') ?></span>
            <span>
                <span class="marca-nombre">Vida Salud</span>
                <span class="marca-lema">Gestión de citas médicas</span>
            </span>
        </a>
        <div>
            <h1>Su salud, a tiempo y sin filas.</h1>
            <p>Sistema de gestión de citas médicas: agenda de especialistas, reservas en línea e historial de atención en un solo lugar.</p>
            <ul class="acceso-beneficios">
                <li><?= icono('calendario-mas') ?> Reserve en línea solo en horarios libres</li>
                <li><?= icono('agenda') ?> Agenda diaria y semanal de cada médico</li>
                <li><?= icono('historial') ?> Historial completo de sus citas</li>
                <li><?= icono('escudo') ?> Acceso seguro según su rol</li>
            </ul>
        </div>
    </section>

    <main class="acceso-panel" id="contenido">
        <div class="acceso-caja">
            <?php if (!empty($mensajes)): ?>
            <div class="mensajes">
                <?php foreach ($mensajes as $m): [$clase, $ico] = $tiposAlerta[$m['tipo']] ?? $tiposAlerta['info']; ?>
                <div class="alerta alerta-plana <?= $clase ?>" role="<?= $m['tipo'] === 'error' ? 'alert' : 'status' ?>">
                    <?= icono($ico) ?>
                    <p><?= e($m['mensaje']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?= $contenido ?>
        </div>
    </main>
</div>
<script src="<?= recurso('js/app.js') ?>"></script>
</body>
</html>
