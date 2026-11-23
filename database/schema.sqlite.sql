-- Esquema WineMatch v2 para SQLite. Misma estructura que schema.mysql.sql.
-- Modelo de la memoria del proyecto (ER) + Restaurante como entidad real.

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
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre      TEXT NOT NULL,
    bodega      TEXT NOT NULL,
    botella    TEXT NOT NULL DEFAULT '',
    tipo_id     INTEGER NOT NULL REFERENCES TipoVino(id),
    variedad    TEXT NOT NULL DEFAULT '',
    descripcion TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS Especie (
    id     INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE
);

-- Platos de la carta de cada restaurante, ligados a su especie aromatica.
CREATE TABLE IF NOT EXISTS Comida (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    restaurante_id INTEGER NOT NULL REFERENCES Restaurante(id),
    nombre         TEXT NOT NULL,
    slug           TEXT NOT NULL,
    descripcion    TEXT NOT NULL DEFAULT '',
    especie_id     INTEGER NOT NULL REFERENCES Especie(id),
    UNIQUE (restaurante_id, slug)
);
CREATE INDEX IF NOT EXISTS idx_comida_restaurante ON Comida(restaurante_id);

-- Regla de maridaje definida por el admin: vino + especie + plato -> mensaje.
CREATE TABLE IF NOT EXISTS Recomendacion (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    vino_id   INTEGER NOT NULL REFERENCES Vino(id),
    especie_id INTEGER NOT NULL REFERENCES Especie(id),
    comida_id INTEGER NOT NULL REFERENCES Comida(id),
    mensaje   TEXT NOT NULL,
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
