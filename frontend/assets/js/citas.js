/* =====================================================================
   Clínica Vida Salud · citas.js
   CU09 Programar cita / Reservar cita y CU10 Reprogramar cita.

   Consulta la disponibilidad con fetch, sin recargar la página:
     GET /api/pacientes?q=                       (solo recepción)
     GET /api/medicos?especialidad=ID
     GET /api/disponibilidad/dias?medico=ID&desde=AAAA-MM-DD[&excluir=ID]
     GET /api/disponibilidad?medico=ID&fecha=AAAA-MM-DD[&excluir=ID]
   Solo se pueden elegir horas LIBRES; aun así el servidor vuelve a
   verificar todo al guardar (RN13) dentro de una transacción.
   ===================================================================== */
(function () {
    'use strict';

    const form = document.getElementById('form-cita');
    if (!form) return;

    const base = document.body.dataset.base || '';
    const esReprogramar = form.hasAttribute('data-reprogramar-cita');
    const $ = (id) => document.getElementById(id);
    const DIAS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    let restaurar = {};
    try { restaurar = JSON.parse(form.dataset.restaurar || '{}') || {}; } catch (e) { restaurar = {}; }

    const estado = {
        paciente: ($('campo-paciente') && $('campo-paciente').value) || (form.querySelector('#paciente-elegido') ? 'propio' : ''),
        especialidad: '',
        medico: esReprogramar ? form.dataset.medico : '',
        fecha: '',
        hora: '',
        desde: hoyIso(),
    };
    const excluir = esReprogramar ? form.dataset.excluir : '';

    /* ---------------- utilidades ---------------- */
    function hoyIso() {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }
    function sumarDias(iso, n) {
        const [a, m, d] = iso.split('-').map(Number);
        const f = new Date(a, m - 1, d + n);
        return `${f.getFullYear()}-${String(f.getMonth() + 1).padStart(2, '0')}-${String(f.getDate()).padStart(2, '0')}`;
    }
    function fechaTexto(iso) {
        const [a, m, d] = iso.split('-').map(Number);
        const f = new Date(a, m - 1, d);
        return `${DIAS[f.getDay()]} ${d} de ${MESES[m - 1]}`;
    }
    function escapar(texto) {
        const div = document.createElement('div');
        div.textContent = texto == null ? '' : String(texto);
        return div.innerHTML;
    }
    async function pedir(ruta, parametros) {
        const url = new URL(base + ruta, window.location.origin);
        Object.entries(parametros || {}).forEach(([k, v]) => { if (v !== '' && v != null) url.searchParams.set(k, v); });
        const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' });
        const datos = await r.json().catch(() => ({ ok: false, mensaje: 'Respuesta no válida del servidor.' }));
        if (r.status === 401) { window.location.href = base + '/login'; }
        if (!r.ok || datos.ok === false) throw new Error(datos.mensaje || 'No se pudo consultar la disponibilidad.');
        return datos;
    }
    function cargando(contenedor, texto) {
        contenedor.innerHTML = `<div class="cargando">${escapar(texto)}</div>`;
    }
    function mensaje(contenedor, texto) {
        contenedor.innerHTML = `<p class="texto-suave mb-0">${escapar(texto)}</p>`;
    }
    function ticket(id, texto) {
        const el = $(id);
        if (!el) return;
        el.textContent = texto || 'Sin elegir';
        el.classList.toggle('vacio-dato', !texto);
    }
    function paso(n, situacion) {
        const el = $('paso-' + n);
        if (!el) return;
        el.classList.remove('activo', 'completo', 'bloqueado');
        el.classList.add(situacion);
    }

    /* ---------------- paso 1: paciente (recepción) ---------------- */
    const buscar = $('buscar-paciente');
    const resultados = $('resultados-paciente');
    let temporizador = null;

    if (buscar) {
        buscar.addEventListener('input', () => {
            clearTimeout(temporizador);
            const q = buscar.value.trim();
            if (q.length < 2) { resultados.classList.add('oculto'); return; }
            temporizador = setTimeout(async () => {
                try {
                    const datos = await pedir('/api/pacientes', { q });
                    resultados.classList.remove('oculto');
                    if (!datos.pacientes.length) {
                        resultados.innerHTML = '<p class="texto-suave" style="padding:.7rem .85rem;margin:0">Sin resultados. Revise el CI o registre al paciente.</p>';
                        return;
                    }
                    resultados.innerHTML = datos.pacientes.map((p) =>
                        `<button type="button" role="option" data-id="${p.id}" data-nombre="${escapar(p.nombre)}" data-detalle="${escapar(p.codigo + ' · ' + p.detalle)}">
                            <span class="avatar">${escapar(iniciales(p.nombre))}</span>
                            <span><strong>${escapar(p.nombre)}</strong><span>${escapar(p.codigo)} · ${escapar(p.detalle)}</span></span>
                         </button>`).join('');
                } catch (e) {
                    resultados.classList.remove('oculto');
                    resultados.innerHTML = `<p class="campo-error" style="padding:.7rem .85rem">${escapar(e.message)}</p>`;
                }
            }, 250);
        });
        resultados.addEventListener('click', (ev) => {
            const b = ev.target.closest('button[data-id]');
            if (b) elegirPaciente(b.dataset.id, b.dataset.nombre, b.dataset.detalle);
        });
    }

    function iniciales(nombre) {
        return nombre.split(/\s+/).slice(0, 2).map((p) => p.charAt(0)).join('').toUpperCase();
    }

    function elegirPaciente(id, nombre, detalle) {
        estado.paciente = id;
        $('campo-paciente').value = id;
        let tarjeta = $('paciente-elegido');
        if (!tarjeta) {
            tarjeta = document.createElement('div');
            tarjeta.className = 'paciente-elegido';
            tarjeta.id = 'paciente-elegido';
            $('buscador-paciente').before(tarjeta);
        }
        tarjeta.classList.remove('oculto');
        tarjeta.innerHTML = `<span class="avatar">${escapar(iniciales(nombre))}</span>
            <div><strong id="nombre-paciente">${escapar(nombre)}</strong><span>${escapar(detalle)}</span></div>
            <button type="button" class="boton boton-texto boton-chico" data-cambiar-paciente>Cambiar</button>`;
        $('buscador-paciente').classList.add('oculto');
        if (window.VidaSalud) window.VidaSalud.limpiarError(buscar);
        ticket('t-paciente', nombre);
        actualizar();
    }

    form.addEventListener('click', (ev) => {
        if (!ev.target.closest('[data-cambiar-paciente]')) return;
        estado.paciente = '';
        $('campo-paciente').value = '';
        const tarjeta = $('paciente-elegido');
        if (tarjeta) tarjeta.classList.add('oculto');
        $('buscador-paciente').classList.remove('oculto');
        buscar.value = '';
        buscar.focus();
        ticket('t-paciente', '');
        actualizar();
    });

    /* ---------------- paso 2: especialidad -> médicos ---------------- */
    const listaMedicos = $('lista-medicos');
    form.querySelectorAll('input[name=id_especialidad]').forEach((radio) => {
        radio.addEventListener('change', () => elegirEspecialidad(radio.value, radio.dataset.nombre));
    });

    async function elegirEspecialidad(id, nombre, medicoARestaurar) {
        estado.especialidad = id;
        estado.medico = '';
        reiniciarFecha();
        ticket('t-especialidad', nombre);
        ticket('t-medico', '');
        $('bloque-dias').classList.add('oculto');
        if ($('resultado-primer-turno')) $('resultado-primer-turno').innerHTML = '';
        actualizar();
        cargando(listaMedicos, 'Buscando médicos activos…');
        try {
            const datos = await pedir('/api/medicos', { especialidad: id });
            if (!datos.medicos.length) {
                mensaje(listaMedicos, 'No hay médicos activos con horario para esta especialidad.');
                return;
            }
            listaMedicos.innerHTML = datos.medicos.map((m) =>
                `<div class="opcion-tarjeta">
                    <input type="radio" name="id_medico" id="med-${m.id}" value="${m.id}" data-nombre="${escapar(m.nombre)}">
                    <label for="med-${m.id}"><span class="avatar">${escapar(iniciales(m.nombre.replace(/^Dr\(a\)\.\s*/, '')))}</span>
                        <span>${escapar(m.nombre)}<small>${escapar(m.codigo)}</small></span></label>
                 </div>`).join('');
            listaMedicos.querySelectorAll('input').forEach((r) => r.addEventListener('change', () => elegirMedico(r.value, r.dataset.nombre)));
            const aRestaurar = medicoARestaurar && listaMedicos.querySelector(`#med-${medicoARestaurar}`);
            if (aRestaurar) {
                aRestaurar.checked = true;
                await elegirMedico(aRestaurar.value, aRestaurar.dataset.nombre);
            } else if (datos.medicos.length === 1) {
                const unico = listaMedicos.querySelector('input');
                unico.checked = true;
                await elegirMedico(unico.value, unico.dataset.nombre);
            }
        } catch (e) {
            mensaje(listaMedicos, e.message);
        }
    }

    /* ---------------- paso 3: médico -> días ---------------- */
    const tira = $('tira-dias');
    const botonAnteriores = $('dias-anteriores');
    const botonSiguientes = $('dias-siguientes');

    /** @param {string} [desde] primer día de la tira (por defecto, hoy) */
    async function elegirMedico(id, nombre, desde) {
        estado.medico = id;
        estado.desde = desde && desde > hoyIso() ? desde : hoyIso();
        reiniciarFecha();
        ticket('t-medico', nombre);
        $('bloque-dias').classList.remove('oculto');
        actualizar();
        await cargarDias();
    }

    /* ---------------- CU15: buscar el primer turno libre ---------------- */
    const botonPrimerTurno = $('boton-primer-turno');
    const resultadoPrimerTurno = $('resultado-primer-turno');

    let turnosSugeridos = [];

    function tarjetaTurno(t, principal) {
        const fecha = t.fecha_texto.charAt(0).toUpperCase() + t.fecha_texto.slice(1);
        turnosSugeridos.push(t);
        return `<div class="turno-sugerido${principal ? ' destacado' : ''}">
                    <div><strong>${escapar(fecha)} · ${escapar(t.inicio)} a ${escapar(t.fin)}</strong>
                         <span>${escapar(t.medico)}</span></div>
                    <button type="button" class="boton ${principal ? 'boton-primario' : 'boton-secundario'} boton-chico"
                            data-usar-turno="${turnosSugeridos.length - 1}">Usar este turno</button>
                </div>`;
    }

    if (botonPrimerTurno) {
        botonPrimerTurno.addEventListener('click', async () => {
            if (!estado.especialidad) return;
            cargando(resultadoPrimerTurno, 'Buscando el turno libre más cercano…');
            try {
                const datos = await pedir('/api/primer-turno', { especialidad: estado.especialidad });
                if (!datos.turno) {
                    mensaje(resultadoPrimerTurno, datos.mensaje || 'No hay turnos libres.');
                    return;
                }
                turnosSugeridos = [];
                resultadoPrimerTurno.innerHTML =
                    '<p class="etiqueta mb-1">Turno libre más cercano</p>' + tarjetaTurno(datos.turno, true)
                    + (datos.alternativas.length ? '<p class="etiqueta mt-2 mb-1">Con otros médicos</p>'
                        + datos.alternativas.map((t) => tarjetaTurno(t, false)).join('') : '');
            } catch (e) {
                mensaje(resultadoPrimerTurno, e.message);
            }
        });

        resultadoPrimerTurno.addEventListener('click', async (ev) => {
            const boton = ev.target.closest('[data-usar-turno]');
            if (!boton) return;
            const t = turnosSugeridos[Number(boton.dataset.usarTurno)];
            const radio = listaMedicos.querySelector(`#med-${t.id_medico}`);
            if (!radio) return;
            radio.checked = true;
            resultadoPrimerTurno.innerHTML = '';
            restaurar.fecha = t.fecha;          // cargarDias() elige el día y elegirFecha() la hora
            restaurar.hora_inicio = t.inicio;
            await elegirMedico(String(t.id_medico), radio.dataset.nombre, t.fecha);
            $('paso-4').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    async function cargarDias() {
        cargando(tira, 'Calculando días con turnos libres…');
        botonAnteriores.disabled = estado.desde <= hoyIso();
        try {
            const datos = await pedir('/api/disponibilidad/dias', { medico: estado.medico, desde: estado.desde, excluir });
            const dias = datos.dias;
            $('titulo-dias').textContent = `Del ${fechaTexto(dias[0].fecha)} al ${fechaTexto(dias[dias.length - 1].fecha)}`;
            tira.innerHTML = dias.map((d) => {
                const [, m, dia] = d.fecha.split('-');
                const libre = d.libres > 0;
                const nota = libre ? `${d.libres} libre${d.libres === 1 ? '' : 's'}` : (d.motivo ? 'Sin turnos' : (d.atiende ? 'Lleno' : 'No atiende'));
                const titulo = d.motivo || (libre ? `${d.libres} turno(s) libre(s)` : nota);
                return `<button type="button" class="dia-boton${d.fecha === estado.fecha ? ' elegido' : ''}" data-fecha="${d.fecha}" ${libre ? '' : 'disabled'} title="${escapar(titulo)}">
                    <span class="dia-nombre">${DIAS[new Date(+d.fecha.slice(0, 4), +m - 1, +dia).getDay()]}</span>
                    <span class="dia-numero">${+dia}</span>
                    <span class="dia-libres">${escapar(nota)}</span></button>`;
            }).join('');
            botonSiguientes.disabled = dias.every((d) => d.motivo && d.motivo.startsWith('Solo se reservan'));

            if (restaurar.fecha && tira.querySelector(`[data-fecha="${restaurar.fecha}"]:not(:disabled)`)) {
                const f = restaurar.fecha;
                restaurar.fecha = null;
                await elegirFecha(f);
            } else if (!tira.querySelector('.dia-boton:not(:disabled)')) {
                tira.insertAdjacentHTML('beforeend', '<p class="texto-suave" style="grid-column:1/-1;margin:.5rem 0 0">No hay turnos libres en estos días. Pruebe con los siguientes.</p>');
            }
        } catch (e) {
            mensaje(tira, e.message);
        }
    }

    tira.addEventListener('click', (ev) => {
        const b = ev.target.closest('.dia-boton:not(:disabled)');
        if (b) elegirFecha(b.dataset.fecha);
    });
    botonSiguientes.addEventListener('click', () => { estado.desde = sumarDias(estado.desde, 14); cargarDias(); });
    botonAnteriores.addEventListener('click', () => {
        const nuevo = sumarDias(estado.desde, -14);
        estado.desde = nuevo < hoyIso() ? hoyIso() : nuevo;
        cargarDias();
    });

    /* ---------------- paso 4: fecha -> horas ---------------- */
    const listaHoras = $('lista-horas');

    async function elegirFecha(fecha) {
        estado.fecha = fecha;
        estado.hora = '';
        $('campo-fecha').value = fecha;
        $('campo-hora').value = '';
        tira.querySelectorAll('.dia-boton').forEach((b) => b.classList.toggle('elegido', b.dataset.fecha === fecha));
        ticket('t-fecha', fechaTexto(fecha));
        ticket('t-hora', '');
        actualizar();
        cargando(listaHoras, 'Cargando horas libres…');
        try {
            const datos = await pedir('/api/disponibilidad', { medico: estado.medico, fecha, excluir });
            $('texto-fecha-elegida').textContent = `${datos.fecha_texto.charAt(0).toUpperCase()}${datos.fecha_texto.slice(1)} · las horas ocupadas aparecen tachadas.`;
            if (!datos.intervalos.length) {
                mensaje(listaHoras, datos.bloqueo || 'No hay horarios este día.');
                return;
            }
            listaHoras.innerHTML = datos.intervalos.map((i) =>
                `<button type="button" class="hora-boton" role="radio" aria-checked="false" data-hora="${i.inicio}" data-fin="${i.fin}" ${i.libre ? '' : 'disabled aria-disabled="true"'}
                    title="${i.libre ? 'Libre' : 'Ocupada'}: ${i.inicio} a ${i.fin}">${i.inicio}</button>`).join('');
            if (restaurar.hora_inicio) {
                const b = listaHoras.querySelector(`[data-hora="${restaurar.hora_inicio}"]:not(:disabled)`);
                restaurar.hora_inicio = null;
                if (b) elegirHora(b);
            }
        } catch (e) {
            mensaje(listaHoras, e.message);
        }
    }

    listaHoras.addEventListener('click', (ev) => {
        const b = ev.target.closest('.hora-boton:not(:disabled)');
        if (b) elegirHora(b);
    });

    function elegirHora(boton) {
        estado.hora = boton.dataset.hora;
        $('campo-hora').value = estado.hora;
        listaHoras.querySelectorAll('.hora-boton').forEach((b) => {
            const elegida = b === boton;
            b.classList.toggle('elegida', elegida);
            b.setAttribute('aria-checked', String(elegida));
        });
        ticket('t-hora', `${boton.dataset.hora} a ${boton.dataset.fin}`);
        actualizar();
    }

    function reiniciarFecha() {
        estado.fecha = '';
        estado.hora = '';
        $('campo-fecha').value = '';
        $('campo-hora').value = '';
        ticket('t-fecha', '');
        ticket('t-hora', '');
        if (listaHoras) listaHoras.innerHTML = '';
    }

    /* ---------------- modalidad ---------------- */
    form.querySelectorAll('input[name=modalidad]').forEach((r) => {
        r.addEventListener('change', () => ticket('t-modalidad', r.value === 'virtual' ? 'Virtual' : 'Presencial'));
    });

    /* ---------------- estado de los pasos y del botón ---------------- */
    function actualizar() {
        const boton = $('boton-confirmar');
        const ayuda = $('ayuda-confirmar');
        let completo;
        if (esReprogramar) {
            paso(3, estado.fecha ? 'completo' : 'activo');
            paso(4, estado.fecha ? (estado.hora ? 'completo' : 'activo') : 'bloqueado');
            completo = estado.fecha && estado.hora;
        } else {
            paso(1, estado.paciente ? 'completo' : 'activo');
            paso(2, estado.especialidad ? 'completo' : (estado.paciente ? 'activo' : 'activo'));
            paso(3, !estado.especialidad ? 'bloqueado' : (estado.fecha ? 'completo' : 'activo'));
            paso(4, !estado.fecha ? 'bloqueado' : (estado.hora ? 'completo' : 'activo'));
            completo = estado.paciente && estado.especialidad && estado.medico && estado.fecha && estado.hora;
        }
        boton.disabled = !completo;
        if (ayuda) {
            ayuda.textContent = completo ? 'Revise el resumen y confirme.'
                : (esReprogramar ? 'Elija la nueva fecha y hora.' : (!estado.paciente ? 'Elija primero al paciente.' : 'Complete los 4 pasos para confirmar.'));
        }
    }

    form.addEventListener('submit', (ev) => {
        if ($('boton-confirmar').disabled || !$('campo-fecha').value || !$('campo-hora').value) {
            ev.preventDefault();
        }
    });

    /* ---------------- inicio (y restauración tras un error) ---------------- */
    (async function iniciar() {
        if (esReprogramar) {
            actualizar();
            await cargarDias();
            return;
        }
        const nombre = $('nombre-paciente');
        if (estado.paciente && nombre) ticket('t-paciente', nombre.textContent);
        actualizar();
        if (restaurar.id_especialidad) {
            const radio = form.querySelector(`input[name=id_especialidad][value="${restaurar.id_especialidad}"]`);
            if (radio) {
                radio.checked = true;
                await elegirEspecialidad(radio.value, radio.dataset.nombre, restaurar.id_medico);
            }
        }
    })();
})();
