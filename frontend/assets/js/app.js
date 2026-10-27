/* =====================================================================
   Clínica Vida Salud · app.js (JavaScript puro, sin librerías)

   Usabilidad y validación en el NAVEGADOR. El servidor vuelve a validar
   todo (Validador.php): esta capa solo evita idas y vueltas al usuario.

     1. Menú lateral en el celular
     2. Mensajes, contraseña visible, impresión
     3. Ventanas modales (<dialog>) y confirmaciones
     4. Validación de formularios  (atributos data-validar, data-regla…)
     5. Formulario de usuario: campos según el rol
   ===================================================================== */
(function () {
    'use strict';

    const $ = (selector, raiz = document) => raiz.querySelector(selector);
    const $$ = (selector, raiz = document) => Array.from(raiz.querySelectorAll(selector));

    /* -----------------------------------------------------------------
       1. Menú lateral en el celular (botón hamburguesa)
       ----------------------------------------------------------------- */
    const botonMenu = $('[data-abrir-menu]');
    function menu(abierto) {
        document.body.classList.toggle('menu-abierto', abierto);
        if (botonMenu) botonMenu.setAttribute('aria-expanded', String(abierto));
    }
    if (botonMenu) {
        botonMenu.addEventListener('click', () => menu(!document.body.classList.contains('menu-abierto')));
    }
    $$('[data-cerrar-menu]').forEach((el) => el.addEventListener('click', () => menu(false)));
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') menu(false);
    });

    /* -----------------------------------------------------------------
       2. Pequeñas ayudas
       ----------------------------------------------------------------- */
    // Cerrar mensajes
    $$('[data-cerrar-alerta]').forEach((b) => b.addEventListener('click', () => b.closest('.alerta').remove()));

    // Los mensajes de éxito desaparecen solos a los 8 segundos
    $$('.alerta-exito').forEach((a) => setTimeout(() => a.remove(), 8000));

    // Mostrar u ocultar la contraseña
    $$('[data-ver-clave]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const campo = document.getElementById(boton.dataset.verClave);
            if (!campo) return;
            const ver = campo.type === 'password';
            campo.type = ver ? 'text' : 'password';
            boton.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
            const uso = boton.querySelector('use');
            if (uso) uso.setAttribute('href', uso.getAttribute('href').replace(/#.*$/, ver ? '#ojo-no' : '#ojo'));
        });
    });


    // Filtros que se aplican al cambiar (sin botón)
    $$('[data-enviar-al-cambiar]').forEach((campo) => {
        campo.addEventListener('change', () => campo.form && campo.form.submit());
    });

    // Imprimir / Guardar como PDF
    $$('[data-imprimir]').forEach((b) => b.addEventListener('click', () => window.print()));

    /* -----------------------------------------------------------------
       3. Ventanas modales y confirmaciones
       ----------------------------------------------------------------- */
    function abrirModal(dialogo) {
        if (!dialogo) return;
        if (typeof dialogo.showModal === 'function') {
            dialogo.showModal();
        } else {
            dialogo.setAttribute('open', '');
        }
        const primero = dialogo.querySelector('textarea, input:not([type=hidden]), select, button:not(.modal-cerrar)');
        if (primero) primero.focus();
    }
    function cerrarModal(dialogo) {
        if (!dialogo) return;
        if (typeof dialogo.close === 'function') dialogo.close(); else dialogo.removeAttribute('open');
    }

    $$('[data-modal-abrir]').forEach((boton) => {
        boton.addEventListener('click', () => abrirModal(document.getElementById(boton.dataset.modalAbrir)));
    });
    document.addEventListener('click', (ev) => {
        const cerrar = ev.target.closest('[data-modal-cerrar]');
        if (cerrar) cerrarModal(cerrar.closest('dialog'));
        // Clic fuera de la ventana (en el fondo oscuro)
        if (ev.target.tagName === 'DIALOG') cerrarModal(ev.target);
    });

    // Ventana de confirmación propia (no usa window.confirm)
    let dialogoConfirmar = null;
    function confirmar(mensaje, alAceptar) {
        if (!dialogoConfirmar) {
            dialogoConfirmar = document.createElement('dialog');
            dialogoConfirmar.className = 'modal';
            dialogoConfirmar.innerHTML =
                '<div class="modal-cabecera"><h2>Confirmar</h2></div>' +
                '<div class="modal-cuerpo"><p data-mensaje></p></div>' +
                '<div class="modal-pie">' +
                '<button type="button" class="boton boton-secundario" data-no>Cancelar</button>' +
                '<button type="button" class="boton boton-primario" data-si>Sí, continuar</button></div>';
            document.body.appendChild(dialogoConfirmar);
            dialogoConfirmar.querySelector('[data-no]').addEventListener('click', () => cerrarModal(dialogoConfirmar));
        }
        dialogoConfirmar.querySelector('[data-mensaje]').textContent = mensaje;
        const si = dialogoConfirmar.querySelector('[data-si]');
        const nuevo = si.cloneNode(true);             // quita oyentes anteriores
        si.replaceWith(nuevo);
        nuevo.addEventListener('click', () => { cerrarModal(dialogoConfirmar); alAceptar(); });
        abrirModal(dialogoConfirmar);
        nuevo.focus();
    }

    $$('form[data-confirmar]').forEach((form) => {
        form.addEventListener('submit', (ev) => {
            if (form.dataset.confirmado === '1') return;
            ev.preventDefault();
            confirmar(form.dataset.confirmar, () => {
                form.dataset.confirmado = '1';
                if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
            });
        });
    });

    /* -----------------------------------------------------------------
       4. Validación de formularios en el navegador
          (mismas reglas que backend/app/nucleo/Validador.php)
       ----------------------------------------------------------------- */
    const REGLAS = {
        nombre:   [/^[\p{L}][\p{L} .'-]*$/u, 'Solo se admiten letras y espacios.'],
        ci:       [/^[0-9]{5,12}([-A-Za-z0-9]{1,3})?$/, 'De 5 a 12 dígitos; puede llevar complemento (ej. 1234567-1B).'],
        telefono: [/^[0-9+\-\s]{7,20}$/, 'Debe tener entre 7 y 20 dígitos.'],
        usuario:  [/^[a-z0-9._]{4,40}$/, 'De 4 a 40 caracteres: minúsculas, números, punto o guion bajo.'],
        correo:   [/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/, 'Escriba un correo válido (ej. nombre@correo.com).'],    };

    function contenedor(campo) {
        return campo.closest('.campo') || campo.closest('fieldset') || campo.parentElement;
    }

    function mostrarError(campo, mensaje) {
        const caja = contenedor(campo);
        caja.classList.add('con-error');
        campo.setAttribute('aria-invalid', 'true');
        let p = caja.querySelector('.campo-error');
        if (!p) {
            p = document.createElement('p');
            p.className = 'campo-error';
            p.setAttribute('role', 'alert');
            caja.appendChild(p);
        }
        p.textContent = mensaje;
    }

    function limpiarError(campo) {
        const caja = contenedor(campo);
        if (!caja) return;
        caja.classList.remove('con-error');
        campo.removeAttribute('aria-invalid');
        const p = caja.querySelector('.campo-error');
        if (p) p.remove();
    }

    /** Devuelve el mensaje de error del campo o '' si es válido. */
    function errorDe(campo, form) {
        if (campo.disabled || campo.type === 'hidden' || campo.closest('.oculto')) return '';
        const valor = campo.type === 'checkbox' ? (campo.checked ? 'si' : '') : campo.value.trim();

        if (campo.required && !valor) {
            return campo.type === 'checkbox' ? 'Debe marcar esta casilla.' : 'Este dato es obligatorio.';
        }
        if (!valor) return '';

        if (campo.minLength > 0 && valor.length < campo.minLength) return `Escriba al menos ${campo.minLength} caracteres.`;
        if (campo.maxLength > 0 && valor.length > campo.maxLength) return `Escriba como máximo ${campo.maxLength} caracteres.`;
        if (campo.type === 'email' && !REGLAS.correo[0].test(valor)) return REGLAS.correo[1];

        const regla = campo.dataset.regla;
        if (regla === 'clave') {
            if (valor.length < 8 || !/[A-Za-z]/.test(valor) || !/\d/.test(valor)) return 'De 8 a 64 caracteres, con letras y números.';
        } else if (regla && REGLAS[regla] && !REGLAS[regla][0].test(regla === 'ci' ? valor.replace(/\s/g, '') : valor)) {
            return REGLAS[regla][1];
        }

        if (campo.type === 'date') {
            if (campo.min && valor < campo.min) return 'La fecha no puede ser anterior al ' + fechaCorta(campo.min) + '.';
            if (campo.max && valor > campo.max) return 'La fecha no puede ser posterior al ' + fechaCorta(campo.max) + '.';
        }
        if (campo.dataset.igualA) {
            const otro = form.querySelector('#' + campo.dataset.igualA);
            if (otro && otro.value !== campo.value) return 'No coincide con la contraseña.';
        }
        if (campo.dataset.posteriorA) {
            const otro = document.getElementById(campo.dataset.posteriorA);
            if (otro && otro.value && valor <= otro.value) return 'La hora final debe ser posterior a la hora inicial.';
        }
        return '';
    }

    function fechaCorta(iso) {
        const [a, m, d] = iso.split('-');
        return `${d}/${m}/${a}`;
    }

    function validarFormulario(form) {
        let primero = null;
        const campos = $$('input, select, textarea', form).filter((c) => c.name || c.id);

        campos.forEach((campo) => {
            const error = errorDe(campo, form);
            if (error) {
                mostrarError(campo, error);
                primero = primero || campo;
            } else if (campo.type !== 'checkbox' || campo.required) {
                limpiarError(campo);
            }
        });

        // Grupo "todo o nada" (contacto de emergencia)
        const grupos = {};
        $$('[data-grupo]', form).forEach((c) => (grupos[c.dataset.grupo] = grupos[c.dataset.grupo] || []).push(c));
        Object.values(grupos).forEach((lista) => {
            const algunoLleno = lista.some((c) => c.value.trim() !== '');
            if (!algunoLleno) return;
            lista.filter((c) => c.value.trim() === '').forEach((c) => {
                mostrarError(c, 'Complete también este dato del contacto.');
                primero = primero || c;
            });
        });

        // Al menos una casilla marcada (especialidades del médico)
        $$('[data-al-menos-uno]', form).forEach((fs) => {
            const casillas = $$('input[type=checkbox]', fs);
            if (!casillas.some((c) => c.checked)) {
                mostrarError(casillas[0] || fs, 'Seleccione al menos una opción.');
                primero = primero || casillas[0];
            }
        });

        if (primero) {
            primero.focus();
            if (primero.scrollIntoView) primero.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
        return !primero;
    }

    $$('form[data-validar]').forEach((form) => {
        form.addEventListener('submit', (ev) => {
            if (!validarFormulario(form)) {
                ev.preventDefault();
                ev.stopImmediatePropagation();
                form.dataset.intentado = '1';
            }
        }, true);
        // Una vez intentado, se revalida mientras el usuario corrige
        form.addEventListener('input', (ev) => {
            if (form.dataset.intentado !== '1' || !ev.target.matches('input, select, textarea')) return;
            const error = errorDe(ev.target, form);
            if (error) mostrarError(ev.target, error); else limpiarError(ev.target);
        });
        form.addEventListener('change', (ev) => {
            if (ev.target.type === 'checkbox' && ev.target.closest('[data-al-menos-uno]')) limpiarError(ev.target);
        });
    });

    // Evita el doble envío: deshabilita el botón al enviar
    document.addEventListener('submit', (ev) => {
        if (ev.defaultPrevented) return;
        const boton = ev.target.querySelector('button[type=submit], button:not([type])');
        if (boton && ev.target.method.toLowerCase() === 'post') {
            setTimeout(() => {
                boton.disabled = true;
                boton.dataset.texto = boton.innerHTML;
                boton.lastChild.textContent = ' Guardando…';
            }, 0);
        }
    });

    /* -----------------------------------------------------------------
       5. Formulario de usuario: vincular médico o paciente según el rol
       ----------------------------------------------------------------- */
    const selectorRol = $('[data-selector-rol]');
    if (selectorRol) {
        const aplicar = () => {
            const opcion = selectorRol.selectedOptions[0];
            const rol = opcion ? opcion.dataset.rol : '';
            $$('[data-solo-rol]').forEach((caja) => {
                const visible = caja.dataset.soloRol === rol;
                caja.classList.toggle('oculto', !visible);
                const select = caja.querySelector('select');
                if (select) {
                    select.required = visible;
                    if (!visible) select.value = '';
                }
            });
        };
        selectorRol.addEventListener('change', aplicar);
        aplicar();
    }

    /* -----------------------------------------------------------------
       6. Información médica: opciones rápidas (data-opciones="A|B|C")
          Cada botón agrega o quita su texto del campo, separado por comas.
          El campo sigue siendo de texto libre: se puede escribir otra cosa.
       ----------------------------------------------------------------- */
    const NINGUNO = /^(ninguna conocida|ninguno)$/i;

    function partes(valor) {
        return valor.split(/\s*[,;]\s*/).map((p) => p.trim()).filter(Boolean);
    }

    $$('[data-opciones]').forEach((campo) => {
        const opciones = campo.dataset.opciones.split('|').filter(Boolean);
        if (!opciones.length) return;
        const caja = document.createElement('div');
        caja.className = 'opciones-rapidas';
        caja.setAttribute('role', 'group');
        caja.setAttribute('aria-label', 'Opciones frecuentes');

        const botones = opciones.map((texto) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'opcion-rapida';
            b.textContent = texto;
            b.addEventListener('click', () => {
                let lista = partes(campo.value);
                const marcado = lista.some((p) => p.toLowerCase() === texto.toLowerCase());
                if (marcado) {
                    lista = lista.filter((p) => p.toLowerCase() !== texto.toLowerCase());
                } else if (NINGUNO.test(texto)) {
                    lista = [texto];                                  // «Ninguna» reemplaza a las demás
                } else {
                    lista = lista.filter((p) => !NINGUNO.test(p)).concat(texto);
                }
                const nuevo = lista.join(', ');
                if (campo.maxLength > 0 && nuevo.length > campo.maxLength) {
                    mostrarError(campo, `El texto no puede pasar de ${campo.maxLength} caracteres.`);
                    return;
                }
                campo.value = nuevo;
                limpiarError(campo);
                campo.dispatchEvent(new Event('input', { bubbles: true }));
                pintar();
            });
            caja.appendChild(b);
            return b;
        });

        function pintar() {
            const lista = partes(campo.value).map((p) => p.toLowerCase());
            botones.forEach((b) => {
                const activo = lista.includes(b.textContent.toLowerCase());
                b.classList.toggle('activa', activo);
                b.setAttribute('aria-pressed', String(activo));
            });
        }

        campo.insertAdjacentElement('afterend', caja);
        campo.addEventListener('input', pintar);
        pintar();
    });

    // Exportado para citas.js
    window.VidaSalud = { abrirModal, cerrarModal, confirmar, mostrarError, limpiarError };
})();
