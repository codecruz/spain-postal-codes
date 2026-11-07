<?php

declare(strict_types=1);

namespace App\Import;

use App\Data\Province;

/**
 * Streams and validates the postal code dataset (semicolon-separated CSV with
 * the header "provincia;poblacion;cod_postal").
 */
final class LocationCsvReader
{
    public const HEADER = ['provincia', 'poblacion', 'cod_postal'];

    /**
     * @return \Generator<int, array{provincia: string, poblacion: string, cod_postal: string}> keyed by line number
     *
     * @throws \RuntimeException         if the file cannot be read
     * @throws \UnexpectedValueException if a line is malformed
     */
    public function read(string $path): \Generator
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException(\sprintf('Dataset file "%s" does not exist or is not readable.', $path));
        }

        $handle = fopen($path, 'r');
        if (false === $handle) {
            throw new \RuntimeException(\sprintf('Unable to open dataset file "%s".', $path));
        }

        try {
            $header = $this->readLine($handle);
            if (null !== $header && isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]); // strip UTF-8 BOM
            }
            if ($header !== self::HEADER) {
                throw new \UnexpectedValueException(\sprintf('Invalid header in "%s": expected "%s".', $path, implode(';', self::HEADER)));
            }

            $line = 1;
            while (null !== $row = $this->readLine($handle)) {
                ++$line;

                if ([null] === $row) {
                    continue; // blank line
                }

                yield $line => $this->normalize($row, $line);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     *
     * @return list<string|null>|null
     */
    private function readLine($handle): ?array
    {
        $row = fgetcsv($handle, 0, ';', '"', '');

        return false === $row ? null : $row;
    }

    /**
     * @param list<string|null> $row
     *
     * @return array{provincia: string, poblacion: string, cod_postal: string}
     */
    private function normalize(array $row, int $line): array
    {
        if (3 !== \count($row)) {
            throw new \UnexpectedValueException(\sprintf('Line %d: expected 3 columns, got %d.', $line, \count($row)));
        }

        [$provincia, $poblacion, $codPostal] = array_map(static fn (?string $v): string => trim((string) $v), $row);

        if (null === Province::codeFromName($provincia)) {
            throw new \UnexpectedValueException(\sprintf('Line %d: unknown province "%s".', $line, $provincia));
        }

        if ('' === $poblacion) {
            throw new \UnexpectedValueException(\sprintf('Line %d: empty town name.', $line));
        }

        // Tolerate codes whose leading zero was lost by a spreadsheet (e.g. "1450").
        if (1 === preg_match('/^\d{4}$/', $codPostal)) {
            $codPostal = '0'.$codPostal;
        }

        if (1 !== preg_match('/^\d{5}$/', $codPostal)) {
            throw new \UnexpectedValueException(\sprintf('Line %d: invalid postal code "%s" (expected 5 digits).', $line, $codPostal));
        }

        return ['provincia' => $provincia, 'poblacion' => $poblacion, 'cod_postal' => $codPostal];
    }
}
