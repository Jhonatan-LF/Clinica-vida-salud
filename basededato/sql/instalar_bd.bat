@echo off
REM =====================================================================
REM  Clinica Vida Salud - Instalador de la base de datos (Windows)
REM
REM  Uso (desde esta carpeta, basededato\sql):
REM     instalar_bd.bat
REM  Si PostgreSQL no usa el puerto 5432, antes escriba por ejemplo:
REM     set PGPORT=5433
REM  Variables opcionales: PGHOST (127.0.0.1), PGPORT (5432), PGUSER (postgres)
REM
REM  Borra y vuelve a crear la base "clinica_vida_salud" y ejecuta
REM  01 -> 05. Sirve tambien para dejar los datos como nuevos.
REM =====================================================================
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul
cd /d "%~dp0"

if "%PGHOST%"=="" set PGHOST=127.0.0.1
if "%PGPORT%"=="" set PGPORT=5432
if "%PGUSER%"=="" set PGUSER=postgres
set BD=clinica_vida_salud
set PGCLIENTENCODING=UTF8
set PGOPTIONS=-c client_min_messages=warning

echo.
echo  ============================================================
echo   Clinica Vida Salud - instalacion de la base de datos
echo   Servidor: %PGHOST%:%PGPORT%   Usuario: %PGUSER%   Base: %BD%
echo  ============================================================
echo.

REM ---- 1. Buscar psql.exe ------------------------------------------------
set PSQL=
where psql >nul 2>nul && set PSQL=psql
if "%PSQL%"=="" (
    for /d %%D in ("C:\Program Files\PostgreSQL\*") do (
        if exist "%%D\bin\psql.exe" set "PSQL=%%D\bin\psql.exe"
    )
)
if "%PSQL%"=="" (
    for /d %%D in ("C:\Program Files (x86)\PostgreSQL\*") do (
        if exist "%%D\bin\psql.exe" set "PSQL=%%D\bin\psql.exe"
    )
)
if "%PSQL%"=="" (
    echo  [ERROR] No se encontro psql.exe. Instale PostgreSQL o agregue su carpeta bin al PATH.
    goto :error
)
echo  Usando: %PSQL%

REM ---- 2. Contrasena (se pide una sola vez) -------------------------------
if "%PGPASSWORD%"=="" (
    set /p PGPASSWORD= Escriba la contrasena del usuario %PGUSER% de PostgreSQL:
)
echo.

REM ---- 3. Recrear la base de datos ----------------------------------------
echo  [1/6] Creando la base de datos %BD% ...
"%PSQL%" -v ON_ERROR_STOP=1 -q -d postgres -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '%BD%' AND pid <> pg_backend_pid();" >nul
if errorlevel 1 goto :error
"%PSQL%" -v ON_ERROR_STOP=1 -q -d postgres -c "DROP DATABASE IF EXISTS %BD%;"
if errorlevel 1 goto :error
"%PSQL%" -v ON_ERROR_STOP=1 -q -d postgres -c "CREATE DATABASE %BD% WITH ENCODING 'UTF8' TEMPLATE template0;"
if errorlevel 1 goto :error

REM ---- 4. Ejecutar los scripts en orden -----------------------------------
set N=2
for %%F in (01_esquema.sql 02_funciones_triggers.sql 03_vistas.sql 04_datos_iniciales.sql 05_datos_prueba.sql) do (
    echo  [!N!/6] %%F ...
    "%PSQL%" -v ON_ERROR_STOP=1 -q -X -d %BD% -f "%%F"
    if errorlevel 1 goto :error
    set /a N+=1
)

echo.
echo  ============================================================
echo   Listo. Base "%BD%" instalada en %PGHOST%:%PGPORT%.
echo   Cuentas: admin, recepcion, dr.perez, lucia.vargas
echo   Contrasena de todas: Clinica2026*
echo  ============================================================
echo.
endlocal
pause
exit /b 0

:error
echo.
echo  [ERROR] La instalacion no termino. Revise el mensaje de arriba:
echo   - "password authentication failed": contrasena incorrecta.
echo   - "could not connect" / "Connection refused": PostgreSQL apagado o puerto distinto (set PGPORT=5433).
echo.
endlocal
pause
exit /b 1
