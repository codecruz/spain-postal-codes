<?php

declare(strict_types=1);

namespace App\Data;

/**
 * The 52 Spanish provinces (including the autonomous cities of Ceuta and Melilla).
 *
 * Keys are the official two-digit province codes, which are also the first two
 * digits of every postal code in that province.
 */
final class Province
{
    public const ALL = [
        '01' => 'Araba/Álava',
        '02' => 'Albacete',
        '03' => 'Alicante/Alacant',
        '04' => 'Almería',
        '05' => 'Ávila',
        '06' => 'Badajoz',
        '07' => 'Illes Balears',
        '08' => 'Barcelona',
        '09' => 'Burgos',
        '10' => 'Cáceres',
        '11' => 'Cádiz',
        '12' => 'Castellón/Castelló',
        '13' => 'Ciudad Real',
        '14' => 'Córdoba',
        '15' => 'A Coruña',
        '16' => 'Cuenca',
        '17' => 'Girona',
        '18' => 'Granada',
        '19' => 'Guadalajara',
        '20' => 'Gipuzkoa',
        '21' => 'Huelva',
        '22' => 'Huesca',
        '23' => 'Jaén',
        '24' => 'León',
        '25' => 'Lleida',
        '26' => 'La Rioja',
        '27' => 'Lugo',
        '28' => 'Madrid',
        '29' => 'Málaga',
        '30' => 'Murcia',
        '31' => 'Navarra',
        '32' => 'Ourense',
        '33' => 'Asturias',
        '34' => 'Palencia',
        '35' => 'Las Palmas',
        '36' => 'Pontevedra',
        '37' => 'Salamanca',
        '38' => 'Santa Cruz de Tenerife',
        '39' => 'Cantabria',
        '40' => 'Segovia',
        '41' => 'Sevilla',
        '42' => 'Soria',
        '43' => 'Tarragona',
        '44' => 'Teruel',
        '45' => 'Toledo',
        '46' => 'Valencia/València',
        '47' => 'Valladolid',
        '48' => 'Bizkaia',
        '49' => 'Zamora',
        '50' => 'Zaragoza',
        '51' => 'Ceuta',
        '52' => 'Melilla',
    ];

    /** Common alternative (mostly Spanish-language) names, already normalized. */
    private const ALIASES = [
        'alava' => '01',
        'araba' => '01',
        'alicante' => '03',
        'alacant' => '03',
        'baleares' => '07',
        'islas baleares' => '07',
        'castellon' => '12',
        'castello' => '12',
        'la coruna' => '15',
        'coruna' => '15',
        'gerona' => '17',
        'guipuzcoa' => '20',
        'lerida' => '25',
        'orense' => '32',
        'valencia' => '46',
        'vizcaya' => '48',
    ];

    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'é' => 'e', 'è' => 'e', 'í' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
        'Á' => 'a', 'À' => 'a', 'É' => 'e', 'È' => 'e', 'Í' => 'i', 'Ï' => 'i',
        'Ó' => 'o', 'Ò' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n', 'Ç' => 'c',
    ];

    private function __construct()
    {
    }

    public static function nameFromCode(string $code): ?string
    {
        return self::ALL[$code] ?? null;
    }

    /** Returns the province code for an exact (canonical) province name. */
    public static function codeFromName(string $name): ?string
    {
        $code = array_search($name, self::ALL, true);

        return false === $code ? null : (string) $code;
    }

    /** Province code a postal code belongs to, based on its first two digits. */
    public static function codeFromPostalCode(string $postalCode): ?string
    {
        $code = substr($postalCode, 0, 2);

        return isset(self::ALL[$code]) ? $code : null;
    }

    /**
     * Resolves user input (a code like "41", a canonical name, an alias or any
     * part of a bilingual name) to its canonical province name. Matching is
     * case- and accent-insensitive.
     */
    public static function resolve(string $input): ?string
    {
        $input = trim($input);

        if (1 === preg_match('/^\d{1,2}$/', $input)) {
            return self::nameFromCode(str_pad($input, 2, '0', \STR_PAD_LEFT));
        }

        $needle = self::normalize($input);
        if ('' === $needle) {
            return null;
        }

        if (isset(self::ALIASES[$needle])) {
            return self::ALL[self::ALIASES[$needle]];
        }

        foreach (self::ALL as $name) {
            $normalized = self::normalize($name);
            if ($normalized === $needle || \in_array($needle, explode('/', $normalized), true)) {
                return $name;
            }
        }

        return null;
    }

    public static function normalize(string $value): string
    {
        return strtolower(trim(strtr($value, self::ACCENTS)));
    }
}
