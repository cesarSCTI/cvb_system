-- ===========================================
-- Migración: datos de contacto en notarías
-- Añade email y telefono a la tabla notarias.
-- Ejecutar UNA vez sobre una base ya existente:
--   docker exec -i cvb_db mysql -uroot -proot cvb_sistema < database/migration_2026_09_notarias_contacto.sql
-- (En instalaciones nuevas ya viene en schema.sql, no hace falta correr esto.)
-- ===========================================

USE cvb_sistema;

ALTER TABLE notarias
    ADD COLUMN email VARCHAR(150) NULL AFTER nombre,
    ADD COLUMN telefono VARCHAR(30) NULL AFTER email;
