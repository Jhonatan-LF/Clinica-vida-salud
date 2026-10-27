# Frontend — plantillas PHP, CSS y JavaScript puro

Todo lo que ve el usuario. Sin Bootstrap, sin jQuery y sin CDN: HTML5 generado por plantillas PHP, un CSS propio y
JavaScript puro para validar y dar usabilidad. Las fuentes **Inter** (texto) y **Lora** (títulos) vienen incluidas.

```
frontend/
├── assets/                 ← se publica en /assets (lo hace el .htaccess de la raíz o backend/public/router.php)
│   ├── css/app.css         sistema de diseño completo
│   ├── fonts/              Inter y Lora (woff2) + licencias OFL
│   ├── img/                logo.svg, favicon.svg, iconos.svg (sprite de íconos de línea)
│   └── js/
│       ├── app.js          validación de formularios, confirmaciones, modales, menú en celular, ver contraseña
│       └── citas.js        CU09/CU10: médicos, días y horas libres con fetch, sin recargar
└── vistas/                 ← una carpeta por paquete del backend
    ├── compartido/         layout_principal.php, layout_simple.php, menu.php, paginacion.php, error.php
    ├── inicio/             panel.php (según el rol), citas_hoy.php
    ├── seguridad/          login, cambiar_clave, usuarios_lista, usuario_form, permisos, bitacora
    ├── pacientes/          lista, formulario, ficha, historial, registro, mi_perfil, mis_citas
    ├── medicos/            especialidades, medicos_lista, medico_form, medico_ficha, horarios
    ├── citas/              programar, reprogramar, detalle, listado, agenda, agenda_semana
    └── reportes/           reportes (pantalla, impresión/PDF y CSV)
```

## Cómo se muestra una vista

Un caso de uso llama a `$this->vista('paquete/plantilla', [...datos])`:

```php
$this->vista('citas/agenda', ['titulo' => 'Agenda médica', 'citas' => $citas]);
// → frontend/vistas/citas/agenda.php dentro de frontend/vistas/compartido/layout_principal.php
```

Las claves del arreglo llegan a la plantilla como variables (`$titulo`, `$citas`…). Toda plantilla recibe además
`$anterior` (lo que el usuario escribió antes de un error) y `$errores` (mensaje por campo). El layout recibe
`$mensajes` (avisos flash) y `$scripts` (JS extra, p. ej. `['citas.js']`). El login, el registro y las páginas de
error usan `layout_simple.php`.

## Reglas para escribir una vista

| Necesito… | Uso |
|---|---|
| imprimir un dato | `<?= e($valor) ?>` — **siempre**, evita XSS |
| un enlace | `<a href="<?= url('/pacientes', ['q' => $q]) ?>">` |
| un archivo estático | `<?= recurso('css/app.css') ?>` (agrega versión para la caché) |
| un ícono | `<?= icono('calendario') ?>` (símbolos de `img/iconos.svg`) |
| un formulario POST | `<?= csrf_campo() ?>` dentro del `<form>` |
| repetir lo escrito | `value="<?= viejo($anterior, 'nombres', $p['nombres'] ?? '') ?>"` |
| mostrar el error de un campo | `class="campo<?= clase_error($errores, 'ci') ?>"` y `<?= error_campo($errores, 'ci') ?>` |
| un select | `<option value="x"<?= seleccionado($actual, 'x') ?>>` · casillas: `marcado($bool)` |
| fechas y estados | `fecha_corta()`, `fecha_larga()`, `fecha_hora()`, `hora()`, `edad()`, `estado_cita()`, `estado_registro()` |
| la especialidad con su color | `especialidad_color($c['especialidad'], $c['color_especialidad'])` (punto de color + nombre) |
| un fragmento | `Vista::parcial('compartido/paginacion', [...])` |

Cada campo lleva su `<label for>`. Las vistas **no** consultan la base de datos: reciben todo del caso de uso
(solo leen la configuración o el usuario de la sesión para decidir qué botones mostrar).

## Validación en el navegador (app.js)

Agregue `data-validar novalidate` al `<form>` y use atributos en los campos:

- `required`, `minlength`, `maxlength`, `type="email"`, `min`/`max` en fechas;
- `data-regla="nombre|ci|telefono|usuario|clave"` (mismas reglas que `Validador.php`);
- `data-igual-a="clave"` (confirmación), `data-posterior-a="hora_inicio"` (horas);
- `data-grupo="emergencia"` (si se llena uno, se exigen todos);
- `<fieldset data-al-menos-uno>` (al menos una casilla marcada).

`data-opciones="A|B|C"` en un campo de texto agrega botones de opciones rápidas (información médica).

Otros atributos: `data-confirmar="¿Seguro…?"` (ventana de confirmación propia), `data-modal-abrir="id"` /
`data-modal-cerrar` (ventanas `<dialog>`), `data-enviar-al-cambiar` (filtros), `data-imprimir`, `data-ver-clave="id"`.

El servidor **siempre** vuelve a validar: el JavaScript solo ahorra idas y vueltas.

## Cambiar los colores

Todos los colores están como variables al inicio de `assets/css/app.css`:

```css
:root {
    --primario: #0f3d73; /* menú lateral y botones principales (azul marino) */
    --acento:   #c7defa; /* acentos sobre el azul */
    --verde:    #1e8a5a; /* éxito, cita atendida */
    --fondo:    #f1f4f9; /* fondo gris azulado */
    --azul:   #2563c9;   /* cita programada */
    --turquesa: #0e7f86; /* confirmada */  --gris: #6f7a77; /* cancelada */  --naranja: #c56a12; /* inasistencia */
}
```

Cambie el valor y recargue con Ctrl+F5. El CSS incluye estilos para celular (menú con botón hamburguesa, tablas
que se vuelven tarjetas) y para impresión (reportes y agendas → "Guardar como PDF").
