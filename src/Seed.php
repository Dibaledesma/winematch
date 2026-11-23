<?php

declare(strict_types=1);

namespace WineMatch;

use PDO;

/**
 * Datos de la v1 del proyecto (abril 2025), tal como se definieron en
 * winematch_db.sql: los mismos vinos, las mismas especias por restaurante, los
 * mismos platos y los mismos maridajes con su bodega, botella y mensaje.
 * Solo cambian los nombres de los dos restaurantes, inventados para el portfolio.
 */
final class Seed
{
    public const RESTAURANTES = ['Mas Guasón' => 'mas-guason', 'Quatre Lents' => 'quatre-lents'];

    /** @return string contrasena del admin generado (solo se muestra una vez) */
    public static function run(PDO $pdo, string $adminEmail = 'admin@winematch.local'): string
    {
        $restaurantes = self::insertar($pdo, 'Restaurante', ['nombre', 'slug', 'descripcion'], [
            ['Mas Guasón', 'mas-guason', 'Cocina de mercado y producto de temporada.'],
            ['Quatre Lents', 'quatre-lents', 'Brasería y platos de toda la vida.'],
        ]);
        $tipos = self::insertar($pdo, 'TipoVino', ['nombre'], [
            ['Tinto'], ['Blanco'], ['Rosado'],
        ]);
        $vinos = self::insertar($pdo, 'Vino', ['nombre', 'tipo_id', 'variedad', 'denominacion_origen'], [
            ['Camins del Priorat', $tipos['Tinto'], 'Cariñena, Garnacha', 'Priorat'],
            ['Clos de Agon', $tipos['Tinto'], 'Syrah, Cabernet Franc, Merlot', 'Empordà'],
            ['Pas Curtei', $tipos['Tinto'], 'Garnacha', 'Penedès'],
            ['Calcari', $tipos['Tinto'], 'Cabernet Franc, Syrah', 'Empordà'],
            ['Trenca Closques', $tipos['Blanco'], 'Macabeo', 'Penedès'],
            ['Jhana Rosat', $tipos['Rosado'], 'Viognier', 'Priorat'],
        ]);

        // Especias aromaticas especificas de cada restaurante (v1: tabla Especies).
        $especie = [];
        foreach ([
            'Mas Guasón' => ['Pimienta negra', 'Pimienta blanca', 'Canela', 'Comino'],
            'Quatre Lents' => ['Clavo', 'Curry', 'Menta'],
        ] as $restaurante => $nombres) {
            $stmt = $pdo->prepare('INSERT INTO Especie (restaurante_id, nombre) VALUES (?, ?)');
            foreach ($nombres as $n) {
                $stmt->execute([$restaurantes[$restaurante], $n]);
                $especie[$restaurante][$n] = (int) $pdo->lastInsertId();
            }
        }
        $mg = 'Mas Guasón';
        $ql = 'Quatre Lents';

        // Platos de la v1 con su tipo, cada uno ligado a su especia y a su restaurante.
        $comidas = self::insertar($pdo, 'Comida', ['restaurante_id', 'nombre', 'slug', 'tipo', 'descripcion', 'especie_id'], [
            [$restaurantes[$mg], 'Carpaccio de pie de cerdo', 'carpaccio-de-pie-de-cerdo', 'Primero', 'Láminas finas, aceite y limón.', $especie[$mg]['Pimienta negra']],
            [$restaurantes[$mg], 'Esqueixada', 'esqueixada', 'Primero', 'Bacalao desalado, tomate y cebolla.', $especie[$mg]['Canela']],
            [$restaurantes[$mg], 'Rodaballo al horno', 'rodaballo-al-horno', 'Segundo', 'Rodaballo de la lonja al horno.', $especie[$mg]['Comino']],
            [$restaurantes[$mg], 'Crema catalana', 'crema-catalana', 'Postre', 'Crema quemada a la vela.', $especie[$mg]['Pimienta blanca']],
            [$restaurantes[$ql], 'Tataki de atún', 'tataki-de-atun', 'Primero', 'Atún marcado a la plancha.', $especie[$ql]['Menta']],
            [$restaurantes[$ql], 'Entrecot a la plancha', 'entrecot-a-la-plancha', 'Segundo', 'Entrecot de vaca vieja.', $especie[$ql]['Clavo']],
        ]);

        // Maridajes de la v1, verbatim: vino, bodega, botella y mensaje.
        $reglas = [
            ['Pas Curtei', $mg, 'Pimienta negra', 'Carpaccio de pie de cerdo', 'Hugas de Batlle', 'Pas Curtei', 'Ideal para platos frescos como ensaladas o mariscos.'],
            ['Clos de Agon', $mg, 'Comino', 'Rodaballo al horno', 'Clos d\'Agon', 'Clos de Agon', 'Perfecto para acompañar platos de pescado a la parrilla.'],
            ['Camins del Priorat', $mg, 'Pimienta blanca', 'Crema catalana', 'Parés Baltà', 'Camins del Priorat', 'Excelente para maridar con carnes de cerdo o guisos contundentes.'],
            ['Calcari', $ql, 'Clavo', 'Entrecot a la plancha', 'Celler Pardas', 'Calcari', 'Para carnes asadas o platos de pasta con salsas fuertes.'],
            ['Trenca Closques', $ql, 'Menta', 'Tataki de atún', 'Cellers Can Blau', 'Trenca Closques', 'Excelente vino para acompañar postres tradicionales como la crema catalana.'],
            ['Jhana Rosat', $mg, 'Canela', 'Esqueixada', 'Clos Mogador', 'Jhana Rosat', 'Ideal para tapas y aperitivos ligeros, como el tataki de atún.'],
        ];
        $ins = $pdo->prepare('INSERT INTO Recomendacion (vino_id, especie_id, comida_id, nombre_bodega, nombre_botella, mensaje) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($reglas as [$vino, $restaurante, $especieNombre, $comida, $bodega, $botella, $mensaje]) {
            $ins->execute([$vinos[$vino], $especie[$restaurante][$especieNombre], $comidas[$comida], $bodega, $botella, $mensaje]);
        }

        // Admin con contrasena aleatoria: se muestra una vez y nunca se guarda en claro.
        $adminPass = self::password();
        $pdo->prepare('INSERT INTO Usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)')
            ->execute(['Administración', $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), 'admin']);

        // Dos usuarios de ejemplo para que la ficha del vino tenga vida.
        $usuario = $pdo->prepare('INSERT INTO Usuarios (nombre, email, password_hash) VALUES (?, ?, ?)');
        $ids = [];
        foreach ([['Marta', 'marta@ejemplo.test'], ['Joan', 'joan@ejemplo.test']] as [$nombre, $email]) {
            $usuario->execute([$nombre, $email, password_hash(self::password(), PASSWORD_DEFAULT)]);
            $ids[$nombre] = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('INSERT INTO Comentario (usuario_id, vino_id, comentario) VALUES (?, ?, ?), (?, ?, ?)')->execute([
            $ids['Marta'], $vinos['Pas Curtei'], 'Con el carpaccio fue todo frescura; repetiría.',
            $ids['Joan'], $vinos['Calcari'], 'Con el entrecot, potente pero sin comerse la carne.',
        ]);
        $pdo->prepare('INSERT INTO Valoracion (usuario_id, vino_id, puntuacion) VALUES (?, ?, 5), (?, ?, 4)')->execute([
            $ids['Marta'], $vinos['Pas Curtei'], $ids['Joan'], $vinos['Calcari'],
        ]);

        return $adminPass;
    }

    /**
     * Inserta filas y devuelve valor de la columna clave => id.
     * @param list<string> $columnas @param list<list<mixed>> $filas @return array<string, int>
     */
    private static function insertar(PDO $pdo, string $tabla, array $columnas, array $filas, string $clave = 'nombre'): array
    {
        // Nombres de tabla y columnas salen de codigo, nunca de peticiones.
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $tabla, implode(', ', $columnas), implode(', ', array_fill(0, count($columnas), '?')));
        $stmt = $pdo->prepare($sql);
        $pos = array_search($clave, $columnas, true);
        if ($pos === false) {
            throw new \InvalidArgumentException("La tabla {$tabla} no tiene la columna clave {$clave}");
        }
        $ids = [];
        foreach ($filas as $fila) {
            $stmt->execute($fila);
            $ids[(string) $fila[$pos]] = (int) $pdo->lastInsertId();
        }
        return $ids;
    }

    private static function password(): string
    {
        return 'wm-' . rtrim(strtr(base64_encode(random_bytes(9)), '+/', 'AZ'), '=');
    }
}
