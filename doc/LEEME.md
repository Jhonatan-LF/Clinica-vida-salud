# Documentación del grupo — Examen 2 · Sistemas de Información I (UAGRM)

**Proyecto:** Sistema Web de Gestión de Citas Médicas — Clínica Vida Salud (Santa Cruz, Bolivia)
**Grupo:** 19 · **Docente:** MSc. Ing. Angélica Gazón Cuéllar

## Coloque aquí su documento

`Grupo19_SI1_Clinica_Vida_Salud.docx` ← el documento final del grupo va en esta carpeta, con este nombre.

## Estructura del documento (formato PUDS-UML del examen)

- **Carátula:** título, N.º de grupo, integrantes. **Índice.**
- **1. Perfil:** introducción, objetivos (general y específicos), descripción del problema, alcance.
- **2. Marco teórico:** PUDS, UML, arquitectura en capas, PHP 8, PostgreSQL, PDO, sesiones, seguridad web.
- **3. Modelo de negocio:** actores y trabajadores del negocio, casos de uso del negocio, diagrama de actividades.
- **4. Captura de requisitos:** RF01–RF13, RN01–RN14, requisitos no funcionales; actores; diagrama general de casos de
  uso (`casos_de_uso/`) y especificación de CU01–CU15 (las fichas al inicio de cada archivo en
  `backend/app/paquetes/*/casos_uso/` sirven de base).
- **5. Análisis:** clases de análisis (interfaz, control, entidad) y diagramas de colaboración por caso de uso;
  paquetes de análisis (seguridad, pacientes, médicos, citas, reportes, inicio).
- **6. Diseño:**
  - 6.1 Arquitectura física (cliente – Apache/PHP – PostgreSQL) y lógica (interfaz / lógica de negocio / acceso a datos).
  - 6.2 Diagramas de secuencia por caso de uso (`secuencia/`).
  - 6.3 Diagramas de clases de diseño por caso de uso (`staruml/`).
  - 6.4 Diseño de datos: modelo lógico (tablas y relaciones) y físico (`basededato/sql/01_esquema.sql`,
    restricciones, triggers y vistas — ver `basededato/sql/LEEME.md`).
- **7. Implementación:** herramientas (XAMPP, PHP 8, PostgreSQL, VS Code, Git/GitHub, StarUML), arquitectura del
  sistema (`backend/README.md`) y de los subsistemas (paquetes), capturas de pantalla (`capturas/`).
- **8. Pruebas:** las 10 pruebas mínimas (`backend/tests/resultados_pruebas.md`) y las pruebas de la base
  (`basededato/sql/pruebas/resultados_pruebas_bd.txt`).
- **Conclusión, recomendación, bibliografía y anexos** (manual de instalación = sección "Cómo levantar el sistema" del
  `README.md` raíz; URL del repositorio y código QR).

## Qué va en cada carpeta

| Carpeta | Contenido |
|---|---|
| `staruml/` | modelo `Clinica_Vida_Salud.mdj` y los diagramas de clases de diseño por caso de uso exportados en `.png` (`CU01_clases.png` … `CU15_clases.png`) |
| `casos_de_uso/` | diagrama general de casos de uso y las especificaciones (texto o `.docx`) |
| `secuencia/` | diagramas de secuencia de la sección 6.2 (`CU09_secuencia.png`, …) |
| `capturas/` | pantallas del sistema para el documento y la defensa (ya incluye 28 capturas en computadora y celular) |
| `qr/` | código QR con la URL del repositorio de GitHub (`qr_repositorio.png`) |

## Fechas

| Entrega | Fecha |
|---|---|
| Presentación 1 (avance ≥ 50 %) | domingo 25/10/2026, 23:59 |
| Presentación 2 (100 %) | domingo 08/11/2026, 23:59 |
| Defensa | martes 10/11/2026 |
