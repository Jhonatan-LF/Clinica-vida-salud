/* =====================================================================
   CU17 · Panel de estadísticas: tooltip de los gráficos.
   Cada marca con data-tip (JSON [[etiqueta, valor, clase]]) muestra sus
   valores al pasar el mouse, al tocarla o al enfocarla con el teclado.
   El texto se inserta con textContent (los nombres vienen de la base).
   ===================================================================== */
(function () {
    'use strict';

    const tooltip = document.querySelector('.est-tooltip');
    if (!tooltip) return;

    let activa = null;

    function llenar(marca) {
        let filas = [];
        try { filas = JSON.parse(marca.dataset.tip || '[]'); } catch (e) { filas = []; }
        tooltip.replaceChildren();
        const titulo = document.createElement('p');
        titulo.className = 'est-tooltip-titulo';
        titulo.textContent = marca.dataset.tipTitulo || '';
        tooltip.appendChild(titulo);
        filas.forEach(([etiqueta, valor, clase]) => {
            const fila = document.createElement('p');
            fila.className = 'est-tooltip-fila';
            const clave = document.createElement('i');
            clave.className = 'est-tooltip-clave ' + (clase || 'sin-color');
            const v = document.createElement('b');
            v.textContent = typeof valor === 'number' ? valor.toLocaleString('es-BO') : String(valor);
            const t = document.createElement('span');
            t.textContent = etiqueta;
            fila.append(clave, v, t);
            tooltip.appendChild(fila);
        });
    }

    function ubicar(x, y) {
        const margen = 12;
        const ancho = tooltip.offsetWidth;
        const alto = tooltip.offsetHeight;
        let izq = x + margen;
        let arriba = y - alto - margen;
        if (izq + ancho > window.innerWidth - 8) izq = x - ancho - margen;
        if (izq < 8) izq = 8;
        if (arriba < 8) arriba = y + margen;
        tooltip.style.left = izq + 'px';
        tooltip.style.top = arriba + 'px';
    }

    function mostrar(marca, x, y) {
        if (activa !== marca) {
            if (activa) activa.classList.remove('activa');
            activa = marca;
            marca.classList.add('activa');
            llenar(marca);
        }
        tooltip.hidden = false;
        ubicar(x, y);
    }

    function ocultar() {
        if (activa) activa.classList.remove('activa');
        activa = null;
        tooltip.hidden = true;
    }

    document.querySelectorAll('[data-tip]').forEach((marca) => {
        marca.addEventListener('pointermove', (ev) => mostrar(marca, ev.clientX, ev.clientY));
        marca.addEventListener('pointerleave', ocultar);
        marca.addEventListener('focus', () => {
            const r = marca.getBoundingClientRect();
            mostrar(marca, r.left + r.width / 2, r.top + Math.min(r.height / 2, 40));
        });
        marca.addEventListener('blur', ocultar);
    });

    document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') ocultar(); });
    window.addEventListener('scroll', ocultar, { passive: true });
})();
