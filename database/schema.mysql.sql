-- Esquema WineMatch v2 para MySQL 8 (InnoDB). Misma estructura que schema.sqlite.sql.
-- Uso: mysql -u usuario -p basedatos < database/schema.mysql.sql

CREATE TABLE IF NOT EXISTS Usuarios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(120) NOT NULL,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol           ENUM('usuario', 'admin') NOT NULL DEFAULT 'usuario',
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Restaurante (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(120) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    descripcion TEXT NOT NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS TipoVino (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Vino (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(160) NOT NULL,
    bodega      VARCHAR(160) NOT NULL,
    botella    VARCHAR(160) NOT NULL DEFAULT '',
    tipo_id     INT NOT NULL,
    variedad    VARCHAR(120) NOT NULL DEFAULT '',
    descripcion TEXT NOT NULL,
    CONSTRAINT fk_vino_tipo FOREIGN KEY (tipo_id) REFERENCES TipoVino(id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Especie (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Comida (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    restaurante_id INT NOT NULL,
    nombre         VARCHAR(160) NOT NULL,
    descripcion    TEXT NOT NULL,
    especie_id     INT NOT NULL,
    CONSTRAINT fk_comida_restaurante FOREIGN KEY (restaurante_id) REFERENCES Restaurante(id),
    CONSTRAINT fk_comida_especie FOREIGN KEY (especie_id) REFERENCES Especie(id),
    INDEX idx_comida_restaurante (restaurante_id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Recomendacion (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    vino_id    INT NOT NULL,
    especie_id INT NOT NULL,
    comida_id  INT NOT NULL,
    mensaje    TEXT NOT NULL,
    UNIQUE KEY uq_recomendacion (vino_id, especie_id, comida_id),
    CONSTRAINT fk_rec_vino FOREIGN KEY (vino_id) REFERENCES Vino(id),
    CONSTRAINT fk_rec_especie FOREIGN KEY (especie_id) REFERENCES Especie(id),
    CONSTRAINT fk_rec_comida FOREIGN KEY (comida_id) REFERENCES Comida(id),
    INDEX idx_recomendacion_comida (comida_id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Consulta (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT NOT NULL,
    recomendacion_id INT NOT NULL,
    fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_consulta_usuario FOREIGN KEY (usuario_id) REFERENCES Usuarios(id),
    CONSTRAINT fk_consulta_recomendacion FOREIGN KEY (recomendacion_id) REFERENCES Recomendacion(id),
    INDEX idx_consulta_usuario (usuario_id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Comentario (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    vino_id    INT NOT NULL,
    comentario TEXT NOT NULL,
    fecha      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comentario_usuario FOREIGN KEY (usuario_id) REFERENCES Usuarios(id),
    CONSTRAINT fk_comentario_vino FOREIGN KEY (vino_id) REFERENCES Vino(id),
    INDEX idx_comentario_vino (vino_id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Valoracion (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    vino_id    INT NOT NULL,
    puntuacion TINYINT NOT NULL CHECK (puntuacion BETWEEN 1 AND 5),
    fecha      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_valoracion (usuario_id, vino_id),
    CONSTRAINT fk_valoracion_usuario FOREIGN KEY (usuario_id) REFERENCES Usuarios(id),
    CONSTRAINT fk_valoracion_vino FOREIGN KEY (vino_id) REFERENCES Vino(id)
) ENGINE = InnoDB;
