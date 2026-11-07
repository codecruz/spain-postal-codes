<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Data\Province;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProvinceTest extends TestCase
{
    public function testThereAre52Provinces(): void
    {
        self::assertCount(52, Province::ALL);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function resolveProvider(): iterable
    {
        yield 'two-digit code' => ['41', 'Sevilla'];
        yield 'one-digit code' => ['5', 'Ávila'];
        yield 'canonical name' => ['Madrid', 'Madrid'];
        yield 'lowercase without accents' => ['avila', 'Ávila'];
        yield 'uppercase' => ['CÁDIZ', 'Cádiz'];
        yield 'part of bilingual name' => ['Alacant', 'Alicante/Alacant'];
        yield 'other part of bilingual name' => ['valencia', 'Valencia/València'];
        yield 'spanish alias' => ['Vizcaya', 'Bizkaia'];
        yield 'surrounding whitespace' => ['  Lugo ', 'Lugo'];
        yield 'unknown name' => ['Atlantis', null];
        yield 'unknown code' => ['99', null];
        yield 'empty' => ['', null];
    }

    #[DataProvider('resolveProvider')]
    public function testResolve(string $input, ?string $expected): void
    {
        self::assertSame($expected, Province::resolve($input));
    }

    public function testCodeLookups(): void
    {
        self::assertSame('01', Province::codeFromName('Araba/Álava'));
        self::assertNull(Province::codeFromName('alava'), 'codeFromName() only accepts canonical names');
        self::assertSame('Melilla', Province::nameFromCode('52'));
        self::assertSame('28', Province::codeFromPostalCode('28001'));
        self::assertNull(Province::codeFromPostalCode('99001'));
    }
}
