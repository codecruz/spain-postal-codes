<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Import\LocationCsvReader;
use PHPUnit\Framework\TestCase;

final class LocationCsvReaderTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testReadsAndNormalizesRows(): void
    {
        $file = $this->csv("\xEF\xBB\xBFprovincia;poblacion;cod_postal\nMadrid; Madrid ;28001\n\nAraba/Álava;Amurrio;1450\n");

        $rows = iterator_to_array((new LocationCsvReader())->read($file));

        self::assertSame([
            2 => ['provincia' => 'Madrid', 'poblacion' => 'Madrid', 'cod_postal' => '28001'],
            4 => ['provincia' => 'Araba/Álava', 'poblacion' => 'Amurrio', 'cod_postal' => '01450'],
        ], $rows);
    }

    public function testRejectsInvalidHeader(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid header');

        iterator_to_array((new LocationCsvReader())->read($this->csv("province,town,zip\n")));
    }

    public function testRejectsUnknownProvince(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Line 2: unknown province "Atlantis"');

        iterator_to_array((new LocationCsvReader())->read($this->csv("provincia;poblacion;cod_postal\nAtlantis;Foo;28001\n")));
    }

    public function testRejectsInvalidPostalCode(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Line 2: invalid postal code "548"');

        iterator_to_array((new LocationCsvReader())->read($this->csv("provincia;poblacion;cod_postal\nÁvila;Candeleda;548\n")));
    }

    public function testRejectsMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);

        iterator_to_array((new LocationCsvReader())->read('/nonexistent/file.csv'));
    }

    public function testBundledDatasetIsValid(): void
    {
        $count = iterator_count((new LocationCsvReader())->read(\dirname(__DIR__, 2).'/data/es_cp.csv'));

        self::assertGreaterThan(13000, $count);
    }

    private function csv(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'cp_');
        self::assertNotFalse($file);
        file_put_contents($file, $contents);
        $this->files[] = $file;

        return $file;
    }
}
