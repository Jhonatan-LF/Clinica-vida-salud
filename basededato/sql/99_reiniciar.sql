-- =====================================================================
--  Clínica Vida Salud · 99_reiniciar.sql
--  Borra TODOS los objetos del sistema (tablas, vistas, funciones,
--  tipos) para volver a instalar desde cero dentro de la misma base.
--
--  Uso:  psql -U postgres -d clinica_vida_salud -f 99_reiniciar.sql
--  y luego ejecute 01 → 05 (o simplemente instalar_bd.bat, que además
--  vuelve a crear la base completa).
--  ¡Se pierden todos los datos!
-- =====================================================================

SET client_min_messages = warning;

DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO public;
COMMENT ON SCHEMA public IS 'Clínica Vida Salud';
