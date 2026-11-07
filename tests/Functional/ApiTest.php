<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->loadLocations([
            ['Madrid', 'Madrid', '28001'],
            ['Madrid', 'Alcobendas', '28100'],
            ['Araba/Álava', 'Amurrio', '01450'],
            ['Sevilla', 'Sevilla', '41001'],
            ['Sevilla', 'Dos Hermanas', '41700'],
            ['Ávila', 'Candeleda', '05480'],
        ]);
    }

    public function testIndexDescribesTheApi(): void
    {
        $body = $this->getJson('/');

        self::assertResponseIsSuccessful();
        self::assertSame('Spain Postal Codes API', $body['name']);
    }

    public function testListLocationsIsPaginatedAndSorted(): void
    {
        $body = $this->getJson('/api/locations?limit=2&page=2');

        self::assertResponseIsSuccessful();
        self::assertSame(['page' => 2, 'limit' => 2, 'total' => 6, 'pages' => 3], $body['meta']);
        self::assertSame(['28001', '28100'], array_column($body['data'], 'cod_postal'));
    }

    public function testLocationsAreSerializedWithUnescapedUnicode(): void
    {
        $this->client->request('GET', '/api/locations?limit=1');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertStringContainsString('"provincia":"Araba/Álava"', (string) $this->client->getResponse()->getContent());
        self::assertSame(
            ['provincia' => 'Araba/Álava', 'poblacion' => 'Amurrio', 'cod_postal' => '01450'],
            $this->decode()['data'][0],
        );
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provinceFilterProvider(): iterable
    {
        yield 'by name' => ['Madrid', ['Madrid', 'Alcobendas']];
        yield 'by code' => ['41', ['Sevilla', 'Dos Hermanas']];
        yield 'without accents' => ['avila', ['Candeleda']];
        yield 'by bilingual alias' => ['alava', ['Amurrio']];
    }

    /**
     * @param list<string> $expectedTowns
     */
    #[DataProvider('provinceFilterProvider')]
    public function testFilterByProvince(string $provincia, array $expectedTowns): void
    {
        $body = $this->getJson('/api/locations?provincia='.rawurlencode($provincia));

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing($expectedTowns, array_column($body['data'], 'poblacion'));
    }

    public function testSearchByTownName(): void
    {
        $body = $this->getJson('/api/locations?q=HERMANAS');

        self::assertResponseIsSuccessful();
        self::assertSame(['Dos Hermanas'], array_column($body['data'], 'poblacion'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidListQueryProvider(): iterable
    {
        yield 'unknown province' => ['/api/locations?provincia=Atlantis'];
        yield 'page zero' => ['/api/locations?page=0'];
        yield 'non-numeric limit' => ['/api/locations?limit=abc'];
        yield 'limit too high' => ['/api/locations?limit=501'];
        yield 'search too short' => ['/api/locations?q=a'];
    }

    #[DataProvider('invalidListQueryProvider')]
    public function testInvalidListQueriesReturn400(string $uri): void
    {
        $body = $this->getJson($uri);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(400, $body['error']['status']);
        self::assertNotEmpty($body['error']['message']);
    }

    public function testShowByPostalCode(): void
    {
        $body = $this->getJson('/api/locations/28001');

        self::assertResponseIsSuccessful();
        self::assertSame([['provincia' => 'Madrid', 'poblacion' => 'Madrid', 'cod_postal' => '28001']], $body['data']);
    }

    public function testShowPadsFourDigitPostalCodes(): void
    {
        $body = $this->getJson('/api/locations/1450');

        self::assertResponseIsSuccessful();
        self::assertSame('01450', $body['data'][0]['cod_postal']);
    }

    public function testShowUnknownPostalCodeReturns404(): void
    {
        $body = $this->getJson('/api/locations/99999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('No locations found for postal code 99999.', $body['error']['message']);
    }

    public function testShowInvalidPostalCodeReturns400(): void
    {
        $this->getJson('/api/locations/abc');

        self::assertResponseStatusCodeSame(400);
    }

    public function testProvincesIncludeCounts(): void
    {
        $body = $this->getJson('/api/provinces');

        self::assertResponseIsSuccessful();
        self::assertCount(52, $body['data']);

        $byCode = array_column($body['data'], null, 'code');
        self::assertSame(['code' => '28', 'name' => 'Madrid', 'locations' => 2, 'postal_codes' => 2], $byCode['28']);
        self::assertSame(0, $byCode['52']['locations']);
    }

    public function testResponsesAreCacheableAndAllowCors(): void
    {
        $this->client->request('GET', '/api/provinces');

        self::assertResponseHeaderSame('Access-Control-Allow-Origin', '*');
        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function testUnsupportedMethodReturnsJson405(): void
    {
        $this->client->request('POST', '/api/locations');

        self::assertResponseStatusCodeSame(405);
        self::assertSame(405, $this->decode()['error']['status']);
    }

    public function testUnknownRouteReturnsJson404(): void
    {
        $body = $this->getJson('/api/nope');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(404, $body['error']['status']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $uri): array
    {
        $this->client->request('GET', $uri);

        return $this->decode();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        return json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }
}
