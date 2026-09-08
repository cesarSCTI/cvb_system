-- ===========================================
-- Usuarios de prueba
-- IMPORTANTE: cambia estas contraseñas antes de producción
-- ===========================================

-- Administrador (equipo CVB)
-- Usuario: admin@cvb.mx / Contraseña: admin2026
INSERT INTO usuarios (nombre, email, username, password, rol, notaria_id) VALUES
('Admin Chris', 'admin@cvb.mx', NULL, '$2b$12$8DgpGFOGmB5XZbGdKtySjOJEqIqZC7BkCFC6duENTKtVFcX1P/uLC', 'admin', NULL);

-- Clientes (uno por notaría). Usuario: notaria1..notaria4 / Contraseña: cvb2026
INSERT INTO usuarios (nombre, email, username, password, rol, notaria_id) VALUES
('Lic. Gema',    NULL, 'notaria1', '$2b$12$llEpmjed/.roB1zbWtldbeKxxbvzCsRKkZUiHPKhmTW4JADbFbADq', 'cliente', 1),
('Lic. Karla',   NULL, 'notaria2', '$2b$12$llEpmjed/.roB1zbWtldbeKxxbvzCsRKkZUiHPKhmTW4JADbFbADq', 'cliente', 2),
('Lic. Cristy',  NULL, 'notaria3', '$2b$12$llEpmjed/.roB1zbWtldbeKxxbvzCsRKkZUiHPKhmTW4JADbFbADq', 'cliente', 3),
('Lic. Denisse', NULL, 'notaria4', '$2b$12$llEpmjed/.roB1zbWtldbeKxxbvzCsRKkZUiHPKhmTW4JADbFbADq', 'cliente', 4);
