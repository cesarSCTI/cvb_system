-- ===========================================
-- SEED DATA generado a partir del Excel de seguimiento real
-- ===========================================

INSERT INTO notarias (id, nombre) VALUES
(1, 'Lic. Gema'),
(2, 'Lic. Karla'),
(3, 'Lic. Cristy'),
(4, 'Lic. Denisse');

INSERT INTO expedientes
(folio, notaria_id, nombre_cliente, tipo_inmueble, direccion, codigo_postal, municipio, estado,
 valor_referencia, valor_resultante, fecha_solicitud_valores, fecha_entrega_valores,
 solicitud_ingreso_check, fecha_solicitud_ingreso, fecha_ingreso_catastro, fecha_entrega_notaria, estatus_manual)
VALUES
('EXP-0001', 1, NULL, 'Comercial', 'Paseo de los Almendros #1186', NULL, 'Zapopan', 'Jalisco', 1696400.0, 1696400.0, '2026-05-21', '2026-05-21', 1, '2026-07-24', '2026-07-29', '2026-08-06', ''),
('EXP-0002', 2, NULL, NULL, 'Vista Poniente #1329 Int. 22, Tlajomulco', NULL, 'Tlajomulco de Zúñiga', 'Jalisco', 1146560.0, 1157090.0, '2026-05-25', '2026-05-25', 1, '2026-06-10', '2026-06-20', '2026-06-24', 'Pagado'),
('EXP-0003', 2, NULL, NULL, 'Fuente Brillante', NULL, 'Zapopan', 'Jalisco', 398520.0, 488210.0, '2026-05-25', '2026-05-25', 1, '2026-06-10', '2026-06-20', '2026-06-24', 'Pagado'),
('EXP-0004', 1, NULL, NULL, 'Industria #127, Zapopan', NULL, 'Zapopan', 'Jalisco', 1629129.5, 1629129.5, '2026-05-25', '2026-05-25', 0, NULL, NULL, NULL, ''),
('EXP-0005', 3, NULL, NULL, 'C. Jardines de Aranjuez #3593', NULL, 'Zapopan', 'Jalisco', 1208983.84, NULL, '2026-06-03', '2026-06-03', 0, NULL, NULL, NULL, ''),
('EXP-0006', 4, NULL, NULL, 'Av. Real Acueducto #360,Nivel 18', NULL, 'Zapopan', 'Jalisco', 6299985.59, NULL, '2026-05-26', '2026-05-26', 0, NULL, NULL, NULL, ''),
('EXP-0007', 2, NULL, NULL, 'Auroral Boreal #678', NULL, 'Tlajomulco de Zúñiga', 'Jalisco', 763592, 688305.0, '2026-06-09', '2026-06-09', 1, '2026-06-22', '2026-06-24', '2026-07-09', ''),
('EXP-0008', 3, NULL, NULL, 'Av. Paseo de la cazcana #354 Int L07', NULL, 'Tonala', 'Jalisco', 533792.0, NULL, '2026-06-29', '2026-06-29', 0, NULL, NULL, NULL, ''),
('EXP-0009', 3, NULL, NULL, 'Calle San Miguel el Alto, Nuevo Israel, Tonalá, Región Centro, Jalisco, C.P. 45412, México.', NULL, NULL, 'Jalisco', NULL, NULL, '2026-07-02', '2026-07-02', 0, NULL, NULL, NULL, ''),
('EXP-0010', 2, NULL, NULL, 'Calle Siderugia #2777, Frac. El Alamo Segunda Sección', NULL, 'Guadalajara', 'Jalisco', 1601054.86, NULL, '2026-07-03', '2026-07-03', 0, NULL, NULL, NULL, ''),
('EXP-0011', 3, NULL, NULL, 'Av. Paseo de Castilla S/N UM 18, San Isidro Residencial', NULL, 'Zapopan', 'Jalisco', 1345950.0, NULL, '2026-07-10', '2026-07-10', 0, NULL, NULL, NULL, ''),
('EXP-0012', 2, NULL, NULL, 'Av. De la Mancha 244 3 COND 4-A', NULL, 'Zapopan', 'Jalisco', 474535.0, NULL, '2026-07-17', '2026-07-17', 0, NULL, NULL, NULL, ''),
('EXP-0013', 2, NULL, NULL, 'Calle Aldama N° 30 lt. 5. Mza 32, Col. San Jose del Valle, Tlajomulco de Zuñiga', NULL, 'Tlajomulco de Zúñiga', 'Jalisco', 803300.0, NULL, '2026-07-17', '2026-07-17', 1, '2026-08-05', NULL, NULL, ''),
('EXP-0014', 2, NULL, NULL, 'Calle San Francisco 4012 - Int. 58, Colonia Parques del Palmar Fraccionamiento', NULL, 'San Pedro Tlaquepaque', 'Jalisco', 1147721.6, NULL, '2026-07-20', '2026-07-21', 0, NULL, NULL, NULL, ''),
('EXP-0015', 2, NULL, NULL, 'Colon No. 50 LT 55 COTO 4', NULL, 'Tlajomulco de Zúñiga', 'Jalisco', 446200.0, NULL, '2026-07-27', '2026-07-27', 0, NULL, NULL, NULL, ''),
('EXP-0016', 2, NULL, NULL, 'Valle de las bugambilias no. 453 lt. up-25 mza. - condominio d bugambilias ii', NULL, 'San Pedro Tlaquepaque', 'Jalisco', 686003.2, NULL, '2026-07-27', '2026-07-28', 0, NULL, NULL, NULL, ''),
('EXP-0017', 2, NULL, NULL, 'Calle Clavel #133 lote 46, Manzana 2', NULL, 'Tlajomulco de Zúñiga', 'Jalisco', 604116.5, 265872.0, '2026-07-30', '2026-07-30', 1, '2026-08-10', NULL, NULL, ''),
('EXP-0018', 3, NULL, NULL, 'Calle Abasolo N°49 PTE. Col. Centro', NULL, 'Tlajomulco de Zúñiga', 'Jalisco', 954664.0, NULL, '2026-07-30', '2026-07-30', 0, NULL, NULL, NULL, '');
