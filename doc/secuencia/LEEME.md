# Diagramas de secuencia (6.2)

Un diagrama por caso de uso. Recorrido típico de una petición:
`Actor → vista (frontend/vistas) → public/index.php → Enrutador → CUxx (control) → Modelo (entidad) → PostgreSQL`
y de vuelta `CUxx → Vista`. Para CU09 incluya `Disponibilidad::verificar()` y el `SELECT … FOR UPDATE` dentro de la transacción.
