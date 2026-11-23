-- Esquema WineMatch v2 para SQLite. Misma estructura que schema.mysql.sql.
-- Modelo de datos de la v1 del proyecto (memoria DAW 2025): especias por restaurante,
-- platos con tipo (primero/segundo/postre), maridaje con bodega y botella.

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS Usuarios (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre        TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    rol           TEXT NOT NULL DEFAULT 'usuario' CHECK (rol IN ('usuario', 'admin')),
    creado_en     TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS Restaurante (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre      TEXT NOT NULL,
    slug        TEXT NOT NULL UNIQUE,
    descripcion TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS TipoVino (
    id     INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS Vino (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre              TEXT NOT NULL,
    tipo_id             INTEGER NOT NULL REFERENCES TipoVino(id),
    variedad            TEXT NOT NULL DEFAULT '',
    denominacion_origen  TEXT NOT NULL DEFAULT ''
);

-- Especias aromaticas, especificas de cada restaurante (como en la v1).
CREATE TABLE IF NOT EXISTS Especie (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurante_id INTEGER NOT NULL REFERENCES Restaurante(id),
    nombre         TEXT NOT NULL,
    UNIQUE (restaurante_id, nombre)
);

-- Platos de la carta: primero, segundo o postre, ligados a una especia de su restaurante.
CREATE TABLE IF NOT EXISTS Comida (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurante_id INTEGER NOT NULL REFERENCES Restaurante(id),
    nombre         TEXT NOT NULL,
    slug           TEXT NOT NULL,
    tipo           TEXT NOT NULL CHECK (tipo IN ('Primero', 'Segundo', 'Postre')),
    descripcion    TEXT NOT NULL DEFAULT '',
    especie_id     INTEGER NOT NULL REFERENCES Especie(id),
    UNIQUE (restaurante_id, slug)
);
CREATE INDEX IF NOT EXISTS idx_comida_restaurante ON Comida(restaurante_id);

-- Regla de maridaje de la casa: vino + especia + plato, con bodega, botella y mensaje (v1).
CREATE TABLE IF NOT EXISTS Recomendacion (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    vino_id       INTEGER NOT NULL REFERENCES Vino(id),
    especie_id    INTEGER NOT NULL REFERENCES Especie(id),
    comida_id     INTEGER NOT NULL REFERENCES Comida(id),
    nombre_bodega TEXT NOT NULL,
    nombre_botella TEXT NOT NULL,
    mensaje       TEXT NOT NULL,
    UNIQUE (vino_id, especie_id, comida_id)
);
CREATE INDEX IF NOT EXISTS idx_recomendacion_comida ON Recomendacion(comida_id);

-- Un usuario realiza una o varias consultas (1:N en el ER de la memoria).
CREATE TABLE IF NOT EXISTS Consulta (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id      INTEGER NOT NULL REFERENCES Usuarios(id),
    recomendacion_id INTEGER NOT NULL REFERENCES Recomendacion(id),
    fecha           TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_consulta_usuario ON Consulta(usuario_id);

CREATE TABLE IF NOT EXISTS Comentario (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL REFERENCES Usuarios(id),
    vino_id    INTEGER NOT NULL REFERENCES Vino(id),
    comentario TEXT NOT NULL,
    fecha      TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_comentario_vino ON Comentario(vino_id);

CREATE TABLE IF NOT EXISTS Valoracion (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL REFERENCES Usuarios(id),
    vino_id    INTEGER NOT NULL REFERENCES Vino(id),
    puntuacion INTEGER NOT NULL CHECK (puntuacion BETWEEN 1 AND 5),
    fecha      TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (usuario_id, vino_id)
);
