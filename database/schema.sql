-- ===========================================
-- CVB - Sistema de Seguimiento de Expedientes
-- Esquema de base de datos
-- ===========================================

CREATE DATABASE IF NOT EXISTS cvb_sistema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cvb_sistema;

-- Notarías / solicitantes (Lic. Gema, Lic. Karla, etc.)
CREATE TABLE notarias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuarios del sistema: admins (equipo CVB) y clientes (notarías)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL UNIQUE,
    username VARCHAR(100) NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin','cliente') NOT NULL,
    notaria_id INT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_notaria FOREIGN KEY (notaria_id) REFERENCES notarias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Expedientes de avalúo
CREATE TABLE expedientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(20) NOT NULL UNIQUE,
    notaria_id INT NOT NULL,
    nombre_cliente VARCHAR(200) NULL,
    tipo_inmueble VARCHAR(50) NULL,

    -- Ubicación
    direccion VARCHAR(255) NOT NULL,
    codigo_postal VARCHAR(10) NULL,
    municipio VARCHAR(100) NULL,
    estado VARCHAR(100) NULL,
    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,

    -- Valores
    valor_referencia DECIMAL(14,2) NULL,
    valor_resultante DECIMAL(14,2) NULL,

    -- Etapa 1: Cvb -> Notaria (solicitud/entrega de valores)
    fecha_solicitud_valores DATE NULL,
    fecha_entrega_valores DATE NULL,

    -- Bandera de "solicitud de ingreso" (dispara estatus En Desarrollo)
    solicitud_ingreso_check TINYINT(1) NOT NULL DEFAULT 0,
    fecha_solicitud_ingreso DATE NULL,

    -- Etapa 2: Desarrollo Cvb -> Catastro
    fecha_ingreso_catastro DATE NULL,

    -- Etapa 3: Catastro -> Notaria
    fecha_entrega_notaria DATE NULL,

    -- Override manual de estatus (Pagado, Detenido, etc.)
    estatus_manual VARCHAR(30) NOT NULL DEFAULT '',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_expediente_notaria FOREIGN KEY (notaria_id) REFERENCES notarias(id),
    INDEX idx_folio (folio),
    INDEX idx_municipio (municipio),
    INDEX idx_notaria (notaria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
