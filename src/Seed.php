<?php

declare(strict_types=1);

namespace WineMatch;

use PDO;

/**
 * Datos de ejemplo: dos restaurantes, sus cartas y las reglas de maridaje.
 * Los mensajes de maridaje son criterio de sommelier, no relleno.
 */
final class Seed
{
    /** @return string contrasena del admin generado (solo se muestra una vez) */
    public static function run(PDO $pdo, string $adminEmail = 'admin@winematch.local'): string
    {
        $tipos = self::insertar($pdo, 'TipoVino', ['nombre'], [
            ['Tinto'], ['Blanco'], ['Rosado'], ['Espumoso'],
        ]);
        $especies = self::insertar($pdo, 'Especie', ['nombre'], [
            ['Cítricos'], ['Frutos rojos'], ['Flores blancas'], ['Especias'],
            ['Fruta madura'], ['Hierbas frescas'],
        ]);
        $restaurantes = self::insertar($pdo, 'Restaurante', ['nombre', 'slug', 'descripcion'], [
            ['Mas Bosson', 'mas-bosson', 'Cocina de mercado, mar y montaña. Producto de temporada a la brasa y arroces de casa.'],
            ['Quatre Vents', 'quatre-vents', 'Brasería. Carnes maduradas, verduras de la huerta y marisco de la lonja.'],
        ]);

        $vinos = self::insertar($pdo, 'Vino', ['nombre', 'bodega', 'botella', 'tipo_id', 'variedad', 'descripcion'], [
            ['Cune Crianza', 'CVNE', '750 ml', $tipos['Tinto'], 'Tempranillo', 'Rioja: fruta roja, vainilla y taninos suaves.'],
            ['Finca Malaveïna', 'Castillo de Perelada', '750 ml', $tipos['Tinto'], 'Garnacha, Cabernet Sauvignon', 'Empordà: fruta madura, especias y balsámicos.'],
            ['Marqués de Cáceres Crianza', 'Marqués de Cáceres', '750 ml', $tipos['Tinto'], 'Tempranillo', 'Rioja clásico: fruta, madera fina y estructura.'],
            ['Martín Códax', 'Bodegas Martín Códax', '750 ml', $tipos['Blanco'], 'Albariño', 'Rías Baixas: cítricos, flor blanca y salinidad.'],
            ['Viña Sol', 'Torres', '750 ml', $tipos['Blanco'], 'Parellada, Garnacha blanca', 'Penedès: fruta blanca y hierbas frescas, paso ligero.'],
            ['Muga Rosado', 'Muga', '750 ml', $tipos['Rosado'], 'Tempranillo, Garnacha, Viura', 'Rosado de sangrado: frutos rojos y cítricos, frescura.'],
            ['Recaredo Brut Nature', 'Recaredo', '750 ml', $tipos['Espumoso'], 'Macabeo, Xarel·lo, Parellada', 'Cava de larga crianza: bollería, fruta madura y frescura.'],
            ['Anna de Codorníu Brut', 'Codorníu', '750 ml', $tipos['Espumoso'], 'Chardonnay, Parellada, Macabeo', 'Cava fresco: manzana y cítricos, burbuja fina.'],
        ]);

        $comidas = self::insertar($pdo, 'Comida', ['restaurante_id', 'nombre', 'descripcion', 'especie_id'], [
            [$restaurantes['Mas Bosson'], 'Arroz de bogavante', 'Arroz meloso con bogavante, sofrito de marisco y azafrán.', $especies['Fruta madura']],
            [$restaurantes['Mas Bosson'], 'Sepia a la brasa con alioli', 'Sepia de la lonja, brasa suave y alioli de ajo asado.', $especies['Cítricos']],
            [$restaurantes['Mas Bosson'], 'Canelones de ternera', 'Canelones de casa, bechamel ligera y canela en el relleno.', $especies['Especias']],
            [$restaurantes['Mas Bosson'], 'Pollo de payés con setas', 'Pollo de payés guisado con setas de temporada y romero.', $especies['Hierbas frescas']],
            [$restaurantes['Mas Bosson'], 'Bacalao con pisto', 'Bacalao confitado sobre pisto maduro de la huerta.', $especies['Fruta madura']],
            [$restaurantes['Quatre Vents'], 'Chuletón de vaca vieja', 'Chuletón madurado 40 días, brasa de encina.', $especies['Frutos rojos']],
            [$restaurantes['Quatre Vents'], 'Costillas glaseadas con miel', 'Costillas largas, glaseado de miel y especias dulces.', $especies['Especias']],
            [$restaurantes['Quatre Vents'], 'Verduras de la huerta a la brasa', 'Verduras de temporada a la brasa, aceite de oliva y sal en escamas.', $especies['Hierbas frescas']],
            [$restaurantes['Quatre Vents'], 'Hamburguesa de buey', 'Buey picado a cuchillo, queso curado y cebolla confitada.', $especies['Frutos rojos']],
            [$restaurantes['Quatre Vents'], 'Lubina a la sal', 'Lubina de la lonja a la sal, limón y aceite.', $especies['Cítricos']],
        ]);

        $reglas = [
            ['Martín Códax', 'Fruta madura', 'Arroz de bogavante', 'El arroz de bogavante pide acidez y cuerpo: el Albariño limpia el sofrito y acompaña el marisco sin taparlo.'],
            ['Muga Rosado', 'Fruta madura', 'Arroz de bogavante', 'Un rosado de sangrado tiene la fruta y la frescura justas para seguir un arroz de marisco.'],
            ['Recaredo Brut Nature', 'Cítricos', 'Sepia a la brasa con alioli', 'La brasa y el limón piden burbuja: el cava de larga crianza limpia el paladar en cada sorbo.'],
            ['Cune Crianza', 'Especias', 'Canelones de ternera', 'La madera fina del crianza abraza el bechamel y la canela del relleno.'],
            ['Viña Sol', 'Hierbas frescas', 'Pollo de payés con setas', 'El blanco del Penedès acompaña el romero y las setas sin dominar el guiso.'],
            ['Marqués de Cáceres Crianza', 'Hierbas frescas', 'Pollo de payés con setas', 'Si prefieres tinto, un Rioja de taninos redondos sostiene el pollo de payés.'],
            ['Marqués de Cáceres Crianza', 'Fruta madura', 'Bacalao con pisto', 'El pisto maduro y el bacalao encuentran en el Rioja fruta y balsámicos a su altura.'],
            ['Finca Malaveïna', 'Frutos rojos', 'Chuletón de vaca vieja', 'La vaca vieja pide tinto de altura: la Garnacha de l\'Empordà tiene fruta y estructura para la brasa.'],
            ['Cune Crianza', 'Frutos rojos', 'Chuletón de vaca vieja', 'Un Rioja clásico: fruta roja, vainilla y taninos para la grasa de la vaca vieja.'],
            ['Finca Malaveïna', 'Especias', 'Costillas glaseadas con miel', 'El glaseado de miel pide fruta madura y especias; el tinto del Empordà responde con balsámicos.'],
            ['Anna de Codorníu Brut', 'Hierbas frescas', 'Verduras de la huerta a la brasa', 'Las verduras quedan luminosas con un cava fresco de manzana y cítricos.'],
            ['Cune Crianza', 'Frutos rojos', 'Hamburguesa de buey', 'La grasa del buey y la brasa se cortan con la fruta roja y la vainilla del crianza.'],
            ['Martín Códax', 'Cítricos', 'Lubina a la sal', 'La lubina, limpia y salina, se duplica con un Albariño de cítricos y flor blanca.'],
            ['Recaredo Brut Nature', 'Cítricos', 'Lubina a la sal', 'En versión festiva: los tostados del cava envuelven la sal y la piel de la lubina.'],
        ];
        $ins = $pdo->prepare('INSERT INTO Recomendacion (vino_id, especie_id, comida_id, mensaje) VALUES (?, ?, ?, ?)');
        foreach ($reglas as [$vino, $especie, $comida, $mensaje]) {
            $ins->execute([$vinos[$vino], $especies[$especie], $comidas[$comida], $mensaje]);
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
            $ids['Marta'], $vinos['Martín Códax'], 'Con el arroz de bogavante fue un acierto: cortaba el sofrito y realzaba el marisco.',
            $ids['Joan'], $vinos['Finca Malaveïna'], 'Muy bien con el chuletón; la fruta madura y la brasa de la carne se llevan bien.',
        ]);
        $pdo->prepare('INSERT INTO Valoracion (usuario_id, vino_id, puntuacion) VALUES (?, ?, 5), (?, ?, 4), (?, ?, 3)')->execute([
            $ids['Marta'], $vinos['Martín Códax'], $ids['Joan'], $vinos['Finca Malaveïna'], $ids['Joan'], $vinos['Viña Sol'],
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
