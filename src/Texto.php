<?php

declare(strict_types=1);

namespace WineMatch;

/** Utilidades de texto: slugs para URLs publicas. */
final class Texto
{
    public static function slug(string $texto): string
    {
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; lower()', $texto) ?? strtolower($texto);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '';
        return trim($slug, '-');
    }
}
