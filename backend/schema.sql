CREATE TABLE IF NOT EXISTS organic_campaign (
    id INT AUTO_INCREMENT PRIMARY KEY,
    semana INT NOT NULL,
    usuario VARCHAR(255) NOT NULL,
    plataforma VARCHAR(50) NOT NULL,
    link_publicacion VARCHAR(512) NOT NULL,
    categoria_perfil VARCHAR(100) NOT NULL,
    mes VARCHAR(20) NOT NULL,
    views_semana INT NULL,
    likes INT DEFAULT 0,
    compartidos INT DEFAULT 0,
    comentarios INT DEFAULT 0,
    guardados INT DEFAULT 0,
    sentiment VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_organic_semana (semana),
    INDEX idx_organic_mes (mes),
    INDEX idx_organic_usuario (usuario),
    INDEX idx_organic_plataforma (plataforma),
    INDEX idx_organic_sentiment (sentiment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paid_campaign (
    id INT AUTO_INCREMENT PRIMARY KEY,
    semana INT NOT NULL,
    mes VARCHAR(20) NOT NULL,
    usuario VARCHAR(255) NOT NULL,
    plataforma VARCHAR(50) NOT NULL,
    link_publicacion VARCHAR(512) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    views_semana INT NULL,
    likes INT DEFAULT 0,
    compartidos INT DEFAULT 0,
    comentarios INT DEFAULT 0,
    guardados INT DEFAULT 0,
    sentiment VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_paid_semana (semana),
    INDEX idx_paid_mes (mes),
    INDEX idx_paid_usuario (usuario),
    INDEX idx_paid_plataforma (plataforma),
    INDEX idx_paid_sentiment (sentiment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    month VARCHAR(20) NOT NULL,
    year INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
